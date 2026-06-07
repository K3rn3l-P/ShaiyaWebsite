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
            $patchFiles[] = [
                'name'  => basename($f),
                'size'  => filesize($f),
                'mtime' => filemtime($f),
            ];
        }
    }
}
$totalPatches  = count($patchFiles);
$nextVersion   = $totalPatches + 1;
$nextPadded    = str_pad($nextVersion, 4, '0', STR_PAD_LEFT);

function fmtBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
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
        <h4 style="color:#ccc;font-size:14px;margin-bottom:20px;text-transform:uppercase;letter-spacing:1px;">Carica nuova patch</h4>

        <!-- Tab buttons -->
        <div style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:12px;">
            <button type="button" data-tab="sah" onclick="switchTab('sah')" id="tab-sah"
                style="padding:7px 18px;border-radius:4px;border:1px solid rgba(232,200,129,0.4);background:rgba(232,200,129,0.12);color:#e8c881;font-size:12px;cursor:pointer;letter-spacing:0.5px;">
                <i class="fas fa-file-archive"></i> Crea da SAH/SAF
            </button>
            <button type="button" data-tab="direct" onclick="switchTab('direct')" id="tab-direct"
                style="padding:7px 18px;border-radius:4px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:#666;font-size:12px;cursor:pointer;letter-spacing:0.5px;">
                <i class="fas fa-upload"></i> Upload .patch diretto
            </button>
        </div>

        <!-- Tab: Crea da SAH/SAF -->
        <div id="content-sah">
            <p style="color:#666;font-size:12px;margin-bottom:20px;">
                Esegui <code style="color:#e8c881;background:rgba(232,200,129,0.07);padding:1px 5px;border-radius:3px;">SHAIYA_MAKE.exe</code> localmente,
                rinomina <code style="color:#e8c881;background:rgba(232,200,129,0.07);padding:1px 5px;border-radius:3px;">data.sah/.saf → update.sah/.saf</code>,
                poi carica qui. Il server crea il .patch automaticamente.
            </p>
            <form action="/admin_actions" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="patch_upload">
                <input type="hidden" name="upload_mode" value="sah">
                <input type="hidden" name="return_view" value="patch_manager">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Descrizione patch <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="patch_desc" placeholder="es: fix_login_flow" required maxlength="80"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#fff;font-size:13px;box-sizing:border-box;">
                        <div style="color:#555;font-size:10px;margin-top:4px;">Solo lettere, numeri, _ e - (no spazi). Verrà preceduta da <?php echo $nextPadded; ?>_</div>
                    </div>
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Nome file risultante</label>
                        <div style="background:rgba(232,200,129,0.05);border:1px solid rgba(232,200,129,0.15);border-radius:4px;padding:10px 14px;color:#e8c881;font-size:13px;font-family:monospace;" id="preview-name">
                            <?php echo $nextPadded; ?>_<span id="desc-preview">descrizione</span>.patch
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

                <!-- delete.lst collapsible -->
                <div style="margin-bottom:20px;">
                    <button type="button" onclick="toggleDeleteLst()" id="delete-lst-toggle"
                        style="background:transparent;border:1px solid rgba(255,255,255,0.08);border-radius:4px;padding:6px 14px;color:#666;font-size:11px;cursor:pointer;text-transform:uppercase;letter-spacing:0.5px;">
                        <i class="fas fa-plus" id="delete-lst-icon"></i> Aggiungi delete.lst (opzionale)
                    </button>
                    <div id="delete-lst-panel" style="display:none;margin-top:12px;">
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Contenuto delete.lst — file da eliminare dal client (uno per riga)</label>
                        <textarea name="delete_lst_content" rows="6" placeholder="# Esempi:&#10;Cash.SData&#10;filter.txt&#10;Item/Item.SData&#10;interface/sonovlogo.bmp"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#ccc;font-size:12px;font-family:monospace;box-sizing:border-box;resize:vertical;"></textarea>
                        <div style="color:#555;font-size:10px;margin-top:4px;">⚠ Solo file sciolti nella cartella client (DLL, EXE, INI, SData ecc.). NON inserire file che stanno dentro data.sah/saf — delete.lst non può rimuoverli; per modificarli usa update.sah/saf nella patch.</div>
                    </div>
                </div>

                <button type="submit" class="admin-btn" style="padding:10px 32px;font-size:13px;">
                    <i class="fas fa-archive"></i> Crea e Carica Patch
                </button>
            </form>
        </div>

        <!-- Tab: Upload .patch diretto -->
        <div id="content-direct" style="display:none;">
            <p style="color:#666;font-size:12px;margin-bottom:20px;">
                Carica un file .patch già pronto (ZIP con estensione .patch).
                Puoi usare <code style="color:#e8c881;background:rgba(232,200,129,0.07);padding:1px 5px;border-radius:3px;">crea_patch.bat</code> localmente per crearlo.
            </p>
            <form action="/admin_actions" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="patch_upload">
                <input type="hidden" name="upload_mode" value="direct">
                <input type="hidden" name="return_view" value="patch_manager">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;color:#888;font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px;">Descrizione patch <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="patch_desc" placeholder="es: fix_login_flow" required maxlength="80"
                            style="width:100%;background:rgba(0,0,0,0.4);border:1px solid #333;border-radius:4px;padding:10px 14px;color:#fff;font-size:13px;box-sizing:border-box;">
                        <div style="color:#555;font-size:10px;margin-top:4px;">Verrà preceduta da <?php echo $nextPadded; ?>_</div>
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
                <thead>
                    <tr>
                        <th style="text-align:left;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Ver.</th>
                        <th style="text-align:left;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Nome file</th>
                        <th style="text-align:right;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Dimensione</th>
                        <th style="text-align:right;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Data</th>
                        <th style="text-align:center;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,0.07);color:#666;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;width:80px;">Azione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patchFiles as $i => $p): ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.02);">
                        <td style="padding:11px 14px;color:#e8c881;font-size:13px;font-weight:600;"><?php echo $i + 1; ?></td>
                        <td style="padding:11px 14px;color:#ddd;font-size:13px;font-family:monospace;"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td style="padding:11px 14px;color:#888;font-size:12px;text-align:right;"><?php echo fmtBytes((int)$p['size']); ?></td>
                        <td style="padding:11px 14px;color:#555;font-size:12px;text-align:right;"><?php echo date('Y-m-d H:i', (int)$p['mtime']); ?></td>
                        <td style="padding:11px 14px;text-align:center;">
                            <form action="/admin_actions" method="POST" style="display:inline;"
                                onsubmit="return confirm('Eliminare <?php echo htmlspecialchars(addslashes($p['name'])); ?>? Assicurati che tutti i client abbiano già questa versione.')">
                                <input type="hidden" name="action" value="patch_delete">
                                <input type="hidden" name="return_view" value="patch_manager">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="patch_file" value="<?php echo htmlspecialchars($p['name']); ?>">
                                <button type="submit" title="Elimina patch"
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
</div>

