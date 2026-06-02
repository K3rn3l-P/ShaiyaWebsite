                        <?php
                        $luckySettings  = fetchWebsiteSettings(['LuckyChestEnabled', 'LuckyChestCooldown']);
                        $luckyEnabled   = (string) ($luckySettings['LuckyChestEnabled'] ?? '0');
                        $lcCooldown     = max(60, (int)($luckySettings['LuckyChestCooldown'] ?? 21600));

                        $lcRewards      = [];
                        $lcTotalWeight  = 0;
                        $lcTableExists  = false;
                        try {
                            $lcStmt = $pdoAdmin->query(
                                "SELECT SlotID, ItemID, DisplayName, Icon, Color, Probability, SortOrder, IsActive
                                 FROM PS_WebSite.dbo.Web_LuckyChest ORDER BY SortOrder ASC, SlotID ASC"
                            );
                            $lcRewards = $lcStmt->fetchAll(PDO::FETCH_ASSOC);
                            $lcTableExists = true;
                            foreach ($lcRewards as $r) { $lcTotalWeight += (int)$r['Probability']; }
                        } catch (Throwable $e) {}
                        $lcActiveCount = count(array_filter($lcRewards, fn($r) => (int)$r['IsActive'] === 1));

                        $lcItemSearch  = isset($_GET['item_search']) ? trim((string)$_GET['item_search']) : '';
                        $lcItemResults = [];
                        if (mb_strlen($lcItemSearch) >= 2 && mb_strlen($lcItemSearch) <= 64
                            && preg_match('/^[\p{L}\p{N}\s_\-\.\'"]+$/u', $lcItemSearch)) {
                            try {
                                $lcIStmt = $pdoAdmin->prepare(
                                    "SELECT TOP 30 ItemID, ItemName, ReqLevel, Slot
                                     FROM PS_GameDefs.dbo.Items
                                     WHERE ItemName LIKE ? AND LEFT(ItemName, 1) != CHAR(63)
                                     ORDER BY ItemName ASC"
                                );
                                $lcIStmt->execute(['%' . $lcItemSearch . '%']);
                                $lcItemResults = $lcIStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}
                        }

                        $lcFaIcons = [
                            'gem','star','star-half-alt','trophy','crown','medal','award','ribbon',
                            'coins','money-bill-wave','ticket-alt','tag','tags','gift','box-open','box',
                            'magic','hat-wizard','scroll','flask','vial','ring',
                            'shield-alt','shield','bolt','fire','fire-alt','bomb','skull','skull-crossbones',
                            'snowflake','leaf','wind','feather-alt','feather','sun','moon',
                            'times-circle','question-circle','exclamation-circle','ban','check-circle',
                            'heart','star-of-life','info-circle','exclamation-triangle',
                            'dice','dice-d20','chess-king','chess-queen','key','lock','unlock',
                            'compass','map-marker-alt','crosshairs','dungeon','dragon',
                            'dove','crow','horse','cat','spider','fish','frog','bug',
                            'hammer','wrench','anchor','egg','apple-alt','candy-cane',
                            'book','bookmark','map','hourglass-half','clock','infinity',
                        ];
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

                        <style>
                        .lc-table{width:100%;border-collapse:collapse;background:rgba(0,0,0,0.4);border:1px solid var(--border-color);margin-bottom:16px;}
                        .lc-table th{background:linear-gradient(to bottom,#1a1a1a,#0a0a0a);padding:9px 7px;text-align:left;color:var(--text-gold);border-bottom:1px solid var(--border-color);font-size:10px;text-transform:uppercase;letter-spacing:1px;}
                        .lc-table td{padding:7px 5px;border-bottom:1px solid rgba(200,160,90,0.07);color:#ddd;font-size:12px;vertical-align:middle;}
                        .lc-table tr:hover td{background:rgba(200,160,90,0.04);}
                        .lc-inp{height:28px;padding:0 7px;background:rgba(0,0,0,0.5);border:1px solid rgba(200,160,90,0.2);color:#ddd;border-radius:3px;outline:none;font-size:12px;width:100%;box-sizing:border-box;}
                        .lc-inp:focus{border-color:rgba(200,160,90,0.5);}
                        .lc-btn-s{height:26px;padding:0 10px;background:rgba(72,187,120,0.15);border:1px solid rgba(72,187,120,0.3);color:#48bb78;border-radius:3px;cursor:pointer;font-size:11px;transition:all 0.2s;white-space:nowrap;}
                        .lc-btn-s:hover{background:rgba(72,187,120,0.3);border-color:#48bb78;}
                        .lc-btn-d{height:26px;padding:0 9px;background:rgba(229,62,62,0.1);border:1px solid rgba(229,62,62,0.3);color:#e53e3e;border-radius:3px;cursor:pointer;font-size:11px;transition:all 0.2s;white-space:nowrap;}
                        .lc-btn-d:hover{background:rgba(229,62,62,0.25);border-color:#e53e3e;}
                        .lc-section{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:8px;padding:22px 24px;margin-bottom:22px;}
                        .lc-stitle{color:var(--text-gold);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid rgba(232,200,129,0.1);}
                        </style>

                        <!-- Stats bar -->
                        <div style="display:flex;gap:24px;flex-wrap:wrap;margin:24px 0 0;padding:13px 18px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:8px;align-items:center;">
                            <div style="font-size:12px;color:#888;">Slots: <strong style="color:#fff;"><?php echo count($lcRewards); ?></strong></div>
                            <div style="font-size:12px;color:#888;">Attivi: <strong style="color:#48bb78;"><?php echo $lcActiveCount; ?></strong></div>
                            <div style="font-size:12px;color:#888;">Peso totale: <strong style="color:var(--text-gold);"><?php echo $lcTotalWeight; ?></strong></div>
                            <?php if (!$lcTableExists): ?>
                            <div style="font-size:11px;color:#e53e3e;"><i class="fas fa-exclamation-triangle"></i> Tabella Web_LuckyChest non trovata — esegui <code>sql/create_web_tables.sql</code></div>
                            <?php endif; ?>
                        </div>

                        <!-- Config cooldown -->
                        <div class="lc-section" style="margin-top:20px;">
                            <h4 class="lc-stitle"><i class="fas fa-clock" style="margin-right:6px;"></i>COOLDOWN CONFIG</h4>
                            <form action="/admin_actions" method="POST" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <input type="hidden" name="action" value="luckychest_save_config">
                                <input type="hidden" name="return_view" value="<?php echo htmlspecialchars($view); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <label style="color:#888;font-size:12px;">Secondi:</label>
                                <input type="number" name="cooldown_seconds" class="lc-inp" style="width:110px;" min="60" max="2592000" value="<?php echo $lcCooldown; ?>" id="lc-cd-inp">
                                <span style="color:#555;font-size:12px;" id="lc-cd-disp">= <?php echo floor($lcCooldown/3600); ?>h <?php echo floor(($lcCooldown%3600)/60); ?>m</span>
                                <button type="submit" class="lc-btn-s"><i class="fas fa-save" style="margin-right:4px;"></i>Salva</button>
                            </form>
                            <p style="color:#444;font-size:11px;margin:8px 0 0;">Min: 60s · Max: 2592000s (30 giorni) · Default: 21600s (6h)</p>
                        </div>

                        <!-- Rewards Editor -->
                        <div class="lc-section">
                            <h4 class="lc-stitle"><i class="fas fa-gift" style="margin-right:6px;"></i>REWARDS EDITOR</h4>
                            <?php if (!$lcTableExists): ?>
                            <p style="color:#555;font-size:13px;text-align:center;padding:16px;">Crea prima la tabella <code>Web_LuckyChest</code> eseguendo <code>sql/create_web_tables.sql</code>.</p>
                            <?php elseif (empty($lcRewards)): ?>
                            <p style="color:#555;font-size:13px;text-align:center;padding:16px;">Nessun reward. Aggiungine uno con il form qui sotto.</p>
                            <?php else: ?>
                            <div style="overflow-x:auto;">
                            <table class="lc-table">
                                <thead><tr>
                                    <th style="width:34px;">#</th>
                                    <th style="width:70px;">ItemID</th>
                                    <th style="min-width:90px;">Nome</th>
                                    <th style="width:130px;">Icon (FA)</th>
                                    <th style="width:58px;">Colore</th>
                                    <th style="width:56px;">Peso</th>
                                    <th style="width:46px;">%</th>
                                    <th style="width:44px;">Ord</th>
                                    <th style="width:48px;">Attivo</th>
                                    <th style="width:80px;">Azioni</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($lcRewards as $lc):
                                    $sid   = (int)$lc['SlotID'];
                                    $fid   = 'lcf' . $sid;
                                    $perc  = $lcTotalWeight > 0 ? round((int)$lc['Probability'] / $lcTotalWeight * 100, 1) : 0;
                                    $icEsc = htmlspecialchars($lc['Icon']);
                                    $clEsc = htmlspecialchars($lc['Color']);
                                ?>
                                <tr>
                                    <td style="color:#555;font-size:10px;"><?php echo $sid; ?></td>
                                    <td>
                                        <form id="<?php echo $fid; ?>" method="post" action="/admin_actions">
                                            <input type="hidden" name="action" value="luckychest_save_reward">
                                            <input type="hidden" name="return_view" value="<?php echo htmlspecialchars($view); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="slot_id" value="<?php echo $sid; ?>">
                                        </form>
                                        <input form="<?php echo $fid; ?>" type="number" name="item_id" class="lc-inp" style="width:64px;" min="0" value="<?php echo (int)$lc['ItemID']; ?>">
                                    </td>
                                    <td><input form="<?php echo $fid; ?>" type="text" name="display_name" class="lc-inp" maxlength="100" value="<?php echo htmlspecialchars($lc['DisplayName']); ?>"></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <i class="fas fa-<?php echo $icEsc; ?>" id="lciprv<?php echo $sid; ?>" style="color:<?php echo $clEsc; ?>;font-size:15px;flex-shrink:0;min-width:16px;"></i>
                                            <input form="<?php echo $fid; ?>" type="text" name="icon" id="lcic<?php echo $sid; ?>" class="lc-inp lc-icon-input" style="width:68px;" maxlength="50" value="<?php echo $icEsc; ?>"
                                                   data-prv-id="lciprv<?php echo $sid; ?>">
                                            <button type="button" class="lc-open-picker-btn"
                                                    data-inp-id="lcic<?php echo $sid; ?>" data-prv-id="lciprv<?php echo $sid; ?>"
                                                    style="height:26px;padding:0 6px;background:rgba(200,160,90,0.08);border:1px solid rgba(200,160,90,0.2);color:var(--text-gold);border-radius:3px;cursor:pointer;flex-shrink:0;"
                                                    title="Scegli icona"><i class="fas fa-th" style="font-size:11px;pointer-events:none;"></i></button>
                                        </div>
                                    </td>
                                    <td>
                                        <input form="<?php echo $fid; ?>" type="color" name="color" value="<?php echo $clEsc; ?>"
                                               style="width:36px;height:26px;border:none;background:none;cursor:pointer;padding:0;display:block;"
                                               title="<?php echo $clEsc; ?>">
                                    </td>
                                    <td><input form="<?php echo $fid; ?>" type="number" name="probability" class="lc-inp" style="width:50px;" min="1" max="9999" value="<?php echo (int)$lc['Probability']; ?>"></td>
                                    <td style="color:#666;font-size:11px;"><?php echo $perc; ?>%</td>
                                    <td><input form="<?php echo $fid; ?>" type="number" name="sort_order" class="lc-inp" style="width:40px;" min="0" value="<?php echo (int)$lc['SortOrder']; ?>"></td>
                                    <td style="text-align:center;">
                                        <input form="<?php echo $fid; ?>" type="checkbox" name="is_active" <?php echo (int)$lc['IsActive'] ? 'checked' : ''; ?> style="transform:scale(1.2);cursor:pointer;">
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:3px;">
                                            <button form="<?php echo $fid; ?>" type="submit" class="lc-btn-s" title="Salva"><i class="fas fa-save"></i></button>
                                            <form method="post" action="/admin_actions" style="display:inline;" class="js-confirm-submit" data-confirm="Eliminare reward #<?php echo $sid; ?>?">
                                                <input type="hidden" name="action" value="luckychest_delete_reward">
                                                <input type="hidden" name="return_view" value="<?php echo htmlspecialchars($view); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="slot_id" value="<?php echo $sid; ?>">
                                                <button type="submit" class="lc-btn-d" title="Elimina"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Add new reward -->
                        <div class="lc-section">
                            <h4 class="lc-stitle"><i class="fas fa-plus" style="margin-right:6px;"></i>AGGIUNGI NUOVO REWARD</h4>
                            <form action="/admin_actions" method="POST" id="lc-add-form">
                                <input type="hidden" name="action" value="luckychest_add_reward">
                                <input type="hidden" name="return_view" value="<?php echo htmlspecialchars($view); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:14px;">
                                    <div>
                                        <label style="display:block;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">ItemID</label>
                                        <input type="number" name="item_id" id="lc-new-itemid" class="lc-inp" min="0" value="0" required>
                                    </div>
                                    <div>
                                        <label style="display:block;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Nome</label>
                                        <input type="text" name="display_name" class="lc-inp" maxlength="100" placeholder="es. 100 AP" required>
                                    </div>
                                    <div>
                                        <label style="display:block;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Icon (FA name)</label>
                                        <div style="display:flex;align-items:center;gap:4px;">
                                            <i class="fas fa-gem" id="lc-new-iprv" style="color:#bdc3c7;font-size:15px;flex-shrink:0;"></i>
                                            <input type="text" name="icon" id="lc-new-icon" class="lc-inp lc-icon-input" style="width:76px;" maxlength="50" placeholder="gem" value="gem" required
                                                   data-prv-id="lc-new-iprv">
                                            <button type="button" class="lc-open-picker-btn"
                                                    data-inp-id="lc-new-icon" data-prv-id="lc-new-iprv"
                                                    style="height:26px;padding:0 6px;background:rgba(200,160,90,0.08);border:1px solid rgba(200,160,90,0.2);color:var(--text-gold);border-radius:3px;cursor:pointer;flex-shrink:0;"
                                                    title="Scegli icona"><i class="fas fa-th" style="font-size:11px;pointer-events:none;"></i></button>
                                        </div>
                                    </div>
                                    <div>
                                        <label style="display:block;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Colore</label>
                                        <input type="color" name="color" class="lc-inp" style="padding:2px 4px;" value="#bdc3c7" required>
                                    </div>
                                    <div>
                                        <label style="display:block;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Peso prob.</label>
                                        <input type="number" name="probability" class="lc-inp" min="1" max="9999" value="5" required>
                                    </div>
                                    <div>
                                        <label style="display:block;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Sort Ord</label>
                                        <input type="number" name="sort_order" class="lc-inp" min="0" value="99">
                                    </div>
                                    <div style="display:flex;align-items:flex-end;padding-bottom:2px;">
                                        <div>
                                            <label style="display:block;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Attivo</label>
                                            <input type="checkbox" name="is_active" checked style="transform:scale(1.3);cursor:pointer;">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn-action"><i class="fas fa-plus" style="margin-right:6px;"></i>Aggiungi Reward</button>
                            </form>
                        </div>

                        <!-- Items List -->
                        <div class="lc-section">
                            <h4 class="lc-stitle"><i class="fas fa-search" style="margin-right:6px;"></i>ITEMS LIST — Ricerca ItemID</h4>
                            <form method="GET" action="/admin" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
                                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                                <input type="text" name="item_search" class="lc-inp" style="flex:1;min-width:180px;max-width:340px;"
                                       placeholder="Cerca nome item..." maxlength="64"
                                       value="<?php echo htmlspecialchars($lcItemSearch); ?>">
                                <button type="submit" class="lc-btn-s" style="padding:0 16px;height:28px;"><i class="fas fa-search" style="margin-right:4px;"></i>Cerca</button>
                                <?php if ($lcItemSearch !== ''): ?>
                                <a href="/admin?view=<?php echo htmlspecialchars($view); ?>" class="lc-btn-d" style="display:flex;align-items:center;padding:0 10px;height:26px;text-decoration:none;" title="Pulisci">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </form>
                            <?php if ($lcItemSearch !== '' && mb_strlen($lcItemSearch) < 2): ?>
                                <p style="color:#e53e3e;font-size:12px;">Inserire almeno 2 caratteri.</p>
                            <?php elseif ($lcItemSearch !== '' && empty($lcItemResults)): ?>
                                <p style="color:#888;font-size:12px;">Nessun item trovato per "<em><?php echo htmlspecialchars($lcItemSearch); ?></em>".</p>
                            <?php elseif (!empty($lcItemResults)): ?>
                                <p style="color:#555;font-size:11px;margin-bottom:10px;"><?php echo count($lcItemResults); ?> risultati — clicca <strong style="color:#e8c881;">[Usa ID]</strong> per inserire l'ItemID nel form "Aggiungi Reward"</p>
                                <table class="lc-table">
                                    <thead><tr>
                                        <th style="width:80px;">ItemID</th>
                                        <th>Nome Item</th>
                                        <th style="width:72px;">Lvl req.</th>
                                        <th style="width:56px;">Slot</th>
                                        <th style="width:80px;">Azione</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php foreach ($lcItemResults as $ir):
                                        $iId   = (int)$ir['ItemID'];
                                        $iName = htmlspecialchars(mb_convert_encoding((string)$ir['ItemName'], 'UTF-8', 'Windows-1251'));
                                    ?>
                                    <tr>
                                        <td style="font-family:monospace;color:var(--text-gold);"><?php echo $iId; ?></td>
                                        <td><?php echo $iName; ?></td>
                                        <td><?php echo (int)$ir['ReqLevel']; ?></td>
                                        <td><?php echo (int)$ir['Slot']; ?></td>
                                        <td>
                                            <button type="button" class="lc-use-id-btn lc-btn-s" style="font-size:10px;padding:0 8px;"
                                                    data-itemid="<?php echo $iId; ?>">
                                                Usa ID
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="color:#555;font-size:12px;">Cerca un item per nome per trovare il suo ItemID da usare nel form "Aggiungi Reward".</p>
                            <?php endif; ?>
                        </div>

                        <!-- Icon Picker Modal — griglia generata da PHP (HTML statico, click via event delegation) -->
                        <style>
                        .lc-icon-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;padding:8px 4px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:5px;cursor:pointer;min-height:58px;transition:background 0.15s,border-color 0.15s;}
                        .lc-icon-btn:hover{background:rgba(200,160,90,0.12);border-color:rgba(200,160,90,0.35);}
                        .lc-icon-btn i{font-size:18px;color:var(--text-gold);pointer-events:none;}
                        .lc-icon-btn span{font-size:9px;color:#888;word-break:break-all;text-align:center;line-height:1.2;pointer-events:none;}
                        </style>
                        <div id="lc-icon-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:999999;align-items:center;justify-content:center;padding:20px;overflow:hidden;">
                            <div style="background:#0d0d0d;border:1px solid var(--border-color);border-radius:8px;padding:20px;width:100%;max-width:520px;max-height:90vh;box-shadow:0 8px 40px rgba(0,0,0,0.9);flex-shrink:0;display:flex;flex-direction:column;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid rgba(200,160,90,0.2);">
                                    <h4 style="color:var(--text-gold);margin:0;font-size:14px;"><i class="fas fa-th" style="margin-right:6px;"></i>SCEGLI ICONA</h4>
                                    <button type="button" class="lc-close-modal-btn" style="background:none;border:1px solid rgba(200,160,90,0.3);color:var(--text-gold);width:28px;height:28px;border-radius:4px;cursor:pointer;font-size:14px;">✕</button>
                                </div>
                                <input type="text" id="lc-icon-search" placeholder="Filtra icona..." autocomplete="off"
                                       style="width:100%;box-sizing:border-box;height:30px;padding:0 10px;background:rgba(0,0,0,0.5);border:1px solid rgba(200,160,90,0.2);color:#ddd;border-radius:4px;outline:none;font-size:12px;margin-bottom:14px;">
                                <div id="lc-icon-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(72px,1fr));gap:6px;overflow-y:auto;flex:1;min-height:0;">
                                    <?php foreach ($lcFaIcons as $lcIc): $lcIcE = htmlspecialchars($lcIc); ?>
                                    <button type="button" class="lc-icon-btn" data-icon="<?php echo $lcIcE; ?>">
                                        <i class="fas fa-<?php echo $lcIcE; ?>"></i>
                                        <span><?php echo $lcIcE; ?></span>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <script>
                        // ================================================================
                        // Tutto via document.addEventListener — CSP-compatibile (no onclick inline)
                        // Il nonce viene iniettato automaticamente dall'output buffer di security_bootstrap.php
                        // ================================================================
                        (function () {
                            var _lcIconInputId = null;
                            var _lcIconPrvId   = null;

                            // ---- CLICK delegation ----
                            document.addEventListener('click', function (e) {

                                // ⊞ Apri icon picker
                                var pickerBtn = e.target.closest('.lc-open-picker-btn[data-inp-id]');
                                if (pickerBtn) {
                                    _lcIconInputId = pickerBtn.getAttribute('data-inp-id');
                                    _lcIconPrvId   = pickerBtn.getAttribute('data-prv-id');
                                    var srch = document.getElementById('lc-icon-search');
                                    if (srch) { srch.value = ''; }
                                    document.querySelectorAll('#lc-icon-grid .lc-icon-btn').forEach(function (b) { b.style.display = ''; });
                                    var modal = document.getElementById('lc-icon-modal');
                                    if (modal) { modal.style.display = 'flex'; }
                                    if (srch) { setTimeout(function () { srch.focus({ preventScroll: true }); }, 30); }
                                    return;
                                }

                                // Seleziona icona nella griglia
                                var iconBtn = e.target.closest('#lc-icon-grid .lc-icon-btn[data-icon]');
                                if (iconBtn) {
                                    var name = iconBtn.getAttribute('data-icon');
                                    var inp  = _lcIconInputId ? document.getElementById(_lcIconInputId) : null;
                                    if (inp) { inp.value = name; inp.dispatchEvent(new Event('input')); }
                                    var prv  = _lcIconPrvId ? document.getElementById(_lcIconPrvId) : null;
                                    if (prv) { prv.className = 'fas fa-' + name; }
                                    var m = document.getElementById('lc-icon-modal');
                                    if (m) { m.style.display = 'none'; }
                                    return;
                                }

                                // ✕ Chiudi modal
                                if (e.target.closest('.lc-close-modal-btn')) {
                                    var m2 = document.getElementById('lc-icon-modal');
                                    if (m2) { m2.style.display = 'none'; }
                                    return;
                                }

                                // Click backdrop overlay
                                var modal2 = document.getElementById('lc-icon-modal');
                                if (modal2 && e.target === modal2) {
                                    modal2.style.display = 'none';
                                    return;
                                }

                                // Usa ID → copia ItemID nel form Aggiungi Reward
                                var useIdBtn = e.target.closest('.lc-use-id-btn[data-itemid]');
                                if (useIdBtn) {
                                    var id = useIdBtn.getAttribute('data-itemid');
                                    var f  = document.getElementById('lc-new-itemid');
                                    if (f && id) {
                                        f.value = id;
                                        f.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        f.style.transition = 'border-color 0.3s';
                                        f.style.borderColor = '#48bb78';
                                        setTimeout(function () { f.style.borderColor = ''; }, 1600);
                                    }
                                    return;
                                }
                            });

                            // ---- INPUT delegation ----
                            document.addEventListener('input', function (e) {
                                // Filtro ricerca icone
                                if (e.target.id === 'lc-icon-search') {
                                    var fv = e.target.value.toLowerCase().trim();
                                    document.querySelectorAll('#lc-icon-grid .lc-icon-btn').forEach(function (b) {
                                        b.style.display = (!fv || b.getAttribute('data-icon').indexOf(fv) !== -1) ? '' : 'none';
                                    });
                                    return;
                                }

                                // Cooldown human-readable display
                                if (e.target.id === 'lc-cd-inp') {
                                    var disp = document.getElementById('lc-cd-disp');
                                    if (disp) {
                                        var s = parseInt(e.target.value) || 0;
                                        disp.textContent = '= ' + Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
                                    }
                                    return;
                                }

                                // Preview live icona negli input con classe lc-icon-input
                                if (e.target.classList.contains('lc-icon-input')) {
                                    var prvId = e.target.getAttribute('data-prv-id');
                                    var prvEl = prvId ? document.getElementById(prvId) : null;
                                    if (prvEl) { prvEl.className = 'fas fa-' + e.target.value; }
                                    return;
                                }
                            });

                            // ---- ESC chiudi modal ----
                            document.addEventListener('keydown', function (e) {
                                if (e.key === 'Escape') {
                                    var m = document.getElementById('lc-icon-modal');
                                    if (m && m.style.display !== 'none') { m.style.display = 'none'; }
                                }
                            });
                        })();
                        </script>

