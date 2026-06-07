<?php
if (!hasAdminAccess('PATCH_MANAGER')) {
    echo '<div style="padding:40px;text-align:center;color:#e53e3e;"><i class="fas fa-lock" style="font-size:32px;margin-bottom:16px;display:block;opacity:0.4;"></i>Accesso non autorizzato.</div>';
    return;
}

$patchesDir = dirname(__DIR__) . '/Updater/Patches/';
$patchFiles = [];
if (is_dir($patchesDir)) {
    $raw = glob($patchesDir . '*.patch');
    if ($raw) {
        natsort($raw);
        foreach ($raw as $f) {
            $patchFiles[] = ['name' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)];
        }
    }
}
$totalPatches = count($patchFiles);
$nextVersion  = $totalPatches + 1;
$nextPadded   = str_pad($nextVersion, 4, '0', STR_PAD_LEFT);

function fmtBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

$patchHistory = [];
$patchHistoryErr = '';
try {
    global $pdoAdmin;
    $histStmt = $pdoAdmin->query(
        "SELECT TOP 30
             PatchID, PatchFile, PatchVersion, Description, UploadMode,
             FileSizeBytes, HasDeleteLst, ClientVersionBefore, ClientVersionAfter,
             UploadedBy, UploadedAt
         FROM PS_WebSite.dbo.Web_PatchHistory
         ORDER BY PatchID DESC"
    );
    $patchHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $patchHistoryErr = $e->getMessage();
}
?>

