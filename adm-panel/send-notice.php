                        <div class="char-edit-section" style="max-width: 800px; margin: 0 auto; padding: 20px;">
                            <div style="text-align: center; margin-bottom: 40px;">
                                <h3 style="margin-bottom: 10px; color: #e8c881;">SEND SERVER NOTICE</h3>
                                <p style="color: #666; font-size: 14px;">The message will be broadcasted to all online
                                    players
                                    instantly.</p>
                            </div>

                            <div class="item-editor-card"
                                style="padding: 40px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px;">
                                <form action="/admin_actions" method="POST">
                                    <input type="hidden" name="action" value="send_notice">
                                    <input type="hidden" name="return_view" value="SEND_NOTICE">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div class="char-input-group" style="margin-bottom: 30px; display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Notice
                                            Message (Max 150 chars)</label>
                                        <textarea name="notice_text" class="char-input"
                                            placeholder="Type your message here..." required maxlength="150"
                                            style="width: 100%; height: 120px; background: rgba(0,0,0,0.3); border-color: #333; color: #fff; padding: 15px; font-size: 15px; resize: none;"></textarea>
                                    </div>

                                    <div style="display: flex; justify-content: center;">
                                        <button type="submit" class="btn-action" style="width: 100%; max-width: 300px;">
                                            <i class="fas fa-bullhorn"></i> BROADCAST NOTICE
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

