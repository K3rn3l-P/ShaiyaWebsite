                        
                        <div class="char-edit-section">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                                <div>
                                    <h3 style="margin-bottom: 5px; color: #e8c881;">DROPS MANAGEMENT</h3>
                                    <p style="color: #666; font-size: 13px;">Manage global drop settings and item blacklists
                                        from this central hub.</p>
                                </div>

                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start;">
                                <!-- Column 1: Config & Blacklist Add -->
                                <div style="display: flex; flex-direction: column; gap: 40px;">
                                    <?php
                                    $settings = fetchWebsiteSettings(['DropsMaxGrade', 'DropsMaxLevel', 'DropsHideZero', 'DropsHideEmpty']);
                                    ?>
                                    <div
                                        style="background: rgba(255,255,255,0.02); padding: 25px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                        <h4
                                            style="color: #fff; margin-bottom: 25px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                            GLOBAL CONFIGURATION</h4>
                                        <form action="/admin_actions" method="POST">
                                            <input type="hidden" name="action" value="drops_config">
                                            <input type="hidden" name="return_view" value="<?php echo $view; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                            <div
                                                style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                                                <div class="char-input-group">
                                                    <label
                                                        style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">Max
                                                        Grade</label>
                                                    <input type="number" name="drops_max_grade" class="char-input"
                                                        value="<?php echo $settings['DropsMaxGrade'] ?? '3072'; ?>"
                                                        style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%;">
                                                </div>
                                                <div class="char-input-group">
                                                    <label
                                                        style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">Max
                                                        Level</label>
                                                    <input type="number" name="drops_max_level" class="char-input"
                                                        value="<?php echo $settings['DropsMaxLevel'] ?? '80'; ?>"
                                                        style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%;">
                                                </div>
                                            </div>

                                            <div style="display: flex; gap: 40px; margin-bottom: 30px; padding: 0 5px;">
                                                <label
                                                    style="display: flex; align-items: center; color: #ccc; font-size: 13px; cursor: pointer; user-select: none;">
                                                    <input type="checkbox" name="drops_hide_zero" <?php echo ($settings['DropsHideZero'] ?? '1') == '1' ? 'checked' : ''; ?>
                                                        style="width: 18px; height: 18px; margin-right: 12px; accent-color: #e8c881;">
                                                    Hide 0% Chance
                                                </label>
                                                <label
                                                    style="display: flex; align-items: center; color: #ccc; font-size: 13px; cursor: pointer; user-select: none;">
                                                    <input type="checkbox" name="drops_hide_empty" <?php echo ($settings['DropsHideEmpty'] ?? '1') == '1' ? 'checked' : ''; ?>
                                                        style="width: 18px; height: 18px; margin-right: 12px; accent-color: #e8c881;">
                                                    Hide Empty
                                                </label>
                                            </div>
                                            <button type="submit" class="btn-action">SAVE SETTINGS</button>
                                        </form>
                                    </div>

                                    <div
                                        style="background: rgba(255,255,255,0.02); padding: 25px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                        <h4
                                            style="color: #fff; margin-bottom: 25px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                            BLACKLIST OPERATIONS</h4>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                            <form action="/admin_actions" method="POST">
                                                <input type="hidden" name="action" value="drops_blacklist_add">
                                                <input type="hidden" name="return_view" value="<?php echo $view; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <div class="char-input-group" style="margin-bottom: 12px;">
                                                    <input type="number" name="itemid" class="char-input"
                                                        placeholder="ItemID to add..." required
                                                        style="background: rgba(45, 90, 39, 0.05); border-color: rgba(45, 90, 39, 0.3); color: #fff; width: 100%; height: 40px;">
                                                </div>
                                                <button type="submit" class="btn-action btn-sm">ADD TO LIST</button>
                                            </form>

                                            <form action="/admin_actions" method="POST">
                                                <input type="hidden" name="action" value="drops_blacklist_remove">
                                                <input type="hidden" name="return_view" value="<?php echo $view; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <div class="char-input-group" style="margin-bottom: 12px;">
                                                    <input type="number" name="itemid" class="char-input"
                                                        placeholder="ItemID to remove..." required
                                                        style="background: rgba(165, 39, 39, 0.05); border-color: rgba(165, 39, 39, 0.3); color: #fff; width: 100%; height: 40px;">
                                                </div>
                                                <button type="submit" class="btn-action btn-sm">REMOVE ITEM</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Column 2: Current Blacklist -->
                                <div
                                    style="background: rgba(0,0,0,0.25); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 30px; height: 100%; min-height: 520px; display: flex; flex-direction: column;">
                                    <h4
                                        style="color: #fff; margin-bottom: 25px; font-size: 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                                        CURRENT BLACKLIST
                                        <span
                                            style="background: rgba(232,200,129,0.1); color: #e8c881; font-size: 10px; padding: 4px 10px; border-radius: 20px; letter-spacing: 1px;">LOCKED
                                            ITEMS</span>
                                    </h4>
                                    <div class="blacklist-scroll"
                                        style="flex-grow: 1; max-height: 480px; overflow-y: auto; padding-right: 10px;">
                                        <?php
                                        $blacklistSettings = fetchWebsiteSettings(['DropsBlacklist']);
                                        $idList = trim((string) ($blacklistSettings['DropsBlacklist'] ?? ''));
                                        if ($idList !== '') {
                                            $ids = array_map('intval', explode(',', $idList));
                                            $q = false;
                                            if (!empty($ids)) {
                                                $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                                                $names = [];
                                                try {
                                                    $q = $pdoAdmin->prepare("SELECT ItemID, ItemName FROM PS_GameDefs.dbo.Items WHERE ItemID IN ($idPlaceholders)");
                                                    $q->execute($ids);
                                                    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $iRow) {
                                                        $names[$iRow['ItemID']] = $iRow['ItemName'];
                                                    }
                                                } catch (Throwable $e) {}
                                            }

                                            echo '<div style="display: flex; flex-direction: column; gap: 12px;">';
                                            foreach ($ids as $id) {
                                                $name = $names[$id] ?? 'Unknown Item';
                                                echo '<div style="background: rgba(255,255,255,0.02); padding: 15px 20px; border: 1px solid rgba(255,255,255,0.03); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;">';
                                                echo '<span><span style="color: #e8c881; font-weight: 600; font-size: 14px;">' . htmlspecialchars($name) . '</span> <span style="color: #555; font-size: 11px; margin-left: 10px;">ID: ' . $id . '</span></span>';
                                                echo '<i class="fas fa-lock" style="color: #333; font-size: 14px;"></i>';
                                                echo '</div>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<div style="text-align: center; padding: 100px 0; color: #444;"><i class="fas fa-shield-alt" style="font-size: 40px; margin-bottom: 15px; opacity: 0.2;"></i><br>Blacklist is empty.</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

