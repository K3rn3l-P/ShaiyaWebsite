<?php
// Incluso da admin.php — sicurezza e sessione già gestite

if (!hasAdminAccess('BOSSES_EDITOR')) {
    echo '<div style="padding:40px;text-align:center;color:#e53e3e;"><i class="fas fa-lock" style="font-size:32px;margin-bottom:16px;display:block;opacity:0.4;"></i>Access denied.</div>';
    return;
}

$rlRetry = 0;
if (!appRateLimitAllow('bosses_editor:' . $adminUserUID, 60, 300, $rlRetry)) {
    echo '<div style="padding:20px;color:#e53e3e;"><i class="fas fa-exclamation-triangle"></i> Rate limit. Retry in ' . (int)$rlRetry . 's.</div>';
    return;
}

$PER_PAGE = 20;

function beFormatRespawn(int $secs): string {
    if ($secs <= 0) return '?';
    if ($secs >= 86400) { $v = $secs / 86400; return (floor($v) == $v ? (int)$v : number_format($v, 1)) . 'd'; }
    if ($secs >= 3600)  { $v = $secs / 3600;  return (floor($v) == $v ? (int)$v : number_format($v, 1)) . 'h'; }
    return (int)round($secs / 60) . 'min';
}

function beGetFaction(int $mapId): array {
    static $light = [1,3,4,5,6,12,13,14,19,21,22,25,28,31,33,35,37,43,48,51,53,55,59,65,68,71,75,77,78];
    static $dark  = [2,7,8,15,16,17,20,23,24,26,27,29,32,34,36,38,44,49,52,54,56,61,66,69,72,76,79];
    if (in_array($mapId, $light, true)) return ['key'=>'light',   'label'=>'LIGHT',   'icon'=>'fa-sun',    'css'=>'rgba(106,189,244,0.15)','color'=>'#6abdf4','border'=>'rgba(106,189,244,0.3)'];
    if (in_array($mapId, $dark,  true)) return ['key'=>'fury',    'label'=>'FURY',    'icon'=>'fa-fire',   'css'=>'rgba(244,106,106,0.15)','color'=>'#f46a6a','border'=>'rgba(244,106,106,0.3)'];
    return                                     ['key'=>'neutral', 'label'=>'NEUTRAL', 'icon'=>'fa-circle', 'css'=>'rgba(232,200,129,0.10)','color'=>'var(--text-gold)','border'=>'var(--border-gold)'];
}

