                        <?php
                        $registerSettings = fetchWebsiteSettings(['RegistrationEnabled']);
                        $regEnabled = (string) ($registerSettings['RegistrationEnabled'] ?? '1');
                        ?>
                        <div class="char-edit-section"
                            style="max-width: 600px; margin: 80px auto; text-align: center; padding: 20px;">
                            <h3 style="margin-bottom: 10px; font-size: 24px; color: #e8c881;">REGISTRATION CONTROL</h3>
                            <p style="color: #666; margin-bottom: 50px;">Global toggle for new account creation via the
                                website.
                            </p>

                            <form action="/admin_actions" method="POST" style="display: block;">
                                <input type="hidden" name="action" value="register_toggle">
                                <input type="hidden" name="return_view" value="REGISTER_TOGGLE">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div
                                    style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 50px; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                                    <div
                                        style="width: 70px; height: 70px; background: <?php echo $regEnabled == '1' ? 'rgba(72,187,120,0.1)' : 'rgba(229,62,62,0.1)'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; border: 1px solid <?php echo $regEnabled == '1' ? 'rgba(72,187,120,0.2)' : 'rgba(229,62,62,0.2)'; ?>;">
                                        <i class="fas fa-user-plus"
                                            style="font-size: 28px; color: <?php echo $regEnabled == '1' ? '#48bb78' : '#e53e3e'; ?>;"></i>
                                    </div>

                                    <div
                                        style="font-size: 18px; color: #fff; margin-bottom: 30px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">
                                        Registration is: <span
                                            style="color: <?php echo $regEnabled == '1' ? '#48bb78' : '#e53e3e'; ?>;"><?php echo $regEnabled == '1' ? 'OPEN' : 'CLOSED'; ?></span>
                                    </div>

                                    <div style="display: flex; justify-content: center; margin-bottom: 30px;">
                                        <label class="switch" style="transform: scale(1.8);">
                                            <input type="checkbox" name="reg_enabled" <?php echo $regEnabled == '1' ? 'checked' : ''; ?> class="js-auto-submit-onchange">
                                            <span class="slider round"></span>
                                        </label>
                                    </div>

                                    <div
                                        style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 6px; color: #555; font-size: 12px; line-height: 1.6;">
                                        <?php if ($regEnabled == '1'): ?>
                                            <i class="fas fa-info-circle" style="margin-right: 5px; color: #48bb78;"></i>
                                            PUBLIC:
                                            Users can sign up freely.
                                        <?php else: ?>
                                            <i class="fas fa-lock" style="margin-right: 5px; color: #e53e3e;"></i> PRIVATE: New
                                            account creation is disabled.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
