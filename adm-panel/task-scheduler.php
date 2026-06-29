                        <?php
                        $schedulerSettings = fetchWebsiteSettings(['TaskSchedulerEnabled', 'TaskSchedulerLastHeartbeat', 'TaskSchedulerWorkerId']);
                        $schedulerEnabled = (string) ($schedulerSettings['TaskSchedulerEnabled'] ?? '0');
                        $schedulerHeartbeat = (string) ($schedulerSettings['TaskSchedulerLastHeartbeat'] ?? '-');
                        $schedulerWorkerId = trim((string) ($schedulerSettings['TaskSchedulerWorkerId'] ?? 'worker-main'));
                        if ($schedulerWorkerId === '') {
                            $schedulerWorkerId = 'worker-main';
                        }
                        $activeWorkerId = '-';
                        if (strpos($schedulerHeartbeat, '|') !== false) {
                            $heartbeatParts = explode('|', $schedulerHeartbeat, 2);
                            $activeWorkerId = trim((string) ($heartbeatParts[1] ?? '-'));
                        }

                        $schedulerJobs = [];
                        try {
                            $qSchedulerJobs = $pdoAdmin->query("SELECT TOP 200 JobID, JobName, IsEnabled, Status, ScheduleMode, ScheduleValue, TaskType, ServiceName, CommandText, SqlPayload, SqlSource, CreatedBy, CreatedAt, UpdatedAt FROM PS_WebSite.dbo.Web_TaskJobs ORDER BY JobID DESC");
                            if ($qSchedulerJobs) $schedulerJobs = $qSchedulerJobs->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) {}

                        $schedulerRuns = [];
                        $schedulerDbName = trim((string) ($website_db_name ?? 'PS_WebSite'));
                        if ($schedulerDbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $schedulerDbName)) {
                            $schedulerDbName = 'PS_WebSite';
                        }
                        $schedulerDbEscaped = '[' . $schedulerDbName . ']';

                        $schedulerRunsPerPage = 20;
                        $schedulerRunsPage = isset($_GET['runs_page']) ? (int) $_GET['runs_page'] : 1;
                        if ($schedulerRunsPage < 1) {
                            $schedulerRunsPage = 1;
                        }

                        $schedulerRunsSearch = trim((string) ($_GET['runs_q'] ?? ''));
                        if (strlen($schedulerRunsSearch) > 120) {
                            $schedulerRunsSearch = substr($schedulerRunsSearch, 0, 120);
                        }
                        $schedulerRunsSearchPlain = trim(ltrim($schedulerRunsSearch, '#'));

                        $schedulerRunsStatus = strtolower(trim((string) ($_GET['runs_status'] ?? '')));
                        if (!in_array($schedulerRunsStatus, ['', 'success', 'done', 'failed', 'error', 'skipped'], true)) {
                            $schedulerRunsStatus = '';
                        }

                        $schedulerRunsWhereParts = [];
                        $schedulerRunsParams = [];
                        if ($schedulerRunsStatus !== '') {
                            $schedulerRunsWhereParts[] = 'LOWER(LTRIM(RTRIM(CAST(RunStatus AS VARCHAR(50))))) = ?';
                            $schedulerRunsParams[] = $schedulerRunsStatus;
                        }
                        if ($schedulerRunsSearch !== '') {
                            $searchLike = '%' . $schedulerRunsSearchPlain . '%';
                            $schedulerSearchClauseParts = [
                                'CAST(RunID AS VARCHAR(20)) LIKE ?',
                                'CAST(JobID AS VARCHAR(20)) LIKE ?',
                                'LOWER(CAST(RunStatus AS VARCHAR(50))) LIKE ?',
                                'CAST(ISNULL(Output, \'\') AS NVARCHAR(MAX)) LIKE ?',
                                'CAST(ISNULL(ErrorMessage, \'\') AS NVARCHAR(MAX)) LIKE ?',
                                'LOWER(CAST(ISNULL(TriggeredBy, \'\') AS VARCHAR(100))) LIKE ?',
                                'CAST(StartedAt AS VARCHAR(30)) LIKE ?',
                                'CAST(ISNULL(FinishedAt, StartedAt) AS VARCHAR(30)) LIKE ?'
                            ];
                            $schedulerSearchParams = [
                                $searchLike,
                                $searchLike,
                                strtolower($searchLike),
                                $searchLike,
                                $searchLike,
                                strtolower($searchLike),
                                $searchLike,
                                $searchLike,
                            ];

                            if ($schedulerRunsSearchPlain !== '' && ctype_digit($schedulerRunsSearchPlain)) {
                                array_unshift($schedulerSearchClauseParts, 'RunID = ? OR JobID = ?');
                                array_unshift($schedulerSearchParams, (int) $schedulerRunsSearchPlain, (int) $schedulerRunsSearchPlain);
                            }

                            $schedulerRunsWhereParts[] = '(' . implode(' OR ', $schedulerSearchClauseParts) . ')';
                            $schedulerRunsParams = array_merge($schedulerRunsParams, $schedulerSearchParams);
                        }

                        $schedulerRunsWhereSql = '';
                        if (!empty($schedulerRunsWhereParts)) {
                            $schedulerRunsWhereSql = ' WHERE ' . implode(' AND ', $schedulerRunsWhereParts);
                        }

                        $schedulerFilteredRunIds = [];
                        $schedulerRunsIdSql = "SELECT RunID FROM {$schedulerDbEscaped}.dbo.Web_TaskJobRuns{$schedulerRunsWhereSql} ORDER BY RunID DESC";
                        try {
                            $schedulerRunsIdStmt = $pdoAdmin->prepare($schedulerRunsIdSql);
                            $schedulerRunsIdStmt->execute($schedulerRunsParams);
                            while (($idRow = $schedulerRunsIdStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                                $idValue = (int) ($idRow['RunID'] ?? 0);
                                if ($idValue > 0) {
                                    $schedulerFilteredRunIds[] = $idValue;
                                }
                            }
                        } catch (Throwable $e) {}

                        $schedulerRunsTotalCount = count($schedulerFilteredRunIds);

                        $schedulerRunsTotalPages = $schedulerRunsTotalCount > 0 ? (int) ceil($schedulerRunsTotalCount / $schedulerRunsPerPage) : 1;
                        if ($schedulerRunsPage > $schedulerRunsTotalPages) {
                            $schedulerRunsPage = $schedulerRunsTotalPages;
                        }
                        if ($schedulerRunsPage < 1) {
                            $schedulerRunsPage = 1;
                        }

                        $schedulerRunsOffset = ($schedulerRunsPage - 1) * $schedulerRunsPerPage;
                        $schedulerPageRunIds = array_slice($schedulerFilteredRunIds, $schedulerRunsOffset, $schedulerRunsPerPage);
                        if (!empty($schedulerPageRunIds)) {
                            $schedulerRunIdPlaceholders = implode(', ', array_fill(0, count($schedulerPageRunIds), '?'));
                            $schedulerRunsDataSql = "SELECT RunID, JobID, RunStatus, StartedAt, FinishedAt, Output, ErrorMessage, TriggeredBy FROM {$schedulerDbEscaped}.dbo.Web_TaskJobRuns WHERE RunID IN ({$schedulerRunIdPlaceholders}) ORDER BY RunID DESC";
                            try {
                                $schedulerRunsDataStmt = $pdoAdmin->prepare($schedulerRunsDataSql);
                                $schedulerRunsDataStmt->execute($schedulerPageRunIds);
                                $schedulerRuns = $schedulerRunsDataStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}
                        }

                        $schedulerRunsSummaryText = 'No runs logged yet.';
                        if ($schedulerRunsTotalCount > 0) {
                            $schedulerRunsStart = $schedulerRunsOffset + 1;
                            $schedulerRunsEnd = min($schedulerRunsOffset + $schedulerRunsPerPage, $schedulerRunsTotalCount);
                            $schedulerRunsSummaryText = 'Showing ' . $schedulerRunsStart . '-' . $schedulerRunsEnd . ' of ' . $schedulerRunsTotalCount . ' runs';
                        }

                        $schedulerRunsRowsHtml = '';
                        ob_start();
                        foreach ($schedulerRuns as $run) {
                            $runOutput = trim((string) ($run['Output'] ?? ''));
                            $runError = trim((string) ($run['ErrorMessage'] ?? ''));
                            ?>
                            <tr>
                                <td style="color:#777; white-space: nowrap;">#<?php echo (int) $run['RunID']; ?></td>
                                <td style="white-space: nowrap;"><?php echo (int) $run['JobID']; ?></td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['RunStatus'] ?? '')); ?></td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['StartedAt'] ?? '')); ?></td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['FinishedAt'] ?? '-')); ?></td>
                                <td style="min-width: 360px; max-width: 620px; text-align: left; white-space: normal; word-break: break-word;">
                                    <?php if ($runOutput !== ''): ?>
                                        <details>
                                            <summary style="cursor:pointer; color:#b8b8b8;">Show output</summary>
                                            <pre style="margin:8px 0 0 0; white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($runOutput); ?></pre>
                                        </details>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 260px; max-width: 520px; text-align: left; white-space: normal; word-break: break-word;">
                                    <?php if ($runError !== ''): ?>
                                        <details>
                                            <summary style="cursor:pointer; color:#ff9a9a;">Show error</summary>
                                            <pre style="margin:8px 0 0 0; white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($runError); ?></pre>
                                        </details>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['TriggeredBy'] ?? '-')); ?></td>
                            </tr>
                            <?php
                        }
                        if (empty($schedulerRuns)) {
                            ?>
                            <tr><td colspan="8" style="text-align:center; padding: 35px; color:#555;">No runs logged yet.</td></tr>
                            <?php
                        }
                        $schedulerRunsRowsHtml = (string) ob_get_clean();

                        $schedulerRunsBuildQuery = function (int $page) use ($schedulerRunsSearch, $schedulerRunsStatus) {
                            $query = [
                                'view' => 'task_scheduler',
                                'runs_page' => $page,
                            ];
                            if ($schedulerRunsSearch !== '') {
                                $query['runs_q'] = $schedulerRunsSearch;
                            }
                            if ($schedulerRunsStatus !== '') {
                                $query['runs_status'] = $schedulerRunsStatus;
                            }
                            if (isset($_GET['scheduler_form']) && (string) $_GET['scheduler_form'] === '1') {
                                $query['scheduler_form'] = '1';
                            }
                            if (isset($_GET['edit_job_id']) && (int) $_GET['edit_job_id'] > 0) {
                                $query['edit_job_id'] = (int) $_GET['edit_job_id'];
                            }
                            if (isset($_GET['job_mode']) && in_array((string) $_GET['job_mode'], ['simple', 'advanced'], true)) {
                                $query['job_mode'] = (string) $_GET['job_mode'];
                            }
                            return '/admin?' . http_build_query($query) . '#scheduler-runs-panel';
                        };

                        $schedulerRunsPaginationHtml = '';
                        ob_start();
                        if ($schedulerRunsTotalPages > 1) {
                            $prevPage = max(1, $schedulerRunsPage - 1);
                            $nextPage = min($schedulerRunsTotalPages, $schedulerRunsPage + 1);
                            ?>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <a href="<?php echo htmlspecialchars($schedulerRunsBuildQuery($prevPage)); ?>" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width:80px; padding:0 10px; opacity: <?php echo $schedulerRunsPage <= 1 ? '0.45' : '1'; ?>; pointer-events: <?php echo $schedulerRunsPage <= 1 ? 'none' : 'auto'; ?>;">Prev</a>
                                <?php
                                $pageWindowStart = max(1, $schedulerRunsPage - 2);
                                $pageWindowEnd = min($schedulerRunsTotalPages, $schedulerRunsPage + 2);
                                for ($pageIndex = $pageWindowStart; $pageIndex <= $pageWindowEnd; $pageIndex++) {
                                    $isCurrentPage = $pageIndex === $schedulerRunsPage;
                                    ?>
                                    <a href="<?php echo htmlspecialchars($schedulerRunsBuildQuery($pageIndex)); ?>" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width:46px; padding:0 10px; <?php echo $isCurrentPage ? 'background: rgba(232,200,129,0.34); border-color: rgba(232,200,129,0.75); color:#fff3c9; font-weight:700; box-shadow: 0 0 0 1px rgba(232,200,129,0.35), 0 0 14px rgba(232,200,129,0.22);' : ''; ?>"><?php echo $pageIndex; ?></a>
                                    <?php
                                }
                                ?>
                                <a href="<?php echo htmlspecialchars($schedulerRunsBuildQuery($nextPage)); ?>" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width:80px; padding:0 10px; opacity: <?php echo $schedulerRunsPage >= $schedulerRunsTotalPages ? '0.45' : '1'; ?>; pointer-events: <?php echo $schedulerRunsPage >= $schedulerRunsTotalPages ? 'none' : 'auto'; ?>;">Next</a>
                            </div>
                            <?php
                        }
                        $schedulerRunsPaginationHtml = (string) ob_get_clean();

                        if (isset($_GET['runs_partial']) && (string) $_GET['runs_partial'] === '1') {
                            header('Content-Type: application/json; charset=UTF-8');
                            echo json_encode([
                                'ok' => true,
                                'rows_html' => $schedulerRunsRowsHtml,
                                'pagination_html' => $schedulerRunsPaginationHtml,
                                'summary_text' => $schedulerRunsSummaryText,
                                'current_page' => $schedulerRunsPage,
                                'total_pages' => $schedulerRunsTotalPages,
                                'total_count' => $schedulerRunsTotalCount,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            exit;
                        }

                        $schedulerEditJobId = isset($_GET['edit_job_id']) ? (int) $_GET['edit_job_id'] : 0;
                        $schedulerEditJob = null;
                        if ($schedulerEditJobId > 0) {
                            foreach ($schedulerJobs as $schedulerJobItem) {
                                if ((int) ($schedulerJobItem['JobID'] ?? 0) === $schedulerEditJobId) {
                                    $schedulerEditJob = $schedulerJobItem;
                                    break;
                                }
                            }
                        }

                        $schedulerSimpleDatabases = [];
                        try {
                            $schedulerDbStmt = $pdoAdmin->prepare("SELECT name FROM sys.databases WHERE state = 0 AND HAS_DBACCESS(name) = 1 AND database_id > 4 ORDER BY name");
                            $schedulerDbStmt->execute([]);
                            foreach ($schedulerDbStmt->fetchAll(PDO::FETCH_ASSOC) as $schedulerDbRow) {
                                $schedulerDbNameTmp = trim((string) ($schedulerDbRow['name'] ?? ''));
                                if ($schedulerDbNameTmp !== '' && preg_match('/^[A-Za-z0-9_]+$/', $schedulerDbNameTmp)) {
                                    $schedulerSimpleDatabases[] = $schedulerDbNameTmp;
                                }
                            }
                        } catch (Throwable $e) {}

                        $runtimeDbName = trim((string) (getenv('DB_NAME') ?: 'PS_UserData'));
                        $runtimeWebsiteDbName = trim((string) (getenv('DB_WEBSITE_NAME') ?: 'PS_WebSite'));
                        if ($runtimeDbName !== '') {
                            $schedulerSimpleDatabases[] = $runtimeDbName;
                        }
                        if ($runtimeWebsiteDbName !== '') {
                            $schedulerSimpleDatabases[] = $runtimeWebsiteDbName;
                        }
                        $schedulerSimpleDatabases[] = 'PS_UserData';
                        $schedulerSimpleDatabases[] = 'PS_GameData';
                        $schedulerSimpleDatabases[] = 'PS_WebSite';

                        $schedulerSimpleDatabases = array_values(array_unique(array_filter($schedulerSimpleDatabases, function ($dbName) {
                            return preg_match('/^[A-Za-z0-9_]+$/', (string) $dbName) === 1;
                        })));

                        $schedulerSimpleTableSuggestions = [];
                        $schedulerSimpleColumnSuggestions = [];
                        $schedulerTablesByDatabase = [];
                        $schedulerColumnsByDatabaseAndTable = [];
                        foreach ($schedulerSimpleDatabases as $simpleDbName) {
                            $simpleDbEscaped = '[' . $simpleDbName . ']';
                            $metaStmt = null;
                            try {
                                $metaStmt = $pdoAdmin->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM {$simpleDbEscaped}.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' ORDER BY TABLE_NAME, COLUMN_NAME");
                                $metaStmt->execute([]);
                                while ($metaRow = $metaStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $tableName = trim((string) ($metaRow['TABLE_NAME'] ?? ''));
                                    $columnName = trim((string) ($metaRow['COLUMN_NAME'] ?? ''));
                                    if ($tableName === '' || $columnName === '') {
                                        continue;
                                    }
                                    $schedulerSimpleTableSuggestions[] = $simpleDbName . '.dbo.' . $tableName;
                                    $schedulerSimpleColumnSuggestions[] = $tableName . '.' . $columnName;

                                    if (!isset($schedulerTablesByDatabase[$simpleDbName])) {
                                        $schedulerTablesByDatabase[$simpleDbName] = [];
                                    }
                                    $schedulerTablesByDatabase[$simpleDbName][$tableName] = true;

                                    if (!isset($schedulerColumnsByDatabaseAndTable[$simpleDbName])) {
                                        $schedulerColumnsByDatabaseAndTable[$simpleDbName] = [];
                                    }
                                    if (!isset($schedulerColumnsByDatabaseAndTable[$simpleDbName][$tableName])) {
                                        $schedulerColumnsByDatabaseAndTable[$simpleDbName][$tableName] = [];
                                    }
                                    $schedulerColumnsByDatabaseAndTable[$simpleDbName][$tableName][$columnName] = true;
                                }
                            } catch (Throwable $e) {}
                        }
                        $schedulerSimpleTableSuggestions = array_values(array_unique($schedulerSimpleTableSuggestions));
                        $schedulerSimpleColumnSuggestions = array_values(array_unique($schedulerSimpleColumnSuggestions));

                        foreach ($schedulerTablesByDatabase as $dbName => $tablesMap) {
                            $tables = array_keys($tablesMap);
                            natcasesort($tables);
                            $schedulerTablesByDatabase[$dbName] = array_values($tables);
                        }

                        foreach ($schedulerColumnsByDatabaseAndTable as $dbName => $tablesMap) {
                            foreach ($tablesMap as $tableName => $columnsMap) {
                                $columns = array_keys($columnsMap);
                                natcasesort($columns);
                                $schedulerColumnsByDatabaseAndTable[$dbName][$tableName] = array_values($columns);
                            }
                        }

                        $schedulerModeParam = strtolower(trim((string) ($_GET['job_mode'] ?? '')));
                        $schedulerIsEditSimple = $schedulerEditJob && strtoupper((string) ($schedulerEditJob['TaskType'] ?? '')) === 'SERVICE_COMMAND' && strtolower((string) ($schedulerEditJob['SqlSource'] ?? '')) === 'simple_meta';
                        if (!in_array($schedulerModeParam, ['simple', 'advanced'], true)) {
                            $schedulerModeParam = $schedulerIsEditSimple ? 'simple' : 'advanced';
                        }

                        $schedulerEditSimpleMeta = [
                            'database' => 'PS_WebSite',
                            'table' => '',
                            'column' => '',
                            'operator' => 'eq',
                            'expected_value' => '',
                            'second_command' => ''
                        ];
                        if ($schedulerIsEditSimple) {
                            $decodedSimpleMeta = json_decode((string) ($schedulerEditJob['SqlPayload'] ?? ''), true);
                            if (is_array($decodedSimpleMeta)) {
                                $schedulerEditSimpleMeta = array_merge($schedulerEditSimpleMeta, $decodedSimpleMeta);
                            }
                        }

                        $schedulerFormAction = $schedulerEditJob ? 'task_scheduler_update_job' : 'task_scheduler_save_job';
                        $schedulerFormTitle = $schedulerEditJob ? 'Modify Job' : 'Create Job';
                        $schedulerSubmitLabelSimple = $schedulerEditJob ? 'UPDATE QUICK JOB' : 'SAVE QUICK JOB';
                        $schedulerSubmitLabelAdvanced = $schedulerEditJob ? 'UPDATE ADVANCED JOB' : 'SAVE ADVANCED JOB';

                        $schedulerDefaultJobName = $schedulerEditJob ? (string) ($schedulerEditJob['JobName'] ?? '') : '';
                        $schedulerDefaultSchedule = $schedulerEditJob ? (string) ($schedulerEditJob['ScheduleValue'] ?? 'hourly') : 'hourly';
                        $schedulerDefaultService = $schedulerEditJob ? strtolower((string) ($schedulerEditJob['ServiceName'] ?? 'ps_game')) : 'ps_game';
                        $schedulerDefaultCommand = $schedulerEditJob ? (string) ($schedulerEditJob['CommandText'] ?? '') : '';
                        $schedulerDefaultSimpleSecondCommand = (string) ($schedulerEditSimpleMeta['second_command'] ?? '');
                        $schedulerDefaultTaskType = $schedulerEditJob ? strtoupper((string) ($schedulerEditJob['TaskType'] ?? 'SERVICE_COMMAND')) : 'SERVICE_COMMAND';
                        $schedulerDefaultSqlPayload = $schedulerEditJob && $schedulerDefaultTaskType === 'SQL_SCRIPT' ? (string) ($schedulerEditJob['SqlPayload'] ?? '') : '';
                        $schedulerShowForm = $schedulerEditJob || (string) ($_GET['scheduler_form'] ?? '') === '1';
                        ?>
                        <div id="task-scheduler-top" class="char-edit-section" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
                            <div style="text-align: center; margin-bottom: 35px;">
                                <h3 style="margin-bottom: 8px; color: #e8c881;">TASK SCHEDULER</h3>
                                <p style="color: #666; font-size: 13px;">Configura job schedulati da pannello. Esecuzione demandata al worker Windows locale (queue-driven).</p>
                                <p style="color: #666; font-size: 12px; margin-top: 6px;">`Run Now` accoda la richiesta subito; al ciclo di polling successivo il worker esegue direttamente il job.</p>
                            </div>

                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(232,200,129,0.18); border-radius: 10px; padding: 22px; margin-bottom: 20px; display:flex; align-items:center; justify-content: space-between; gap: 20px;">
                                <div>
                                    <div style="color: #fff; font-size: 18px; font-weight: 700; margin-bottom: 6px; letter-spacing: .4px;">Global Module Status</div>
                                    <div style="color:#bfc7d2; font-size: 13px; line-height:1.7;">Abilita/disabilita creazione e queueing dei task dal pannello admin. Il poller Windows locale resta separato e gira con il task schedulato sul worker.</div>
                                    <div style="color:#a9b3c1; font-size: 13px; margin-top: 4px; line-height:1.7;">Il toggle salva <code>TaskSchedulerEnabled</code> nel database sito configurato da <code>DB_WEBSITE_NAME</code> (default: <code>PS_WebSite</code>).</div>
                                    <div style="margin-top: 10px; color:#e5e7eb; font-size: 12px; display:flex; flex-wrap:wrap; gap: 10px 18px;">
                                        <span><b style="color:#e8c881;">Worker configurato (DB):</b> <?php echo htmlspecialchars($schedulerWorkerId); ?></span>
                                        <span><b style="color:#93c5fd;">Worker attivo (heartbeat):</b> <?php echo htmlspecialchars($activeWorkerId); ?></span>
                                        <span><b style="color:#f3f4f6;">Last Worker Heartbeat:</b> <?php echo htmlspecialchars($schedulerHeartbeat); ?></span>
                                    </div>
                                </div>
                                <form action="/admin_actions" method="POST" style="display:flex; align-items:center; gap: 12px;">
                                    <input type="hidden" name="action" value="task_scheduler_toggle">
                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <label class="switch">
                                        <input type="checkbox" name="scheduler_enabled" <?php echo $schedulerEnabled === '1' ? 'checked' : ''; ?> class="js-auto-submit-onchange">
                                        <span class="slider round"></span>
                                    </label>
                                    <span style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: <?php echo $schedulerEnabled === '1' ? '#48bb78' : '#e53e3e'; ?>;">
                                        <?php echo $schedulerEnabled === '1' ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </form>
                            </div>

                            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin: 0 0 22px 0; flex-wrap:wrap;">
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <a href="/admin?view=task_scheduler&scheduler_form=1#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 148px; padding: 0 14px;">Create Job</a>
                                    <?php if ($schedulerEditJob): ?>
                                        <a href="/admin?view=task_scheduler&edit_job_id=<?php echo (int) $schedulerEditJob['JobID']; ?>&scheduler_form=1#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 170px; padding: 0 14px;">Modify Job #<?php echo (int) $schedulerEditJob['JobID']; ?></a>
                                    <?php endif; ?>
                                    <?php if ($schedulerShowForm): ?>
                                        <a href="/admin?view=task_scheduler#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 130px; padding: 0 14px;">Close Form</a>
                                    <?php endif; ?>
                                </div>
                                <a href="#task-scheduler-guide" style="display:inline-flex; align-items:center; justify-content:center; padding: 10px 16px; border-radius: 8px; background: linear-gradient(180deg, rgba(232,200,129,0.18), rgba(232,200,129,0.08)); border: 1px solid rgba(232,200,129,0.28); color:#e8c881; text-decoration:none; font-size: 12px; font-weight: 600; letter-spacing: .3px; white-space: nowrap;">Open setup guide</a>
                            </div>

                            <div style="display:flex; flex-direction:column; gap: 20px;">
                                <?php if ($schedulerShowForm): ?>
                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 25px;">
                                    <h4 style="color:#fff; font-size: 13px; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 14px;"><?php echo htmlspecialchars($schedulerFormTitle); ?></h4>
                                    <?php if ($schedulerEditJob): ?>
                                        <div style="margin-bottom: 12px; color:#8f98a3; font-size: 11px;">Editing job #<?php echo (int) $schedulerEditJob['JobID']; ?> — <a href="/admin?view=task_scheduler" style="color:#e8c881; text-decoration:none;">clear edit mode</a></div>
                                    <?php endif; ?>

                                    <div style="display:flex; gap:8px; margin-bottom: 14px;">
                                        <button id="scheduler-mode-simple-btn" type="button" class="btn-action btn-sm js-scheduler-mode" data-mode="simple">Quick Command Job</button>
                                        <button id="scheduler-mode-advanced-btn" type="button" class="btn-action btn-sm js-scheduler-mode" data-mode="advanced">Advanced Job</button>
                                    </div>

                                    <form id="scheduler-simple-form" action="/admin_actions" method="POST" enctype="multipart/form-data" style="display:block;">
                                        <input type="hidden" name="action" value="<?php echo htmlspecialchars($schedulerFormAction); ?>">
                                        <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="job_profile" value="simple">
                                        <?php if ($schedulerEditJob): ?>
                                            <input type="hidden" name="job_id" value="<?php echo (int) $schedulerEditJob['JobID']; ?>">
                                        <?php endif; ?>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Job Name</label>
                                            <input type="text" name="job_name" class="char-input" maxlength="100" required placeholder="EXP Recovery Monitor" value="<?php echo htmlspecialchars($schedulerDefaultJobName); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Windows Schedule</label>
                                            <select name="simple_interval" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <?php $scheduleOptions = ['every_10m' => 'Every 10 min', 'every_15m' => 'Every 15 min', 'every_30m' => 'Every 30 min', 'hourly' => 'Hourly', 'daily_00_00' => 'Daily 00:00', 'daily_03_00' => 'Daily 03:00', 'daily_06_00' => 'Daily 06:00', 'daily_12_00' => 'Daily 12:00', 'daily_18_00' => 'Daily 18:00']; ?>
                                                <?php foreach ($scheduleOptions as $scheduleKey => $scheduleLabel): ?>
                                                    <option value="<?php echo htmlspecialchars($scheduleKey); ?>" <?php echo $schedulerDefaultSchedule === $scheduleKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($scheduleLabel); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service</label>
                                            <select name="service_name" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <option value="ps_game" <?php echo $schedulerDefaultService === 'ps_game' ? 'selected' : ''; ?>>ps_game</option>
                                                <option value="ps_login" <?php echo $schedulerDefaultService === 'ps_login' ? 'selected' : ''; ?>>ps_login</option>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service Command</label>
                                            <input type="text" name="command_text" class="char-input" maxlength="400" placeholder="/exp2xenable 100" value="<?php echo htmlspecialchars($schedulerDefaultCommand); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Second Command (optional)</label>
                                            <input type="text" name="simple_second_command" class="char-input" maxlength="400" placeholder="/nt Player, today is {day}. EXP: x{exp}. Enjoy the game!" value="<?php echo htmlspecialchars($schedulerDefaultSimpleSecondCommand); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                            <div style="margin-top:6px; color:#707986; font-size:11px; line-height:1.5;">Supporta placeholder dinamici: <code>{exp}</code>, <code>{day}</code>, <code>{value}</code>. Se valorizzato, viene eseguito subito dopo il comando principale.</div>
                                        </div>

                                        <div style="margin-top: 2px; margin-bottom: 10px; color:#e8c881; font-size: 11px; letter-spacing: .4px; text-transform: uppercase;">Data selector check (real DB validation)</div>
                                        <div class="char-input-group" style="display:block; margin-bottom: 10px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Database</label>
                                            <select id="scheduler-simple-database" name="simple_database" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <?php foreach ($schedulerSimpleDatabases as $simpleDbName): ?>
                                                    <option value="<?php echo htmlspecialchars($simpleDbName); ?>" <?php echo (string) ($schedulerEditSimpleMeta['database'] ?? '') === $simpleDbName ? 'selected' : ''; ?>><?php echo htmlspecialchars($simpleDbName); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 10px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Table</label>
                                            <input type="text" id="scheduler-simple-table" name="simple_table" class="char-input" list="scheduler-simple-tables" maxlength="128" placeholder="Chars" value="<?php echo htmlspecialchars((string) ($schedulerEditSimpleMeta['table'] ?? '')); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 10px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Column</label>
                                            <input type="text" id="scheduler-simple-column" name="simple_column" class="char-input" list="scheduler-simple-columns" maxlength="128" placeholder="Level" value="<?php echo htmlspecialchars((string) ($schedulerEditSimpleMeta['column'] ?? '')); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                                            <div class="char-input-group" style="display:block;">
                                                <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Comparison</label>
                                                <select name="simple_operator" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                    <?php $simpleOperators = ['eq' => '=', 'neq' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', 'contains' => 'contains']; ?>
                                                    <?php foreach ($simpleOperators as $operatorKey => $operatorLabel): ?>
                                                        <option value="<?php echo htmlspecialchars($operatorKey); ?>" <?php echo (string) ($schedulerEditSimpleMeta['operator'] ?? 'eq') === $operatorKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($operatorLabel); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="char-input-group" style="display:block;">
                                                <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Expected Value</label>
                                                <input type="text" name="simple_expected_value" class="char-input" maxlength="255" placeholder="100" value="<?php echo htmlspecialchars((string) ($schedulerEditSimpleMeta['expected_value'] ?? '')); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                            </div>
                                        </div>

                                        <div style="margin-top:5px; color:#666; font-size:11px; margin-bottom: 8px;">I campi Database/Table/Column usano metadata reali e vengono verificati al salvataggio sul DB effettivo.</div>
                                        <div style="margin-top:0; color:#707986; font-size:11px; margin-bottom: 16px; line-height:1.6;">
                                            Esempi Comparison (vecchio flusso manuale):<br>
                                            • <code>PS_GameDefs</code> + <code>EXPTable</code> + <code>EXP</code> + <code>&gt;=</code> + <code>100</code><br>
                                            • <code>PS_GameDefs</code> + <code>EXPTable</code> + <code>NameDay</code> + <code>contains</code> + <code>Monday</code>
                                        </div>

                                        <button type="submit" class="btn-action"><?php echo htmlspecialchars($schedulerSubmitLabelSimple); ?></button>
                                    </form>

                                    <form id="scheduler-advanced-form" action="/admin_actions" method="POST" enctype="multipart/form-data" style="display:none; margin-top: 4px;">
                                        <input type="hidden" name="action" value="<?php echo htmlspecialchars($schedulerFormAction); ?>">
                                        <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="job_profile" value="advanced">
                                        <?php if ($schedulerEditJob): ?>
                                            <input type="hidden" name="job_id" value="<?php echo (int) $schedulerEditJob['JobID']; ?>">
                                        <?php endif; ?>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Job Name</label>
                                            <input type="text" name="job_name" class="char-input" maxlength="100" required placeholder="EXP Recovery Monitor" value="<?php echo htmlspecialchars($schedulerDefaultJobName); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Task Type</label>
                                            <select name="task_type" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <option value="SERVICE_COMMAND" <?php echo $schedulerDefaultTaskType === 'SERVICE_COMMAND' ? 'selected' : ''; ?>>Service Command</option>
                                                <option value="SQL_SCRIPT" <?php echo $schedulerDefaultTaskType === 'SQL_SCRIPT' ? 'selected' : ''; ?>>SQL Script</option>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Windows Schedule</label>
                                            <select name="simple_interval" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <?php foreach ($scheduleOptions as $scheduleKey => $scheduleLabel): ?>
                                                    <option value="<?php echo htmlspecialchars($scheduleKey); ?>" <?php echo $schedulerDefaultSchedule === $scheduleKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($scheduleLabel); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service</label>
                                            <select name="service_name" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <option value="ps_game" <?php echo $schedulerDefaultService === 'ps_game' ? 'selected' : ''; ?>>ps_game</option>
                                                <option value="ps_login" <?php echo $schedulerDefaultService === 'ps_login' ? 'selected' : ''; ?>>ps_login</option>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service Command</label>
                                            <input type="text" name="command_text" class="char-input" maxlength="400" placeholder="/exp2xenable 100" value="<?php echo htmlspecialchars($schedulerDefaultCommand); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 12px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">SQL Script (inline)</label>
                                            <textarea name="sql_payload" class="char-input" placeholder="Esempio rapido: DECLARE @DayOfWeek NVARCHAR(50) = DATENAME(dw, GETDATE()); SELECT EXP FROM PS_GameDefs.dbo.EXPTable WHERE NameDay = @DayOfWeek;" style="width:100%; height: 120px; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; resize: vertical;"><?php echo htmlspecialchars($schedulerDefaultSqlPayload); ?></textarea>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 18px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">SQL File (.sql optional)</label>
                                            <input type="file" name="sql_file" accept=".sql" style="width:100%; color:#aaa; font-size: 12px;">
                                        </div>

                                        <button type="submit" class="btn-action"><?php echo htmlspecialchars($schedulerSubmitLabelAdvanced); ?></button>
                                    </form>

                                    <datalist id="scheduler-simple-tables"></datalist>
                                    <datalist id="scheduler-simple-columns"></datalist>
                                </div>
                                <?php endif; ?>

                                <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; min-width:0;">
                                    <h4 style="color:#fff; margin-bottom: 15px; font-size: 15px;">Configured Jobs</h4>
                                    <div class="chars-table-container" style="width: 100%; overflow-x: auto; overflow-y: hidden;">
                                        <table class="chars-table" style="min-width: 980px; width: 100%;">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Job</th>
                                                        <th>Type</th>
                                                        <th>Schedule</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($schedulerJobs as $job): ?>
                                                    <tr>
                                                        <td style="color:#777; white-space: nowrap;">#<?php echo (int) $job['JobID']; ?></td>
                                                        <td style="min-width: 180px; text-align: left;"><?php echo htmlspecialchars($job['JobName']); ?></td>
                                                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($job['TaskType']); ?></td>
                                                        <td style="white-space: nowrap;"><span style="color:#999;"><?php echo htmlspecialchars($job['ScheduleMode'] . ': ' . $job['ScheduleValue']); ?></span></td>
                                                        <td>
                                                            <?php $isEnabled = (int) ($job['IsEnabled'] ?? 0) === 1; ?>
                                                            <span class="status-badge" style="background: <?php echo $isEnabled ? 'rgba(72,187,120,0.12)' : 'rgba(229,62,62,0.12)'; ?>; color: <?php echo $isEnabled ? '#48bb78' : '#e53e3e'; ?>; border: 1px solid <?php echo $isEnabled ? 'rgba(72,187,120,0.2)' : 'rgba(229,62,62,0.2)'; ?>;">
                                                                <?php echo $isEnabled ? 'ACTIVE' : 'PAUSED'; ?>
                                                            </span>
                                                        </td>
                                                        <td style="min-width: 250px;">
                                                            <div style="display:flex; gap:6px; justify-content:flex-start; flex-wrap: nowrap;">
                                                                <a href="/admin?view=task_scheduler&edit_job_id=<?php echo (int) $job['JobID']; ?>#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none;">Edit</a>
                                                                <form action="/admin_actions" method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="task_scheduler_toggle_job">
                                                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
                                                                    <input type="hidden" name="enabled" value="<?php echo $isEnabled ? '0' : '1'; ?>">
                                                                    <button type="submit" class="btn-action btn-sm"><?php echo $isEnabled ? 'Pause' : 'Resume'; ?></button>
                                                                </form>
                                                                <form action="/admin_actions" method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="task_scheduler_run_now">
                                                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
                                                                    <button type="submit" class="btn-action btn-sm">Run Now</button>
                                                                </form>
                                                                <form action="/admin_actions" method="POST" style="display:inline;" class="js-confirm-submit" data-confirm="Delete this scheduler job?">
                                                                    <input type="hidden" name="action" value="task_scheduler_delete_job">
                                                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
                                                                    <button type="submit" class="btn-action btn-sm" style="background: rgba(165,39,39,0.8); border-color:#a52727;">Delete</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($schedulerJobs)): ?>
                                                    <tr><td colspan="6" style="text-align:center; padding: 40px; color:#555;">No scheduler jobs configured yet.</td></tr>
                                                <?php endif; ?>
                                                </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div id="scheduler-runs-panel" style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; min-width:0;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom: 12px;">
                                        <h4 style="color:#fff; margin:0; font-size: 15px;">Recent Runs (Worker Feedback)</h4>
                                        <form id="scheduler-runs-filter-form" action="/admin" method="GET" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                            <input type="hidden" name="view" value="task_scheduler">
                                            <input type="hidden" name="runs_page" value="1">
                                            <?php if (isset($_GET['scheduler_form']) && (string) $_GET['scheduler_form'] === '1'): ?>
                                                <input type="hidden" name="scheduler_form" value="1">
                                            <?php endif; ?>
                                            <?php if (isset($_GET['edit_job_id']) && (int) $_GET['edit_job_id'] > 0): ?>
                                                <input type="hidden" name="edit_job_id" value="<?php echo (int) $_GET['edit_job_id']; ?>">
                                            <?php endif; ?>
                                            <?php if (isset($_GET['job_mode']) && in_array((string) $_GET['job_mode'], ['simple', 'advanced'], true)): ?>
                                                <input type="hidden" name="job_mode" value="<?php echo htmlspecialchars((string) $_GET['job_mode']); ?>">
                                            <?php endif; ?>

                                            <input type="text" id="scheduler-runs-search" name="runs_q" value="<?php echo htmlspecialchars($schedulerRunsSearch); ?>" maxlength="120" placeholder="Filter runs, output, error, by..." class="char-input" style="width: 280px; height: 34px; background: rgba(0,0,0,0.3); border-color:#333; color:#fff;">
                                            <select id="scheduler-runs-status" name="runs_status" class="char-input" style="width: 150px; height: 34px; background: rgba(0,0,0,0.3); border-color:#333; color:#fff;">
                                                <option value="" <?php echo $schedulerRunsStatus === '' ? 'selected' : ''; ?>>All status</option>
                                                <option value="success" <?php echo $schedulerRunsStatus === 'success' ? 'selected' : ''; ?>>Success</option>
                                                <option value="done" <?php echo $schedulerRunsStatus === 'done' ? 'selected' : ''; ?>>Done</option>
                                                <option value="failed" <?php echo $schedulerRunsStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                <option value="error" <?php echo $schedulerRunsStatus === 'error' ? 'selected' : ''; ?>>Error</option>
                                                <option value="skipped" <?php echo $schedulerRunsStatus === 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                                            </select>

                                            <button type="submit" class="btn-action btn-sm" style="width:auto; min-width: 86px; padding: 0 12px;">Apply</button>
                                            <a href="/admin?view=task_scheduler#scheduler-runs-panel" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 86px; padding: 0 12px;">Reset</a>
                                            <button type="button" id="scheduler-runs-refresh-btn" class="btn-action btn-sm" style="width:auto; min-width: 42px; padding: 0 12px;" title="Refresh recent runs" aria-label="Refresh recent runs">
                                                <i class="fas fa-redo-alt"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <div id="scheduler-runs-summary" style="color:#8f98a3; font-size: 12px; margin-bottom: 10px;"><?php echo htmlspecialchars($schedulerRunsSummaryText); ?></div>
                                    <div class="chars-table-container" style="width: 100%; overflow-x: auto; overflow-y: hidden;">
                                        <table class="chars-table" style="min-width: 1220px; width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>RunID</th>
                                                    <th>JobID</th>
                                                    <th>Status</th>
                                                    <th>Started</th>
                                                    <th>Finished</th>
                                                    <th>Output</th>
                                                    <th>Error</th>
                                                    <th>By</th>
                                                </tr>
                                            </thead>
                                            <tbody id="scheduler-runs-tbody"><?php echo $schedulerRunsRowsHtml; ?></tbody>
                                        </table>
                                    </div>

                                    <div style="display:flex; align-items:center; justify-content:flex-end; margin-top: 12px;">
                                        <div id="scheduler-runs-pagination"><?php echo $schedulerRunsPaginationHtml; ?></div>
                                    </div>
                                </div>
                            </div>

                            <div id="task-scheduler-guide" style="margin-top: 28px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 22px;">
                                <div style="display:flex; align-items:flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
                                    <div>
                                        <div style="color:#e8c881; font-size: 15px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; margin-bottom: 6px;">Worker setup guide</div>
                                        <div style="color:#b8c0cc; font-size: 13px; line-height: 1.8; max-width: 980px;">Questa guida serve solo quando devi installare o aggiornare il worker Windows. Il task di sistema sul server worker esegue il poll ogni minuto; il pannello salva la pianificazione del singolo job che il worker poi applica localmente.</div>
                                    </div>
                                    <a href="#task-scheduler-top" style="display:inline-flex; align-items:center; justify-content:center; padding: 9px 14px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color:#b8b8b8; text-decoration:none; font-size: 12px; font-weight: 600;">Back to top</a>
                                </div>

                                <details open style="background: rgba(0,0,0,0.18); border: 1px solid rgba(255,255,255,0.04); border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;">
                                    <summary style="cursor: pointer; color:#fff; font-size: 13px; font-weight: 700; list-style: none;">Quick start (worker Windows)</summary>
                                    <div style="color:#c0c7d2; font-size: 13px; line-height: 1.8; margin-top: 12px;">
                                        <div style="margin-bottom: 10px;">Ci sono due copie della stessa cartella:</div>
                                        <ul style="margin: 10px 0 0 18px; padding: 0;">
                                            <li><b style="color:#e8c881;">Copie nel sito web</b>: vivono sotto <code>C:\xampp\htdocs\tools\task-scheduler-worker\</code> e servono solo come riferimento/documentazione nel progetto. Questa copia non deve essere esposta al pubblico: la cartella usa <b>.htaccess</b> per bloccarne l'accesso.</li>
                                            <li><b style="color:#93c5fd;">Copie sul worker Windows</b>: la stessa cartella va copiata su un computer Windows che esegue il worker come processo locale. Lì stanno <b>worker.ps1</b>, <b>install-worker.ps1</b>, <b>generate-worker-config.ps1</b> e <b>worker.config.json</b>. Questa copia non passa da Apache e non è pubblica.</li>
                                        </ul>
                                        <ol style="margin: 12px 0 0 18px; padding: 0;">
                                            <li>Sul worker Windows copia i file della cartella in una directory locale dedicata, poi esegui <b>generate-worker-config.ps1</b> per creare un <code>hmac_secret</code> casuale e scrivere <b>worker.config.json</b>.</li>
                                            <li>Imposta <code>api_url</code> su <code>http://127.0.0.1/task_scheduler_worker_api.php</code> solo se il worker gira sulla stessa macchina del sito; altrimenti usa l'URL interno corretto e mantieni l'endpoint limitato al loopback lato server.</li>
                                            <li>Compila <code>sql_connection_string</code> con il database del sito web (es. <code>PS_WebSite</code>) e poi esegui <b>install-worker.ps1</b> come Administrator: il task Windows viene registrato con polling a intervalli di minuti tramite <code>schtasks.exe</code>. Se nell'XML vedi <code>&lt;TimeTrigger&gt;</code> con <code>PT1M</code>, il worker poller e' configurato correttamente.</li>
                                            <li>In <b>Permissions Management</b> abilita <code>TASK_SCHEDULER</code>, scegli <b>Quick Command Job</b> o <b>Advanced Job</b>, poi crea/modifica il job e controlla <b>heartbeat</b> e <b>run log</b>: il pannello prepara i job, il worker Windows li consuma e li traduce in task locali quando serve.</li>
                                            <li><b>Esempio pratico dal tuo script manuale</b>: i job <code>SERVICE_COMMAND</code> usano ora direttamente <code>PSM_Cmd.dbo.usp_RunCommand</code>, quindi puoi usare sia <code>/nt ...</code> sia <code>/exp2xenable 100</code> nel flusso scheduler standard.</li>
                                        </ol>
                                    </div>
                                </details>

                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                    <div style="background: rgba(0,0,0,0.18); border: 1px solid rgba(255,255,255,0.04); border-radius: 10px; padding: 14px 16px; color:#bfc7d2; font-size: 13px; line-height: 1.8;">
                                        <div style="color:#e8c881; font-weight: 600; margin-bottom: 8px;">Field guide</div>
                                        <div><b>Quick Command Job</b>: form semplificata con Job Name, Windows Schedule, Service, Command, Second Command opzionale e selettori DB/Table/Column validati su metadata reali.</div>
                                        <div><b>Advanced Job</b>: mantiene la form completa con <code>SERVICE_COMMAND</code> / <code>SQL_SCRIPT</code> e payload SQL inline/upload.</div>
                                        <div><b>Comparison</b>: filtra una condizione semplice (es. <code>EXP &gt;= 100</code> o <code>NameDay contains Monday</code>) come metadato operativo del quick job.</div>
                                        <div><b>Windows Schedule</b>: scegli una frequenza pronta nel pannello; il worker salva quel valore e crea il task locale con la pianificazione corretta.</div>
                                        <div><b>Worker Poller</b>: il task <code>ShaiyaSchedulerWorker</code> gira ogni minuto e sincronizza i job dal sito verso Task Scheduler.</div>
                                        <div><b>Job Trigger</b>: dopo il salvataggio del job, il worker crea o aggiorna il task locale <code>ShaiyaWebJob_&lt;JobID&gt;</code> con il suo trigger Windows.</div>
                                        <div><b>Service</b>: servizio target del comando, ad esempio <code>ps_game</code> o <code>ps_login</code>.</div>
                                        <div><b>Service Command</b>: comando principale inviato a <code>PSM_Cmd.dbo.usp_RunCommand</code>; in quick mode, <code>/exp2xenable</code> usa il valore dinamico risolto da DB (es. per giorno corrente da <code>NameDay</code>).</div>
                                        <div><b>Second Command (optional)</b>: comando aggiuntivo eseguito subito dopo il principale (tipico <code>/nt ...</code>) con placeholder <code>{day}</code> / <code>{exp}</code>.</div>
                                    </div>

                                    <div style="background: rgba(0,0,0,0.18); border: 1px solid rgba(255,255,255,0.04); border-radius: 10px; padding: 14px 16px; color:#bfc7d2; font-size: 13px; line-height: 1.8;">
                                        <div style="color:#e8c881; font-weight: 600; margin-bottom: 8px;">Example build: restore EXP notice</div>
                                        <div>Per il tuo caso reale puoi usare un solo quick job con doppio comando:</div>
                                        <div style="margin-top: 8px;"><b>Main command</b>: <code>/exp2xenable</code> (senza valore fisso), con selettori DB su <code>PS_GameDefs.EXPTable.EXP</code>.</div>
                                        <div><b>Second command</b>: <code>/nt Player, today is {day}. EXP: x{exp}. Enjoy the game!</code>.</div>
                                        <div><b>Runtime</b>: il worker risolve il valore del giorno corrente, esegue <code>/exp2xenable &lt;exp&gt;</code> e poi il comando notice con placeholder sostituiti.</div>
                                        <div style="margin-top: 8px;">Per logiche molto più complesse resta disponibile il profilo <b>Advanced</b> con <code>SQL_SCRIPT</code>.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