// ─── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['boss_action'])) {
    if (!appVerifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $_SESSION['admin_error'] = 'Security token invalid. Refresh and retry.';
        header('Location: /admin?view=bosses_editor');
        exit;
    }

    $bossAction = (string)$_POST['boss_action'];

    if ($bossAction === 'add_boss') {
        $newMobId   = (int)($_POST['mob_id'] ?? 0);
        $newRespawn = (int)($_POST['respawn_seconds'] ?? 43200);
        $newNotes   = trim(substr((string)($_POST['notes'] ?? ''), 0, 200));
        $newSort    = (int)($_POST['sort_order'] ?? 0);
        $newActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($newMobId > 0 && $newRespawn > 0) {
            try {
                $stmt = $pdoAdmin->prepare(
                    "INSERT INTO PS_WebSite.dbo.Web_Bosses (MobID, RespawnSeconds, Notes, SortOrder, IsActive) VALUES (?,?,?,?,?)"
                );
                $stmt->execute([$newMobId, $newRespawn, $newNotes ?: null, $newSort, $newActive]);
                appAuditLog('bosses_editor_add', ['mob_id' => $newMobId, 'respawn' => $newRespawn, 'admin' => $username]);
                $_SESSION['admin_success'] = "Boss MobID <strong>$newMobId</strong> aggiunto.";
            } catch (Throwable $e) {
                $_SESSION['admin_error'] = 'Errore inserimento: ' . htmlspecialchars($e->getMessage());
            }
        } else {
            $_SESSION['admin_error'] = 'MobID e Respawn devono essere > 0.';
        }
        $qs = http_build_query(array_filter(['view'=>'bosses_editor','mob_id_filter'=>$_POST['back_mob_filter']??null,'status_filter'=>$_POST['back_status_filter']??null,'page'=>$_POST['back_page']??null]));
        header("Location: /admin?$qs");
        exit;
    }

    if ($bossAction === 'update_boss') {
        $bossId  = (int)($_POST['boss_id'] ?? 0);
        $mobId   = (int)($_POST['mob_id'] ?? 0);
        $respawn = (int)($_POST['respawn_seconds'] ?? 0);
        $notes   = trim(substr((string)($_POST['notes'] ?? ''), 0, 200));
        $sort    = (int)($_POST['sort_order'] ?? 0);
        $active  = isset($_POST['is_active']) ? 1 : 0;

        $newMapId = (isset($_POST['mobs_map_id']) && $_POST['mobs_map_id'] !== '')
            ? (int)$_POST['mobs_map_id'] : null;

        if ($bossId > 0 && $mobId > 0 && $respawn > 0) {
            try {
                $stmt = $pdoAdmin->prepare(
                    "UPDATE PS_WebSite.dbo.Web_Bosses SET MobID=?, RespawnSeconds=?, Notes=?, SortOrder=?, IsActive=? WHERE BossID=?"
                );
                $stmt->execute([$mobId, $respawn, $notes ?: null, $sort, $active, $bossId]);

                // Aggiorna MapID in PS_GameDefs.dbo.Mobs se specificato
                if ($newMapId !== null) {
                    $stmtMap = $pdoAdmin->prepare(
                        "UPDATE PS_GameDefs.dbo.Mobs SET MapID = ? WHERE MobID = ?"
                    );
                    $stmtMap->execute([$newMapId, $mobId]);
                }

                appAuditLog('bosses_editor_update', [
                    'boss_id'     => $bossId,
                    'mob_id'      => $mobId,
                    'new_map_id'  => $newMapId,
                    'admin'       => $username,
                ]);
                $_SESSION['admin_success'] = "Boss #$bossId aggiornato." . ($newMapId !== null ? " MapID → $newMapId" : '');
            } catch (Throwable $e) {
                $_SESSION['admin_error'] = 'Update fallito: ' . htmlspecialchars($e->getMessage());
            }
        }
        $qs = http_build_query(array_filter(['view'=>'bosses_editor','mob_id_filter'=>$_POST['back_mob_filter']??null,'status_filter'=>$_POST['back_status_filter']??null,'page'=>$_POST['back_page']??null]));
        header("Location: /admin?$qs");
        exit;
    }
}

// ─── Filtri GET ────────────────────────────────────────────────
$filterMobId   = isset($_GET['mob_id_filter']) && ctype_digit((string)$_GET['mob_id_filter']) ? (int)$_GET['mob_id_filter'] : 0;
$filterStatus  = in_array($_GET['status_filter'] ?? '', ['active','inactive']) ? $_GET['status_filter'] : 'all';
$filterPage    = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1,(int)$_GET['page']) : 1;

// ─── Check tabella ─────────────────────────────────────────────
$tableExists = false;
try {
    $pdoAdmin->query("SELECT TOP 1 BossID FROM PS_WebSite.dbo.Web_Bosses");
    $tableExists = true;
} catch (Throwable $e) {}

// ─── Query principale con JOIN cross-DB ───────────────────────
$bossList   = [];
$totalRows  = 0;
$totalPages = 1;

