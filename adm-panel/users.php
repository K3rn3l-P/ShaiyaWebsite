                        <?php
                        // Original searches for compatibility
                        $searchChar = isset($_GET['search_char']) ? trim($_GET['search_char']) : '';
                        $searchUser = isset($_GET['search_user']) ? trim($_GET['search_user']) : '';

                        // New Header Filters
                        $fUserID = isset($_GET['f_userid']) ? trim($_GET['f_userid']) : $searchUser;
                        $fCharName = isset($_GET['f_charname']) ? trim($_GET['f_charname']) : $searchChar;
                        $fUserUID = isset($_GET['f_useruid']) ? trim($_GET['f_useruid']) : '';
                        $fStatus = isset($_GET['f_status']) ? trim($_GET['f_status']) : '';
                        $fFaction = isset($_GET['f_faction']) ? trim($_GET['f_faction']) : '';

                        $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                        $perPage = 50;
                        $offset = ($page - 1) * $perPage;

                        // Build WHERE with PDO params — pdoAdmin uses EMULATE_PREPARES=true which
                        // handles parameter binding client-side, bypassing the ODBC legacy driver's
                        // SQLDescribeParameter limitations on aliased JOIN queries and subqueries.
                        $whereClauses = ["1=1"];
                        $whereParams = [];
                        if ($fCharName) {
                            $q = $pdoAdmin->prepare("SELECT DISTINCT UserUID FROM PS_GameData.dbo.Chars WHERE CharName LIKE ?");
                            $q->execute(['%' . $fCharName . '%']);
                            $uids = [];
                            while ($r = $q->fetch()) {
                                $uids[] = (int) $r['UserUID'];
                            }
                            if (count($uids) > 0) {
                                $inPlaceholders = implode(',', array_fill(0, count($uids), '?'));
                                $whereClauses[] = "U.UserUID IN ($inPlaceholders)";
                                $whereParams = array_merge($whereParams, $uids);
                            } else {
                                $whereClauses[] = "1=0";
                            }
                        }
                        if ($fUserID !== '') {
                            $whereClauses[] = "U.UserID LIKE ?";
                            $whereParams[] = '%' . $fUserID . '%';
                        }
                        if ($fUserUID !== '' && ctype_digit((string) $fUserUID)) {
                            $whereClauses[] = "U.UserUID = ?";
                            $whereParams[] = (int) $fUserUID;
                        }
                        if ($fStatus !== '' && ctype_digit((string) $fStatus)) {
                            $whereClauses[] = "U.Status = ?";
                            $whereParams[] = (int) $fStatus;
                        }
                        if ($fFaction !== '' && in_array((string) $fFaction, ['0', '1'], true)) {
                            $whereClauses[] = "MG.Country = ?";
                            $whereParams[] = (int) $fFaction;
                        }
                        $whereSql = "WHERE " . implode(" AND ", $whereClauses);

                        // Count total for pagination
                        $countSql = "SELECT COUNT(*) as Total FROM PS_UserData.dbo.Users_Master U LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = U.UserUID $whereSql";
                        $countStmt = $pdoAdmin->prepare($countSql);
                        $countStmt->execute($whereParams);
                        $countQArr = $countStmt->fetch();
                        $totalUsers = (int) ($countQArr['Total'] ?? 0);
                        $totalPages = ceil($totalUsers / $perPage);

                        // List query — OFFSET/FETCH values are (int), safe to inline
                        $userSql = "SELECT 
                            U.UserUID, U.UserID, U.Status, U.Point, U.JoinDate,
                            C.CharID as MainCharID,
                            C.CharName as MainChar,
                            C.K1 as Kills,
                            C.LoginStatus,
                            C.LeaveDate as OnlineLeaveDate,
                            MG.Country as Faction,
                            (SELECT SUM(UsePoint) FROM PS_GameData.dbo.PointLog WHERE UserUID = U.UserUID) as Spent
                        FROM PS_UserData.dbo.Users_Master U
                        LEFT JOIN PS_GameData.dbo.Chars C ON C.UserUID = U.UserUID AND C.Slot = 0 AND C.Del = 0
                        LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = U.UserUID
                        $whereSql
                        ORDER BY U.UserUID DESC
                        OFFSET $offset ROWS FETCH NEXT $perPage ROWS ONLY";
                        $userRows = [];
                        $userStmt = $pdoAdmin->prepare($userSql);
                        $userStmt->execute($whereParams);
                        while ($u = $userStmt->fetch()) {
                            $userRows[] = $u;
                        }

                        // Optimized batch playtime fetch for the current page
                        $pageUserIDs = [];
                        $allUsers = [];
                        foreach ($userRows as $u) {
                            $pageUserIDs[] = (string) $u['UserID'];
                            $allUsers[] = $u;
                        }

                        $sessionSecondsStmt = $pdoAdmin->prepare("SELECT TOP 1 DATEDIFF(SECOND, ActionTime, GETDATE()) as SessionSeconds FROM PS_GameLog.dbo.ActionLog WHERE CAST(UserID AS VARCHAR(18)) = CAST(? AS VARCHAR(18)) AND ActionType = 107 ORDER BY ActionTime DESC");

                        $playtimes = [];
                        if (count($pageUserIDs) > 0) {
                            // PDO with EMULATE_PREPARES=true handles ? in subqueries correctly
                            // CAST(a.UserID AS VARCHAR(18)): ActionLog.UserID is varchar(18) type; must cast for IN() comparison
                            $uidPlaceholders = implode(',', array_fill(0, count($pageUserIDs), 'CAST(? AS VARCHAR(18))'));
                            $p_sql = "SELECT UserID, SUM(DATEDIFF(SECOND, LoginTime, LogoutTime)) as TotalSeconds
                          FROM (
                              SELECT a.UserID, a.ActionTime as LogoutTime, MAX(l.ActionTime) as LoginTime
                              FROM PS_GameLog.dbo.ActionLog a
                              LEFT JOIN PS_GameLog.dbo.ActionLog l ON CAST(l.UserID AS VARCHAR(18)) = CAST(a.UserID AS VARCHAR(18)) AND l.ActionType = 107 AND l.ActionTime < a.ActionTime
                              WHERE CAST(a.UserID AS VARCHAR(18)) IN ($uidPlaceholders) AND a.ActionType = 108
                              GROUP BY a.UserID, a.ActionTime
                          ) t
                          WHERE LoginTime IS NOT NULL
                          GROUP BY UserID";
                            $p_stmt = $pdoAdmin->prepare($p_sql);
                            $p_stmt->execute($pageUserIDs);
                            while ($p_row = $p_stmt->fetch()) {
                                $playtimes[$p_row['UserID']] = (int) $p_row['TotalSeconds'];
                            }
                        }
                        ?>

                        <div class="chars-table-container">
                            <form method="GET" id="userHeaderFilters">
                                <input type="hidden" name="view" value="USERS">

                                <table class="chars-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;">Faction
                                                    <select name="f_faction" class="table-filter-select js-auto-submit-onchange">
                                                    <option value="">All</option>
                                                    <option value="0" <?php echo $fFaction === '0' ? 'selected' : ''; ?>>AOL
                                                    </option>
                                                    <option value="1" <?php echo $fFaction === '1' ? 'selected' : ''; ?>>UOF
                                                    </option>
                                                </select>
                                            </th>
                                            <th style="width: 80px;">UID
                                                <input type="text" name="f_useruid" class="table-filter-input js-auto-submit-onchange"
                                                    placeholder="ID" value="<?php echo htmlspecialchars($fUserUID); ?>">
                                            </th>
                                            <th style="width: 150px;">Username
                                                <input type="text" name="f_userid" class="table-filter-input js-auto-submit-onchange"
                                                    placeholder="User" value="<?php echo htmlspecialchars($fUserID); ?>">
                                            </th>
                                            <th style="width: 100px;">Status
                                                <select name="f_status" class="table-filter-select js-auto-submit-onchange">
                                                    <option value="">All</option>
                                                    <option value="16" <?php echo $fStatus === '16' ? 'selected' : ''; ?>>ADM
                                                    </option>
                                                    <option value="32" <?php echo $fStatus === '32' ? 'selected' : ''; ?>>GM
                                                    </option>
                                                    <option value="48" <?php echo $fStatus === '48' ? 'selected' : ''; ?>>GMA
                                                    </option>
                                                    <option value="0" <?php echo $fStatus === '0' ? 'selected' : ''; ?>>Player
                                                    </option>
                                                </select>
                                            </th>
                                            <th style="width: 100px;">Points</th>
                                            <th style="width: 100px;">Spent</th>
                                            <th style="width: 150px;">Main Char
                                                <input type="text" name="f_charname" class="table-filter-input js-auto-submit-onchange"
                                                    placeholder="Name" value="<?php echo htmlspecialchars($fCharName); ?>">
                                            </th>
                                            <th style="width: 150px;">Kills</th>
                                            <th style="width: 100px;">Playtime</th>
                                            <th style="width: 130px;">Created</th>
                                            <th style="width: 120px;">Last Login</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allUsers as $u): ?>
                                            <tr>
                                                <td>
                                                    <?php if ((int) $u['Faction'] === 1): ?>
                                                        <img src="assets/uof.webp" style="width: 20px;">
                                                    <?php elseif (isset($u['Faction']) && (int) $u['Faction'] === 0 && !empty($u['MainChar'])): ?>
                                                        <img src="assets/aol.webp" style="width: 20px;">
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td style="color: #666;"><?php echo $u['UserUID']; ?></td>
                                                <td>
                                                    <a href="/admin?view=UserEdit&uid=<?php echo $u['UserUID']; ?>"
                                                        class="user-link"><?php echo htmlspecialchars($u['UserID']); ?></a>
                                                </td>
                                                <td>
                                                    <?php
                                                    $uStatus = (int) ($u['Status'] ?? 0);
                                                    $sLabel = 'Player';
                                                    $sClass = '';
                                                    if ($uStatus == 16) {
                                                        $sLabel = 'ADM';
                                                        $sClass = 'status-active';
                                                    } elseif ($uStatus == 32) {
                                                        $sLabel = 'GM';
                                                        $sClass = 'status-active';
                                                    } elseif ($uStatus == 48) {
                                                        $sLabel = 'GMA';
                                                        $sClass = 'status-active';
                                                    }
                                                    ?>
                                                    <span
                                                        class="status-badge <?php echo $sClass; ?>"><?php echo $sLabel; ?></span>
                                                </td>
                                                <td><?php echo number_format($u['Point']); ?></td>
                                                <td><?php echo number_format($u['Spent'] ?? 0); ?></td>
                                                <td>
                                                    <?php if (!empty($u['MainChar'])): ?>
                                                        <a href="/admin?view=CharEdit&id=<?php echo $u['MainCharID']; ?>"
                                                            class="user-link"><?php echo htmlspecialchars($u['MainChar']); ?></a>
                                                    <?php else: ?>
                                                        <span style="color: #444; font-size: 13px;">No chars</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($u['Kills'] ?? 0); ?>
                                                    <?php echo getAdminRankIcon((int) ($u['Kills'] ?? 0)); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $u_esc = $u['UserID'];
                                                    $uPlaytime = $playtimes[$u_esc] ?? 0;
                                                    if ((int) ($u['LoginStatus'] ?? 0) === 1) {
                                                        try {
                                                            $sessionSecondsStmt->execute([$u_esc]);
                                                            $s_row = $sessionSecondsStmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($s_row) $uPlaytime += (int) $s_row['SessionSeconds'];
                                                        } catch (Throwable $e) {}
                                                    }
                                                    echo formatPlaytime($uPlaytime);
                                                    ?>
                                                </td>
                                                <td style="color: #888; font-size: 13px;">
                                                    <?php echo date('d M Y', strtotime($u['JoinDate'])); ?>
                                                </td>
                                                <td class="time-relative">
                                                    <?php echo formatRelativeTime($u['OnlineLeaveDate']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </form>
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination"
                                    style="display: flex; justify-content: center; gap: 10px; margin-top: 30px;">
                                    <?php
                                    $queryParams = [
                                        'view' => 'USERS',
                                        'f_useruid' => $fUserUID,
                                        'f_userid' => $fUserID,
                                        'f_charname' => $fCharName,
                                        'f_status' => $fStatus,
                                        'f_faction' => $fFaction
                                    ];
                                    $queryString = http_build_query(array_filter($queryParams, function ($v) {
                                        return $v !== '';
                                    }));
                                    $url = "/admin?" . $queryString;

                                    if ($page > 1): ?>
                                        <a href="<?php echo $url; ?>&p=<?php echo $page - 1; ?>" class="btn-search"
                                            style="padding: 5px 15px;">PREV</a>
                                    <?php endif; ?>
                                    <span style="color: #888; padding: 5px;">Page <?php echo $page; ?> of
                                        <?php echo $totalPages; ?></span>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?php echo $url; ?>&p=<?php echo $page + 1; ?>" class="btn-search"
                                            style="padding: 5px 15px;">NEXT</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

