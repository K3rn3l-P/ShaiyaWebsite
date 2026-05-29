                    <?php
                    $userIdSearch = isset($_GET['username']) ? trim((string) $_GET['username']) : '';
                    $charNameSearch = isset($_GET['charname']) ? trim((string) $_GET['charname']) : '';
                    $actionTypesSearch = isset($_GET['action-type']) && is_array($_GET['action-type']) ? $_GET['action-type'] : [];
                    $rowsLimit = isset($_GET['rows']) && (int) $_GET['rows'] > 0 ? (int) $_GET['rows'] : 25;
                    $rowsLimit = max(1, min(5000, $rowsLimit));
                    $dateStartSearch = isset($_GET['date-start-enabled'], $_GET['date-start']) ? trim((string) $_GET['date-start']) : '';
                    $dateEndSearch = isset($_GET['date-end-enabled'], $_GET['date-end']) ? trim((string) $_GET['date-end']) : '';

                    $ActionTypes = [
                        10 => 'Own Kill',
                        102 => 'Unknown 102',
                        103 => 'Kill/Win',
                        104 => 'Death/Lose',
                        105 => 'Res 105',
                        106 => 'Res 106',
                        107 => 'Game Login',
                        108 => 'Game Logout',
                        111 => 'Pick Item',
                        112 => 'Lose Item',
                        113 => 'Purchase',
                        114 => 'Sell to NPC',
                        115 => 'Trade In',
                        116 => 'Trade Out',
                        118 => 'Duel Trade',
                        119 => 'Lapis Link',
                        121 => 'WH Put',
                        122 => 'WH Take',
                        123 => 'Duel Lose',
                        124 => 'Duel Win',
                        131 => 'Take Quest',
                        133 => 'Finish Quest',
                        141 => 'Learn Skill',
                        146 => 'Level Up',
                        151 => 'Stat STR',
                        152 => 'Stat DEX',
                        153 => 'Stat INT',
                        154 => 'Stat WIS',
                        155 => 'Stat REC',
                        156 => 'Stat LUC',
                        163 => 'Money Transfer',
                        164 => 'Teleport',
                        173 => 'Boss Action',
                        180 => 'Admin Action',
                        210 => 'Party Join',
                        211 => 'Party Leave',
                        212 => 'Enchant',
                        213 => 'Rec (Reroll)',
                        216 => 'Auction Create',
                        217 => 'Auction Remove',
                        218 => 'Auction Buy',
                        219 => 'Auction Bet',
                        220 => 'Auction Take Item',
                        221 => 'Auction Take Money',
                        222 => 'Auction Sell',
                        223 => 'Auction Finish',
                        224 => 'Auction Get Bet',
                        226 => 'Auction Bet Cancel',
                    ];

                    $whereClauses = ['1=1'];
                    $queryParams = [];

                    if ($userIdSearch !== '') {
                        try {
                            $qUid = $pdoAdmin->prepare('SELECT UserUID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?');
                            $qUid->execute([$userIdSearch]);
                            $rUid = $qUid->fetch(PDO::FETCH_ASSOC);
                            if ($rUid && isset($rUid['UserUID'])) {
                                $whereClauses[] = 'AL.UserUID = ?';
                                $queryParams[] = (int) $rUid['UserUID'];
                            } else {
                                $whereClauses[] = '1=0';
                            }
                        } catch (Throwable $e) {
                            $whereClauses[] = '1=0';
                        }
                    }

                    if ($charNameSearch !== '') {
                        try {
                            $qCid = $pdoAdmin->prepare('SELECT CharID FROM PS_GameData.dbo.Chars WHERE CharName = ?');
                            $qCid->execute([$charNameSearch]);
                            $rCid = $qCid->fetch(PDO::FETCH_ASSOC);
                            if ($rCid && isset($rCid['CharID'])) {
                                $whereClauses[] = 'AL.CharID = ?';
                                $queryParams[] = (int) $rCid['CharID'];
                            } else {
                                $whereClauses[] = '1=0';
                            }
                        } catch (Throwable $e) {
                            $whereClauses[] = '1=0';
                        }
                    }

                    if (!empty($actionTypesSearch)) {
                        $validActionTypes = [];
                        foreach ($actionTypesSearch as $selectedType) {
                            $typeId = (int) $selectedType;
                            if (isset($ActionTypes[$typeId])) {
                                $validActionTypes[] = $typeId;
                            }
                        }
                        $validActionTypes = array_values(array_unique($validActionTypes));
                        if (!empty($validActionTypes)) {
                            $typePlaceholders = implode(',', array_fill(0, count($validActionTypes), '?'));
                            $whereClauses[] = 'AL.ActionType IN (' . $typePlaceholders . ')';
                            foreach ($validActionTypes as $typeParam) {
                                $queryParams[] = $typeParam;
                            }
                        }
                    }

                    if ($dateStartSearch !== '') {
                        $dStart = date('Y-m-d H:i:s', strtotime($dateStartSearch));
                        if ($dStart !== false) {
                            $whereClauses[] = 'AL.ActionTime >= ?';
                            $queryParams[] = $dStart;
                        }
                    }

                    if ($dateEndSearch !== '') {
                        $dEnd = date('Y-m-d H:i:s', strtotime($dateEndSearch));
                        if ($dEnd !== false) {
                            $whereClauses[] = 'AL.ActionTime <= ?';
                            $queryParams[] = $dEnd;
                        }
                    }

                    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
                    $logsRows = [];

                    try {
                        $logsSql = "SELECT TOP $rowsLimit AL.*, UM.UserID, UM.AdminLevel, C.CharName
                                    FROM PS_GameLog.dbo.ActionLog AL
                                    LEFT JOIN PS_UserData.dbo.Users_Master UM ON AL.UserUID = UM.UserUID
                                    LEFT JOIN PS_GameData.dbo.Chars C ON AL.CharID = C.CharID
                                    $whereSql
                                    ORDER BY AL.[row] DESC";
                        $logsStmt = $pdoAdmin->prepare($logsSql);
                        $logsStmt->execute($queryParams);
                        $logsRows = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {
                        $logsRows = [];
                    }
                    ?>

                    <div class="logs-filter-card" style="background: rgba(255,255,255,0.02); padding: 30px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 40px;">
                        <form action="/admin" method="GET" id="logsSearchForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px;">
                            <input type="hidden" name="view" value="ActionLog">

                            <div class="editor-field">
                                <label class="editor-label">UserID</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($userIdSearch); ?>" class="editor-input" placeholder="Search UserID...">
                                <div style="font-size: 10px; color: #444; margin-top: 5px; text-align: center;">-- OR --</div>
                                <label class="editor-label" style="margin-top: 5px;">Char Name</label>
                                <input type="text" name="charname" value="<?php echo htmlspecialchars($charNameSearch); ?>" class="editor-input" placeholder="Search Char Name...">
                            </div>

                            <div class="editor-field">
                                <label class="editor-label">Action Types</label>
                                <select name="action-type[]" multiple class="editor-select" style="height: 180px; padding: 2px; font-size: 12px; line-height: 1.2;">
                                    <?php foreach ($ActionTypes as $atid => $atname): ?>
                                        <option value="<?php echo $atid; ?>" <?php echo in_array((string) $atid, $actionTypesSearch, true) || in_array($atid, $actionTypesSearch, true) ? 'selected' : ''; ?> style="padding: 4px 8px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                            [<?php echo $atid; ?>] <?php echo $atname; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="font-size: 9px; color: #666; cursor: pointer; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px;" class="js-reset-log-action-types">RESET SELECTION</div>
                            </div>

                            <div class="editor-field">
                                <label class="editor-label">Date Range</label>
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                    <input type="checkbox" name="date-start-enabled" id="ds_en" <?php echo isset($_GET['date-start-enabled']) ? 'checked' : ''; ?>>
                                    <input type="datetime-local" name="date-start" id="dateStart" class="editor-input" style="flex:1;" value="<?php echo $dateStartSearch ? date('Y-m-d\TH:i', strtotime($dateStartSearch)) : date('Y-m-d\TH:i'); ?>">
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="checkbox" name="date-end-enabled" id="de_en" <?php echo isset($_GET['date-end-enabled']) ? 'checked' : ''; ?>>
                                    <input type="datetime-local" name="date-end" id="dateEnd" class="editor-input" style="flex:1;" value="<?php echo $dateEndSearch ? date('Y-m-d\TH:i', strtotime($dateEndSearch)) : date('Y-m-d\TH:i'); ?>">
                                </div>
                            </div>

                            <div class="editor-field" style="display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <label class="editor-label">Rows</label>
                                    <input type="number" name="rows" value="<?php echo $rowsLimit; ?>" min="1" max="5000" class="editor-input" style="text-align: center;">
                                </div>
                                <button type="submit" class="btn-action" style="padding: 10px;">APPLY FILTERS</button>
                            </div>
                        </form>
                    </div>

                    <div class="chars-table-container">
                        <table class="chars-table">
                            <thead>
                                <tr>
                                    <th style="width: 150px;">Date</th>
                                    <th style="width: 130px;">User</th>
                                    <th style="width: 130px;">Char Name</th>
                                    <th style="width: 150px;">Action</th>
                                    <th style="text-align: left;">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logsRows as $log): ?>
                                    <?php
                                    $actId = (int) ($log['ActionType'] ?? 0);
                                    $actName = $ActionTypes[$actId] ?? ('Action ' . $actId);
                                    $time = strtotime((string) ($log['ActionTime'] ?? ''));

                                    $details = '';
                                    switch ($actId) {
                                        case 107:
                                            $details = 'IP: <b>' . htmlspecialchars((string) ($log['Text1'] ?? '')) . '</b> | ' . htmlspecialchars((string) ($log['Text2'] ?? ''));
                                            break;
                                        case 111:
                                        case 112:
                                            $details = "<span style='color: #e8c881;'>" . htmlspecialchars((string) ($log['Text1'] ?? '')) . '</span> (x' . (int) ($log['Value3'] ?? 0) . ') | ID: ' . (int) ($log['Value2'] ?? 0);
                                            $craftLabel = getCraftnameLabel((string) ($log['Text3'] ?? ''));
                                            if ($craftLabel !== '') {
                                                $details .= " <span style='color: #666; font-size: 11px;'>[" . htmlspecialchars($craftLabel) . ']</span>';
                                            }
                                            break;
                                        case 113:
                                        case 114:
                                            $details = "<span style='color: #e8c881;'>" . htmlspecialchars((string) ($log['Text1'] ?? '')) . '</span> (x' . (int) ($log['Value4'] ?? 0) . ') | Gold: ' . number_format(castToUInt((int) ($log['Value5'] ?? 0)));
                                            break;
                                        case 163:
                                            $details = 'To: ' . htmlspecialchars((string) ($log['Text1'] ?? '')) . ' | Amount: ' . number_format(castToUInt((int) ($log['Value1'] ?? 0)));
                                            break;
                                        case 164:
                                            $details = 'Map: ' . htmlspecialchars((string) getMapName((int) ($log['Value3'] ?? 0))) . ' | Target: ' . htmlspecialchars((string) ($log['Text1'] ?? ''));
                                            break;
                                        default:
                                            $tStr = trim((string) (($log['Text1'] ?? '') . ' ' . ($log['Text2'] ?? '') . ' ' . ($log['Text3'] ?? '') . ' ' . ($log['Text4'] ?? '')));
                                            $vStr = 'V1:' . (int) ($log['Value1'] ?? 0) . ' V2:' . (int) ($log['Value2'] ?? 0);
                                            $details = $tStr !== '' ? htmlspecialchars($tStr) : htmlspecialchars($vStr);
                                            break;
                                    }
                                    ?>
                                    <tr>
                                        <td style="color: #666; font-size: 11px;">
                                            <?php echo $time ? date('d M, H:i', $time) : '-'; ?>
                                        </td>
                                        <td>
                                            <a href="/admin?view=USERS&f_userid=<?php echo urlencode((string) ($log['UserID'] ?? '')); ?>" class="user-link"><?php echo htmlspecialchars((string) ($log['UserID'] ?? '-')); ?></a>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['CharName'])): ?>
                                                <a href="/admin?view=CharEdit&id=<?php echo (int) ($log['CharID'] ?? 0); ?>" class="user-link" style="color: #fff;"><?php echo htmlspecialchars((string) $log['CharName']); ?></a>
                                            <?php else: ?>
                                                <span style="color: #444; font-size: 11px;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="color: #6a8ec1; font-weight: 500; font-size: 11px; text-transform: uppercase;" title="<?php echo 'ID: ' . $actId; ?>"><?php echo htmlspecialchars($actName); ?></span>
                                        </td>
                                        <td style="text-align: left; font-size: 12px; color: #888; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo $details; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($logsRows) === 0): ?>
                                    <tr>
                                        <td colspan="5" style="padding: 60px; color: #444; text-align: center;">No log rows found with current filters.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