if ($tableExists) {
    $where  = "WHERE 1=1";
    $params = [];

    if ($filterMobId > 0) {
        $where   .= " AND b.MobID = ?";
        $params[] = $filterMobId;
    }
    if ($filterStatus === 'active') {
        $where .= " AND b.IsActive = 1";
    } elseif ($filterStatus === 'inactive') {
        $where .= " AND b.IsActive = 0";
    }

    try {
        $cntStmt = $pdoAdmin->prepare(
            "SELECT COUNT(*) AS Total
             FROM PS_WebSite.dbo.Web_Bosses b
             LEFT JOIN PS_GameDefs.dbo.Mobs m ON m.MobID = b.MobID
             $where"
        );
        $cntStmt->execute($params);
        $totalRows = (int)($cntStmt->fetchColumn() ?? 0);
    } catch (Throwable $e) {}

    $totalPages = max(1, (int)ceil($totalRows / $PER_PAGE));
    $filterPage = min($filterPage, $totalPages);
    $offset     = ($filterPage - 1) * $PER_PAGE;

    try {
        $dataStmt = $pdoAdmin->prepare(
            "SELECT b.BossID, b.MobID, b.RespawnSeconds, b.IsActive, b.SortOrder, b.Notes,
                    m.MobName, m.Level, m.MapID, mn.MapName
             FROM PS_WebSite.dbo.Web_Bosses b
             LEFT JOIN PS_GameDefs.dbo.Mobs m ON m.MobID = b.MobID
             LEFT JOIN PS_GameDefs.dbo.MapNames mn ON mn.MapID = m.MapID
             $where
             ORDER BY b.SortOrder ASC, b.BossID ASC
             OFFSET $offset ROWS FETCH NEXT $PER_PAGE ROWS ONLY"
        );
        $dataStmt->execute($params);
        $bossList = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

$filterQs = http_build_query(array_filter([
    'view'          => 'bosses_editor',
    'mob_id_filter' => $filterMobId  ?: null,
    'status_filter' => $filterStatus !== 'all' ? $filterStatus : null,
    'page'          => $filterPage > 1 ? $filterPage : null,
]));

$csrf = $_SESSION['csrf_token'] ?? '';
?>

<style>
.be-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; }
.be-title  { color:var(--text-gold); font-size:22px; margin:0 0 4px 0; }
.be-sub    { color:#555; font-size:13px; margin:0; }
.be-filter-bar {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
    border-radius: 8px; padding: 18px 22px; margin-bottom: 24px;
    display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;
}
.be-filter-field { display:flex; flex-direction:column; gap:6px; }
.be-filter-field label { color:#888; font-size:11px; text-transform:uppercase; letter-spacing:1px; }
.be-input {
    background: rgba(0,0,0,0.35); border: 1px solid #333; color: #e0e0e0;
    padding: 7px 10px; border-radius: 4px; font-size: 13px; box-sizing: border-box;
    font-family: 'Courier New', monospace;
}
.be-input:focus { outline:none; border-color: var(--text-gold); }
.be-select {
    background: rgba(0,0,0,0.35); border: 1px solid #333; color: #e0e0e0;
    padding: 7px 10px; border-radius: 4px; font-size: 13px; cursor: pointer;
}
.be-btn-filter {
    background: rgba(232,200,129,0.1); color: var(--text-gold);
    border: 1px solid rgba(232,200,129,0.3); padding: 7px 18px;
    border-radius: 4px; cursor: pointer; font-size: 13px; font-weight:600;
    letter-spacing:0.05em; transition: background 0.15s; white-space:nowrap;
    align-self: flex-end;
}
.be-btn-filter:hover { background: rgba(232,200,129,0.2); }
.be-btn-reset { background:transparent; border:1px solid #333; color:#666; padding:7px 14px; border-radius:4px; font-size:12px; cursor:pointer; align-self:flex-end; transition:border-color 0.15s; }
.be-btn-reset:hover { border-color:#666; color:#aaa; }

.be-stats-bar {
    display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
}
.be-stat-pill {
    background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 20px; padding: 5px 14px; font-size: 12px; color: #888;
}
.be-stat-pill strong { color: var(--text-gold); }
.be-stat-pill.active-pill { border-color: rgba(72,187,120,0.3); }
.be-stat-pill.active-pill strong { color: #48bb78; }
.be-stat-pill.inactive-pill { border-color: rgba(229,62,62,0.2); }
.be-stat-pill.inactive-pill strong { color: #e53e3e; }

.be-table-wrap { overflow-x: auto; margin-bottom: 20px; }
.be-table { width:100%; border-collapse:collapse; font-size:13px; }
.be-table thead th {
    background: rgba(232,200,129,0.07); color: var(--text-gold);
    font-family: var(--heading-font); font-size:10px; letter-spacing:0.08em;
    text-transform:uppercase; padding:10px 8px;
    border-bottom:1px solid rgba(232,200,129,0.15); white-space:nowrap;
}
.be-table tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.12s; }
.be-table tbody tr:hover { background:rgba(255,255,255,0.025); }
.be-table tbody td { padding:7px 8px; vertical-align:middle; }
.be-row-inactive { opacity: 0.5; }
.be-cell-id   { color:#555; font-size:11px; }
.be-cell-name { color:var(--text-gold); font-weight:600; font-size:13px; }
.be-cell-unknown { color:#555; font-style:italic; font-size:12px; }
.be-input-sm  { background:rgba(0,0,0,0.4); border:1px solid rgba(232,200,129,0.15); color:#ddd; padding:5px 7px; border-radius:3px; font-size:12px; font-family:'Courier New',monospace; box-sizing:border-box; }
.be-input-sm:focus { outline:none; border-color:var(--text-gold); }
.be-input-notes-sm { font-family:inherit; font-size:12px; }
.be-faction-badge {
    display:inline-block; padding:2px 7px; border-radius:3px;
    font-size:10px; font-weight:700; letter-spacing:0.04em; white-space:nowrap;
}
.be-status-on  { display:inline-flex;align-items:center;gap:5px;color:#48bb78;font-size:12px;font-weight:700; }
.be-status-off { display:inline-flex;align-items:center;gap:5px;color:#6b7280;font-size:12px; }
.be-status-dot { width:7px;height:7px;border-radius:50%;flex-shrink:0; }
.be-dot-on  { background:#48bb78; box-shadow:0 0 5px rgba(72,187,120,0.6); }
.be-dot-off { background:#374151; }
.be-btn-save {
    background:rgba(232,200,129,0.1); color:var(--text-gold);
    border:1px solid rgba(232,200,129,0.35); padding:5px 12px;
    border-radius:3px; cursor:pointer; font-size:11px; font-weight:700;
    letter-spacing:0.05em; white-space:nowrap; transition:background 0.15s;
}
.be-btn-save:hover { background:rgba(232,200,129,0.2); }

.be-pagination { display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin-bottom:30px; }
.be-page-link {
    background:rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.08);
    color:#888; padding:5px 11px; border-radius:3px; font-size:12px;
    text-decoration:none; transition:border-color 0.15s,color 0.15s;
}
.be-page-link:hover { border-color:rgba(232,200,129,0.3); color:var(--text-gold); }
.be-page-link.active { background:rgba(232,200,129,0.12); border-color:rgba(232,200,129,0.4); color:var(--text-gold); font-weight:700; cursor:default; }
.be-page-link.disabled { opacity:0.3; pointer-events:none; }

.be-add-section {
    background:rgba(0,0,0,0.25); border:1px solid rgba(232,200,129,0.1);
    border-radius:6px; padding:22px; margin-top:8px;
}
.be-add-title { font-family:var(--heading-font); font-size:15px; color:var(--text-gold); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:18px; }
.be-add-grid { display:grid; grid-template-columns:100px 150px 100px 1fr 90px auto; gap:10px; align-items:end; }
.be-field label { font-size:11px; color:#777; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.05em; }
.be-hint { font-size:11px; color:#555; margin-top:3px; font-style:italic; }
@media(max-width:900px){.be-add-grid{grid-template-columns:1fr 1fr;}}

.be-empty { padding:40px; text-align:center; color:#555; }
.be-empty i { font-size:32px; margin-bottom:12px; display:block; opacity:0.3; }
</style>

<!-- ── Header ── -->
<div class="be-header">
    <div>
        <h2 class="be-title"><i class="fas fa-dragon" style="opacity:0.8;margin-right:10px;"></i>Bosses Management</h2>
        <p class="be-sub">Gestisci i World Boss della pagina pubblica — i nomi vengono letti da <code style="color:#666;">PS_GameDefs.dbo.Mobs</code>.</p>
    </div>
</div>

<?php if (!$tableExists): ?>
<div style="padding:20px;color:#f46a6a;border:1px solid rgba(244,106,106,0.2);border-radius:6px;margin-bottom:20px;background:rgba(244,106,106,0.05);">
    <i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>
    Tabella <strong>Web_Bosses</strong> non trovata. Esegui prima lo script <code>sql/create_web_tables.sql</code> sul SQL Server.
</div>
<?php else: ?>

<!-- ── Stats bar ── -->
<?php
$statActive   = 0; $statInactive = 0;
try {
    $s = $pdoAdmin->query("SELECT IsActive, COUNT(*) AS n FROM PS_WebSite.dbo.Web_Bosses GROUP BY IsActive");
    while($r = $s->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$r['IsActive'] === 1) $statActive   = (int)$r['n'];
        else                           $statInactive  = (int)$r['n'];
    }
} catch(Throwable $e){}
$statTotal = $statActive + $statInactive;
?>
<div class="be-stats-bar">
    <div class="be-stat-pill">Totale: <strong><?php echo $statTotal; ?></strong> boss</div>
    <div class="be-stat-pill active-pill">Attivi: <strong><?php echo $statActive; ?></strong></div>
    <div class="be-stat-pill inactive-pill">Inattivi: <strong><?php echo $statInactive; ?></strong></div>
    <?php if ($totalRows !== $statTotal): ?>
    <div class="be-stat-pill">Filtro: <strong><?php echo $totalRows; ?></strong> risultati</div>
    <?php endif; ?>
</div>

<!-- ── Filtri ── -->
<form method="GET" action="/admin" class="be-filter-bar">
    <input type="hidden" name="view" value="bosses_editor">
    <div class="be-filter-field">
        <label>MobID</label>
        <input type="number" name="mob_id_filter" class="be-input" value="<?php echo $filterMobId ?: ''; ?>" min="1" placeholder="es. 2469" style="width:110px;">
    </div>
    <div class="be-filter-field">
        <label>Stato</label>
        <select name="status_filter" class="be-select">
            <option value="all"      <?php echo $filterStatus==='all'      ? 'selected' : ''; ?>>Tutti</option>
            <option value="active"   <?php echo $filterStatus==='active'   ? 'selected' : ''; ?>>Solo Attivi</option>
            <option value="inactive" <?php echo $filterStatus==='inactive' ? 'selected' : ''; ?>>Solo Inattivi</option>
        </select>
    </div>
    <button type="submit" class="be-btn-filter"><i class="fas fa-search"></i> Filtra</button>
    <?php if ($filterMobId || $filterStatus !== 'all'): ?>
    <a href="/admin?view=bosses_editor" class="be-btn-reset"><i class="fas fa-times"></i> Reset</a>
    <?php endif; ?>
</form>

<!-- ── Tabella principale ── -->
<div class="be-table-wrap">
<?php if (empty($bossList)): ?>
    <div class="be-empty">
        <i class="fas fa-search"></i>
        <?php echo $totalRows === 0 && $filterMobId === 0 && $filterStatus === 'all'
            ? 'Nessun boss configurato. Usa il form in basso per aggiungerne.'
            : 'Nessun risultato per i filtri selezionati.'; ?>
    </div>
<?php else: ?>
    <table class="be-table">
        <thead>
            <tr>
                <th style="width:45px">#</th>
                <th style="width:75px">MobID</th>
                <th style="min-width:120px">Nome DB</th>
                <th style="width:45px">Lvl</th>
                <th style="width:62px">MapID</th>
                <th style="min-width:100px">Mappa (DB)</th>
                <th style="width:85px">Fazione</th>
                <th style="width:120px">Respawn (sec)</th>
                <th style="width:55px">Sort</th>
                <th>Note</th>
                <th style="width:95px">Stato</th>
                <th style="width:70px">Salva</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bossList as $row):
            $bId      = (int)$row['BossID'];
            $mobId    = (int)$row['MobID'];
            $respawn  = (int)$row['RespawnSeconds'];
            $isActive = (int)$row['IsActive'];
            $sort     = (int)$row['SortOrder'];
            $notes    = (string)($row['Notes'] ?? '');

            $rawName  = $row['MobName'] ?? null;
            $name     = $rawName !== null
                ? htmlspecialchars(mb_convert_encoding($rawName, 'UTF-8', 'Windows-1251'))
                : null;
            $level    = isset($row['Level']) && (int)$row['Level'] > 0 ? (int)$row['Level'] : null;
            $mapId    = isset($row['MapID']) ? (int)$row['MapID'] : 0;
            $rawMap   = $row['MapName'] ?? null;
            $mapName  = $rawMap !== null
                ? htmlspecialchars(mb_convert_encoding($rawMap, 'UTF-8', 'Windows-1251'))
                : null;

            $faction  = beGetFaction($mapId);
            $fBadge   = "<span class='be-faction-badge' style='background:{$faction['css']};color:{$faction['color']};border:1px solid {$faction['border']};'>"
                      . "<i class='fas {$faction['icon']}'></i> {$faction['label']}</span>";

            $hint = beFormatRespawn($respawn);
        ?>
        <tr class="<?php echo $isActive ? '' : 'be-row-inactive'; ?>">
            <td class="be-cell-id"><?php echo $bId; ?></td>
            <form method="POST" action="/admin?<?php echo htmlspecialchars($filterQs); ?>">
            <input type="hidden" name="boss_action"       value="update_boss">
            <input type="hidden" name="boss_id"           value="<?php echo $bId; ?>">
            <input type="hidden" name="csrf_token"        value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="back_mob_filter"   value="<?php echo $filterMobId; ?>">
            <input type="hidden" name="back_status_filter" value="<?php echo htmlspecialchars($filterStatus); ?>">
            <input type="hidden" name="back_page"         value="<?php echo $filterPage; ?>">
            <td><input type="number" class="be-input-sm" name="mob_id" value="<?php echo $mobId; ?>" min="1" style="width:68px;"></td>
            <td>
                <?php if ($name !== null): ?>
                    <span class="be-cell-name"><?php echo $name; ?></span>
                <?php else: ?>
                    <span class="be-cell-unknown">Boss #<?php echo $mobId; ?></span>
                <?php endif; ?>
            </td>
            <td style="color:#aaa;font-size:12px;"><?php echo $level ?? '<span style="color:#555">?</span>'; ?></td>
            <td>
                <input type="number" class="be-input-sm" name="mobs_map_id"
                       value="<?php echo $mapId > 0 ? $mapId : ''; ?>"
                       min="0" placeholder="—" style="width:54px;"
                       title="Scrive su PS_GameDefs.dbo.Mobs.MapID">
            </td>
            <td style="color:#888;font-size:12px;white-space:nowrap;">
                <?php echo $mapName ? $mapName : "<span style='color:#444'>—</span>"; ?>
            </td>
            <td><?php echo $fBadge; ?></td>
            <td>
                <input type="number" class="be-input-sm" name="respawn_seconds" value="<?php echo $respawn; ?>" min="1" style="width:100px;">
                <div style="font-size:10px;color:#555;margin-top:2px;"><?php echo $hint; ?></div>
            </td>
            <td><input type="number" class="be-input-sm" name="sort_order" value="<?php echo $sort; ?>" style="width:48px;"></td>
            <td><input type="text" class="be-input-sm be-input-notes-sm" name="notes" value="<?php echo htmlspecialchars($notes); ?>" placeholder="—" maxlength="200" style="min-width:80px;width:100%;"></td>
            <td>
                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" <?php echo $isActive ? 'checked' : ''; ?> style="accent-color:var(--text-gold);width:15px;height:15px;">
                    <?php if ($isActive): ?>
                        <span class="be-status-on"><span class="be-status-dot be-dot-on"></span>ON</span>
                    <?php else: ?>
                        <span class="be-status-off"><span class="be-status-dot be-dot-off"></span>OFF</span>
                    <?php endif; ?>
                </label>
            </td>
            <td><button type="submit" class="be-btn-save"><i class="fas fa-save"></i> Salva</button></td>
            </form>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<!-- ── Paginazione ── -->
<?php if ($totalPages > 1):
    $baseUrl = '/admin?' . http_build_query(array_filter(['view'=>'bosses_editor','mob_id_filter'=>$filterMobId?:null,'status_filter'=>$filterStatus!=='all'?$filterStatus:null]));
?>
<div class="be-pagination">
    <a href="<?php echo $baseUrl . ($filterPage > 1 ? '&page='.($filterPage-1) : ''); ?>"
       class="be-page-link <?php echo $filterPage <= 1 ? 'disabled' : ''; ?>">
       <i class="fas fa-chevron-left"></i>
    </a>
    <?php
    $start = max(1, $filterPage - 3);
    $end   = min($totalPages, $filterPage + 3);
    if ($start > 1)          echo '<span class="be-page-link disabled">…</span>';
    for ($p = $start; $p <= $end; $p++) {
        $cls = ($p === $filterPage) ? 'active' : '';
        echo "<a href='{$baseUrl}&page={$p}' class='be-page-link {$cls}'>{$p}</a>";
    }
    if ($end < $totalPages)  echo '<span class="be-page-link disabled">…</span>';
    ?>
    <a href="<?php echo $baseUrl . '&page='. ($filterPage < $totalPages ? $filterPage+1 : $totalPages); ?>"
       class="be-page-link <?php echo $filterPage >= $totalPages ? 'disabled' : ''; ?>">
       <i class="fas fa-chevron-right"></i>
    </a>
    <span style="color:#555;font-size:12px;margin-left:6px;">
        Pagina <?php echo $filterPage; ?> di <?php echo $totalPages; ?> &nbsp;·&nbsp; <?php echo $totalRows; ?> totali
    </span>
</div>
<?php endif; ?>

<!-- ── Aggiungi Boss ── -->
<div class="be-add-section">
    <div class="be-add-title"><i class="fas fa-plus-circle" style="margin-right:8px;opacity:0.7;"></i>Aggiungi Boss</div>
    <form method="POST" action="/admin?<?php echo htmlspecialchars($filterQs); ?>">
        <input type="hidden" name="boss_action"        value="add_boss">
        <input type="hidden" name="csrf_token"         value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="back_mob_filter"    value="<?php echo $filterMobId; ?>">
        <input type="hidden" name="back_status_filter" value="<?php echo htmlspecialchars($filterStatus); ?>">
        <input type="hidden" name="back_page"          value="<?php echo $filterPage; ?>">
        <div class="be-add-grid">
            <div class="be-field">
                <label>MobID *</label>
                <input type="number" class="be-input" name="mob_id" placeholder="es. 2469" min="1" required style="width:90px;">
            </div>
            <div class="be-field">
                <label>Respawn (secondi) *</label>
                <input type="number" class="be-input" name="respawn_seconds" value="43200" min="1" required style="width:140px;">
                <div class="be-hint">43200 = 12h &nbsp;·&nbsp; 86400 = 1d &nbsp;·&nbsp; 604800 = 7d</div>
            </div>
            <div class="be-field">
                <label>Sort</label>
                <input type="number" class="be-input" name="sort_order" value="0" style="width:80px;">
            </div>
            <div class="be-field">
                <label>Note (opzionale)</label>
                <input type="text" class="be-input" name="notes" placeholder="es. nome boss, zona..." maxlength="200">
            </div>
            <div class="be-field">
                <label>Attivo</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;height:36px;">
                    <input type="checkbox" name="is_active" value="1" checked style="accent-color:var(--text-gold);width:15px;height:15px;">
                    <span style="font-size:13px;color:#48bb78;">ON</span>
                </label>
            </div>
            <div class="be-field" style="display:flex;align-items:flex-end;">
                <button type="submit" class="be-btn-save" style="padding:8px 18px;font-size:13px;">
                    <i class="fas fa-plus"></i> Aggiungi
                </button>
            </div>
        </div>
    </form>
</div>

<?php endif; ?>
