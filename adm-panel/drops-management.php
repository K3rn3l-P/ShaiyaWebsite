                        
                        <div class="char-edit-section">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                                <div>
                                    <h3 style="margin-bottom: 5px; color: #e8c881;">DROPS MANAGEMENT</h3>
                                    <p style="color: #666; font-size: 13px;">Manage global drop settings and item blacklists
                                        from this central hub.</p>
                                </div>
                                <button id="btn-dm-help" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); color:#666; border-radius:6px; padding:7px 16px; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-question-circle"></i> HELP
                                </button>

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

                            <!-- ── Upload CSV Items ─────────────────────────────────── -->
                            <div style="margin-top: 40px; background: rgba(255,255,255,0.02); padding: 28px; border-radius: 12px; border: 1px solid rgba(52,152,219,0.12);">
                                <h4 style="color: #3498db; margin: 0 0 6px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                    <i class="fas fa-file-csv" style="margin-right: 8px;"></i>Upload CSV Items
                                </h4>
                                <p style="color: #555; font-size: 12px; margin: 0 0 20px 0;">
                                    Importa items da <code style="color:#777;">Item.csv</code> (Shaiya File Converter V2) → aggiorna <code style="color:#777;">PS_GameDefs.dbo.Items</code>.
                                    Riga saltata se Grade=0 o nome inizia con "???". Formato atteso: colonna 2=Nome, 6=MinLevel, 48=Grade.
                                </p>
                                <?php
                                // Mostra risultato import se presente
                                if (!empty($_SESSION['items_csv_result'])) {
                                    $r = $_SESSION['items_csv_result'];
                                    unset($_SESSION['items_csv_result']);
                                    $rColor = ($r['status'] ?? 'error') === 'ok' ? '#48bb78' : '#e74c3c';
                                    echo '<div style="background:rgba(0,0,0,0.3); border:1px solid ' . $rColor . '33; border-radius:6px; padding:14px 18px; margin-bottom:18px; color:' . $rColor . '; font-size:13px;">';
                                    echo htmlspecialchars($r['msg'] ?? '');
                                    if (!empty($r['inserted']))  echo ' <span style="color:#48bb78;">+' . (int)$r['inserted']  . ' inseriti</span>';
                                    if (!empty($r['updated']))   echo ' <span style="color:#e8c881;">' . (int)$r['updated']   . ' aggiornati</span>';
                                    if (!empty($r['skipped']))   echo ' <span style="color:#555;">' . (int)$r['skipped']    . ' saltati</span>';
                                    if (!empty($r['errors']))    echo ' <span style="color:#e74c3c;">' . (int)$r['errors']   . ' errori</span>';
                                    echo '</div>';
                                }
                                ?>
                                <form action="/admin_actions" method="POST" enctype="multipart/form-data"
                                      onsubmit="return confirm('Importare il file CSV? Gli items esistenti con lo stesso Grade verranno aggiornati.');">
                                    <input type="hidden" name="action"       value="items_csv_import">
                                    <input type="hidden" name="return_view"  value="<?php echo $view; ?>">
                                    <input type="hidden" name="csrf_token"   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                                        <input type="file" name="csv_file" accept=".csv,.txt"
                                               style="background: rgba(0,0,0,0.3); border: 1px solid rgba(52,152,219,0.25); color: #aaa; border-radius: 6px; padding: 8px 12px; font-size: 13px; flex: 1; min-width: 240px;">
                                        <button type="submit" style="background: rgba(52,152,219,0.1); border: 1px solid rgba(52,152,219,0.3); color: #3498db; border-radius: 6px; padding: 9px 22px; font-size: 13px; cursor: pointer; white-space: nowrap; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                            <i class="fas fa-upload" style="margin-right: 6px;"></i>Importa Items
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

