                    
                    <div class="char-edit-section">
                        <p style="color: #666; font-size: 13px; margin-bottom: 30px;">Manage user points and view transaction history.</p>

                        <!-- Two-Column Layout: Form | Transaction History -->
                        <div style="display: grid; grid-template-columns: 380px 1fr; gap: 35px; align-items: start;">

                            <!-- Column 1: Add Points Form -->
                            <div style="background: rgba(255,255,255,0.02); padding: 35px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                <h4 style="color: #fff; margin-bottom: 30px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                                    <i class="fas fa-coins" style="color: #e8c881; margin-right: 10px;"></i>ADD POINTS</h4>
                                <form action="/admin_actions" method="POST" style="display: block;">
                                    <input type="hidden" name="action" value="points_add">
                                    <input type="hidden" name="return_view" value="POINTS">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div style="display: flex; flex-direction: column; gap: 22px; margin-bottom: 30px;">
                                        <div class="char-input-group" style="display: block;">
                                            <label style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">UserID</label>
                                            <input type="text" name="target_user" class="char-input"
                                                placeholder="Enter UserID..." list="user_list" required
                                                value="<?php echo isset($_GET['target_user']) ? htmlspecialchars($_GET['target_user']) : ''; ?>"
                                                style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px; font-size: 14px;">
                                            <datalist id="user_list">
                                                <?php
                                                try {
                                                    $uList = $pdoAdmin->query("SELECT TOP 100 UserID FROM PS_UserData.dbo.Users_Master ORDER BY UserID ASC");
                                                    if ($uList) foreach ($uList->fetchAll(PDO::FETCH_ASSOC) as $uRow) {
                                                        echo '<option value="' . htmlspecialchars($uRow['UserID']) . '">';
                                                    }
                                                } catch (Throwable $e) {}
                                                ?>
                                            </datalist>
                                        </div>

                                        <div class="char-input-group" style="display: block;">
                                            <label style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">SP (Amount)</label>
                                            <input type="number" name="point_amount" class="char-input" value="100" min="1" required
                                                style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px; text-align: center; font-size: 18px; font-weight: 700; color: #e8c881;">
                                        </div>

                                        <div class="char-input-group" style="display: block;">
                                            <label style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">Reason</label>
                                            <input type="text" name="reason" class="char-input" placeholder="Required reason..." required
                                                style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px; font-size: 14px;">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-action" style="width: 100%;">ACCEPT</button>
                                </form>
                            </div>

                            <!-- Column 2: Transaction History -->
                            <div style="background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 30px; display: flex; flex-direction: column;">
                                <h4 style="color: #fff; margin-bottom: 25px; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-history" style="color: #da9f50; font-size: 14px;"></i> Transaction History</h4>

                        <div class="chars-table-container">
                            <table class="chars-table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">#</th>
                                        <th style="text-align: left; padding-left: 20px;">UserID</th>
                                        <th>Date</th>
                                        <th>Points</th>
                                        <th style="text-align: left;">Reason</th>
                                        <th>Admin / GM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $perPage = 50;
                                    $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                    $offset = ($page - 1) * $perPage;

                                    $totalHistory = 0;
                                    $totalHistoryPages = 1;
                                    $pointHistory = [];
                                    try {
                                        $cRes = $pdoAdmin->query("SELECT COUNT(*) as Total FROM PS_WebSite.dbo.Web_PointHistory");
                                        if ($cRes) $totalHistory = (int) ($cRes->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                        $totalHistoryPages = max(1, (int) ceil($totalHistory / $perPage));

                                        // Robust fallback: avoid SQL Server ODBC pagination quirks (OFFSET/CTE + placeholders)
                                        // and paginate in PHP to ensure history is always visible when records exist.
                                        $histQ = $pdoAdmin->query("SELECT TOP 5000 RowID, UserID, [Date], PointsAdded, Reason, GM_Account FROM PS_WebSite.dbo.Web_PointHistory ORDER BY RowID DESC");
                                        $historyRows = $histQ ? $histQ->fetchAll(PDO::FETCH_ASSOC) : [];
                                        $pointHistory = array_slice($historyRows, $offset, $perPage);
                                    } catch (Throwable $e) {
                                        appSecurityLog('Points history load failed in admin.php: ' . $e->getMessage());
                                    }
                                    $count = 0;
                                    foreach ($pointHistory as $h):
                                        $count++;
                                        ?>
                                        <tr>
                                            <td style="font-family: 'monospace'; color: #888;">#<?php echo $h['RowID']; ?></td>
                                            <td style="text-align: left; padding-left: 20px;">
                                                <a href="/admin?view=USERS&search_user=<?php echo urlencode($h['UserID']); ?>" class="user-link"><?php echo htmlspecialchars($h['UserID']); ?></a>
                                            </td>
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
                                    <?php if ($count === 0): ?>
                                        <tr>
                                            <td colspan="6" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                <i class="fas fa-info-circle" style="margin-right: 10px;"></i> No transaction records found in database.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                                </div><!-- /transaction history column -->
                        </div><!-- /two-column grid -->
                    </div><!-- /char-edit-section -->
