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
                        'invalid_item'      => ['Item cannot be edited (not an equipment type).', 'error'],
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
                            <div style="<?php echo $ceMsgType === 'error'
                                ? 'background:rgba(229,62,62,0.1);color:#e53e3e;border:1px solid rgba(229,62,62,0.2);'
                                : 'background:rgba(72,187,120,0.1);color:#48bb78;border:1px solid rgba(72,187,120,0.2);'; ?>
                                padding:12px 18px;border-radius:4px;font-size:13px;display:flex;align-items:center;gap:8px;">
                                <i class="fas <?php echo $ceMsgType === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                                <?php echo htmlspecialchars($ceMsg); ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tabs -->
                        <div style="display: flex; gap: 6px; margin-bottom: 25px;">
                            <a href="/admin?view=CharEdit&id=<?php echo $charID; ?>"
                               style="text-decoration: none; padding: 8px 20px; border-radius: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s;
                                      <?php echo (!$charTab || $charTab === 'Overview') ? 'background: rgba(232,200,129,0.15); border: 1px solid rgba(232,200,129,0.4); color: #e8c881; font-weight: 600;' : 'background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: #666;'; ?>">
                                Overview
                            </a>
                            <a href="/admin?view=CharEdit&id=<?php echo $charID; ?>&tab=Inventory"
                               style="text-decoration: none; padding: 8px 20px; border-radius: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s;
                                      <?php echo $charTab === 'Inventory' ? 'background: rgba(232,200,129,0.15); border: 1px solid rgba(232,200,129,0.4); color: #e8c881; font-weight: 600;' : 'background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: #666;'; ?>">
                                Inventory
                            </a>
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
                            // Load lapis list (Type=30) for gem selects
                            $lapisList = [];
                            try {
                                $lapStmt = $pdoAdmin->prepare("SELECT TypeID, ItemName FROM PS_GameDefs.dbo.Items WHERE Type=30 ORDER BY TypeID ASC");
                                $lapStmt->execute();
                                $lapisList = $lapStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}

                            // Load inventory with gems
                            $invRows = [];
                            try {
                                $invStmt = $pdoAdmin->prepare("SELECT CI.ItemUID, CI.Slot, CI.ItemID, CI.[Count] AS ItemCount, CI.Craftname, CI.Gem1, CI.Gem2, CI.Gem3, CI.Gem4, CI.Gem5, CI.Gem6, I.ItemName, I.Type AS ItemType FROM PS_GameData.dbo.CharItems CI LEFT JOIN PS_GameDefs.dbo.Items I ON I.ItemID = CI.ItemID WHERE CI.CharID = ? AND CI.Del = 0 ORDER BY CI.Slot ASC");
                                $invStmt->execute([$charID]);
                                $invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}

                            if (!function_exists('parseCraftname')) {
                                function parseCraftname(string $cn): array {
                                    $cn = str_pad($cn, 20, '0', STR_PAD_LEFT);
                                    return [
                                        'str'  => (int) substr($cn, 0, 2),
                                        'dex'  => (int) substr($cn, 2, 2),
                                        'rec'  => (int) substr($cn, 4, 2),
                                        'int'  => (int) substr($cn, 6, 2),
                                        'wis'  => (int) substr($cn, 8, 2),
                                        'luc'  => (int) substr($cn, 10, 2),
                                        'hp'   => (int) substr($cn, 12, 2),
                                        'sp'   => (int) substr($cn, 14, 2),
                                        'mp'   => (int) substr($cn, 16, 2),
                                        'ench' => (int) substr($cn, 18, 2),
                                    ];
                                }
                            }
                            // Equipment Types: weapons (1–15, 45–65), armor (16–21, 31–36, 67–92),
                            // accessories (22–24, 37, 40–41, 96–97). Consumables/lapis/etc. excluded.
                            $equipTypes = array_merge(
                                range(1, 24),
                                range(31, 37),
                                [40, 41],
                                range(45, 65),
                                range(67, 77),
                                range(82, 92),
                                [96, 97]
                            );
                            ?>
                            <div class="chars-table-container">
                                <table class="chars-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 55px;">Slot</th>
                                            <th>Item Name</th>
                                            <th style="width: 75px;">Item ID</th>
                                            <th style="width: 55px;">Count</th>
                                            <th style="width: 75px;">Enchant</th>
                                            <th style="width: 110px;">Lapis</th>
                                            <th style="width: 95px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invRows as $invItem): ?>
                                        <?php
                                        $invCn    = (string) ($invItem['Craftname'] ?? '');
                                        $invStats = parseCraftname($invCn);
                                        $invGems  = array_filter([
                                            (int) ($invItem['Gem1'] ?? 0),
                                            (int) ($invItem['Gem2'] ?? 0),
                                            (int) ($invItem['Gem3'] ?? 0),
                                            (int) ($invItem['Gem4'] ?? 0),
                                            (int) ($invItem['Gem5'] ?? 0),
                                            (int) ($invItem['Gem6'] ?? 0),
                                        ]);
                                        ?>
                                        <tr>
                                            <td><?php echo (int) $invItem['Slot']; ?></td>
                                            <td><?php echo htmlspecialchars((string) ($invItem['ItemName'] ?? 'Unknown')); ?></td>
                                            <td><?php echo (int) $invItem['ItemID']; ?></td>
                                            <td><?php echo (int) ($invItem['ItemCount'] ?? 0); ?></td>
                                            <td>
                                                <?php if ($invStats['ench'] > 0): ?>
                                                    <span style="color: #e8c881; font-weight: 600;">+<?php echo $invStats['ench']; ?></span>
                                                <?php else: ?>
                                                    <span style="color: #333;">+0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php $gemCount = count($invGems); ?>
                                                <?php if ($gemCount > 0): ?>
                                                    <span style="color: #48bb78; font-size: 11px;"><?php echo $gemCount; ?>/6 linked</span>
                                                <?php else: ?>
                                                    <span style="color: #333; font-size: 11px;">none</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="display: flex; gap: 6px; align-items: center;">
                                                <?php if (in_array((int) ($invItem['ItemType'] ?? 0), $equipTypes, true)): ?>
                                                <button data-action="open-item-modal" data-uid="<?php echo (int) $invItem['ItemUID']; ?>"
                                                        style="background: rgba(232,200,129,0.08); border: 1px solid rgba(232,200,129,0.25); color: #e8c881; padding: 4px 10px; border-radius: 3px; cursor: pointer; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                                    EDIT
                                                </button>
                                                <?php endif; ?>
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
                                            <td colspan="7" style="text-align: center; padding: 60px; color: #444;">
                                                <i class="fas fa-box-open" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                No items in inventory.
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Item Edit Dialogs (native <dialog> → top layer, immune to backdrop-filter) -->
                            <style>
                            dialog.item-edit-dialog {
                                background: #0d0d0d;
                                border: 1px solid rgba(232,200,129,0.22);
                                border-radius: 10px;
                                width: 600px;
                                max-width: 96vw;
                                max-height: 92vh;
                                overflow-y: auto;
                                padding: 0;
                                margin: auto;
                                box-shadow: 0 24px 80px rgba(0,0,0,0.75);
                                color: #ccc;
                                font-family: inherit;
                            }
                            dialog.item-edit-dialog::backdrop {
                                background: rgba(0,0,0,0.82);
                                backdrop-filter: blur(4px);
                                -webkit-backdrop-filter: blur(4px);
                            }
                            dialog.item-edit-dialog .dlg-body { padding: 28px; }
                            dialog.item-edit-dialog .dlg-header {
                                display: flex;
                                justify-content: space-between;
                                align-items: flex-start;
                                padding: 20px 28px 18px;
                                border-bottom: 1px solid rgba(255,255,255,0.06);
                                background: rgba(232,200,129,0.04);
                                border-radius: 10px 10px 0 0;
                            }
                            dialog.item-edit-dialog .dlg-close {
                                background: none;
                                border: none;
                                color: #444;
                                font-size: 24px;
                                cursor: pointer;
                                line-height: 1;
                                padding: 0 2px;
                                transition: color 0.15s;
                            }
                            dialog.item-edit-dialog .dlg-close:hover { color: #e8c881; }
                            dialog.item-edit-dialog .dlg-section-label {
                                color: #e8c881;
                                font-size: 10px;
                                text-transform: uppercase;
                                letter-spacing: 1.2px;
                                margin-bottom: 10px;
                            }
                            dialog.item-edit-dialog .stat-grid {
                                display: grid;
                                grid-template-columns: repeat(3,1fr);
                                gap: 8px;
                                margin-bottom: 22px;
                            }
                            dialog.item-edit-dialog .stat-cell {
                                background: rgba(255,255,255,0.02);
                                border: 1px solid rgba(255,255,255,0.05);
                                border-radius: 5px;
                                padding: 10px 12px;
                            }
                            dialog.item-edit-dialog .stat-cell label {
                                display: block;
                                color: #484848;
                                font-size: 10px;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                margin-bottom: 5px;
                            }
                            dialog.item-edit-dialog .stat-cell input {
                                background: transparent;
                                border: none;
                                border-bottom: 1px solid rgba(255,255,255,0.09);
                                color: #ccc;
                                width: 100%;
                                font-size: 16px;
                                font-weight: 600;
                                padding: 2px 0;
                                outline: none;
                            }
                            dialog.item-edit-dialog .gem-grid {
                                display: grid;
                                grid-template-columns: 1fr 1fr;
                                gap: 8px;
                                margin-bottom: 24px;
                            }
                            dialog.item-edit-dialog .gem-cell label {
                                display: block;
                                color: #484848;
                                font-size: 10px;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                margin-bottom: 5px;
                            }
                            /* searchable lapis picker */
                            .lapis-picker { position: relative; }
                            .lapis-picker-input {
                                display: flex;
                                align-items: center;
                                background: #080808;
                                border: 1px solid rgba(255,255,255,0.07);
                                border-radius: 4px;
                                padding: 0;
                                cursor: pointer;
                                transition: border-color 0.15s;
                            }
                            .lapis-picker-input:focus-within { border-color: rgba(232,200,129,0.35); }
                            .lapis-picker-input input[type="text"] {
                                flex: 1;
                                background: transparent;
                                border: none;
                                outline: none;
                                color: #bbb;
                                font-size: 12px;
                                padding: 7px 10px;
                                cursor: pointer;
                                width: 100%;
                                min-width: 0;
                            }
                            .lapis-picker-input input[type="text"]::placeholder { color: #333; }
                            .lapis-picker-arrow {
                                color: #333;
                                font-size: 10px;
                                padding: 0 8px;
                                pointer-events: none;
                                flex-shrink: 0;
                            }
                            .lapis-picker-dropdown {
                                display: none;
                                position: absolute;
                                top: calc(100% + 3px);
                                left: 0;
                                right: 0;
                                z-index: 10;
                                background: #0a0a0a;
                                border: 1px solid rgba(232,200,129,0.2);
                                border-radius: 4px;
                                box-shadow: 0 8px 24px rgba(0,0,0,0.7);
                                overflow: hidden;
                            }
                            .lapis-picker-dropdown.open { display: block; }
                            .lapis-picker-search-wrap {
                                padding: 6px 8px;
                                border-bottom: 1px solid rgba(255,255,255,0.04);
                            }
                            .lapis-picker-search {
                                width: 100%;
                                background: rgba(255,255,255,0.04);
                                border: 1px solid rgba(255,255,255,0.08);
                                border-radius: 3px;
                                color: #ccc;
                                font-size: 11px;
                                padding: 5px 8px;
                                outline: none;
                                box-sizing: border-box;
                            }
                            .lapis-picker-search::placeholder { color: #333; }
                            .lapis-picker-list {
                                max-height: 180px;
                                overflow-y: auto;
                            }
                            .lapis-picker-list::-webkit-scrollbar { width: 4px; }
                            .lapis-picker-list::-webkit-scrollbar-track { background: transparent; }
                            .lapis-picker-list::-webkit-scrollbar-thumb { background: rgba(232,200,129,0.2); border-radius: 2px; }
                            .lapis-opt {
                                padding: 7px 10px;
                                font-size: 11px;
                                color: #888;
                                cursor: pointer;
                                transition: background 0.1s, color 0.1s;
                            }
                            .lapis-opt:hover, .lapis-opt.focused { background: rgba(232,200,129,0.08); color: #e8c881; }
                            .lapis-opt.selected { color: #e8c881; font-weight: 600; }
                            .lapis-opt.hidden { display: none; }
                            dialog.item-edit-dialog .dlg-footer {
                                display: flex;
                                justify-content: flex-end;
                                gap: 10px;
                                padding-top: 16px;
                                border-top: 1px solid rgba(255,255,255,0.05);
                            }
                            dialog.item-edit-dialog .btn-dlg-cancel {
                                background: rgba(255,255,255,0.03);
                                border: 1px solid rgba(255,255,255,0.1);
                                color: #666;
                                padding: 10px 22px;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 12px;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                transition: border-color 0.15s, color 0.15s;
                            }
                            dialog.item-edit-dialog .btn-dlg-cancel:hover { border-color: rgba(255,255,255,0.2); color: #999; }
                            dialog.item-edit-dialog .btn-dlg-save {
                                background: rgba(232,200,129,0.1);
                                border: 1px solid rgba(232,200,129,0.35);
                                color: #e8c881;
                                padding: 10px 26px;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 12px;
                                text-transform: uppercase;
                                font-weight: 600;
                                letter-spacing: 0.5px;
                                transition: background 0.15s, box-shadow 0.15s;
                            }
                            dialog.item-edit-dialog .btn-dlg-save:hover { background: rgba(232,200,129,0.18); box-shadow: 0 0 12px rgba(232,200,129,0.15); }
                            </style>

                            <script>var LAPIS_DATA=<?php
                                $lapisJs = [];
                                foreach ($lapisList as $lap) {
                                    $lapisJs[] = [(int)$lap['TypeID'], (string)$lap['ItemName']];
                                }
                                echo json_encode($lapisJs, JSON_UNESCAPED_UNICODE);
                            ?>;</script>

                            <?php foreach ($invRows as $invItem): ?>
                            <?php if (!in_array((int) ($invItem['ItemType'] ?? 0), $equipTypes, true)) { continue; } ?>
                            <?php
                            $mUid       = (int) $invItem['ItemUID'];
                            $mCn        = (string) ($invItem['Craftname'] ?? '');
                            $mStats     = parseCraftname($mCn);
                            $mName      = htmlspecialchars((string) ($invItem['ItemName'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');
                            $mEnchLabel = $mStats['ench'] > 0 ? ' [+' . $mStats['ench'] . ']' : '';
                            ?>
                            <dialog id="modal-item-<?php echo $mUid; ?>" class="item-edit-dialog">

                                <div class="dlg-header">
                                    <div>
                                        <div style="color: #e8c881; font-size: 15px; font-weight: 600;"><?php echo $mName . $mEnchLabel; ?></div>
                                        <div style="color: #444; font-size: 11px; margin-top: 3px;">UID: <?php echo $mUid; ?> &bull; Inv Slot <?php echo (int) $invItem['Slot']; ?></div>
                                    </div>
                                    <button class="dlg-close" data-action="close-item-modal" data-uid="<?php echo $mUid; ?>" title="Close">&times;</button>
                                </div>

                                <div class="dlg-body">
                                    <form action="/admin" method="POST">
                                        <input type="hidden" name="action" value="update_item">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="item_uid" value="<?php echo $mUid; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                        <!-- Enchant -->
                                        <div style="margin-bottom: 22px;">
                                            <div class="dlg-section-label">Enchant Level</div>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <span style="color: #e8c881; font-size: 20px; font-weight: 700;">+</span>
                                                <input type="number" name="stat_ench" value="<?php echo $mStats['ench']; ?>" min="0" max="20"
                                                       style="background: rgba(232,200,129,0.06); border: 1px solid rgba(232,200,129,0.25); color: #e8c881; padding: 8px 14px; border-radius: 4px; width: 90px; font-size: 20px; font-weight: 700; text-align: center; outline: none;">
                                                <span style="color: #444; font-size: 11px;">range: 0 – 20</span>
                                            </div>
                                        </div>

                                        <!-- Reroll Stats -->
                                        <div class="dlg-section-label">
                                            Reroll Stats
                                            <span style="color: #444; font-weight: normal; text-transform: none; letter-spacing: 0;"> — 0–50 per stat &nbsp;|&nbsp; HP/SP/MP ×100 in-game</span>
                                        </div>
                                        <div class="stat-grid">
                                            <?php
                                            $statFields = [
                                                ['stat_str', 'STR',     $mStats['str']],
                                                ['stat_dex', 'DEX',     $mStats['dex']],
                                                ['stat_rec', 'REC',     $mStats['rec']],
                                                ['stat_int', 'INT',     $mStats['int']],
                                                ['stat_wis', 'WIS',     $mStats['wis']],
                                                ['stat_luc', 'LUC',     $mStats['luc']],
                                                ['stat_hp',  'HP ×100', $mStats['hp']],
                                                ['stat_sp',  'SP ×100', $mStats['sp']],
                                                ['stat_mp',  'MP ×100', $mStats['mp']],
                                            ];
                                            foreach ($statFields as [$fname, $flabel, $fval]): ?>
                                            <div class="stat-cell">
                                                <label><?php echo $flabel; ?></label>
                                                <input type="number" name="<?php echo $fname; ?>" value="<?php echo $fval; ?>" min="0" max="50">
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Lapis Link -->
                                        <div class="dlg-section-label">
                                            Lapis Link <span style="color: #444; font-weight: normal; text-transform: none; letter-spacing: 0;">(Gem 1–6)</span>
                                        </div>
                                        <div class="gem-grid">
                                            <?php
                                            $gemFields = [
                                                ['gem1', 'Gem 1', (int) ($invItem['Gem1'] ?? 0)],
                                                ['gem2', 'Gem 2', (int) ($invItem['Gem2'] ?? 0)],
                                                ['gem3', 'Gem 3', (int) ($invItem['Gem3'] ?? 0)],
                                                ['gem4', 'Gem 4', (int) ($invItem['Gem4'] ?? 0)],
                                                ['gem5', 'Gem 5', (int) ($invItem['Gem5'] ?? 0)],
                                                ['gem6', 'Gem 6', (int) ($invItem['Gem6'] ?? 0)],
                                            ];
                                            foreach ($gemFields as [$gname, $glabel, $gval]): ?>
                                            <div class="gem-cell">
                                                <label><?php echo $glabel; ?></label>
                                                <div class="lapis-picker" data-value="<?php echo $gval; ?>">
                                                    <input type="hidden" name="<?php echo $gname; ?>" value="<?php echo $gval; ?>">
                                                    <div class="lapis-picker-input">
                                                        <input type="text" class="lapis-picker-text" placeholder="— Empty —" autocomplete="off" readonly>
                                                        <span class="lapis-picker-arrow">▾</span>
                                                    </div>
                                                    <div class="lapis-picker-dropdown">
                                                        <div class="lapis-picker-search-wrap">
                                                            <input type="text" class="lapis-picker-search" placeholder="Search lapis...">
                                                        </div>
                                                        <div class="lapis-picker-list"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="dlg-footer">
                                            <button type="button" class="btn-dlg-cancel" data-action="close-item-modal" data-uid="<?php echo $mUid; ?>">Cancel</button>
                                            <button type="submit" class="btn-dlg-save">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </dialog>
                            <?php endforeach; ?>

                            <script>
                            (function () {
                                /* ── modal open / close ── */
                                function openModal(uid) {
                                    var d = document.getElementById('modal-item-' + uid);
                                    if (!d) { return; }
                                    d.showModal();
                                    document.body.style.overflow = 'hidden';
                                    initPickersIn(d);
                                }
                                function closeModal(uid) {
                                    var d = document.getElementById('modal-item-' + uid);
                                    if (!d) { return; }
                                    closeAllDropdowns();
                                    d.close();
                                    document.body.style.overflow = '';
                                }

                                document.querySelectorAll('dialog.item-edit-dialog').forEach(function (d) {
                                    d.addEventListener('cancel', function (e) {
                                        e.preventDefault();
                                        closeAllDropdowns();
                                        d.close();
                                        document.body.style.overflow = '';
                                    });
                                    d.addEventListener('click', function (e) {
                                        var r = d.getBoundingClientRect();
                                        if (e.clientX < r.left || e.clientX > r.right ||
                                            e.clientY < r.top  || e.clientY > r.bottom) {
                                            closeAllDropdowns();
                                            d.close();
                                            document.body.style.overflow = '';
                                        }
                                    });
                                });

                                /* ── lapis picker ── */
                                var lapisMap = {0: '— Empty —'};
                                if (typeof LAPIS_DATA !== 'undefined') {
                                    LAPIS_DATA.forEach(function (l) {
                                        lapisMap[l[0]] = '[' + l[0] + '] ' + l[1];
                                    });
                                }

                                function buildPickerList(list, curVal) {
                                    var frag = document.createDocumentFragment();
                                    function makeOpt(val, text) {
                                        var el = document.createElement('div');
                                        el.className = 'lapis-opt' + (val === curVal ? ' selected' : '');
                                        el.setAttribute('data-value', val);
                                        el.textContent = text;
                                        frag.appendChild(el);
                                    }
                                    makeOpt(0, '— Empty —');
                                    if (typeof LAPIS_DATA !== 'undefined') {
                                        LAPIS_DATA.forEach(function (l) { makeOpt(l[0], '[' + l[0] + '] ' + l[1]); });
                                    }
                                    list.appendChild(frag);
                                    var sel = list.querySelector('.lapis-opt.selected');
                                    if (sel) { sel.scrollIntoView({ block: 'nearest' }); }
                                }

                                function initPickersIn(container) {
                                    container.querySelectorAll('.lapis-picker').forEach(function (picker) {
                                        var val = parseInt(picker.getAttribute('data-value'), 10) || 0;
                                        var textEl = picker.querySelector('.lapis-picker-text');
                                        if (textEl && textEl.value === '') {
                                            textEl.value = lapisMap[val] || '— Empty —';
                                        }
                                    });
                                }

                                function closeAllDropdowns() {
                                    document.querySelectorAll('.lapis-picker-dropdown.open').forEach(function (d) {
                                        d.classList.remove('open');
                                    });
                                }

                                /* event delegation for open/close buttons AND picker */
                                document.addEventListener('click', function (e) {
                                    /* modal actions */
                                    var el = e.target.closest('[data-action]');
                                    if (el) {
                                        var action = el.getAttribute('data-action');
                                        var uid    = el.getAttribute('data-uid');
                                        if (action === 'open-item-modal')  { openModal(uid); return; }
                                        if (action === 'close-item-modal') { closeModal(uid); return; }
                                    }

                                    /* picker: click on display input row → open dropdown */
                                    var pickerInput = e.target.closest('.lapis-picker-input');
                                    if (pickerInput) {
                                        var picker = pickerInput.closest('.lapis-picker');
                                        var dd     = picker.querySelector('.lapis-picker-dropdown');
                                        var isOpen = dd.classList.contains('open');
                                        closeAllDropdowns();
                                        if (!isOpen) {
                                            var list = dd.querySelector('.lapis-picker-list');
                                            if (!list.children.length) {
                                                var val = parseInt(picker.getAttribute('data-value'), 10) || 0;
                                                buildPickerList(list, val);
                                            }
                                            dd.classList.add('open');
                                            dd.querySelector('.lapis-picker-search').value = '';
                                            dd.querySelectorAll('.lapis-opt').forEach(function(o){ o.classList.remove('hidden'); });
                                            dd.querySelector('.lapis-picker-search').focus();
                                        }
                                        return;
                                    }

                                    /* picker: click on option */
                                    var opt = e.target.closest('.lapis-opt');
                                    if (opt) {
                                        var picker = opt.closest('.lapis-picker');
                                        var val    = parseInt(opt.getAttribute('data-value'), 10);
                                        picker.querySelector('input[type="hidden"]').value = val;
                                        picker.querySelector('.lapis-picker-text').value   = lapisMap[val] || '— Empty —';
                                        picker.setAttribute('data-value', val);
                                        picker.querySelectorAll('.lapis-opt').forEach(function (o) { o.classList.remove('selected'); });
                                        opt.classList.add('selected');
                                        picker.querySelector('.lapis-picker-dropdown').classList.remove('open');
                                        return;
                                    }

                                    /* click outside picker → close */
                                    if (!e.target.closest('.lapis-picker')) { closeAllDropdowns(); }
                                });

                                /* picker: search filter */
                                document.addEventListener('input', function (e) {
                                    if (!e.target.classList.contains('lapis-picker-search')) { return; }
                                    var q    = e.target.value.toLowerCase();
                                    var list = e.target.closest('.lapis-picker-dropdown').querySelector('.lapis-picker-list');
                                    list.querySelectorAll('.lapis-opt').forEach(function (o) {
                                        o.classList.toggle('hidden', q !== '' && o.textContent.toLowerCase().indexOf(q) === -1);
                                    });
                                });

                                /* prevent picker search keystrokes from bubbling to dialog cancel */
                                document.addEventListener('keydown', function (e) {
                                    if (e.target.classList.contains('lapis-picker-search') && e.key === 'Escape') {
                                        e.stopPropagation();
                                        closeAllDropdowns();
                                        return;
                                    }
                                }, true);
                            }());
                            </script>

                        <?php endif; ?> <!-- end CharEdit tab switch -->
                    <?php endif; ?> <!-- end if (!$char) -->
