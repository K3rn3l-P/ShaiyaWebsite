                        <?php
                        $userUID = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
                        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'Overview';
                        $userData = null;
                        if ($userUID > 0) {
                            // Fetch basic account details
                            $sql = "SELECT U.*, 
                               (SELECT SUM(UsePoint) FROM PS_GameData.dbo.PointLog WHERE UserUID = U.UserUID) as SpentPoints
                        FROM PS_UserData.dbo.Users_Master U 
                        WHERE U.UserUID = ?";
                            try {
                                $stmt = $pdoAdmin->prepare($sql);
                                $stmt->execute([$userUID]);
                                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}
                        }

                        if (!$userData):
                            ?>
                            <div class="alert alert-error">Account not found (ID: <?php echo $userUID; ?>).</div>
                        <?php else:
                            // Fetch Main Character and Faction
                            $mainSql = "SELECT TOP 1 C.CharID, C.CharName, C.K1 as Kills, C.LeaveDate, C.LoginStatus, MG.Country as Faction
                            FROM PS_GameData.dbo.Chars C
                            LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID
                            WHERE C.UserUID = ? AND C.Del = 0
                            ORDER BY C.Slot ASC";
                            $mainChar = null;
                            try {
                                $mStmt = $pdoAdmin->prepare($mainSql);
                                $mStmt->execute([$userUID]);
                                $mainChar = $mStmt->fetch(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}

                            $isOnline = (int) ($mainChar['LoginStatus'] ?? 0) === 1;

                            // Fetch Reroll Runes Used (Text1 = 'Recreation Rune', Text2 = 'use_item')
                            $runeSql = "SELECT COUNT(*) as RuneCount FROM PS_GameLog.dbo.ActionLog WHERE Text1 = 'Recreation Rune' AND Text2 = 'use_item' AND UserID = ?";
                            $rerollRunesUsed = 0;
                            try {
                                $rStmt = $pdoAdmin->prepare($runeSql);
                                $rStmt->execute([$userData['UserID']]);
                                $runeData = $rStmt->fetch(PDO::FETCH_ASSOC);
                                $rerollRunesUsed = (int) ($runeData['RuneCount'] ?? 0);
                            } catch (Throwable $e) {}

                            // Fetch Log In Count (ActionType 107)
                            $loginSql = "SELECT COUNT(*) as LoginCount FROM PS_GameLog.dbo.ActionLog WHERE ActionType = 107 AND UserID = ?";
                            $loginCount = 0;
                            try {
                                $lStmt = $pdoAdmin->prepare($loginSql);
                                $lStmt->execute([$userData['UserID']]);
                                $loginData = $lStmt->fetch(PDO::FETCH_ASSOC);
                                $loginCount = (int) ($loginData['LoginCount'] ?? 0);
                            } catch (Throwable $e) {}

                            // Fetch Chat Message Count
                            $chatSql = "SELECT COUNT(*) as ChatCount FROM PS_ChatLog.dbo.ChatLog WHERE UserUID = ?";
                            $chatCount = 0;
                            try {
                                $cStmt = $pdoAdmin->prepare($chatSql);
                                $cStmt->execute([$userUID]);
                                $chatData = $cStmt->fetch(PDO::FETCH_ASSOC);
                                $chatCount = (int) ($chatData['ChatCount'] ?? 0);
                            } catch (Throwable $e) {}

                            // Calculate Total Playtime (Seconds)
                            // pdoAdmin uses EMULATE_PREPARES=true: ? works in subqueries/derived tables.
                            // CAST(a.UserID / l.UserID AS VARCHAR(18)): ActionLog.UserID is varchar(18) type
                            $playtimeSql = "SELECT SUM(DATEDIFF(SECOND, LoginTime, LogoutTime)) as TotalSeconds
                                FROM (
                                    SELECT 
                                        a.ActionTime as LogoutTime,
                                        MAX(l.ActionTime) as LoginTime
                                    FROM PS_GameLog.dbo.ActionLog a
                                    LEFT JOIN PS_GameLog.dbo.ActionLog l ON CAST(l.UserID AS VARCHAR(18)) = CAST(a.UserID AS VARCHAR(18)) AND l.ActionType = 107 AND l.ActionTime < a.ActionTime
                                    WHERE CAST(a.UserID AS VARCHAR(18)) = CAST(? AS VARCHAR(18)) AND a.ActionType = 108
                                    GROUP BY a.ActionTime
                                ) t
                                WHERE LoginTime IS NOT NULL";
                            $pStmt = $pdoAdmin->prepare($playtimeSql);
                            $pStmt->execute([$userData['UserID']]);
                            $pData = $pStmt->fetch();
                            $totalSeconds = (int) ($pData['TotalSeconds'] ?? 0);

                            // Add current session if online
                            if ($isOnline) {
                                $sStmt = $pdoAdmin->prepare("SELECT TOP 1 DATEDIFF(SECOND, ActionTime, GETDATE()) as SessionSeconds FROM PS_GameLog.dbo.ActionLog WHERE CAST(UserID AS VARCHAR(18)) = CAST(? AS VARCHAR(18)) AND ActionType = 107 ORDER BY ActionTime DESC");
                                $sStmt->execute([$userData['UserID']]);
                                if ($sData = $sStmt->fetch()) {
                                    $totalSeconds += (int) $sData['SessionSeconds'];
                                }
                            }
                            ?>
                            <!-- User Edit Header -->
                            <div class="char-edit-header">
                                <div class="char-title-section">
                                    <h1><?php echo htmlspecialchars($userData['UserID']); ?> | OVERVIEW</h1>
                                    <p>User Management</p>
                                </div>
                                <div class="char-actions-bar">
                                    <form action="/admin_actions" method="POST" style="display:inline;" class="js-confirm-submit"
                                        data-confirm="<?php echo htmlspecialchars('Kick user ' . (string) $userData['UserID'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="user_kick">
                                        <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="return_view" value="UserEdit">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="admin-btn btn-kick">USER KICK</button>
                                    </form>
                                    <a href="/admin?view=Giftbox&target_user=<?php echo urlencode($userData['UserID']); ?>"
                                        class="admin-btn btn-del-item">ADD ITEM</a>
                                    <a href="/admin?view=Points&target_user=<?php echo urlencode($userData['UserID']); ?>"
                                        class="admin-btn btn-del-item">ADD POINTS</a>
                                    <div class="char-status-badge"
                                        style="<?php echo $isOnline ? '' : 'background: rgba(229, 62, 62, 0.2); color: #e53e3e; border-color: rgba(229, 62, 62, 0.3);'; ?>">
                                        <?php echo $isOnline ? 'ONLINE' : 'OFFLINE'; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabs -->
                            <div class="char-tabs">
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Overview"
                                    class="char-tab <?php echo (!$tab || $tab === 'Overview') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Overview</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Characters"
                                    class="char-tab <?php echo ($tab === 'Characters') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Characters</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog"
                                    class="char-tab <?php echo ($tab === 'AuthLog') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Auth log</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog"
                                    class="char-tab <?php echo ($tab === 'PointLog') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Point log</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases"
                                    class="char-tab <?php echo ($tab === 'Purchases') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Purchases</a>

                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Warehouse"
                                    class="char-tab <?php echo ($tab === 'Warehouse') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Warehouse</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat"
                                    class="char-tab <?php echo ($tab === 'Chat') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Chat
                                    Log</a>
                            </div>

                            <?php if (!$tab || $tab === 'Overview'): ?>

                                <!-- Administrative Actions Area -->
                                <div
                                    style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 40px; background: rgba(0,0,0,0.2); padding: 30px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.03);">
                                    <!-- Status Change -->
                                    <form action="/admin_actions" method="POST"
                                        style="display: flex; align-items: center; gap: 20px;">
                                        <input type="hidden" name="action" value="user_update_status">
                                        <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="return_view" value="UserEdit">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 13px; width: 120px; text-align: right;">Status</label>
                                        <select name="new_status" class="char-input" style="width: 200px;">
                                            <option value="0" <?php echo (int) $userData['Status'] === 0 ? 'selected' : ''; ?>>Player
                                            </option>
                                            <option value="16" <?php echo (int) $userData['Status'] === 16 ? 'selected' : ''; ?>>ADM
                                                (16)
                                            </option>
                                            <option value="32" <?php echo (int) $userData['Status'] === 32 ? 'selected' : ''; ?>>GM
                                                (32)
                                            </option>
                                            <option value="48" <?php echo (int) $userData['Status'] === 48 ? 'selected' : ''; ?>>GMA
                                                (48)
                                            </option>
                                            <option value="-5" <?php echo (int) $userData['Status'] === -5 ? 'selected' : ''; ?>>
                                                Banned (-5)
                                            </option>
                                        </select>
                                        <button type="submit" class="btn-search"
                                            style="padding: 10px 20px; letter-spacing: 1px;">SET
                                            STATUS</button>
                                    </form>

                                    <!-- Password Change -->
                                    <form action="/admin_actions" method="POST"
                                        style="display: flex; align-items: center; gap: 20px;">
                                        <input type="hidden" name="action" value="user_change_password">
                                        <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="return_view" value="UserEdit">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 13px; width: 120px; text-align: right;">Password
                                            change</label>
                                        <input type="text" name="new_password" class="char-input" style="width: 200px;"
                                            placeholder="New password...">
                                        <button type="submit" class="btn-search"
                                            style="padding: 10px 20px; letter-spacing: 1px;">CHANGE
                                            PASSWORD</button>
                                    </form>


                                </div>

                                <!-- Info Grid -->
                                <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                                    <!-- Account Table -->
                                    <table class="char-info-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="header-main">Account</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="width: 30%;">UserUID</td>
                                                <td><?php echo $userUID; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Username</td>
                                                <td><?php echo htmlspecialchars($userData['UserID']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Status</td>
                                                <td><?php echo (int) $userData['Status']; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Points</td>
                                                <td><?php echo number_format($userData['Point']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Spent points</td>
                                                <td><?php echo number_format($userData['SpentPoints'] ?? 0); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Create date</td>
                                                <td style="color: #888;">
                                                    <?php echo date('l, F d, Y g:i A', strtotime($userData['JoinDate'])); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Last enter date</td>
                                                <td class="time-relative">
                                                    <?php echo formatRelativeTime($mainChar['LeaveDate'] ?? $userData['JoinDate']); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Last leave date</td>
                                                <td style="color: #888;">
                                                    <?php echo date('l, F d, Y g:i A', strtotime($mainChar['LeaveDate'] ?? $userData['JoinDate'])); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Main Table -->
                                    <table class="char-info-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="header-main" style="background: #e8c881;">Main</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="width: 30%;">Faction</td>
                                                <td>
                                                    <?php if (isset($mainChar['Faction'])): ?>
                                                        <img src="assets/<?php echo ($mainChar['Faction'] == 0 ? 'aol.webp' : 'uof.webp'); ?>"
                                                            style="width: 24px; vertical-align: middle;">
                                                    <?php else: ?>
                                                        <span style="color: #444;">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Main character</td>
                                                <td>
                                                    <?php if ($mainChar): ?>
                                                        <a href="/admin?view=CharEdit&id=<?php echo $mainChar['CharID']; ?>"
                                                            class="user-link"><?php echo htmlspecialchars($mainChar['CharName']); ?></a>
                                                    <?php else: ?>
                                                        <span style="color: lightblue;">No characters</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Online time</td>
                                                <td><?php echo formatPlaytime($totalSeconds); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Kills</td>
                                                <td><?php echo number_format($mainChar['Kills'] ?? 0); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Other Table (Placeholders) -->
                                    <table class="char-info-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="header-main" style="background: #e8c881;">Other</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <tr>
                                                <td>Reroll Runes used</td>
                                                <td><?php echo number_format($rerollRunesUsed); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Chat Message Sent</td>
                                                <td><?php echo number_format($chatCount); ?></td>
                                            </tr>

                                            <tr>
                                                <td>Log In Count</td>
                                                <td><?php echo number_format($loginCount); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($tab === 'Characters'): ?>
                                <?php
                                $charSql = "SELECT C.*, MG.Country as Faction
                                FROM PS_GameData.dbo.Chars C
                                LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID
                                WHERE C.UserUID = ?
                                ORDER BY C.Del ASC, C.Slot ASC";
                                $activeChars = [];
                                $deletedChars = [];
                                try {
                                    $cStmt = $pdoAdmin->prepare($charSql);
                                    $cStmt->execute([$userUID]);
                                    while ($c = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                                        if ((int) $c['Del'] === 0) {
                                            $activeChars[] = $c;
                                        } else {
                                            $deletedChars[] = $c;
                                        }
                                    }
                                } catch (Throwable $e) {}
                                ?>

                                <!-- Active Characters -->
                                <div style="margin-bottom: 50px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h2 style="color: #fff; font-size: 18px; text-transform: uppercase; letter-spacing: 2px;">
                                            Active
                                            Characters</h2>
                                    </div>
                                    <div class="chars-table-container">
                                        <table class="chars-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 8%;">Slot</th>
                                                    <th style="width: 5%;"></th>
                                                    <th style="text-align: left; padding-left: 10px; width: 22%;">Name</th>
                                                    <th style="width: 10%;">Lv</th>
                                                    <th style="width: 15%;">Race</th>
                                                    <th style="width: 15%;">Create date</th>
                                                    <th style="width: 15%;">Last enter</th>
                                                    <th style="text-align:right; width: 10%;">Actions</th>
                                                </tr>
                                            </thead>
                                        <tbody>
                                            <?php if (empty($activeChars)): ?>
                                                <tr>
                                                    <td colspan="8" style="text-align:center; color:#666; padding: 40px;">No active
                                                        characters
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($activeChars as $c): ?>
                                                    <tr>
                                                        <td><?php echo $c['Slot']; ?></td>
                                                        <td><img src="assets/class/<?php echo (int) $c['Job']; ?>.webp"
                                                                style="width: 24px; vertical-align: middle;"></td>
                                                        <td><a href="/admin?view=CharEdit&id=<?php echo $c['CharID']; ?>"
                                                                class="user-link"><?php echo htmlspecialchars($c['CharName']); ?></a></td>
                                                        <td><?php echo $c['Level']; ?></td>
                                                        <td><?php echo getRaceName((int) $c['Family']); ?></td>
                                                        <td style="color:#888;"><?php echo date('Y M d H:i', strtotime($c['RegDate'])); ?>
                                                        </td>
                                                        <td style="color: #e8c881;"><?php echo formatRelativeTime($c['LeaveDate']); ?></td>
                                                        <td style="text-align:right;">
                                                            <div style="display: flex; justify-content: flex-end; gap: 15px;">
                                                                <form action="/admin_actions" method="POST" class="js-confirm-submit"
                                                                    data-confirm="<?php echo htmlspecialchars('Delete character ' . (string) $c['CharName'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <input type="hidden" name="action" value="char_delete">
                                                                    <input type="hidden" name="char_id" value="<?php echo $c['CharID']; ?>">
                                                                    <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                                                    <input type="hidden" name="return_view" value="UserEdit">
                                                                    <input type="hidden" name="tab" value="Characters">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <button type="submit"
                                                                        style="background:none; border:none; color:#e53e3e; cursor:pointer; font-size: 18px;"
                                                                        title="Delete"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Deleted Characters -->
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h2 style="color: #fff; font-size: 18px; text-transform: uppercase; letter-spacing: 2px;">
                                            Deleted
                                            Characters</h2>
                                    </div>
                                    <div class="chars-table-container">
                                        <table class="chars-table">
                                            <thead>
                                                <tr>
                                                    <th style="text-align: left; padding-left: 10px; width: 20%;">Name</th>
                                                    <th style="width: 10%;">Lv</th>
                                                    <th style="width: 15%;">Race</th>
                                                    <th style="width: 15%;">Create date</th>
                                                    <th style="width: 15%;">Last enter</th>
                                                    <th style="width: 15%;">Delete date</th>
                                                    <th style="text-align:right; width: 10%;">Actions</th>
                                                </tr>
                                            </thead>
                                        <tbody>
                                            <?php if (empty($deletedChars)): ?>
                                                <tr>
                                                    <td colspan="7" style="text-align:center; color:#666; padding: 40px;">No deleted
                                                        characters
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($deletedChars as $c): ?>
                                                    <tr>
                                                        <td><a href="/admin?view=CharEdit&id=<?php echo $c['CharID']; ?>"
                                                                class="user-link"><?php echo htmlspecialchars($c['CharName']); ?></a></td>
                                                        <td><?php echo $c['Level']; ?></td>
                                                        <td><?php echo getRaceName((int) $c['Family']); ?></td>
                                                        <td style="color:#888;"><?php echo date('Y M d H:i', strtotime($c['RegDate'])); ?>
                                                        </td>
                                                        <td style="color: #e8c881;"><?php echo formatRelativeTime($c['LeaveDate']); ?></td>
                                                        <td style="color:#888;">
                                                            <?php echo $c['DeleteDate'] ? date('Y M d H:i', strtotime($c['DeleteDate'])) : 'N/A'; ?>
                                                        </td>
                                                        <td style="text-align:right;">
                                                            <form action="/admin_actions" method="POST" class="js-confirm-submit"
                                                                data-confirm="<?php echo htmlspecialchars('Restore character ' . (string) $c['CharName'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="hidden" name="action" value="char_restore">
                                                                <input type="hidden" name="char_id" value="<?php echo $c['CharID']; ?>">
                                                                <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                                                <input type="hidden" name="return_view" value="UserEdit">
                                                                <input type="hidden" name="tab" value="Characters">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                <button type="submit"
                                                                    style="background:none; border:none; color:#48bb78; cursor:pointer;"
                                                                    title="Restore"><i class="fas fa-undo"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php elseif ($tab === 'AuthLog'): ?>
                                <?php
                                // Pagination for Auth Log
                                $alLimit = 30;
                                $alPage = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $alFilterType = isset($_GET['at']) ? $_GET['at'] : '';
                                $alSearchIP = isset($_GET['sip']) ? trim($_GET['sip']) : '';
                                $alOffset = ($alPage - 1) * $alLimit;

                                // Build WHERE clause
                                $alWhere = ["UserID = ?"];
                                $alParams = [$userData['UserID']];
                                if ($alFilterType === '107') {
                                    $alWhere[] = "ActionType = 107";
                                } elseif ($alFilterType === '108') {
                                    $alWhere[] = "ActionType = 108";
                                } else {
                                    $alWhere[] = "ActionType IN (107, 108)";
                                }
                                if ($alSearchIP !== '') {
                                    $alWhere[] = "Text1 LIKE ?";
                                    $alParams[] = '%' . $alSearchIP . '%';
                                }
                                $alWhereSql = "WHERE " . implode(" AND ", $alWhere);

                                // Total Count
                                $alTotal = 0;
                                $alTotalPages = 1;
                                $alCountSql = "SELECT COUNT(*) as Total FROM PS_GameLog.dbo.ActionLog $alWhereSql";
                                try {
                                    $alCountStmt = $pdoAdmin->prepare($alCountSql);
                                    $alCountStmt->execute($alParams);
                                    $alTotal = (int) ($alCountStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                    $alTotalPages = max(1, ceil($alTotal / $alLimit));
                                } catch (Throwable $e) {}

                                $alSql = "SELECT ActionType, ActionTime, Text1
                                          FROM PS_GameLog.dbo.ActionLog
                                          $alWhereSql
                                          ORDER BY ActionTime DESC
                                          OFFSET $alOffset ROWS FETCH NEXT $alLimit ROWS ONLY";
                                $alRows = [];
                                try {
                                    $alStmt = $pdoAdmin->prepare($alSql);
                                    $alStmt->execute($alParams);
                                    $alRows = $alStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                    <h4 style="color: #fff; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <i class="fas fa-sign-in-alt" style="color: #da9f50; font-size: 14px;"></i> Authentication Log
                                    </h4>
                                    <span style="color: #555; font-size: 12px;"><?php echo number_format($alTotal); ?> event<?php echo $alTotal !== 1 ? 's' : ''; ?></span>
                                </div>

                                <div class="chars-table-container">
                                    <form method="GET" id="authLogFilters">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="AuthLog">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 120px;">Type
                                                    <select name="at" class="table-filter-select js-auto-submit-onchange">
                                                        <option value="">All</option>
                                                        <option value="107" <?php echo $alFilterType === '107' ? 'selected' : ''; ?>>Login</option>
                                                        <option value="108" <?php echo $alFilterType === '108' ? 'selected' : ''; ?>>Logout</option>
                                                    </select>
                                                </th>
                                                <th>Date & Time</th>
                                                <th>IP Address
                                                    <input type="text" name="sip" class="table-filter-input js-auto-submit-onchange" placeholder="IP" value="<?php echo htmlspecialchars($alSearchIP); ?>">
                                                </th>
                                                <th>Relative</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $alCount = 0;
                                            $prevTime = null;
                                            foreach ($alRows as $al):
                                                $alCount++;
                                                $isLogin = (int) $al['ActionType'] === 107;
                                                $actionTime = strtotime($al['ActionTime']);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($isLogin): ?>
                                                            <span style="background: rgba(72, 187, 120, 0.1); color: #48bb78; border: 1px solid rgba(72, 187, 120, 0.2); font-size: 11px; padding: 3px 10px; border-radius: 4px; font-weight: 600;">
                                                                <i class="fas fa-sign-in-alt" style="margin-right: 4px;"></i> LOGIN
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="background: rgba(229, 62, 62, 0.1); color: #e53e3e; border: 1px solid rgba(229, 62, 62, 0.2); font-size: 11px; padding: 3px 10px; border-radius: 4px; font-weight: 600;">
                                                                <i class="fas fa-sign-out-alt" style="margin-right: 4px;"></i> LOGOUT
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('Y M d', $actionTime); ?> <span style="color: #e8c881; font-weight: 500;"><?php echo date('H:i:s', $actionTime); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($isLogin && !empty($al['Text1'])): ?>
                                                            <span style="color: #6a8ec1; font-family: monospace; font-size: 12px;">
                                                                <i class="fas fa-globe" style="margin-right: 5px; opacity: 0.5; font-size: 10px;"></i><?php echo htmlspecialchars($al['Text1']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="color: #333;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="color: #888;">
                                                        <?php echo formatRelativeTime($al['ActionTime']); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php if ($alCount === 0): ?>
                                                <tr>
                                                    <td colspan="4" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                        <i class="fas fa-shield-alt" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                        No authentication events found for this account.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </form>
                                </div>

                                <?php if ($alTotalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($alPage > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog&p=<?php echo $alPage - 1; ?><?php echo $alFilterType !== '' ? '&at=' . $alFilterType : ''; ?><?php echo $alSearchIP !== '' ? '&sip=' . urlencode($alSearchIP) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $alStartPage = max(1, $alPage - 2);
                                        $alEndPage = min($alTotalPages, $alPage + 2);
                                        for ($i = $alStartPage; $i <= $alEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog&p=<?php echo $i; ?><?php echo $alFilterType !== '' ? '&at=' . $alFilterType : ''; ?><?php echo $alSearchIP !== '' ? '&sip=' . urlencode($alSearchIP) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $alPage) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($alPage < $alTotalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog&p=<?php echo $alPage + 1; ?><?php echo $alFilterType !== '' ? '&at=' . $alFilterType : ''; ?><?php echo $alSearchIP !== '' ? '&sip=' . urlencode($alSearchIP) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $alPage; ?> OF <?php echo $alTotalPages; ?> &bull; TOTAL
                                        <?php echo number_format($alTotal); ?> EVENTS
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'PointLog'): ?>
                                <?php
                                // Pagination for Point Log
                                $plLimit = 25;
                                $plPage = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $plSearchReason = isset($_GET['sr']) ? trim($_GET['sr']) : '';
                                $plOffset = ($plPage - 1) * $plLimit;

                                // Build WHERE
                                $plWhereParts = ["UserID = ?"];
                                $plParams = [$userData['UserID']];
                                if ($plSearchReason !== '') {
                                    $plWhereParts[] = "Reason LIKE ?";
                                    $plParams[] = '%' . $plSearchReason . '%';
                                }
                                $plWhereSql = implode(" AND ", $plWhereParts);

                                // Total Count
                                $plTotal = 0;
                                $plTotalPages = 1;
                                $plCountSql = "SELECT COUNT(*) as Total FROM PS_WebSite.dbo.Web_PointHistory WHERE $plWhereSql";
                                try {
                                    $plCountStmt = $pdoAdmin->prepare($plCountSql);
                                    $plCountStmt->execute($plParams);
                                    $plTotal = (int) ($plCountStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                    $plTotalPages = max(1, ceil($plTotal / $plLimit));
                                } catch (Throwable $e) {}

                                // Fetch Point History
                                $plSql = "SELECT RowID, UserID, Date, PointsAdded, Reason, GM_Account 
                                          FROM PS_WebSite.dbo.Web_PointHistory 
                                          WHERE $plWhereSql
                                          ORDER BY RowID DESC 
                                          OFFSET $plOffset ROWS FETCH NEXT $plLimit ROWS ONLY";
                                $plRows = [];
                                try {
                                    $plStmt = $pdoAdmin->prepare($plSql);
                                    $plStmt->execute($plParams);
                                    $plRows = $plStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                    <h4 style="color: #fff; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <i class="fas fa-history" style="color: #da9f50; font-size: 14px;"></i> Point Transaction History
                                    </h4>
                                    <span style="color: #555; font-size: 12px;"><?php echo number_format($plTotal); ?> record<?php echo $plTotal !== 1 ? 's' : ''; ?></span>
                                </div>

                                <div class="chars-table-container">
                                    <form method="GET" id="pointLogFilters">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="PointLog">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 80px;">#</th>
                                                <th>Date</th>
                                                <th>Points</th>
                                                <th style="text-align: left;">Reason
                                                    <input type="text" name="sr" class="table-filter-input js-auto-submit-onchange" style="text-align: left;" placeholder="Reason" value="<?php echo htmlspecialchars($plSearchReason); ?>">
                                                </th>
                                                <th>Admin / GM</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $plCount = 0;
                                            foreach ($plRows as $h):
                                                $plCount++;
                                                ?>
                                                <tr>
                                                    <td style="font-family: 'monospace'; color: #888;">#<?php echo $h['RowID']; ?></td>
                                                    <td>
                                                        <?php echo date('Y M d', strtotime($h['Date'])); ?> <span style="color: #888; font-size: 11px;"><?php echo date('H:i', strtotime($h['Date'])); ?></span>
                                                    </td>
                                                    <td style="color: #e8c881; font-weight: 700;">
                                                        +<?php echo number_format($h['PointsAdded']); ?>
                                                    </td>
                                                    <td style="text-align: left; color: #aaa; font-style: italic;">
                                                        <?php echo htmlspecialchars($h['Reason'] ?: '-'); ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge" style="background: rgba(106, 142, 193, 0.1); color: #6a8ec1; border: 1px solid rgba(106, 142, 193, 0.2); font-size: 11px; padding: 3px 10px;">
                                                            <i class="fas fa-user-shield" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($h['GM_Account']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if ($plCount === 0): ?>
                                                <tr>
                                                    <td colspan="5" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                        <i class="fas fa-info-circle" style="margin-right: 10px;"></i> No point transactions found for this account.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </form>
                                </div>

                                <?php if ($plTotalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($plPage > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog&p=<?php echo $plPage - 1; ?><?php echo $plSearchReason !== '' ? '&sr=' . urlencode($plSearchReason) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $plStartPage = max(1, $plPage - 2);
                                        $plEndPage = min($plTotalPages, $plPage + 2);
                                        for ($i = $plStartPage; $i <= $plEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog&p=<?php echo $i; ?><?php echo $plSearchReason !== '' ? '&sr=' . urlencode($plSearchReason) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $plPage) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($plPage < $plTotalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog&p=<?php echo $plPage + 1; ?><?php echo $plSearchReason !== '' ? '&sr=' . urlencode($plSearchReason) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $plPage; ?> OF <?php echo $plTotalPages; ?> &bull; TOTAL
                                        <?php echo number_format($plTotal); ?> TRANSACTIONS
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'Purchases'): ?>
                                <?php
                                // Pagination for Purchases (ActionType 113 from ActionLog)
                                $puLimit = 25;
                                $puPage = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $puSearchItem = isset($_GET['si']) ? trim($_GET['si']) : '';
                                $puOffset = ($puPage - 1) * $puLimit;

                                // Build WHERE (no alias for count, AL. prefix for join query)
                                // Build WHERE - using parameterized queries
                                $puWhereParts = ["AL.UserUID = ?", "AL.ActionType = 113"];
                                $puCountWhereParts = ["UserUID = ?", "ActionType = 113"];
                                $puParams = [$userUID];
                                if ($puSearchItem !== '') {
                                    $puWhereParts[] = "AL.Text1 LIKE ?";
                                    $puCountWhereParts[] = "Text1 LIKE ?";
                                    $puParams[] = '%' . $puSearchItem . '%';
                                }

                                // Total Count
                                $puTotal = 0;
                                $puTotalPages = 1;
                                $puCountSql = "SELECT COUNT(*) as Total FROM PS_GameLog.dbo.ActionLog WHERE " . implode(" AND ", $puCountWhereParts);
                                try {
                                    $puCountStmt = $pdoAdmin->prepare($puCountSql);
                                    $puCountStmt->execute($puParams);
                                    $puTotal = (int) ($puCountStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                    $puTotalPages = max(1, ceil($puTotal / $puLimit));
                                } catch (Throwable $e) {}

                                // Fetch Purchases with Character Name
                                $puSql = "SELECT AL.ActionTime, AL.CharID, AL.Text1, AL.Value4, AL.Value5, C.CharName
                                          FROM PS_GameLog.dbo.ActionLog AL
                                          LEFT JOIN PS_GameData.dbo.Chars C ON C.CharID = AL.CharID
                                          WHERE " . implode(" AND ", $puWhereParts) . "
                                          ORDER BY AL.ActionTime DESC
                                          OFFSET $puOffset ROWS FETCH NEXT $puLimit ROWS ONLY";
                                $puRows = [];
                                try {
                                    $puStmt = $pdoAdmin->prepare($puSql);
                                    $puStmt->execute($puParams);
                                    $puRows = $puStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                    <h4 style="color: #fff; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <i class="fas fa-shopping-cart" style="color: #da9f50; font-size: 14px;"></i> Purchase History
                                    </h4>
                                    <span style="color: #555; font-size: 12px;"><?php echo number_format($puTotal); ?> purchase<?php echo $puTotal !== 1 ? 's' : ''; ?></span>
                                </div>

                                <div class="chars-table-container">
                                    <form method="GET" id="purchaseFilters">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="Purchases">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th style="text-align: left; padding-left: 20px;">Item
                                                    <input type="text" name="si" class="table-filter-input js-auto-submit-onchange" style="text-align: left;" placeholder="Item" value="<?php echo htmlspecialchars($puSearchItem); ?>">
                                                </th>
                                                <th>Character</th>
                                                <th>Qty</th>
                                                <th>Gold</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $puCount = 0;
                                            foreach ($puRows as $pu):
                                                $puCount++;
                                                $itemName = $pu['Text1'] ?: '-';
                                                $charName = $pu['CharName'] ?: '-';
                                                $qty = (int) ($pu['Value4'] ?? 0);
                                                $gold = number_format(abs((int) ($pu['Value5'] ?? 0)));
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php echo date('Y M d', strtotime($pu['ActionTime'])); ?> <span style="color: #888; font-size: 11px;"><?php echo date('H:i', strtotime($pu['ActionTime'])); ?></span>
                                                    </td>
                                                    <td style="text-align: left; padding-left: 20px;">
                                                        <span style="color: #e8c881; font-weight: 500;"><?php echo htmlspecialchars($itemName); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($pu['CharID'] && $charName !== '-'): ?>
                                                            <a href="/admin?view=CharEdit&id=<?php echo $pu['CharID']; ?>" class="user-link"><?php echo htmlspecialchars($charName); ?></a>
                                                        <?php else: ?>
                                                            <span style="color: #444;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="color: #aaa;">
                                                        x<?php echo $qty; ?>
                                                    </td>
                                                    <td style="color: #e8c881; font-weight: 600;">
                                                        <?php echo $gold; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php if ($puCount === 0): ?>
                                                <tr>
                                                    <td colspan="5" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                        <i class="fas fa-shopping-cart" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                        No purchases found for this account.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </form>
                                </div>

                                <?php if ($puTotalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($puPage > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases&p=<?php echo $puPage - 1; ?><?php echo $puSearchItem !== '' ? '&si=' . urlencode($puSearchItem) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $puStartPage = max(1, $puPage - 2);
                                        $puEndPage = min($puTotalPages, $puPage + 2);
                                        for ($i = $puStartPage; $i <= $puEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases&p=<?php echo $i; ?><?php echo $puSearchItem !== '' ? '&si=' . urlencode($puSearchItem) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $puPage) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($puPage < $puTotalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases&p=<?php echo $puPage + 1; ?><?php echo $puSearchItem !== '' ? '&si=' . urlencode($puSearchItem) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $puPage; ?> OF <?php echo $puTotalPages; ?> &bull; TOTAL
                                        <?php echo number_format($puTotal); ?> PURCHASES
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'Chat'): ?>
                                <?php
                                // Pagination for Chat
                                $limit = 25;
                                $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $chatFilterType = isset($_GET['ct']) ? $_GET['ct'] : '';
                                $offset = ($page - 1) * $limit;

                                // Build WHERE clause
                                $whereClauses = ["L.UserUID = ?"];
                                $queryParams = [$userUID];
                                if ($chatFilterType !== '') {
                                    $whereClauses[] = "L.ChatType = ?";
                                    $queryParams[] = (int) $chatFilterType;
                                }
                                $whereSql = "WHERE " . implode(" AND ", $whereClauses);

                                // Total Count
                                $countSql = "SELECT COUNT(*) as Total FROM PS_ChatLog.dbo.ChatLog L $whereSql";
                                    $totalResults = 0;
                                    $totalPages = 1;
                                    $chatRows = [];
                                    try {
                                        $cStmt = $pdoAdmin->prepare($countSql);
                                        $cStmt->execute($queryParams);
                                        $totalResults = (int) ($cStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                        $totalPages = max(1, ceil($totalResults / $limit));
                                    } catch (Throwable $e) {}

                                    // Fetch Chat Logs - OFFSET and FETCH NEXT require SQL Server 2012+
                                    $chatSql = "SELECT L.CharID, L.ChatType, L.ChatData, L.MapID, L.ChatTime, C.CharName 
                                    FROM PS_ChatLog.dbo.ChatLog L
                                    LEFT JOIN PS_GameData.dbo.Chars C ON C.CharID = L.CharID
                                    $whereSql
                                    ORDER BY L.ChatTime DESC
                                    OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

                                    try {
                                        $chatQuery = $pdoAdmin->prepare($chatSql);
                                        $chatQuery->execute($queryParams);
                                        $chatRows = $chatQuery->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Throwable $e) {}
                                ?>

                                <div style="margin-bottom: 25px; display: flex; align-items: flex-end;">
                                    <form method="GET" style="display: flex; flex-direction: column; gap: 8px;">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="Chat">

                                        <label
                                            style="color: #888; text-transform: uppercase; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; padding-left: 2px;">Type</label>
                                        <select name="ct"
                                            style="background: #050505; border: 1px solid #222; color: #ccc; padding: 8px 15px; font-size: 13px; outline: none; border-radius: 4px; min-width: 150px; cursor: pointer; transition: border-color 0.3s;"
                                            class="js-auto-submit-onchange js-hover-border" data-border-hover="#444" data-border-default="#222">
                                            <option value="">All</option>
                                            <?php
                                            $cTypesList = [1, 3, 4, 5, 7];
                                            foreach ($cTypesList as $ctId):
                                                ?>
                                                <option value="<?php echo $ctId; ?>" <?php echo ($chatFilterType !== '' && (int) $chatFilterType === $ctId) ? 'selected' : ''; ?>>
                                                    <?php echo getChatTypeName($ctId); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </div>

                                <div class="chars-table-container">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 160px; text-align: left; padding-left: 15px;">Time</th>
                                                <th style="width: 140px; text-align: left;">Character</th>
                                                <th style="width: 110px;">Type</th>
                                                <th style="text-align: left;">Message</th>
                                                <th style="width: 120px;">Map</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $hasChat = false;
                                                          foreach ($chatRows as $chat):
                                                $hasChat = true; ?>
                                                <tr>
                                                    <td>
                                                        <?php $ct = strtotime($chat['ChatTime']); ?>
                                                        <span style="color: #aaa;"><?php echo date('d M Y', $ct); ?></span>
                                                        <span style="color: #555; font-size: 11px;"><?php echo date('H:i', $ct); ?></span>
                                                     </td>
                                                    <td>
                                                        <?php if ($chat['CharName']): ?>
                                                            <a href="/admin?view=CharEdit&id=<?php echo $chat['CharID']; ?>" class="user-link"><?php echo htmlspecialchars($chat['CharName']); ?></a>
                                                        <?php else: ?>
                                                            <span><?php echo $chat['CharID']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $cType = (int) $chat['ChatType'];
                                                        $cColor = '#eee';
                                                        $cBg = 'rgba(255,255,255,0.04)';
                                                        if ($cType == 0) { $cColor = '#f6ad55'; $cBg = 'rgba(246,173,85,0.08)'; }      // Whisper
                                                        if ($cType == 1) { $cColor = '#aaa';    $cBg = 'rgba(255,255,255,0.04)'; }     // Normal
                                                        if ($cType == 3) { $cColor = '#da70d6'; $cBg = 'rgba(218,112,214,0.08)'; }     // Guild
                                                        if ($cType == 4) { $cColor = '#68d391'; $cBg = 'rgba(104,211,145,0.08)'; }     // Party
                                                        if ($cType == 5) { $cColor = '#e8c881'; $cBg = 'rgba(232,200,129,0.08)'; }     // Trade
                                                        if ($cType == 7) { $cColor = '#7b8cf7'; $cBg = 'rgba(123,140,247,0.08)'; }     // Area
                                                        ?>
                                                        <span style="display: inline-block; background: <?php echo $cBg; ?>; color: <?php echo $cColor; ?>; border: 1px solid <?php echo $cColor; ?>22; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; padding: 3px 8px; border-radius: 3px;">
                                                            <?php echo getChatTypeName($cType); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($chat['ChatData']); ?>
                                                    </td>
                                                    <td><?php echo getMapName($chat['MapID']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (!$hasChat): ?>
                                                <tr>
                                                    <td colspan="5" style="text-align:center; color:#555; padding: 40px;">No chat history found for this account</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>

                                </div>

                                <?php if ($totalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($page > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat&p=<?php echo $page - 1; ?><?php echo $chatFilterType !== '' ? '&ct=' . urlencode($chatFilterType) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $chatStartPage = max(1, $page - 2);
                                        $chatEndPage = min($totalPages, $page + 2);
                                        for ($i = $chatStartPage; $i <= $chatEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat&p=<?php echo $i; ?><?php echo $chatFilterType !== '' ? '&ct=' . urlencode($chatFilterType) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $page) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat&p=<?php echo $page + 1; ?><?php echo $chatFilterType !== '' ? '&ct=' . urlencode($chatFilterType) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $page; ?> OF <?php echo $totalPages; ?> &bull; TOTAL
                                        <?php echo number_format($totalResults); ?> MESSAGES
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'Warehouse'): ?>
                                <?php
                                $whRows = [];
                                try {
                                    $whStmt = $pdoAdmin->prepare("SELECT S.Slot, S.ItemID, S.ItemCount, S.BuyDate, I.ItemName FROM PS_GameData.dbo.UserStoredPointItems S LEFT JOIN PS_GameDefs.dbo.Items I ON I.ItemID = S.ItemID WHERE S.UserUID = ? ORDER BY S.Slot ASC");
                                    $whStmt->execute([$userUID]);
                                    $whRows = $whStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>
                                <div class="chars-table-container">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">Slot</th>
                                                <th>Item Name</th>
                                                <th style="width: 80px;">Item ID</th>
                                                <th style="width: 80px;">Count</th>
                                                <th style="width: 170px;">Buy Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($whRows) > 0): ?>
                                                <?php foreach ($whRows as $wh): ?>
                                                    <tr>
                                                        <td><?php echo (int) $wh['Slot']; ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($wh['ItemName'] ?? 'Unknown')); ?></td>
                                                        <td><?php echo (int) $wh['ItemID']; ?></td>
                                                        <td><?php echo (int) $wh['ItemCount']; ?></td>
                                                        <td style="color: #888;"><?php echo htmlspecialchars((string) ($wh['BuyDate'] ?? '')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" style="text-align: center; padding: 60px; color: #444;">
                                                        <i class="fas fa-box-open" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                        Warehouse is empty.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php endif; ?> <!-- end tab switch (USEREDIT) -->
                        <?php endif; ?> <!-- end if (!$userData) ... else -->

