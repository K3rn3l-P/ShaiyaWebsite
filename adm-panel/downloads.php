                        <?php
                        $settings = [];
                        try {
                            $q = $pdoAdmin->query("SELECT SettingKey, SettingValue FROM PS_WebSite.dbo.Web_Settings WHERE SettingKey IN ('DownloadsEnabled', 'Link_GoogleDrive', 'Link_OfficialMirror', 'Link_Mega')");
                            if ($q) foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                $settings[$row['SettingKey']] = $row['SettingValue'];
                            }
                        } catch (Throwable $e) {}
                        ?>
                        <div class="char-edit-section" style="max-width: 800px; margin: 0 auto; padding: 20px;">
                            <div style="text-align: center; margin-bottom: 40px;">
                                <h3 style="margin-bottom: 10px; color: #e8c881;">DOWNLOADS MANAGEMENT</h3>
                                <p style="color: #666; font-size: 14px;">Update client download links and page visibility
                                    settings.</p>
                            </div>

                            <form action="/admin_actions" method="POST" class="char-edit-form"
                                style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 40px; display: block;">
                                <input type="hidden" name="action" value="downloads_update">
                                <input type="hidden" name="return_view" value="DOWNLOADS_UPDATE">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <!-- Status Section -->
                                <div
                                    style="background: rgba(0,0,0,0.3); padding: 25px; border-radius: 8px; border: 1px solid rgba(232,200,129,0.1); display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; gap: 20px;">
                                    <div style="flex: 1;">
                                        <div style="color: #fff; font-size: 16px; font-weight: 600; margin-bottom: 4px;">
                                            Page
                                            Visibility</div>
                                        <div style="color: #666; font-size: 12px; line-height: 1.4;">When disabled, users
                                            cannot
                                            access the downloads page. This is useful during maintenance or client updates.
                                        </div>
                                    </div>
                                    <div style="flex-shrink: 0;">
                                        <label class="switch">
                                            <input type="checkbox" name="dl_enabled" <?php echo ($settings['DownloadsEnabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Links Section -->
                                <div style="display: flex; flex-direction: column; gap: 30px;">
                                    <div class="char-input-group" style="display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Google
                                            Drive Client</label>
                                        <div
                                            style="display: flex; align-items: center; background: rgba(0,0,0,0.3); border: 1px solid #333; border-radius: 4px; padding: 0 15px;">
                                            <i class="fab fa-google-drive"
                                                style="color: #444; font-size: 16px; margin-right: 15px;"></i>
                                            <input type="text" name="link_google" class="char-input"
                                                value="<?php echo htmlspecialchars($settings['Link_GoogleDrive'] ?? ''); ?>"
                                                style="border: none !important; background: transparent !important; padding: 12px 0 !important; width: 100%; box-shadow: none !important; color: #fff;">
                                        </div>
                                    </div>

                                    <div class="char-input-group" style="display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Official
                                            Web Mirror</label>
                                        <div
                                            style="display: flex; align-items: center; background: rgba(0,0,0,0.3); border: 1px solid #333; border-radius: 4px; padding: 0 15px;">
                                            <i class="fas fa-server"
                                                style="color: #444; font-size: 16px; margin-right: 15px;"></i>
                                            <input type="text" name="link_mirror" class="char-input"
                                                value="<?php echo htmlspecialchars($settings['Link_OfficialMirror'] ?? ''); ?>"
                                                style="border: none !important; background: transparent !important; padding: 12px 0 !important; width: 100%; box-shadow: none !important; color: #fff;">
                                        </div>
                                    </div>

                                    <div class="char-input-group" style="display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Mega.nz
                                            Storage</label>
                                        <div
                                            style="display: flex; align-items: center; background: rgba(0,0,0,0.3); border: 1px solid #333; border-radius: 4px; padding: 0 15px;">
                                            <i class="fas fa-cloud"
                                                style="color: #444; font-size: 16px; margin-right: 15px;"></i>
                                            <input type="text" name="link_mega" class="char-input"
                                                value="<?php echo htmlspecialchars($settings['Link_Mega'] ?? ''); ?>"
                                                style="border: none !important; background: transparent !important; padding: 12px 0 !important; width: 100%; box-shadow: none !important; color: #fff;">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn-action">
                                    SAVE DOWNLOAD CONFIGURATION
                                </button>
                            </form>
                        </div>

