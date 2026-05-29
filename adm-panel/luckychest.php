                        <?php
                        $luckySettings = fetchWebsiteSettings(['LuckyChestEnabled']);
                        $luckyEnabled = (string) ($luckySettings['LuckyChestEnabled'] ?? '0');
                        ?>
                        <div class="char-edit-section" style="max-width: 900px; margin: 0 auto; padding: 20px;">
                            <div style="text-align: center; margin-bottom: 40px;">
                                <h3 style="margin-bottom: 10px; font-size: 24px; color: #e8c881;">LUCKY CHEST MANAGEMENT
                                </h3>
                                <p style="color: #666; font-size: 14px;">Control global availability and reset player
                                    participation timers.</p>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: stretch;">
                                <!-- Global Status Card -->
                                <div
                                    style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 40px; display: flex; flex-direction: column; align-items: center; text-align: center; justify-content: center;">
                                    <div
                                        style="width: 70px; height: 70px; background: <?php echo $luckyEnabled == '1' ? 'rgba(72,187,120,0.1)' : 'rgba(229,62,62,0.1)'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 25px; border: 1px solid <?php echo $luckyEnabled == '1' ? 'rgba(72,187,120,0.2)' : 'rgba(229,62,62,0.2)'; ?>;">
                                        <i class="fas fa-box-open"
                                            style="font-size: 28px; color: <?php echo $luckyEnabled == '1' ? '#48bb78' : '#e53e3e'; ?>;"></i>
                                    </div>
                                    <h4 style="color: #fff; margin-bottom: 20px; font-size: 16px; letter-spacing: 1px;">
                                        AVAILABILITY</h4>
                                    <form action="/admin_actions" method="POST" style="width: 100%;">
                                        <input type="hidden" name="action" value="luckychest_toggle">
                                        <input type="hidden" name="return_view" value="<?php echo $view; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 20px;">
                                            <label class="switch" style="transform: scale(1.6);">
                                                <input type="checkbox" name="lucky_enabled" <?php echo $luckyEnabled == '1' ? 'checked' : ''; ?> class="js-auto-submit-onchange">
                                                <span class="slider round"></span>
                                            </label>
                                            <div
                                                style="font-size: 13px; font-weight: 600; color: <?php echo $luckyEnabled == '1' ? '#48bb78' : '#e53e3e'; ?>; letter-spacing: 2px; text-transform: uppercase; margin-top: 10px;">
                                                <?php echo $luckyEnabled == '1' ? 'System Active' : 'System Disabled'; ?>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Actions Card -->
                                <div
                                    style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 40px; display: flex; flex-direction: column;">
                                    <h4
                                        style="color: #e8c881; margin-bottom: 25px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(232, 200, 129, 0.1); padding-bottom: 15px;">
                                        DATABASE OPERATIONS</h4>

                                    <form action="/admin_actions" method="POST"
                                        style="margin-bottom: 35px; display: block;">
                                        <input type="hidden" name="action" value="luckychest_reset_user">
                                        <input type="hidden" name="return_view" value="<?php echo $view; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">Reset
                                            Single User</label>
                                        <div style="display: flex; gap: 10px;">
                                            <input type="number" name="user_uid" class="char-input"
                                                placeholder="Enter UserUID..." required
                                                style="height: 45px; background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; border-radius: 4px;">
                                            <button type="submit" class="btn-action btn-sm">RESET</button>
                                        </div>
                                    </form>

                                    <div
                                        style="margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05);">
                                        <form action="/admin_actions" method="POST" class="js-confirm-submit"
                                            data-confirm="CRITICAL ACTION: This will reset the timer for ALL players locally. Continue?"
                                            style="display: block;">
                                            <input type="hidden" name="action" value="luckychest_reset_all">
                                            <input type="hidden" name="return_view" value="<?php echo $view; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <button type="submit" class="btn-action"
                                                style="background: rgba(165,39,39,0.8); border-color: #a52727;">
                                                <i class="fas fa-redo-alt"></i> RESET ALL TIMERS
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

