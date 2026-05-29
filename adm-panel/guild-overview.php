                    <?php
                    $guildID = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                    $tab = $_GET['tab'] ?? 'Overview';
                    $g = false;
                    try {
                        $q = $pdoAdmin->prepare("SELECT * FROM PS_GameData.dbo.Guilds WHERE GuildID = ? AND Del = 0");
                        $q->execute([$guildID]);
                        $g = $q->fetch(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}

                    if (!$g) {
                        echo '<div class="admin-error">Guild not found.</div>';
                    } else {
                        // Get member count
                        $memberCount = 0;
                        try {
                            $countQ = $pdoAdmin->prepare("SELECT COUNT(*) as member_count FROM PS_GameData.dbo.GuildChars WHERE GuildID = ? AND Del = 0");
                            $countQ->execute([$guildID]);
                            $memberCount = (int) ($countQ->fetch(PDO::FETCH_ASSOC)['member_count'] ?? 0);
                        } catch (Throwable $e) {}
                        ?>
                        <div class="guild-overview-container" style="max-width: 1100px; margin: 20px auto;">
                            <!-- Header -->
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <h2
                                    style="color: #fff; font-size: 22px; font-weight: 800; font-family: 'Futura PT', sans-serif; letter-spacing: 2px; margin: 0; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($g['GuildName']); ?>
                                </h2>
                                <div class="faction-badge"
                                    style="background: <?php echo ($g['Country'] == 0 ? 'rgba(52, 152, 219, 0.1)' : 'rgba(231, 76, 60, 0.1)'); ?>; color: <?php echo ($g['Country'] == 0 ? '#3498db' : '#e74c3c'); ?>; padding: 5px 15px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid <?php echo ($g['Country'] == 0 ? 'rgba(52, 152, 219, 0.2)' : 'rgba(231, 76, 60, 0.2)'); ?>;">
                                    <img src="assets/<?php echo ($g['Country'] == 0 ? 'aol.webp' : 'uof.webp'); ?>"
                                        style="width: 14px; margin-right: 8px; vertical-align: middle;">
                                </div>

                            <!-- Tabs -->
                            <div class="admin-tabs"
                                style="margin-bottom: 30px; display: flex; gap: 5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 1px;">
                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $guildID; ?>&tab=Overview"
                                    class="tab-link <?php echo ($tab === 'Overview') ? 'active' : ''; ?>"
                                    style="padding: 12px 25px; color: <?php echo ($tab === 'Overview' ? '#e8c881' : '#666'); ?>; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid <?php echo ($tab === 'Overview' ? '#e8c881' : 'transparent'); ?>; transition: all 0.2s;">General
                                    Info</a>
                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $guildID; ?>&tab=Members"
                                    class="tab-link <?php echo ($tab === 'Members') ? 'active' : ''; ?>"
                                    style="padding: 12px 25px; color: <?php echo ($tab === 'Members' ? '#e8c881' : '#666'); ?>; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid <?php echo ($tab === 'Members' ? '#e8c881' : 'transparent'); ?>; transition: all 0.2s;">Members
                                    (<?php echo $memberCount; ?>)</a>
                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $guildID; ?>&tab=Warehouse"
                                    class="tab-link <?php echo ($tab === 'Warehouse') ? 'active' : ''; ?>"
                                    style="padding: 12px 25px; color: <?php echo ($tab === 'Warehouse' ? '#e8c881' : '#666'); ?>; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid <?php echo ($tab === 'Warehouse' ? '#e8c881' : 'transparent'); ?>; transition: all 0.2s;">Guild
                                    Warehouse</a>
                            </div>

                            <?php if ($tab === 'Overview'): ?>
                                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px;">
                                    <div>
                                        <div class="char-edit-section"
                                            style="margin-bottom: 30px; padding: 25px; background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.03); border-radius: 8px;">
                                            <h3 style="font-size: 14px; margin-bottom: 20px;">RENAME GUILD</h3>
                                            <form class="char-edit-form" action="/admin_actions" method="POST"
                                                style="display: flex; gap: 15px; align-items: flex-end;">
                                                <input type="hidden" name="action" value="guild_rename">
                                                <input type="hidden" name="guild_id" value="<?php echo $guildID; ?>">
                                                <input type="hidden" name="return_view" value="GUILD_OVERVIEW">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <div class="char-input-group" style="flex: 1; margin: 0;">
                                                    <input type="text" name="new_name" class="char-input"
                                                        value="<?php echo htmlspecialchars($g['GuildName']); ?>" required
                                                        style="height: 40px; background: rgba(0,0,0,0.3);">
                                                </div>
                                                <button type="submit" class="btn-action btn-sm"
                                                    style="height: 40px; min-width: 120px;">UPDATE</button>
                                            </form>
                                        </div>

                                        <div class="char-edit-section"
                                            style="padding: 25px; background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.03); border-radius: 8px;">
                                            <h3 style="font-size: 14px; margin-bottom: 20px;">CHANGE LEADER</h3>
                                            <form class="char-edit-form" action="/admin_actions" method="POST"
                                                style="display: flex; gap: 15px; align-items: flex-end;">
                                                <input type="hidden" name="action" value="guild_change_leader">
                                                <input type="hidden" name="guild_id" value="<?php echo $guildID; ?>">
                                                <input type="hidden" name="return_view" value="GUILD_OVERVIEW">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <div class="char-input-group" style="flex: 1; margin: 0;">
                                                    <select name="new_leader_id" class="char-input"
                                                        style="height: 40px; background: rgba(0,0,0,0.3);">
                                                        <?php
                                                        try {
                                                            $membersQ = $pdoAdmin->prepare("SELECT GC.CharID, C.CharName, C.Job FROM PS_GameData.dbo.GuildChars GC JOIN PS_GameData.dbo.Chars C ON C.CharID = GC.CharID WHERE GC.GuildID = ? AND GC.Del = 0 ORDER BY C.CharName ASC");
                                                            $membersQ->execute([$guildID]);
                                                            while ($m = $membersQ->fetch(PDO::FETCH_ASSOC)) {
                                                                $selected = ($m['CharID'] == $g['MasterCharID']) ? 'selected' : '';
                                                                echo "<option value='{$m['CharID']}' $selected>" . htmlspecialchars($m['CharName']) . " (ID: {$m['CharID']})</option>";
                                                            }
                                                        } catch (Throwable $e) {}
                                                        ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn-action btn-sm"
                                                    style="height: 40px; min-width: 120px; background: rgba(52, 152, 219, 0.2); border-color: #3498db;">CHANGE</button>
                                            </form>
                                        </div>
                                    </div>

                                    <div>
                                        <table class="char-info-table" style="margin: 0; background: rgba(0,0,0,0.2);">
                                            <thead>
                                                <tr>
                                                    <th colspan="2" class="header-main" style="font-size: 11px; padding: 12px;">
                                                        STATISTICS & INFO</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px; width: 40%;">Guild ID</td>
                                                    <td style="color: #eee; font-weight: 600;"><?php echo $guildID; ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Leader</td>
                                                    <td style="color: #e8c881; font-weight: 600;">
                                                        <a href="/admin?view=CharEdit&id=<?php echo $g['MasterCharID']; ?>"
                                                            class="user-link"
                                                            style="color: inherit;"><?php echo htmlspecialchars($g['MasterName']); ?></a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Members</td>
                                                    <td><?php echo $memberCount; ?> / 250</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Guild Points</td>
                                                    <td style="color: #48bb78;"><?php echo number_format($g['GuildPoint']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Etin Amount</td>
                                                    <td><?php echo number_format($g['Etin'] ?? 0); ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Created</td>
                                                    <td style="color: #888; font-size: 10px;">
                                                        <?php echo date('Y-m-d H:i', strtotime($g['CreateDate'])); ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            <?php elseif ($tab === 'Members'): ?>
                                <div class="chars-table-container">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px;">Slot</th>
                                                <th style="width: 40px;"></th>
                                                <th>Name</th>
                                                <th>Level</th>
                                                <th>Class</th>
                                                <th>Join Date</th>
                                                <th style="width: 100px; text-align: center;">Leader</th>
                                                <th style="text-align: right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $guildMembers = [];
                                            try {
                                                $membersQ = $pdoAdmin->prepare("SELECT GC.*, C.CharName, C.Job, C.Level, C.RegDate FROM PS_GameData.dbo.GuildChars GC JOIN PS_GameData.dbo.Chars C ON C.CharID = GC.CharID WHERE GC.GuildID = ? AND GC.Del = 0 ORDER BY GC.GuildLevel ASC, C.CharName ASC");
                                                $membersQ->execute([$guildID]);
                                                $guildMembers = $membersQ->fetchAll(PDO::FETCH_ASSOC);
                                            } catch (Throwable $e) {}
                                            foreach ($guildMembers as $m):
                                                $isLeader = ($m['CharID'] == $g['MasterCharID']);
                                                ?>
                                                <tr>
                                                    <td style="color: #555;"><?php echo $m['GuildLevel']; ?></td>
                                                    <td><img src="assets/class/<?php echo (int) $m['Job']; ?>.webp"
                                                            style="width: 20px; vertical-align: middle; opacity: 0.8;"></td>
                                                    <td><a href="/admin?view=CharEdit&id=<?php echo $m['CharID']; ?>"
                                                            class="user-link"><?php echo htmlspecialchars($m['CharName']); ?></a></td>
                                                    <td><?php echo $m['Level']; ?></td>
                                                    <td style="font-size: 11px; color: #888;"><?php echo getJobName((int) $m['Job']); ?>
                                                    </td>
                                                    <td style="color: #666; font-size: 11px;">
                                                        <?php echo date('Y-m-d', strtotime($m['RegDate'])); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if ($isLeader): ?>
                                                            <i class="fas fa-crown" style="color: #e8c881; font-size: 14px;"
                                                                title="Guild Master"></i>
                                                        <?php else: ?>
                                                            <span style="color: #333;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: right;">
                                                        <a href="/admin?view=CharEdit&id=<?php echo $m['CharID']; ?>"
                                                            class="inventory-icon-btn btn-edit" title="Edit Character"><i
                                                                class="fas fa-user-cog"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php elseif ($tab === 'Warehouse'): ?>
                                <div class="styled-inventory-container">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                        <h3
                                            style="font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: #e8c881; border-left: 3px solid #e8c881; padding-left: 15px;">
                                            Guild Bank Contents</h3>
                                    </div>

                                    <table class="users-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 350px; text-align: left; padding-left: 20px;">ITEM NAME</th>
                                                <th style="width: 80px; text-align: center;">SLOT</th>
                                                <th style="width: 80px; text-align: center;">COUNT</th>
                                                <th style="text-align: center;">SOCKETS / GEMS</th>
                                                <th style="width: 150px; text-align: center;">TIMESTAMP</th>
                                                <th style="width: 80px; text-align: center;">ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $warehouseSql = "
                                                SELECT 
                                                    ci.ItemUID, ci.ItemID, ci.Slot, ci.Count, ci.Maketime, ci.Craftname,
                                                    ci.Gem1, ci.Gem2, ci.Gem3, ci.Gem4, ci.Gem5, ci.Gem6,
                                                    i.ItemName, i.Type, i.TypeID, i.ReqLevel, i.Defensefighter, i.Defensemage,
                                                    i.Attrib, i.Range, i.ConstHP, i.ConstSP, i.ConstMP,
                                                    i.ConstStr, i.ConstDex, i.ConstRec, i.ConstInt, i.ConstWis, i.ConstLuc
                                                FROM [PS_GameData].[dbo].[GuildStoredItems] ci
                                                INNER JOIN [PS_GameDefs].[dbo].[Items] i ON i.ItemID = ci.ItemID
                                                WHERE ci.GuildID = ?
                                                ORDER BY ci.Slot ASC
                                            ";
                                            $wStmt = null;
                                            try {
                                                $wStmt = $pdoAdmin->prepare($warehouseSql);
                                                $wStmt->execute([$guildID]);
                                            } catch (Throwable $e) {}

                                            $foundItems = false;
                                            while ($wStmt && ($item = $wStmt->fetch(PDO::FETCH_ASSOC))):
                                                $foundItems = true;
                                                $gems = [];
                                                for ($i = 1; $i <= 6; $i++) {
                                                    $gemID = (int) $item["Gem$i"];
                                                    if ($gemID > 0) {
                                                        $actualGemID = 30000 + $gemID;
                                                        try {
                                                            $gQ = $pdoAdmin->prepare("SELECT ItemName FROM PS_GameDefs.dbo.Items WHERE ItemID = ?");
                                                            $gQ->execute([$actualGemID]);
                                                            if ($gRes = $gQ->fetch(PDO::FETCH_ASSOC)) {
                                                                $gems[] = ['name' => $gRes['ItemName'], 'id' => $gemID];
                                                            }
                                                        } catch (Throwable $e) {}
                                                    }
                                                }

                                                $baseStats = [
                                                    'HP' => (int) ($item['ConstHP'] ?? 0),
                                                    'SP' => (int) ($item['ConstSP'] ?? 0),
                                                    'MP' => (int) ($item['ConstMP'] ?? 0),
                                                    'STR' => (int) ($item['ConstStr'] ?? 0),
                                                    'DEX' => (int) ($item['ConstDex'] ?? 0),
                                                    'REC' => (int) ($item['ConstRec'] ?? 0),
                                                    'INT' => (int) ($item['ConstInt'] ?? 0),
                                                    'WIS' => (int) ($item['ConstWis'] ?? 0),
                                                    'LUC' => (int) ($item['ConstLuc'] ?? 0),
                                                ];

                                                $bonusStats = [
                                                    'Enchant' => 0,
                                                    'STR' => 0,
                                                    'DEX' => 0,
                                                    'REC' => 0,
                                                    'INT' => 0,
                                                    'WIS' => 0,
                                                    'LUC' => 0,
                                                    'HP' => 0,
                                                    'SP' => 0,
                                                    'MP' => 0
                                                ];

                                                if (!empty($item['Craftname'])) {
                                                    $cn = $item['Craftname'];
                                                    $bonusStats['STR'] = (int) substr($cn, 0, 2);
                                                    $bonusStats['DEX'] = (int) substr($cn, 2, 2);
                                                    $bonusStats['REC'] = (int) substr($cn, 4, 2);
                                                    $bonusStats['INT'] = (int) substr($cn, 6, 2);
                                                    $bonusStats['WIS'] = (int) substr($cn, 8, 2);
                                                    $bonusStats['LUC'] = (int) substr($cn, 10, 2);
                                                    $bonusStats['HP'] = (int) substr($cn, 12, 2) * 100;
                                                    $bonusStats['SP'] = (int) substr($cn, 14, 2) * 100;
                                                    $bonusStats['MP'] = (int) substr($cn, 16, 2) * 100;

                                                    $enchantVal = (int) substr($cn, 18, 2);
                                                    if ($enchantVal >= 50)
                                                        $enchantVal -= 50;
                                                    $bonusStats['Enchant'] = $enchantVal;
                                                }
                                                ?>
                                                <tr>
                                                    <td style="text-align: left; padding-left: 20px; overflow: visible;">
                                                        <div class="item-name-wrapper">
                                                            <div style="color: #eee; font-weight: 500; font-size: 13px;">
                                                                <?php echo htmlspecialchars($item['ItemName']); ?>
                                                                <?php if ($bonusStats['Enchant'] > 0): ?>
                                                                    <span
                                                                        class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div style="color: #555; font-size: 10px;">UID:
                                                                <?php echo $item['ItemUID']; ?> | ID: <?php echo $item['ItemID']; ?>
                                                            </div>

                                                            <div class="item-stats-tooltip">
                                                                <div class="tooltip-header">
                                                                    <span><?php echo htmlspecialchars($item['ItemName']); ?></span>
                                                                    <?php if ($bonusStats['Enchant'] > 0): ?>
                                                                        <span
                                                                            class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if (($item['Defensefighter'] ?? 0) > 0): ?>
                                                                    <div class="stat-line" style="color: #fff;">Phys. defense
                                                                        <?php echo $item['Defensefighter']; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (($item['Defensemage'] ?? 0) > 0): ?>
                                                                    <div class="stat-line" style="color: #fff;">Mag. defense
                                                                        <?php echo $item['Defensemage']; ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php
                                                                $allStats = ['HP' => 'hp', 'SP' => 'sp', 'MP' => 'mp', 'STR' => 'str', 'DEX' => 'dex', 'REC' => 'rec', 'INT' => 'int', 'WIS' => 'wis', 'LUC' => 'luc'];
                                                                foreach ($allStats as $label => $cssClass):
                                                                    $base = $baseStats[$label];
                                                                    $bonus = $bonusStats[$label];
                                                                    if ($base > 0 || $bonus > 0): ?>
                                                                        <div class="stat-line">
                                                                            <span
                                                                                class="stat-label-<?php echo $cssClass; ?>"><?php echo $label; ?></span>
                                                                            <span class="stat-v-base">+<?php echo $base + $bonus; ?></span>
                                                                            <?php if ($bonus > 0): ?><span
                                                                                    class="stat-v-bonus">+<?php echo $bonus; ?></span><?php endif; ?>
                                                                        </div>
                                                                    <?php endif; endforeach; ?>

                                                                <?php if (isset($item['ReqLevel']) && $item['ReqLevel'] > 0): ?>
                                                                    <div class="req-level"
                                                                        style="color:#eee; margin-top: 10px; font-size: 11px; opacity: 0.6;">
                                                                        Requires Level <?php echo $item['ReqLevel']; ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center; color: #888; font-size: 11px;">
                                                        #<?php echo $item['Slot']; ?></td>
                                                    <td style="text-align: center; color: #da9f50; font-weight: 700;">
                                                        <?php echo number_format($item['Count']); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if (empty($gems)): ?><span style="color: #333;">-</span><?php else: ?>
                                                            <div
                                                                style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                                                <?php foreach ($gems as $gem): ?>
                                                                    <div class="gem-badge"
                                                                        style="font-size: 9px; color: #e8c881; background: rgba(232, 200, 129, 0.05); padding: 2px 8px; border-radius: 10px; border: 1px solid rgba(232, 200, 129, 0.1); width: fit-content;">
                                                                        <i class="fas fa-gem"
                                                                            style="font-size: 7px; margin-right: 4px; opacity: 0.6;"></i>
                                                                        <?php echo htmlspecialchars($gem['name']); ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center; color: #666; font-size: 11px;">
                                                        <?php echo date('Y-m-d H:i', strtotime($item['Maketime'])); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <form action="/admin_actions" method="POST" class="js-confirm-submit"
                                                            data-confirm="PERMANENTLY delete this guild item?">
                                                            <input type="hidden" name="action" value="guild_warehouse_item_delete">
                                                            <input type="hidden" name="guild_id" value="<?php echo $guildID; ?>">
                                                            <input type="hidden" name="item_uid"
                                                                value="<?php echo $item['ItemUID']; ?>">
                                                            <input type="hidden" name="return_view" value="GUILD_OVERVIEW">
                                                            <input type="hidden" name="tab" value="Warehouse">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <button type="submit" class="inventory-icon-btn btn-delete"
                                                                title="Delete Permanent"><i class="fas fa-trash-alt"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                            <?php if (!$foundItems): ?>
                                                <tr>
                                                    <td colspan="6" style="text-align: center; padding: 60px; color: #444;">
                                                        <i class="fas fa-warehouse"
                                                            style="font-size: 30px; opacity: 0.1; margin-bottom: 15px;"></i><br>
                                                        Guild warehouse is empty.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php } ?>