<!-- ─ Modal Help Drops Management ─────────────────────────────────────── -->
<div id="modal-dm-help" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:#111; border:1px solid rgba(232,200,129,0.2); border-radius:12px; width:600px; max-height:85vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.5);">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid rgba(255,255,255,0.06);">
            <h3 style="color:#e8c881; margin:0; font-size:16px;"><i class="fas fa-question-circle" style="margin-right:10px;"></i>Guida Drops Management</h3>
            <button id="btn-dm-help-close" style="background:none; border:none; color:#555; font-size:18px; cursor:pointer;">&#x2715;</button>
        </div>
        <div style="padding:20px 28px; font-size:13px; line-height:1.6;">
            <div style="color:#888; font-size:11px; text-transform:uppercase; letter-spacing:2px; margin-bottom:8px; margin-top:4px;">Global Configuration</div>
            <p style="color:#666; margin:0 0 16px 0;"><strong style="color:#aaa;">Max Grade</strong> — Items con Grade superiore vengono nascosti nella pagina pubblica drops.<br>
            <strong style="color:#aaa;">Max Level</strong> — Items con Reqlevel superiore vengono nascosti.<br>
            <strong style="color:#aaa;">Hide 0% / Hide Empty</strong> — Filtra items senza drop chance sulla pagina pubblica.</p>

            <div style="color:#888; font-size:11px; text-transform:uppercase; letter-spacing:2px; margin-bottom:8px;">Blacklist</div>
            <p style="color:#666; margin:0 0 16px 0;">ItemID nella blacklist sono <strong style="color:#aaa;">sempre nascosti</strong> dalla pagina drops pubblica, indipendentemente da Grade/Level.</p>

            <div style="color:#888; font-size:11px; text-transform:uppercase; letter-spacing:2px; margin-bottom:8px;">Upload CSV Items</div>
            <p style="color:#666; margin:0 0 8px 0;"><strong style="color:#3498db;">Quando usarlo</strong>: solo quando ci sono nuovi items nel gioco non ancora nel DB (aggiornamenti patch).</p>
            <div style="color:#666; margin-bottom:4px;"><span style="color:#aaa;">1.</span> Apri <strong>Shaiya File Converter V2</strong> → carica <code style="background:rgba(255,255,255,0.06); padding:1px 6px; border-radius:3px;">Item.SData</code> → esporta <code style="background:rgba(255,255,255,0.06); padding:1px 6px; border-radius:3px;">Item.csv</code>.</div>
            <div style="color:#666; margin-bottom:4px;"><span style="color:#aaa;">2.</span> Admin → Drops Management → sezione <strong>Upload CSV Items</strong> → scegli file → <strong>Importa Items</strong>.</div>
            <div style="color:#666; margin-bottom:4px;"><span style="color:#aaa;">3.</span> Il sistema aggiorna Items esistenti (stesso Grade) e inserisce quelli nuovi. Grade=0 e nomi "???" vengono saltati.</div>
            <div style="color:#666;"><span style="color:#aaa;">4.</span> Dopo l'import, i nuovi items sono subito ricercabili nel Drop Editor (per nome o Grade).</div>
        </div>
    </div>
</div>
<script>
(function () {
    var dmHelp      = document.getElementById('modal-dm-help');
    var dmHelpBtn   = document.getElementById('btn-dm-help');
    var dmHelpClose = document.getElementById('btn-dm-help-close');
    if (dmHelp && dmHelpBtn) {
        document.body.appendChild(dmHelp);
        dmHelpBtn.addEventListener('click', function () { dmHelp.style.display = 'flex'; document.body.style.overflow = 'hidden'; });
        dmHelpClose.addEventListener('click', function () { dmHelp.style.display = 'none'; document.body.style.overflow = ''; });
        dmHelp.addEventListener('click', function (e) { if (e.target === dmHelp) { dmHelp.style.display = 'none'; document.body.style.overflow = ''; } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dmHelp.style.display !== 'none') { dmHelp.style.display = 'none'; document.body.style.overflow = ''; } });
    }
})();
</script>