<script>
function switchTab(tab) {
    document.getElementById('content-sah').style.display    = tab === 'sah'    ? '' : 'none';
    document.getElementById('content-direct').style.display = tab === 'direct' ? '' : 'none';
    document.getElementById('tab-sah').style.cssText    = tab === 'sah'
        ? 'padding:7px 18px;border-radius:4px;border:1px solid rgba(232,200,129,0.4);background:rgba(232,200,129,0.12);color:#e8c881;font-size:12px;cursor:pointer;letter-spacing:0.5px;'
        : 'padding:7px 18px;border-radius:4px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:#666;font-size:12px;cursor:pointer;letter-spacing:0.5px;';
    document.getElementById('tab-direct').style.cssText = tab === 'direct'
        ? 'padding:7px 18px;border-radius:4px;border:1px solid rgba(232,200,129,0.4);background:rgba(232,200,129,0.12);color:#e8c881;font-size:12px;cursor:pointer;letter-spacing:0.5px;'
        : 'padding:7px 18px;border-radius:4px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:#666;font-size:12px;cursor:pointer;letter-spacing:0.5px;';
}
function toggleDeleteLst() {
    var p = document.getElementById('delete-lst-panel');
    var i = document.getElementById('delete-lst-icon');
    var t = document.getElementById('delete-lst-toggle');
    var open = p.style.display !== 'none';
    p.style.display = open ? 'none' : '';
    i.className = open ? 'fas fa-plus' : 'fas fa-minus';
}
// Live preview patch name
document.querySelectorAll('input[name="patch_desc"]').forEach(function(el) {
    el.addEventListener('input', function() {
        var val = this.value.replace(/[^a-zA-Z0-9_\-]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '') || 'descrizione';
        document.querySelectorAll('#desc-preview').forEach(function(s) { s.textContent = val; });
    });
});
</script>
