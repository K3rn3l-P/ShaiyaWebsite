                    <?php
                    // --- Dashboard Stats ---
                    $topChars = [];
                    try {
                        $topCharsQ = $pdoAdmin->query("SELECT TOP 5 CharID, CharName, Level, Job, K1 as Kills FROM PS_GameData.dbo.Chars WHERE Del = 0 ORDER BY K1 DESC");
                        if ($topCharsQ) $topChars = $topCharsQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}

                    $recentUsers = [];
                    try {
                        $recentUsersQ = $pdoAdmin->query("SELECT TOP 8 UserID, Status, JoinDate, Point FROM PS_UserData.dbo.Users_Master ORDER BY UserUID DESC");
                        if ($recentUsersQ) $recentUsers = $recentUsersQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}
                    ?>

                    <!-- Bottom Grid: Top Killers + Recent Registrations -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">

                        <!-- Top Killers -->
                        <div style="background: rgba(10,10,10,0.5); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; overflow: hidden;">
                            <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-skull" style="color: #e8c881; font-size: 14px;"></i>
                                <span style="font-family: 'Futura PT', sans-serif; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #aaa;">Top Killers</span>
                            </div>
                            <table style="width: 100%; border-collapse: collapse; font-family: 'Futura PT', sans-serif;">
                                <thead>
                                    <tr style="background: rgba(0,0,0,0.3);">
                                        <th style="padding: 10px 20px; text-align: left; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">#</th>
                                        <th style="padding: 10px 20px; text-align: left; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Character</th>
                                        <th style="padding: 10px 20px; text-align: center; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Level</th>
                                        <th style="padding: 10px 20px; text-align: right; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Kills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topChars as $i => $tc): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                        <td style="padding: 13px 20px; color: #444; font-size: 12px; font-weight: 700;"><?php echo $i+1; ?></td>
                                        <td style="padding: 13px 20px;">
                                            <a href="/admin?view=CharEdit&id=<?php echo $tc['CharID']; ?>" class="user-link" style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($tc['CharName']); ?></a>
                                            <span style="margin-left: 8px; opacity: 0.5;"><img src="assets/class/<?php echo (int)$tc['Job']; ?>.webp" style="width: 16px; vertical-align: middle;"></span>
                                        </td>
                                        <td style="padding: 13px 20px; text-align: center; color: #e8c881; font-size: 13px;"><?php echo $tc['Level']; ?></td>
                                        <td style="padding: 13px 20px; text-align: right; color: #48bb78; font-weight: 700; font-size: 14px;"><?php echo number_format($tc['Kills']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($topChars)): ?>
                                    <tr><td colspan="4" style="padding: 40px; text-align: center; color: #444;">No data available.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Recent Registrations -->
                        <div style="background: rgba(10,10,10,0.5); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; overflow: hidden;">
                            <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-user-clock" style="color: #e8c881; font-size: 14px;"></i>
                                <span style="font-family: 'Futura PT', sans-serif; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #aaa;">Recent Registrations</span>
                            </div>
                            <table style="width: 100%; border-collapse: collapse; font-family: 'Futura PT', sans-serif;">
                                <thead>
                                    <tr style="background: rgba(0,0,0,0.3);">
                                        <th style="padding: 10px 20px; text-align: left; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Username</th>
                                        <th style="padding: 10px 20px; text-align: center; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Status</th>
                                        <th style="padding: 10px 20px; text-align: right; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $ru): ?>
                                    <?php
                                        $ruStatus = (int)($ru['Status'] ?? 0);
                                        $ruLabel = 'Player'; $ruColor = '#555';
                                        if ($ruStatus == 16) { $ruLabel = 'ADM'; $ruColor = '#48bb78'; }
                                        elseif ($ruStatus == 32) { $ruLabel = 'GM'; $ruColor = '#3498db'; }
                                        elseif ($ruStatus == 48) { $ruLabel = 'GMA'; $ruColor = '#e8c881'; }
                                    ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                        <td style="padding: 13px 20px;">
                                            <a href="/admin?view=USERS&search_user=<?php echo urlencode($ru['UserID']); ?>" class="user-link" style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($ru['UserID']); ?></a>
                                        </td>
                                        <td style="padding: 13px 20px; text-align: center;">
                                            <span style="font-size: 10px; font-weight: 700; color: <?php echo $ruColor; ?>; text-transform: uppercase; letter-spacing: 1px;"><?php echo $ruLabel; ?></span>
                                        </td>
                                        <td style="padding: 13px 20px; text-align: right; color: #666; font-size: 12px;"><?php echo date('d M Y', strtotime($ru['JoinDate'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentUsers)): ?>
                                    <tr><td colspan="3" style="padding: 40px; text-align: center; color: #444;">No data available.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
