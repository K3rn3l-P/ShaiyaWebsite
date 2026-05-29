                    <?php
                    $selectedItemID = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
                    $searchIn = isset($_GET['search_in']) ? strtolower(trim((string) $_GET['search_in'])) : 'inventory';
                    if (!in_array($searchIn, ['inventory', 'warehouse'], true)) {
                        $searchIn = 'inventory';
                    }
                    $itemSearchTerm = isset($_GET['search_item_name']) ? trim((string) $_GET['search_item_name']) : '';

                    $matchingItems = [];
                    if ($itemSearchTerm !== '') {
                        try {
                            $qItems = $pdoAdmin->prepare("SELECT TOP 30 ItemID, ItemName FROM PS_GameDefs.dbo.Items WHERE ItemName LIKE ? ORDER BY ItemName ASC");
                            $qItems->execute(['%' . $itemSearchTerm . '%']);
                            $matchingItems = $qItems->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) {
                            $matchingItems = [];
                        }
                    }

                    $results = [];
                    if ($selectedItemID > 0) {
                        try {
                            if ($searchIn === 'inventory') {
                                $sql = "SELECT ci.ItemUID, ci.Type, ci.TypeID, i.ItemID, i.ItemName,
                                               ci.Craftname, ci.Quality, ci.Gem1, ci.Gem2, ci.Gem3, ci.Gem4, ci.Gem5, ci.Gem6,
                                               ci.Maketime,
                                               U.UserID as AccountName, U.UserUID as AccountUID, C.CharName, C.CharID,
                                               i.ReqLevel, i.ConstStr, i.ConstDex, i.ConstRec, i.ConstInt, i.ConstWis, i.ConstLuc,
                                               i.ConstHP, i.ConstSP, i.ConstMP, i.Defensefighter, i.Defensemage,
                                               1 as ItemCount
                                        FROM PS_GameData.dbo.CharItems ci
                                        JOIN PS_GameDefs.dbo.Items i ON ci.Type = i.Type AND ci.TypeID = i.TypeID
                                        JOIN PS_GameData.dbo.Chars C ON ci.CharID = C.CharID
                                        JOIN PS_UserData.dbo.Users_Master U ON C.UserUID = U.UserUID
                                        WHERE i.ItemID = ?
                                        ORDER BY ci.ItemUID DESC";
                                $qRes = $pdoAdmin->prepare($sql);
                                $qRes->execute([$selectedItemID]);
                                $results = $qRes->fetchAll(PDO::FETCH_ASSOC);
                            } else {
                                $sql = "SELECT ci.ItemUID, ci.Type, ci.TypeID, i.ItemID, i.ItemName,
                                               ci.Craftname, 0 as Quality, ci.Gem1, ci.Gem2, ci.Gem3, ci.Gem4, ci.Gem5, ci.Gem6,
                                               ci.Maketime,
                                               U.UserID as AccountName, U.UserUID as AccountUID, C.CharName, C.CharID,
                                               i.ReqLevel, i.ConstStr, i.ConstDex, i.ConstRec, i.ConstInt, i.ConstWis, i.ConstLuc,
                                               i.ConstHP, i.ConstSP, i.ConstMP, i.Defensefighter, i.Defensemage,
                                               ci.[Count] as ItemCount
                                        FROM PS_GameData.dbo.UserStoredItems ci
                                        JOIN PS_GameDefs.dbo.Items i ON ci.Type = i.Type AND ci.TypeID = i.TypeID
                                        JOIN PS_UserData.dbo.Users_Master U ON ci.UserUID = U.UserUID
                                        LEFT JOIN PS_GameData.dbo.Chars C ON C.UserUID = U.UserUID AND C.Slot = 0 AND C.Del = 0
                                        WHERE i.ItemID = ?
                                        ORDER BY ci.ItemUID DESC";
                                $qRes = $pdoAdmin->prepare($sql);
                                $qRes->execute([$selectedItemID]);
                                $results = $qRes->fetchAll(PDO::FETCH_ASSOC);
                            }
                        } catch (Throwable $e) {
                            $results = [];
                        }
                    }
                    ?>

                    <div class="items-search-card">
                        <div class="items-title">ITEMS LIST</div>

                        <div style="display: grid; grid-template-columns: 400px 1fr; gap: 40px; align-items: start;">
                            <div style="background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid rgba(255,255,255,0.03); padding: 25px;">
                                <label class="search-label" style="display: flex; justify-content: space-between; align-items: center;">Find Item</label>
                                <form action="/admin" method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
                                    <input type="hidden" name="view" value="ITEMS">
                                    <input type="text" name="search_item_name" class="char-input" value="<?php echo htmlspecialchars($itemSearchTerm); ?>" placeholder="Search ItemName..." style="background: rgba(255,255,255,0.03); height: 38px;">
                                    <button type="submit" class="btn-action btn-sm" style="height: 38px;"><i class="fas fa-search"></i></button>
                                </form>

                                <div class="blacklist-scroll" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                                    <?php if ($itemSearchTerm !== ''): ?>
                                        <?php if (empty($matchingItems)): ?>
                                            <div style="text-align: center; padding: 20px; color: #444; font-size: 11px;">No items found.</div>
                                        <?php else: ?>
                                            <?php foreach ($matchingItems as $m): ?>
                                                <div class="item-match-row js-select-item-result js-hover-border"
                                                    data-item-id="<?php echo (int) $m['ItemID']; ?>"
                                                    data-item-label="<?php echo htmlspecialchars((int) $m['ItemID'] . ' - ' . (string) $m['ItemName'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-border-hover="#e8c881"
                                                    data-border-default="rgba(255,255,255,0.03)"
                                                    style="background: rgba(255,255,255,0.02); padding: 10px 15px; border: 1px solid rgba(255,255,255,0.03); border-radius: 4px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; cursor: pointer; transition: all 0.2s;">
                                                    <span style="font-size: 13px;">
                                                        <span style="color: #ccc;"><?php echo htmlspecialchars((string) $m['ItemName']); ?></span>
                                                        <span style="color: #444; font-size: 10px; margin-left: 10px;">#<?php echo (int) $m['ItemID']; ?></span>
                                                    </span>
                                                    <i class="fas fa-plus-circle" style="color: #222; font-size: 12px;"></i>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 40px 0; color: #333;">
                                            <i class="fas fa-search" style="font-size: 24px; opacity: 0.1; margin-bottom: 10px;"></i>
                                            <p style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Search for items</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="padding-top: 5px;">
                                <form action="/admin" method="GET" id="itemsSearchForm">
                                    <input type="hidden" name="view" value="ITEMS">
                                    <div class="search-field" style="margin-bottom: 25px;">
                                        <label class="search-label">Selected Item</label>
                                        <div style="background: rgba(0,0,0,0.3); border: 1px solid #222; padding: 15px; border-radius: 6px; display: flex; align-items: center; gap: 20px;">
                                            <div style="flex: 1;">
                                                <input type="text" name="item_id" id="selectedItemId" value="<?php echo $selectedItemID; ?>" placeholder="ItemID..." class="char-input" readonly style="background: transparent; border: none; font-size: 16px; color: #e8c881; font-weight: 600; padding: 0; cursor: default;">
                                                <div id="selectedItemDisplay" style="color: #555; font-size: 11px; margin-top: 5px; text-transform: uppercase;">
                                                    <?php
                                                    if ($selectedItemID > 0) {
                                                        try {
                                                            $checkItem = $pdoAdmin->prepare("SELECT ItemName FROM PS_GameDefs.dbo.Items WHERE ItemID = ?");
                                                            $checkItem->execute([$selectedItemID]);
                                                            $rName = $checkItem->fetch(PDO::FETCH_ASSOC);
                                                            echo $rName ? htmlspecialchars((string) $rName['ItemName']) : 'UNKNOWN ITEM ID';
                                                        } catch (Throwable $e) {
                                                            echo 'UNKNOWN ITEM ID';
                                                        }
                                                    } else {
                                                        echo 'No Item Selected';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div style="height: 30px; width: 1px; background: rgba(255,255,255,0.05);"></div>
                                            <div style="flex: 1;">
                                                <label class="search-label" style="margin-bottom: 5px; font-size: 10px;">Search in</label>
                                                <select name="search_in" class="editor-select" style="width: 100%; min-width: 150px;">
                                                    <option value="inventory" <?php echo $searchIn === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                                                    <option value="warehouse" <?php echo $searchIn === 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: flex-end;">
                                        <button type="submit" class="btn-action" style="padding: 12px 50px;">ACCEPT</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($selectedItemID > 0): ?>
                        <div class="results-header">ITEMS</div>
                        <div class="users-table-container">
                            <table class="items-results-table">
                                <thead>
                                    <tr>
                                        <th style="width: 250px;">ItemUID</th>
                                        <th style="width: 100px;">ItemID</th>
                                        <th>Item Name</th>
                                        <th style="width: 250px;">Owner</th>
                                        <th style="width: 150px;">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($results)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 50px; color: #444;">No items found in the selected <?php echo htmlspecialchars($searchIn); ?>.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($results as $res): ?>
                                            <?php
                                            $craft = (string) ($res['Craftname'] ?? '00000000000000000000');
                                            if (strlen($craft) < 20) {
                                                $craft = str_pad($craft, 20, '0', STR_PAD_LEFT);
                                            }

                                            $bonusStats = [
                                                'STR' => (int) substr($craft, 0, 2),
                                                'DEX' => (int) substr($craft, 2, 2),
                                                'REC' => (int) substr($craft, 4, 2),
                                                'INT' => (int) substr($craft, 6, 2),
                                                'WIS' => (int) substr($craft, 8, 2),
                                                'LUC' => (int) substr($craft, 10, 2),
                                                'HP' => (int) substr($craft, 12, 2) * 100,
                                                'SP' => (int) substr($craft, 14, 2) * 100,
                                                'MP' => (int) substr($craft, 16, 2) * 100,
                                                'Enchant' => (int) substr($craft, 18, 2),
                                            ];

                                            $baseStats = [
                                                'STR' => (int) ($res['ConstStr'] ?? 0),
                                                'DEX' => (int) ($res['ConstDex'] ?? 0),
                                                'REC' => (int) ($res['ConstRec'] ?? 0),
                                                'INT' => (int) ($res['ConstInt'] ?? 0),
                                                'WIS' => (int) ($res['ConstWis'] ?? 0),
                                                'LUC' => (int) ($res['ConstLuc'] ?? 0),
                                                'HP' => (int) ($res['ConstHP'] ?? 0),
                                                'SP' => (int) ($res['ConstSP'] ?? 0),
                                                'MP' => (int) ($res['ConstMP'] ?? 0),
                                            ];
                                            ?>
                                            <tr>
                                                <td class="uid-cell"><?php echo htmlspecialchars((string) ($res['ItemUID'] ?? '')); ?></td>
                                                <td class="item-id-cell"><?php echo number_format((int) ($res['ItemID'] ?? 0)); ?></td>
                                                <td class="item-name-cell">
                                                    <div class="item-name-wrapper">
                                                        <?php echo htmlspecialchars((string) ($res['ItemName'] ?? 'Unknown')); ?>
                                                        <?php if ($bonusStats['Enchant'] > 0): ?>
                                                            <span class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                        <?php endif; ?>

                                                        <div class="item-stats-tooltip">
                                                            <div class="tooltip-header">
                                                                <span><?php echo htmlspecialchars((string) ($res['ItemName'] ?? 'Unknown Item')); ?></span>
                                                                <?php if ($bonusStats['Enchant'] > 0): ?>
                                                                    <span class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if ((int) ($res['Quality'] ?? 0) > 0): ?>
                                                                <div class="stat-line" style="color: #eeee74;">damage Absorption +<?php echo (int) $res['Quality']; ?></div>
                                                            <?php endif; ?>
                                                            <?php if ((int) ($res['Defensefighter'] ?? 0) > 0): ?>
                                                                <div class="stat-line" style="color: #fff;">Phys. defense <?php echo (int) $res['Defensefighter']; ?></div>
                                                            <?php endif; ?>
                                                            <?php if ((int) ($res['Defensemage'] ?? 0) > 0): ?>
                                                                <div class="stat-line" style="color: #fff;">Mag. defense <?php echo (int) $res['Defensemage']; ?></div>
                                                            <?php endif; ?>

                                                            <?php
                                                            $allStats = ['HP' => 'hp', 'SP' => 'sp', 'MP' => 'mp', 'STR' => 'str', 'DEX' => 'dex', 'REC' => 'rec', 'INT' => 'int', 'WIS' => 'wis', 'LUC' => 'luc'];
                                                            foreach ($allStats as $label => $cssClass):
                                                                $base = (int) $baseStats[$label];
                                                                $bonus = (int) $bonusStats[$label];
                                                                if ($base > 0 || $bonus > 0):
                                                            ?>
                                                                    <div class="stat-line">
                                                                        <span class="stat-label-<?php echo $cssClass; ?>"><?php echo $label; ?></span>
                                                                        <span class="stat-v-base">+<?php echo $base + $bonus; ?></span>
                                                                        <?php if ($bonus > 0): ?><span class="stat-v-bonus">+<?php echo $bonus; ?></span><?php endif; ?>
                                                                    </div>
                                                            <?php
                                                                endif;
                                                            endforeach;
                                                            ?>

                                                            <?php if (isset($res['ReqLevel']) && (int) $res['ReqLevel'] > 0): ?>
                                                                <div class="req-level" style="color:#eee;">Lv. <?php echo (int) $res['ReqLevel']; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="owner-info">
                                                    <div>
                                                        <span class="label">Account:</span>
                                                        <a href="/admin?view=USERS&f_userid=<?php echo urlencode((string) ($res['AccountName'] ?? '')); ?>" class="value"><?php echo htmlspecialchars((string) ($res['AccountName'] ?? '')); ?></a>
                                                    </div>
                                                    <div style="margin-top: 4px;">
                                                        <span class="label">Main char:</span>
                                                        <?php if (!empty($res['CharName'])): ?>
                                                            <a href="/admin?view=CharEdit&id=<?php echo (int) ($res['CharID'] ?? 0); ?>" class="value" style="color: #fff;"><?php echo htmlspecialchars((string) $res['CharName']); ?></a>
                                                        <?php else: ?>
                                                            <span class="value" style="color: #444; font-style: italic;">No characters</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ((int) ($res['ItemCount'] ?? 1) > 1): ?>
                                                        <div style="margin-top: 4px;">
                                                            <span class="label">Count:</span>
                                                            <span class="value" style="color: #e8c881; font-weight: bold;"><?php echo number_format((int) $res['ItemCount']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="date-cell"><?php echo isset($res['Maketime']) ? date('d M H:i', strtotime((string) $res['Maketime'])) : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

