                    <?php
                    $adminWhitelistUsers = [];
                    $adminWhitelistIps = [];

                    try {
                        $wQ = $pdoAdmin->query("SELECT UserUID, UserID FROM PS_UserData.dbo.AdminUserWhitelist ORDER BY UserID ASC");
                        if ($wQ) $adminWhitelistUsers = $wQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}

                    try {
                        $wIpQ = $pdoAdmin->query("SELECT IPAddress FROM PS_UserData.dbo.AdminIPWhitelist ORDER BY IPAddress ASC");
                        if ($wIpQ) $adminWhitelistIps = $wIpQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}
                    ?>
                    <div class="char-edit-section" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
                        <div style="text-align: center; margin-bottom: 35px;">
                            <h3 style="margin-bottom: 10px; color: #e8c881;">ADMIN/IP WHITELIST MANAGEMENT</h3>
                            <p style="color: #666; font-size: 14px;">Add privileged admin accounts and trusted admin IPs. Removal is intentionally disabled in this panel.</p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                            <div style="display: flex; flex-direction: column; gap: 30px;">
                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 28px;">
                                    <h4 style="color: #fff; margin-bottom: 20px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Add Privileged Admin</h4>
                                    <form action="/admin_actions" method="POST" style="display: block;">
                                        <input type="hidden" name="action" value="admin_whitelist_add_user">
                                        <input type="hidden" name="return_view" value="ADMIN_WHITELIST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="char-input-group" style="margin-bottom: 14px;">
                                            <input type="text" name="admin_userid" class="char-input" maxlength="32" required placeholder="Enter admin UserID..." style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px;">
                                        </div>
                                        <button type="submit" class="btn-action btn-sm" style="width: 100%;">ADD ADMIN USER</button>
                                    </form>
                                </div>

                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 28px;">
                                    <h4 style="color: #fff; margin-bottom: 20px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Add Trusted Admin IP</h4>
                                    <form action="/admin_actions" method="POST" style="display: block;">
                                        <input type="hidden" name="action" value="admin_whitelist_add_ip">
                                        <input type="hidden" name="return_view" value="ADMIN_WHITELIST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="char-input-group" style="margin-bottom: 14px;">
                                            <input type="text" name="admin_ip" class="char-input" maxlength="45" required placeholder="Enter IPv4 or IPv6..." style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px;">
                                        </div>
                                        <button type="submit" class="btn-action btn-sm" style="width: 100%;">ADD ADMIN IP</button>
                                    </form>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 30px;">
                                <div style="background: rgba(0,0,0,0.25); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 24px; min-height: 250px;">
                                    <h4 style="color: #fff; margin-bottom: 18px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">Whitelisted Admin Accounts</h4>
                                    <?php if (!empty($adminWhitelistUsers)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 10px; max-height: 280px; overflow-y: auto; padding-right: 4px;">
                                            <?php foreach ($adminWhitelistUsers as $wUser): ?>
                                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                                                    <span style="color: #e8c881; font-weight: 600;"><?php echo htmlspecialchars((string) ($wUser['UserID'] ?? '')); ?></span>
                                                    <span style="color: #666; font-size: 11px;">UID: <?php echo (int) ($wUser['UserUID'] ?? 0); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; color: #555; padding: 60px 10px;">No admin users whitelisted yet.</div>
                                    <?php endif; ?>
                                </div>

                                <div style="background: rgba(0,0,0,0.25); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 24px; min-height: 250px;">
                                    <h4 style="color: #fff; margin-bottom: 18px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">Whitelisted Admin IPs</h4>
                                    <?php if (!empty($adminWhitelistIps)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 10px; max-height: 280px; overflow-y: auto; padding-right: 4px;">
                                            <?php foreach ($adminWhitelistIps as $wIp): ?>
                                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                                                    <span style="color: #e8c881; font-weight: 600;"><?php echo htmlspecialchars((string) ($wIp['IPAddress'] ?? '')); ?></span>
                                                    <i class="fas fa-network-wired" style="color: #444;"></i>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; color: #555; padding: 60px 10px;">No admin IPs whitelisted yet.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
