                    <?php
                    $charID = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                    $charTab = isset($_GET['tab']) ? trim($_GET['tab']) : '';
                    $char   = null;
                    if ($charID > 0) {
                        try {
                            $ceStmt = $pdoAdmin->prepare("SELECT C.CharID, C.CharName, C.Level, C.Map, C.Money, C.K1, C.LoginStatus, C.Del, C.UserUID, MG.Country as Faction, U.UserID FROM PS_GameData.dbo.Chars C LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID LEFT JOIN PS_UserData.dbo.Users_Master U ON U.UserUID = C.UserUID WHERE C.CharID = ?");
                            $ceStmt->execute([$charID]);
                            $char = $ceStmt->fetch(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) {}
                    }
                    // Message from redirect
                    $ceMsg = '';
                    $ceMsgType = 'success';
                    $ceMsgMap = [
                        'renamed'           => ['Character renamed successfully.', 'success'],
                        'level_updated'     => ['Level updated successfully.', 'success'],
                        'map_changed'       => ['Map changed successfully.', 'success'],
                        'gold_sent'         => ['Gold sent successfully.', 'success'],
                        'item_deleted'      => ['Item deleted successfully.', 'success'],
                        'item_updated'      => ['Item updated successfully.', 'success'],
                        'char_deleted'      => ['Character deleted.', 'success'],
                        'char_resurrected'  => ['Character resurrected.', 'success'],
                        'error_rename'      => ['Rename failed.', 'error'],
                        'error_level'       => ['Level update failed.', 'error'],
                        'error_map'         => ['Map change failed.', 'error'],
                        'error_gold'        => ['Gold transfer failed.', 'error'],
                        'error_item_delete' => ['Item deletion failed.', 'error'],
                        'error_item_update' => ['Item update failed.', 'error'],
                        'error_char_delete' => ['Character deletion failed.', 'error'],
                        'error_resurrect'   => ['Character resurrection failed.', 'error'],
                    ];
                    if (isset($_GET['msg']) && isset($ceMsgMap[$_GET['msg']])) {
                        $ceMsg     = $ceMsgMap[$_GET['msg']][0];
                        $ceMsgType = $ceMsgMap[$_GET['msg']][1];
                    }
                    ?>

                    <?php if (!$char): ?>
                        <div class="alert alert-error" style="max-width: 600px;">
                            Character not found (ID: <?php echo $charID; ?>). <a href="/admin?view=CHARS" class="user-link">Browse all characters</a>.
                        </div>
                    <?php else: ?>
                        <div class="char-edit-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                            <div>
                                <h1 style="font-size: 22px; color: #e8c881; margin-bottom: 6px;"><?php echo htmlspecialchars($char['CharName']); ?></h1>
                                <p style="color: #666; font-size: 13px;">
                                    Character ID: <?php echo $charID; ?> &bull;
                                    Account: <a href="/admin?view=UserEdit&uid=<?php echo $char['UserUID']; ?>" class="user-link"><?php echo htmlspecialchars((string) ($char['UserID'] ?? '')); ?></a>
                                    <?php if ($char['Faction'] !== null): ?>
                                    &bull; <img src="assets/<?php echo $char['Faction'] == 0 ? 'aol.webp' : 'uof.webp'; ?>" style="width: 14px; vertical-align: middle;" alt="faction"> <?php echo $char['Faction'] == 0 ? 'AOL' : 'UOF'; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($ceMsg): ?>
                            <div class="alert alert-<?php echo $ceMsgType; ?>" style="margin: 0;"><?php echo htmlspecialchars($ceMsg); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Tabs -->
                        <div class="user-tabs" style="margin-bottom: 25px;">
                            <a href="/admin?view=CharEdit&id=<?php echo $charID; ?>" class="user-tab <?php echo (!$charTab || $charTab === 'Overview') ? 'active' : ''; ?>" style="text-decoration: none;">Overview</a>
                            <a href="/admin?view=CharEdit&id=<?php echo $charID; ?>&tab=Inventory" class="user-tab <?php echo $charTab === 'Inventory' ? 'active' : ''; ?>" style="text-decoration: none;">Inventory</a>
                        </div>

                        <?php if (!$charTab || $charTab === 'Overview'): ?>
                            <div class="char-edit-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                <!-- Info Card -->
                                <div style="background: rgba(255,255,255,0.02); padding: 25px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                                    <h4 style="color: #e8c881; margin-bottom: 20px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Character Info</h4>
                                    <table class="char-info-table" style="width: 100%; border-collapse: collapse;">
                                        <tbody>
                                            <tr><td style="padding: 8px 0; color: #666; width: 40%;">Char ID</td><td style="color: #ccc;"><?php echo $charID; ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Level</td><td style="color: #ccc;"><?php echo (int) $char['Level']; ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Map</td><td style="color: #ccc;"><?php echo getMapName((int) $char['Map']); ?> (<?php echo (int) $char['Map']; ?>)</td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Gold</td><td style="color: #ccc;"><?php echo number_format((int) $char['Money']); ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Kills</td><td style="color: #ccc;"><?php echo number_format((int) $char['K1']); ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Status</td><td><?php echo (int) ($char['LoginStatus'] ?? 0) === 1 ? '<span style="color:#48bb78;">ONLINE</span>' : '<span style="color:#e53e3e;">OFFLINE</span>'; ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Deleted</td><td><?php echo (int) $char['Del'] === 1 ? '<span style="color:#e53e3e;">Yes</span>' : '<span style="color:#48bb78;">No</span>'; ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Actions Card -->
                                <div style="background: rgba(255,255,255,0.02); padding: 25px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; gap: 18px;">
                                    <h4 style="color: #e8c881; margin-bottom: 5px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Actions</h4>

                                    <!-- Rename -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="rename">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Rename</label>
                                        <input type="text" name="new_name" class="search-input" value="<?php echo htmlspecialchars($char['CharName']); ?>" style="height: 36px; width: 160px; flex: 1;">
                                        <button type="submit" class="btn-search">RENAME</button>
                                    </form>

                                    <!-- Level -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="update_level">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Level</label>
                                        <input type="number" name="new_level" class="search-input" value="<?php echo (int) $char['Level']; ?>" min="1" max="80" style="height: 36px; width: 80px;">
                                        <button type="submit" class="btn-search">SET LEVEL</button>
                                    </form>

                                    <!-- Map -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="change_map">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Map</label>
                                        <input type="number" name="new_map" class="search-input" value="<?php echo (int) $char['Map']; ?>" min="0" max="109" style="height: 36px; width: 80px;">
                                        <button type="submit" class="btn-search">CHANGE MAP</button>
                                    </form>

                                    <!-- Gold -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="send_gold">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Gold</label>
                                        <input type="number" name="gold_amount" class="search-input" placeholder="Amount to add..." style="height: 36px; width: 160px; flex: 1;" max="500000000">
                                        <button type="submit" class="btn-search">SEND GOLD</button>
                                    </form>

                                    <!-- Delete / Resurrect -->
                                    <?php if ((int) $char['Del'] === 0): ?>
                                    <form action="/admin" method="POST" style="display: inline;" class="js-confirm-submit" data-confirm="<?php echo htmlspecialchars('Permanently delete character ' . (string) $char['CharName'] . '? This cannot be undone.', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="delete_char">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="admin-btn btn-kick" style="margin-top: 5px;">DELETE CHARACTER</button>
                                    </form>
                                    <?php else: ?>
                                    <form action="/admin" method="POST" style="display: inline;" class="js-confirm-submit" data-confirm="<?php echo htmlspecialchars('Resurrect character ' . (string) $char['CharName'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="resurrect_char">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn-action" style="margin-top: 5px; background: rgba(72,187,120,0.15); border-color: rgba(72,187,120,0.3); color: #48bb78;">RESURRECT CHARACTER</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif ($charTab === 'Inventory'): ?>
                            <?php
                            $invRows = [];
                            try {
                                $invStmt = $pdoAdmin->prepare("SELECT CI.ItemUID, CI.Slot, CI.ItemID, CI.ItemCount, CI.Craftname, I.ItemName FROM PS_GameData.dbo.CharItems CI LEFT JOIN PS_GameDefs.dbo.Items I ON I.ItemID = CI.ItemID WHERE CI.CharID = ? AND CI.Del = 0 ORDER BY CI.Slot ASC");
                                $invStmt->execute([$charID]);
                                $invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}
                            ?>
                            <div class="chars-table-container">
                                <table class="chars-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 55px;">Slot</th>
                                            <th>Item Name</th>
                                            <th style="width: 75px;">Item ID</th>
                                            <th style="width: 65px;">Count</th>
                                            <th style="width: 80px;">Enchant</th>
                                            <th style="width: 80px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invRows as $invItem): ?>
                                        <tr>
                                            <td><?php echo (int) $invItem['Slot']; ?></td>
                                            <td><?php echo htmlspecialchars((string) ($invItem['ItemName'] ?? 'Unknown')); ?></td>
                                            <td><?php echo (int) $invItem['ItemID']; ?></td>
                                            <td><?php echo (int) $invItem['ItemCount']; ?></td>
                                            <td>
                                                <?php
                                                $cn = (string) ($invItem['Craftname'] ?? '');
                                                echo (strlen($cn) >= 20 ? substr($cn, 18, 2) : '0') . '+';
                                                ?>
                                            </td>
                                            <td>
                                                <form action="/admin" method="POST" style="display: inline;" class="js-confirm-submit" data-confirm="Delete this item from inventory?">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                                    <input type="hidden" name="item_uid" value="<?php echo (int) $invItem['ItemUID']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <button type="submit" style="background: none; border: none; color: #e53e3e; cursor: pointer;" title="Delete item"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($invRows) === 0): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 60px; color: #444;">
                                                <i class="fas fa-box-open" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                No items in inventory.
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?> <!-- end CharEdit tab switch -->
                    <?php endif; ?> <!-- end if (!$char) -->
