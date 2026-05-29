                    <?php
                    // Get current settings
                    $permissionSettings = fetchWebsiteSettings(['GM_Permissions', 'GMA_Permissions']);
                    $perms_32 = !empty($permissionSettings['GM_Permissions']) ? json_decode($permissionSettings['GM_Permissions'], true) : [];
                    $perms_48 = !empty($permissionSettings['GMA_Permissions']) ? json_decode($permissionSettings['GMA_Permissions'], true) : [];
                    
                    $pagesList = [
                        'DASHBOARD' => 'Dashboard Overview',
                        'SEND_NOTICE' => 'Server Notice (Announcement)',
                        'USERS' => 'Users Management & Edit',
                        'CHARS' => 'Chars Management & Edit',
                        'ITEMS' => 'Items Management',
                        'GUILDS' => 'Guilds Management',
                        'GIFTBOX' => 'Giftbox Management',
                        'POINTS' => 'Points Management',
                        'DROPS_EDITOR' => 'Drops Editor - Modifica drop rate mob',
                        'DROPS_BLACKLIST_ADD' => 'Drops Blacklist Management',
                        'LUCKYCHEST_TOGGLE' => 'LuckyChest Management',
                        'DOWNLOADS_UPDATE' => 'Downloads Management',
                        'REGISTER_TOGGLE' => 'Registration Toggle',
                        'PERMISSIONS_MANAGEMENT' => 'Permissions Management',
                        'ADMIN_WHITELIST' => 'Admin/IP Whitelist Management',
                        'TASK_SCHEDULER' => 'Task Scheduler Management',
                        'BOSSES_EDITOR' => 'Bosses Management - World Boss configurazione',
                        'ACTIONLOG' => 'Action Logs Viewer'
                    ];
                    ?>
                    <div class="char-edit-section" style="max-width: 1000px; margin: 40px auto; padding: 30px; background: rgba(15,15,15,0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px;">
                        <h3 style="font-size: 20px; color: #e8c881; text-align: center; margin-bottom: 10px;">PERMISSIONS MANAGEMENT</h3>
                        <p style="color: #888; text-align: center; font-size: 13px; margin-bottom: 40px;">Check the boxes to allow staff ranks access to specific admin panel sections. ADM (16) always has full access.</p>
                        
                        <form action="/admin" method="POST">
                            <input type="hidden" name="admin_action" value="save_permissions">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; font-size: 14px;">Panel Section</th>
                                        <th style="text-align: center; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #3498db; font-size: 14px; width: 150px;">GM (Status 32)</th>
                                        <th style="text-align: center; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #e8c881; font-size: 14px; width: 150px;">GMA (Status 48)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagesList as $k => $label): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); background: rgba(255,255,255,0.01);">
                                        <td style="padding: 15px; color: #ccc; font-size: 13px;"><?php echo htmlspecialchars($label); ?> <span style="color:#555; font-size:10px; margin-left:10px;">(<?php echo $k; ?>)</span></td>
                                        <td style="padding: 15px; text-align: center; border-left: 1px solid rgba(255,255,255,0.02);">
                                            <input type="checkbox" name="perm_32[]" value="<?php echo $k; ?>" <?php echo in_array($k, $perms_32) ? 'checked' : ''; ?> style="transform: scale(1.3); cursor: pointer;">
                                        </td>
                                        <td style="padding: 15px; text-align: center; border-left: 1px solid rgba(255,255,255,0.02);">
                                            <input type="checkbox" name="perm_48[]" value="<?php echo $k; ?>" <?php echo in_array($k, $perms_48) ? 'checked' : ''; ?> style="transform: scale(1.3); cursor: pointer;">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div style="margin-top: 40px; text-align: center;">
                                <button type="submit" class="admin-btn" style="padding: 10px 40px; font-size: 14px;">SAVE PERMISSIONS</button>
                            </div>
                        </form>
                    </div>