<div class="char-edit-section" style="max-width:1100px;margin:40px auto;padding:30px;background:rgba(15,15,15,0.6);border:1px solid rgba(255,255,255,0.05);border-radius:8px;">

    <h3 style="font-size:20px;color:#e8c881;text-align:center;margin-bottom:6px;">PATCH MANAGER</h3>
    <p style="color:#555;text-align:center;font-size:12px;margin-bottom:32px;">Gestione patch updater client. Il manifest si aggiorna automaticamente.</p>

    <!-- Stats row -->
    <div style="display:flex;gap:16px;margin-bottom:32px;flex-wrap:wrap;">
        <div style="background:rgba(72,187,120,0.08);border:1px solid rgba(72,187,120,0.2);border-radius:6px;padding:16px 24px;flex:1;min-width:160px;">
            <div style="color:#48bb78;font-size:22px;font-weight:700;"><?php echo $totalPatches; ?></div>
            <div style="color:#666;font-size:11px;text-transform:uppercase;letter-spacing:1px;">Patch sul server</div>
        </div>
        <div style="background:rgba(232,200,129,0.07);border:1px solid rgba(232,200,129,0.15);border-radius:6px;padding:16px 24px;flex:1;min-width:160px;">
            <div style="color:#e8c881;font-size:22px;font-weight:700;"><?php echo $totalPatches; ?></div>
            <div style="color:#666;font-size:11px;text-transform:uppercase;letter-spacing:1px;">clientVersion (auto)</div>
        </div>
        <div style="background:rgba(52,152,219,0.08);border:1px solid rgba(52,152,219,0.2);border-radius:6px;padding:16px 24px;flex:1;min-width:160px;">
            <div style="color:#3498db;font-size:22px;font-weight:700;"><?php echo $nextPadded; ?></div>
            <div style="color:#666;font-size:11px;text-transform:uppercase;letter-spacing:1px;">Prossima versione</div>
        </div>
    </div>

    <!-- Upload card -->
    <div style="background:rgba(0,0,0,0.3);border:1px solid rgba(232,200,129,0.1);border-radius:8px;padding:30px;margin-bottom:32px;">
        <h4 style="color:#ccc;font-size:14px;margin-bottom:20px;text-transform:uppercase;letter-spacing:1px;">Crea / carica patch</h4>

        <!-- Tab buttons -->
        <div style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:12px;flex-wrap:wrap;">
            <button type="button" id="tab-zip"
                style="padding:7px 18px;border-radius:4px;border:1px solid rgba(232,200,129,0.4);background:rgba(232,200,129,0.12);color:#e8c881;font-size:12px;cursor:pointer;letter-spacing:0.5px;">
                <i class="fas fa-file-archive"></i> Crea da ZIP
            </button>
            <button type="button" id="tab-sah"
                style="padding:7px 18px;border-radius:4px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:#666;font-size:12px;cursor:pointer;letter-spacing:0.5px;">
                <i class="fas fa-database"></i> Crea da SAH/SAF
            </button>
            <button type="button" id="tab-direct"
                style="padding:7px 18px;border-radius:4px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:#666;font-size:12px;cursor:pointer;letter-spacing:0.5px;">
                <i class="fas fa-upload"></i> Upload .patch
            </button>
        </div>

        <!-- ═══ Tab: Crea da ZIP (default) ═══ -->
        <div id="content-zip">
            <p style="color:#666;font-size:12px;margin-bottom:12px;">
                Carica uno <strong style="color:#ccc;">ZIP con i file</strong>. La struttura dentro lo ZIP viene preservata intatta —
                <code style="color:#e8c881;background:rgba(232,200,129,0.07);padding:1px 4px;border-radius:3px;">Effect/file.EFT</code> nel ZIP
                → <code style="color:#e8c881;background:rgba(232,200,129,0.07);padding:1px 4px;border-radius:3px;">Effect/file.EFT</code> nel SAH.
            </p>

            <form id="form-zip" action="/admin_actions" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="patch_upload">
                <input type="hidden" name="upload_mode" value="workspace_zip">
                <input type="hidden" name="return_view" value="patch_manager">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Descrizione patch <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="patch_desc" id="desc-zip" placeholder="es: armor_effect_v13" required maxlength="80"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#fff;font-size:13px;box-sizing:border-box;">
                        <div style="color:#555;font-size:10px;margin-top:4px;">Preceduta da <?php echo $nextPadded; ?>_</div>
                    </div>
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Nome file risultante</label>
                        <div style="background:rgba(232,200,129,0.05);border:1px solid rgba(232,200,129,0.15);border-radius:4px;padding:10px 14px;color:#e8c881;font-size:13px;font-family:monospace;">
                            <?php echo $nextPadded; ?>_<span id="desc-preview-zip">descrizione</span>.patch
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">File ZIP <span style="color:#e53e3e">*</span></label>
                    <input type="file" name="workspace_zip" accept=".zip" required
                        style="width:100%;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:4px;padding:8px 12px;color:#ccc;font-size:12px;box-sizing:border-box;">
                    <div style="color:#555;font-size:10px;margin-top:4px;">
                        Limite: <code style="color:#666;">upload_max_filesize</code> in php.ini. Max elaborazione: 200 MB totali.
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <button type="button" id="delete-lst-toggle-zip"
                        style="background:transparent;border:1px solid rgba(255,255,255,0.08);border-radius:4px;padding:6px 14px;color:#666;font-size:11px;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="fas fa-plus" id="delete-lst-icon-zip"></i> Aggiungi delete.lst (opzionale)
                    </button>
                    <div id="delete-lst-panel-zip" style="display:none;margin-top:12px;">
                        <textarea name="delete_lst_content" rows="5"
                            placeholder="# Esempi:&#10;Cash.SData&#10;filter.txt"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#ccc;font-size:12px;font-family:monospace;box-sizing:border-box;resize:vertical;"></textarea>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="padding:10px 32px;font-size:13px;">
                    <i class="fas fa-cogs"></i> Genera e Carica Patch
                </button>
            </form>
        </div>

        <!-- ═══ Tab: Crea da SAH/SAF ═══ -->
        <div id="content-sah" style="display:none;">
            <p style="color:#666;font-size:12px;margin-bottom:20px;">
                Carica i file <code style="color:#e8c881;background:rgba(232,200,129,0.07);padding:1px 5px;border-radius:3px;">update.sah</code> e
                <code style="color:#e8c881;background:rgba(232,200,129,0.07);padding:1px 5px;border-radius:3px;">update.saf</code> già pronti
                (creati con SHAIYA_MAKE.exe o tool simile). Il SAH viene cifrato ESAH automaticamente.
            </p>
            <form id="form-sah" action="/admin_actions" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="patch_upload">
                <input type="hidden" name="upload_mode" value="sah">
                <input type="hidden" name="return_view" value="patch_manager">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Descrizione patch <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="patch_desc" id="desc-sah" placeholder="es: fix_login_flow" required maxlength="80"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#fff;font-size:13px;box-sizing:border-box;">
                        <div style="color:#555;font-size:10px;margin-top:4px;">Preceduta da <?php echo $nextPadded; ?>_</div>
                    </div>
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Nome file risultante</label>
                        <div style="background:rgba(232,200,129,0.05);border:1px solid rgba(232,200,129,0.15);border-radius:4px;padding:10px 14px;color:#e8c881;font-size:13px;font-family:monospace;">
                            <?php echo $nextPadded; ?>_<span id="desc-preview-sah">descrizione</span>.patch
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">update.sah <span style="color:#e53e3e">*</span></label>
                        <input type="file" name="sah_file" accept=".sah" required
                            style="width:100%;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:4px;padding:8px 12px;color:#ccc;font-size:12px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">update.saf <span style="color:#e53e3e">*</span></label>
                        <input type="file" name="saf_file" accept=".saf" required
                            style="width:100%;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:4px;padding:8px 12px;color:#ccc;font-size:12px;box-sizing:border-box;">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <button type="button" id="delete-lst-toggle"
                        style="background:transparent;border:1px solid rgba(255,255,255,0.08);border-radius:4px;padding:6px 14px;color:#666;font-size:11px;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="fas fa-plus" id="delete-lst-icon"></i> Aggiungi delete.lst (opzionale)
                    </button>
                    <div id="delete-lst-panel" style="display:none;margin-top:12px;">
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Contenuto delete.lst</label>
                        <textarea name="delete_lst_content" rows="5"
                            placeholder="# Esempi:&#10;Cash.SData&#10;filter.txt"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#ccc;font-size:12px;font-family:monospace;box-sizing:border-box;resize:vertical;"></textarea>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="padding:10px 32px;font-size:13px;">
                    <i class="fas fa-archive"></i> Crea e Carica Patch
                </button>
            </form>
        </div>

        <!-- ═══ Tab: Upload .patch diretto ═══ -->
        <div id="content-direct" style="display:none;">
            <p style="color:#666;font-size:12px;margin-bottom:20px;">
                Carica un file <strong style="color:#ccc;">.patch già pronto</strong> creato localmente con il tool PatchMaker.
            </p>
            <form id="form-direct" action="/admin_actions" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="patch_upload">
                <input type="hidden" name="upload_mode" value="direct">
                <input type="hidden" name="return_view" value="patch_manager">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Descrizione patch <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="patch_desc" placeholder="es: fix_login_flow" required maxlength="80"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#fff;font-size:13px;box-sizing:border-box;">
                        <div style="color:#555;font-size:10px;margin-top:4px;">Preceduta da <?php echo $nextPadded; ?>_</div>
                    </div>
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">File .patch <span style="color:#e53e3e">*</span></label>
                        <input type="file" name="patch_file" accept=".patch,.zip" required
                            style="width:100%;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:4px;padding:8px 12px;color:#ccc;font-size:12px;box-sizing:border-box;">
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="padding:10px 32px;font-size:13px;">
                    <i class="fas fa-upload"></i> Carica Patch
                </button>
            </form>
        </div>
    </div>

    <!-- Patch list -->
    <div>
        <h4 style="color:#ccc;font-size:14px;margin-bottom:16px;text-transform:uppercase;letter-spacing:1px;">Patch sul server (<?php echo $totalPatches; ?>)</h4>
        <?php if (empty($patchFiles)): ?>
            <div style="text-align:center;padding:40px;color:#444;font-size:13px;">
                <i class="fas fa-box-open" style="font-size:32px;display:block;margin-bottom:12px;opacity:0.3;"></i>
                Nessuna patch in <code>Updater/Patches/</code>
            </div>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="text-align:left;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Ver.</th>
                    <th style="text-align:left;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Nome file</th>
                    <th style="text-align:right;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Dim.</th>
                    <th style="text-align:right;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Data</th>
                    <th style="text-align:center;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;width:80px;">Azione</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($patchFiles as $i => $p): ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.02);">
                        <td style="padding:11px 14px;color:#e8c881;font-size:13px;font-weight:600;"><?php echo $i + 1; ?></td>
                        <td style="padding:11px 14px;color:#ddd;font-size:13px;font-family:monospace;"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td style="padding:11px 14px;color:#888;font-size:12px;text-align:right;"><?php echo fmtBytes((int)$p['size']); ?></td>
                        <td style="padding:11px 14px;color:#555;font-size:12px;text-align:right;"><?php echo date('Y-m-d H:i', (int)$p['mtime']); ?></td>
                        <td style="padding:11px 14px;text-align:center;">
                            <form class="form-delete-patch" action="/admin_actions" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="patch_delete">
                                <input type="hidden" name="return_view" value="patch_manager">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="patch_file" value="<?php echo htmlspecialchars($p['name']); ?>">
                                <button type="submit" data-patch="<?php echo htmlspecialchars($p['name']); ?>"
                                    style="background:rgba(229,62,62,0.1);border:1px solid rgba(229,62,62,0.3);border-radius:4px;padding:5px 10px;color:#e53e3e;cursor:pointer;font-size:11px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:12px;padding:12px 14px;background:rgba(229,62,62,0.05);border:1px solid rgba(229,62,62,0.12);border-radius:4px;color:#888;font-size:11px;">
                <i class="fas fa-exclamation-triangle" style="color:#e53e3e;margin-right:6px;"></i>
                Non eliminare patch vecchie finché non sei certo che tutti i client abbiano già quella versione.
                Il client cerca versioni sequenziali (1, 2, 3…) — un gap causa errore di aggiornamento.
            </div>
        <?php endif; ?>
    </div>

    <!-- Storico DB -->
    <div style="margin-top:40px;">
        <h4 style="color:#ccc;font-size:14px;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px;">Storico upload (DB)</h4>
        <p style="color:#444;font-size:11px;margin-bottom:16px;">Ultime 30 operazioni — <code style="color:#666;">Web_PatchHistory</code>.</p>

        <?php if ($patchHistoryErr !== ''): ?>
            <div style="padding:14px 18px;background:rgba(229,62,62,0.07);border:1px solid rgba(229,62,62,0.2);border-radius:6px;color:#e53e3e;font-size:12px;">
                <i class="fas fa-exclamation-circle"></i>
                Tabella <code>Web_PatchHistory</code> non trovata — esegui <code>create_web_tables.sql</code>.
                <br><small style="color:#666;margin-top:4px;display:block;"><?php echo htmlspecialchars($patchHistoryErr); ?></small>
            </div>
        <?php elseif (empty($patchHistory)): ?>
            <div style="text-align:center;padding:30px;color:#333;font-size:12px;">
                <i class="fas fa-history" style="font-size:24px;display:block;margin-bottom:10px;opacity:0.2;"></i>
                Nessun upload registrato ancora.
            </div>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <th style="text-align:left;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">#</th>
                    <th style="text-align:left;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">File</th>
                    <th style="text-align:left;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Descrizione</th>
                    <th style="text-align:center;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Modo</th>
                    <th style="text-align:right;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Dim.</th>
                    <th style="text-align:center;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Ver. prima → dopo</th>
                    <th style="text-align:center;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">del.lst</th>
                    <th style="text-align:left;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Admin</th>
                    <th style="text-align:right;padding:9px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#555;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Data</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($patchHistory as $h): ?>
                    <?php
                        $mode = (string)$h['UploadMode'];
                        if ($mode === 'sah')       { $modeColor = '#48bb78'; $modeLabel = 'SAH/SAF'; }
                        elseif ($mode === 'folder')    { $modeColor = '#e8c881'; $modeLabel = 'File'; }
                        elseif ($mode === 'workspace') { $modeColor = '#9b59b6'; $modeLabel = 'ZIP'; }
                        else                           { $modeColor = '#3498db'; $modeLabel = 'Direct'; }
                    ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.02);">
                        <td style="padding:9px 12px;color:#444;font-size:11px;"><?php echo (int)$h['PatchID']; ?></td>
                        <td style="padding:9px 12px;color:#bbb;font-size:11px;font-family:monospace;"><?php echo htmlspecialchars($h['PatchFile']); ?></td>
                        <td style="padding:9px 12px;color:#888;font-size:11px;"><?php echo htmlspecialchars($h['Description']); ?></td>
                        <td style="padding:9px 12px;text-align:center;">
                            <span style="display:inline-block;padding:2px 7px;border-radius:3px;font-size:10px;font-weight:600;color:<?php echo $modeColor; ?>;background:<?php echo $modeColor; ?>18;border:1px solid <?php echo $modeColor; ?>33;"><?php echo $modeLabel; ?></span>
                        </td>
                        <td style="padding:9px 12px;color:#666;font-size:11px;text-align:right;"><?php echo fmtBytes((int)$h['FileSizeBytes']); ?></td>
                        <td style="padding:9px 12px;text-align:center;color:#666;font-size:11px;">
                            <span style="color:#888;"><?php echo (int)$h['ClientVersionBefore']; ?></span>
                            <span style="color:#444;"> → </span>
                            <span style="color:#e8c881;font-weight:600;"><?php echo (int)$h['ClientVersionAfter']; ?></span>
                        </td>
                        <td style="padding:9px 12px;text-align:center;font-size:12px;">
                            <?php if ($h['HasDeleteLst']): ?>
                                <i class="fas fa-check" style="color:#48bb78;"></i>
                            <?php else: ?>
                                <span style="color:#333;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:9px 12px;color:#666;font-size:11px;"><?php echo htmlspecialchars($h['UploadedBy']); ?></td>
                        <td style="padding:9px 12px;color:#444;font-size:11px;text-align:right;white-space:nowrap;"><?php echo htmlspecialchars((string)$h['UploadedAt']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    /* ── Tab switching (3 tab: zip, sah, direct) ── */
    var TABS = ['zip', 'sah', 'direct'];
    var TAB_ACTIVE = 'padding:7px 18px;border-radius:4px;border:1px solid rgba(232,200,129,0.4);background:rgba(232,200,129,0.12);color:#e8c881;font-size:12px;cursor:pointer;letter-spacing:0.5px;';
    var TAB_IDLE   = 'padding:7px 18px;border-radius:4px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:#666;font-size:12px;cursor:pointer;letter-spacing:0.5px;';

    function switchTab(tab) {
        TABS.forEach(function (t) {
            document.getElementById('content-' + t).style.display = (t === tab) ? '' : 'none';
            document.getElementById('tab-' + t).style.cssText      = (t === tab) ? TAB_ACTIVE : TAB_IDLE;
        });
    }
    TABS.forEach(function (t) {
        document.getElementById('tab-' + t).addEventListener('click', function () { switchTab(t); });
    });

    /* ── delete.lst toggles ── */
    function makeDlstToggle(btnId, panelId, iconId) {
        var open = false;
        document.getElementById(btnId).addEventListener('click', function () {
            open = !open;
            document.getElementById(panelId).style.display = open ? '' : 'none';
            document.getElementById(iconId).className = open ? 'fas fa-minus' : 'fas fa-plus';
        });
    }
    makeDlstToggle('delete-lst-toggle-zip', 'delete-lst-panel-zip', 'delete-lst-icon-zip');
    makeDlstToggle('delete-lst-toggle',     'delete-lst-panel',     'delete-lst-icon');

    /* ── desc live preview ── */
    function makeDescPreview(inputId, previewId) {
        var el = document.getElementById(inputId);
        if (!el) return;
        el.addEventListener('input', function () {
            var v = this.value.replace(/[^a-zA-Z0-9_\-]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '') || 'descrizione';
            var p = document.getElementById(previewId);
            if (p) p.textContent = v;
        });
    }
    makeDescPreview('desc-zip', 'desc-preview-zip');
    makeDescPreview('desc-sah', 'desc-preview-sah');

    /* ── Delete confirm ── */
    document.querySelectorAll('.form-delete-patch').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var btn  = form.querySelector('button[data-patch]');
            var name = btn ? btn.getAttribute('data-patch') : 'questa patch';
            if (!confirm('Eliminare ' + name + '?\n\nAssicurati che tutti i client abbiano già questa versione.')) {
                e.preventDefault();
            }
        });
    });
}());
</script>
