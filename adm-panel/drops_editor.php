<?php
// drops_editor.php — incluso da admin.php nel contesto autenticato
// Variabili disponibili: $pdoAdmin, $adminUserUID, $adminStatus, $username, $_SESSION

$PER_PAGE = 25;

// --- Changelog: auto-cleanup 24h + carica entries ---
$changelogCount   = 0;
$changelogEntries = [];
try {
    $pdoAdmin->exec("
        IF NOT EXISTS (SELECT * FROM PS_WebSite.sys.objects WHERE object_id = OBJECT_ID(N'PS_WebSite.dbo.Web_DropsEditLog') AND type = N'U')
        BEGIN
            CREATE TABLE PS_WebSite.dbo.Web_DropsEditLog (
                LogID INT IDENTITY(1,1) PRIMARY KEY,
                AdminUID INT NOT NULL, AdminID VARCHAR(32) NOT NULL,
                RowID INT NOT NULL, MobID INT NOT NULL, MobName NVARCHAR(100) NULL,
                ItemOrder TINYINT NOT NULL,
                OldGrade SMALLINT NULL, NewGrade SMALLINT NOT NULL,
                OldDropRate INT NULL, NewDropRate INT NOT NULL,
                ChangedAt DATETIME NOT NULL DEFAULT GETDATE()
            );
            CREATE INDEX IX_Web_DropsEditLog_AdminUID ON PS_WebSite.dbo.Web_DropsEditLog(AdminUID, ChangedAt DESC);
        END
    ");
    $pdoAdmin->prepare("DELETE FROM PS_WebSite.dbo.Web_DropsEditLog WHERE ChangedAt < DATEADD(hour, -24, GETDATE())")->execute();
    $clStmt = $pdoAdmin->prepare("
        SELECT TOP 200
            l.LogID, l.RowID, l.MobID, l.MobName, l.ItemOrder,
            l.OldGrade, l.NewGrade, l.OldDropRate, l.NewDropRate, l.ChangedAt,
            m.MapID
        FROM PS_WebSite.dbo.Web_DropsEditLog l
        LEFT JOIN PS_GameDefs.dbo.Mobs m ON m.MobID = l.MobID
        WHERE l.AdminUID = ?
        ORDER BY l.ChangedAt DESC
    ");
    $clStmt->execute([$adminUserUID]);
    $changelogEntries = $clStmt->fetchAll(PDO::FETCH_ASSOC);
    $changelogCount   = count($changelogEntries);
} catch (Throwable $e) {}

// --- Filtri GET ---
$filterMobId   = isset($_GET['mob_id'])   && ctype_digit((string) $_GET['mob_id'])   ? (int) $_GET['mob_id']   : 0;
$filterMobName = isset($_GET['mob_name']) ? trim((string) $_GET['mob_name'])          : '';
$filterMapId   = isset($_GET['map_id'])   && ctype_digit((string) $_GET['map_id'])   ? (int) $_GET['map_id']   : 0;
$filterPage    = isset($_GET['page'])     && ctype_digit((string) $_GET['page'])      ? max(1, (int) $_GET['page']) : 1;
$hasFilter     = $filterMobId > 0 || $filterMobName !== '' || $filterMapId > 0;

// --- Dropdown mappe ---
$mapOptions = [];
try {
    $mapStmt = $pdoAdmin->query("SELECT DISTINCT m.MapID, mn.MapName FROM PS_GameDefs.dbo.Mobs m LEFT JOIN PS_GameDefs.dbo.MapNames mn ON mn.MapID = m.MapID WHERE m.MapID IS NOT NULL ORDER BY m.MapID ASC");
    if ($mapStmt) $mapOptions = $mapStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Lookup MapID → MapName (da $mapOptions già caricata)
$mapNameLookup = [];
foreach ($mapOptions as $m) {
    $mapNameLookup[(int) $m['MapID']] = $m['MapName'] ?? ('Map ' . $m['MapID']);
}

// Raggruppa il changelog per mappa (solo mappe con almeno 1 entry)
$changelogByMap = [];
foreach ($changelogEntries as $cl) {
    $clMapId = (int) ($cl['MapID'] ?? 0);
    if (!isset($changelogByMap[$clMapId])) {
        $changelogByMap[$clMapId] = [
            'map_id'   => $clMapId,
            'map_name' => $clMapId > 0 ? ($mapNameLookup[$clMapId] ?? 'Map ' . $clMapId) : 'Unknown map',
            'entries'  => [],
        ];
    }
    $changelogByMap[$clMapId]['entries'][] = $cl;
}

// Trova nome mappa corrente per FAB export label
$currentMapName = '';
if ($filterMapId > 0) {
    foreach ($mapOptions as $m) {
        if ((int) $m['MapID'] === $filterMapId) {
            $currentMapName = $m['MapName'] ?? ('Map ' . $filterMapId);
            break;
        }
    }
    if ($currentMapName === '') $currentMapName = 'Map ' . $filterMapId;
}

// --- Query principale ---
$rows = []; $totalRows = 0;
if ($hasFilter) {
    $where = "WHERE 1=1"; $params = [];
    if ($filterMobId > 0)   { $where .= " AND mi.MobID = ?";      $params[] = $filterMobId; }
    if ($filterMobName !== ''){ $where .= " AND m.MobName LIKE ?"; $params[] = '%' . $filterMobName . '%'; }
    if ($filterMapId > 0)   { $where .= " AND m.MapID = ?";       $params[] = $filterMapId; }

    try {
        $cntStmt = $pdoAdmin->prepare("SELECT COUNT(*) AS Total FROM PS_GameDefs.dbo.MobItems mi JOIN PS_GameDefs.dbo.Mobs m ON m.MobID = mi.MobID $where");
        $cntStmt->execute($params);
        $totalRows = (int) ($cntStmt->fetchColumn() ?? 0);
    } catch (Throwable $e) {}

    $totalPages = max(1, (int) ceil($totalRows / $PER_PAGE));
    $filterPage = min($filterPage, $totalPages);
    $offset     = ($filterPage - 1) * $PER_PAGE;

    try {
        $dataStmt = $pdoAdmin->prepare("SELECT mi.RowID, mi.MobID, m.MobName, mi.ItemOrder, mi.Grade, mi.DropRate FROM PS_GameDefs.dbo.MobItems mi JOIN PS_GameDefs.dbo.Mobs m ON m.MobID = mi.MobID $where ORDER BY mi.MobID ASC, mi.ItemOrder ASC OFFSET $offset ROWS FETCH NEXT $PER_PAGE ROWS ONLY");
        $dataStmt->execute($params);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
} else {
    $totalPages = 1;
}

// Querystring base per redirect post-save
$filterQs = http_build_query(array_filter([
    'view'     => 'DROPS_EDITOR',
    'mob_id'   => $filterMobId   ?: null,
    'mob_name' => $filterMobName ?: null,
    'map_id'   => $filterMapId   ?: null,
    'page'     => $filterPage > 1 ? $filterPage : null,
]));
?>

<style>
/* ── FAB ────────────────────────────────────────────────────── */
#drops-fab {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 8000;
    display: flex;
    flex-direction: column-reverse;
    align-items: flex-end;
    gap: 12px;
}
#drops-fab .fab-sub {
    display: flex;
    align-items: center;
    gap: 10px;
    pointer-events: none;
    transform: translateY(20px) scale(0.85);
    opacity: 0;
    transition: transform 350ms cubic-bezier(0.34, 1.56, 0.64, 1),
                opacity 180ms ease;
}
#drops-fab.fab-open .fab-sub {
    pointer-events: all;
    transform: translateY(0) scale(1);
    opacity: 1;
}
#drops-fab.fab-open .fab-sub:nth-child(2) { transition-delay: 55ms; }
.fab-label {
    background: rgba(10,10,10,0.96);
    border: 1px solid rgba(232,200,129,0.15);
    color: #ccc;
    padding: 7px 16px;
    border-radius: 22px;
    font-size: 12px;
    white-space: nowrap;
    box-shadow: 0 4px 16px rgba(0,0,0,0.6);
    letter-spacing: 0.3px;
}
.fab-badge-pill {
    background: #e8c881;
    color: #000;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 6px;
}
.fab-icon-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(12,12,12,0.97);
    border: 1px solid rgba(232,200,129,0.2);
    color: #e8c881;
    font-size: 16px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(0,0,0,0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s, transform 0.15s;
    flex-shrink: 0;
}
.fab-icon-btn:hover { background: rgba(232,200,129,0.1); border-color: rgba(232,200,129,0.4); }
.fab-icon-btn.fab-disabled { opacity: 0.3; cursor: not-allowed; pointer-events: none; }
#fab-main {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: linear-gradient(145deg, #c9a03e, #7a5c1a);
    border: 1px solid rgba(232,200,129,0.35);
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    box-shadow: 0 8px 28px rgba(0,0,0,0.65), inset 0 1px 0 rgba(255,255,255,0.12);
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.38s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.2s;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}
#fab-main:hover { box-shadow: 0 12px 32px rgba(0,0,0,0.7); }
#fab-main .fi-dragon { transition: opacity 0.2s, transform 0.35s; }
#fab-main .fi-close  { position: absolute; opacity: 0; transform: rotate(-135deg); transition: opacity 0.2s, transform 0.35s; }
#drops-fab.fab-open #fab-main { transform: rotate(45deg); }
#drops-fab.fab-open #fab-main .fi-dragon { opacity: 0; }
#drops-fab.fab-open #fab-main .fi-close  { opacity: 1; transform: rotate(0deg); }
/* pulse ring when changes present */
<?php if ($changelogCount > 0): ?>
#fab-main::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid rgba(232,200,129,0.4);
    animation: fabPulse 2.5s ease-in-out infinite;
}
@keyframes fabPulse {
    0%,100% { transform: scale(1); opacity: 0.5; }
    50%      { transform: scale(1.15); opacity: 0.1; }
}
<?php endif; ?>

/* ── map hover tooltip (stessa logica di drops.php) ────────── */
.cl-map-hover {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: default;
}
.cl-map-hover:hover { z-index: 99; }
.cl-map-tooltip {
    visibility: hidden;
    opacity: 0;
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    background: #0a0a0a;
    border: 1px solid rgba(232,200,129,0.2);
    padding: 5px;
    border-radius: 6px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.9);
    z-index: 99999;
    transition: opacity 0.18s;
    pointer-events: none;
    width: max-content;
}
.cl-map-hover:hover .cl-map-tooltip { visibility: visible; opacity: 1; }
.cl-map-tooltip img { display: block; width: 220px; height: 220px; object-fit: cover; border-radius: 3px; }

/* ── collapsible map groups ─────────────────────────────────── */
.cl-group-header { cursor: pointer; user-select: none; }
.cl-group-header:hover { background: rgba(232,200,129,0.07) !important; }
.cl-chevron { transition: transform 0.28s ease; color: #555; font-size: 11px; margin-left: 6px; }
.cl-group.collapsed .cl-chevron { transform: rotate(-90deg); }
.cl-group-entries { overflow: hidden; max-height: 4000px; transition: max-height 0.3s ease; }
.cl-group.collapsed .cl-group-entries { max-height: 0; }

/* ── row highlight ──────────────────────────────────────────── */
.drop-row-highlight {
    background: rgba(232,200,129,0.12) !important;
    transition: background 1.6s ease;
}
</style>

<!-- ============================================================
     DROPS EDITOR VIEW
     ============================================================ -->
<div style="max-width: 1300px;">

    <!-- Header (senza badge — ora nel FAB) -->
    <div style="margin-bottom: 28px; display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h2 style="color: #e8c881; font-size: 22px; margin: 0 0 4px 0;">
                <i class="fas fa-dragon" style="margin-right: 10px; opacity: 0.8;"></i>Drops Editor
            </h2>
            <p style="color: #555; font-size: 13px; margin: 0;">
                Edit Grade and DropRate in <code style="color:#777;">PS_GameDefs.dbo.MobItems</code> — DropRate% = raw / 100000.
            </p>
        </div>
        <!-- Indicatore stato sessione (aggiornato da JS keep-alive) -->
        <span id="ka-status" style="font-size:11px; color:#333; margin-top:4px; transition:color 0.4s;"></span>
    </div>

    <!-- Filtri -->
    <form method="GET" action="/admin" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 22px 25px; margin-bottom: 28px;">
        <input type="hidden" name="view" value="drops_editor">
        <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
            <div style="display: flex; flex-direction: column; gap: 6px; flex: 0 0 120px;">
                <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">MobID</label>
                <input type="number" name="mob_id" class="char-input" value="<?php echo $filterMobId ?: ''; ?>" min="1" placeholder="es. 100" style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; height: 38px;">
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px; flex: 1 1 180px;">
                <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Mob Name</label>
                <input type="text" name="mob_name" class="char-input" value="<?php echo htmlspecialchars($filterMobName); ?>" placeholder="Partial search..." style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; height: 38px;">
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px; flex: 0 0 200px;">
                <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Map</label>
                <select name="map_id" class="char-input" style="background: rgba(0,0,0,0.5); border: 1px solid #333; color: #fff; height: 38px; padding: 0 10px; border-radius: 4px; appearance: none;">
                    <option value="">— All —</option>
                    <?php foreach ($mapOptions as $m): ?>
                    <option value="<?php echo (int) $m['MapID']; ?>" <?php echo $filterMapId === (int) $m['MapID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['MapName'] ?? ('Map ' . $m['MapID'])); ?> (<?php echo (int) $m['MapID']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-action" style="height: 38px; padding: 0 24px; white-space: nowrap;"><i class="fas fa-search" style="margin-right: 8px;"></i>SEARCH</button>
            <?php if ($hasFilter): ?>
            <a href="/admin?view=drops_editor" class="btn-action" style="height: 38px; padding: 0 18px; background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); color: #888; display: flex; align-items: center; text-decoration: none;"><i class="fas fa-times" style="margin-right: 6px;"></i>Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$hasFilter): ?>
    <div style="text-align: center; padding: 80px 0; color: #444;">
        <i class="fas fa-dragon" style="font-size: 48px; margin-bottom: 20px; opacity: 0.15;"></i><br>
        <p style="font-size: 15px;">Use the filters above to search mobs and edit their drop rates.</p>
    </div>

    <?php elseif ($totalRows === 0): ?>
    <div style="text-align: center; padding: 60px 0; color: #444;">
        <i class="fas fa-search" style="font-size: 36px; margin-bottom: 16px; opacity: 0.2;"></i><br>
        <p>No results for the selected filters.</p>
    </div>

    <?php else: ?>
    <!-- Tabella risultati -->
    <div style="background: rgba(0,0,0,0.2); border: 1px solid rgba(232,200,129,0.05); border-radius: 8px; overflow: hidden; margin-bottom: 28px;">
        <div style="display: grid; grid-template-columns: 80px 1fr 80px 120px 130px 100px 90px; gap: 0; padding: 12px 16px; background: rgba(232,200,129,0.04); border-bottom: 1px solid rgba(255,255,255,0.05);">
            <span style="color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">MobID</span>
            <span style="color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Mob Name</span>
            <span style="color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">Order</span>
            <span style="color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">Grade</span>
            <span style="color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">DropRate (raw)</span>
            <span style="color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">%</span>
            <span style="color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">Azione</span>
        </div>

        <?php foreach ($rows as $i => $row):
            $dropPct = round((int) $row['DropRate'] / 100000, 4);
            $rowBg = $i % 2 === 0 ? 'rgba(255,255,255,0.01)' : 'rgba(0,0,0,0.15)';
        ?>
        <form id="row-<?php echo (int) $row['RowID']; ?>" method="POST" action="/admin_actions"
            style="display: grid; grid-template-columns: 80px 1fr 80px 120px 130px 100px 90px; gap: 0; padding: 10px 16px; background: <?php echo $rowBg; ?>; border-bottom: 1px solid rgba(255,255,255,0.03); align-items: center; transition: background 1.6s ease;">
            <input type="hidden" name="action"                value="drops_update_row">
            <input type="hidden" name="row_id"                value="<?php echo (int) $row['RowID']; ?>">
            <input type="hidden" name="csrf_token"            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="return_view"           value="DROPS_EDITOR">
            <input type="hidden" name="drop_filter_mob_id"    value="<?php echo $filterMobId; ?>">
            <input type="hidden" name="drop_filter_mob_name"  value="<?php echo htmlspecialchars($filterMobName); ?>">
            <input type="hidden" name="drop_filter_map_id"    value="<?php echo $filterMapId; ?>">
            <input type="hidden" name="drop_filter_page"      value="<?php echo $filterPage; ?>">

            <span style="color: #aaa; font-size: 13px;"><?php echo (int) $row['MobID']; ?></span>
            <span style="color: #ccc; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars((string) $row['MobName']); ?>"><?php echo htmlspecialchars((string) $row['MobName']); ?></span>
            <span style="color: #777; font-size: 13px; text-align: center;"><?php echo (int) $row['ItemOrder']; ?></span>
            <div style="text-align: center;">
                <input type="number" name="grade" value="<?php echo (int) $row['Grade']; ?>" min="0"
                    class="dr-grade"
                    style="width: 90px; background: rgba(0,0,0,0.4); border: 1px solid #2a2a2a; color: #e8c881; text-align: center; border-radius: 4px; padding: 5px 4px; font-size: 13px; height: 32px;">
            </div>
            <div style="text-align: center;">
                <input type="number" name="drop_rate" value="<?php echo (int) $row['DropRate']; ?>" min="0"
                    class="drop-rate-input"
                    style="width: 110px; background: rgba(0,0,0,0.4); border: 1px solid #2a2a2a; color: #3498db; text-align: center; border-radius: 4px; padding: 5px 4px; font-size: 13px; height: 32px;">
            </div>
            <span class="pct-display" style="color: #555; font-size: 12px; text-align: center;"><?php echo $dropPct; ?>%</span>
            <div style="text-align: center;">
                <button type="submit" title="Save changes"
                    style="background: rgba(72,187,120,0.1); border: 1px solid rgba(72,187,120,0.25); color: #48bb78; border-radius: 4px; padding: 5px 14px; font-size: 11px; cursor: pointer; height: 32px; text-transform: uppercase; letter-spacing: 1px; white-space: nowrap;">
                    SAVE
                </button>
            </div>
        </form>
        <?php endforeach; ?>
    </div>

    <!-- Paginazione -->
    <?php if ($totalPages > 1):
        $baseParams = array_filter(['view' => 'drops_editor', 'mob_id' => $filterMobId ?: null, 'mob_name' => $filterMobName ?: null, 'map_id' => $filterMapId ?: null]);
    ?>
    <div style="display: flex; gap: 8px; align-items: center; justify-content: center; margin-bottom: 30px;">
        <?php if ($filterPage > 1): ?>
        <a href="/admin?<?php echo http_build_query($baseParams + ['page' => $filterPage - 1]); ?>" style="padding: 7px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: #888; border-radius: 4px; text-decoration: none; font-size: 13px;">&laquo; Prev</a>
        <?php endif; ?>
        <span style="color: #666; font-size: 13px;">Pagina <?php echo $filterPage; ?> di <?php echo $totalPages; ?> — <?php echo $totalRows; ?> righe</span>
        <?php if ($filterPage < $totalPages): ?>
        <a href="/admin?<?php echo http_build_query($baseParams + ['page' => $filterPage + 1]); ?>" style="padding: 7px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: #888; border-radius: 4px; text-decoration: none; font-size: 13px;">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Range Update -->
    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(231,76,60,0.15); border-radius: 8px; padding: 24px 28px;">
        <h4 style="color: #e74c3c; margin: 0 0 6px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;"><i class="fas fa-layer-group" style="margin-right: 8px;"></i>Range Update</h4>
        <p style="color: #555; font-size: 12px; margin: 0 0 20px 0;">Update Grade and DropRate for all mobs in a MobID range with a given ItemOrder.</p>
        <form method="POST" action="/admin_actions" id="form-range-update">
            <input type="hidden" name="action"               value="drops_update_range">
            <input type="hidden" name="csrf_token"           value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="return_view"          value="DROPS_EDITOR">
            <input type="hidden" name="drop_filter_mob_id"   value="<?php echo $filterMobId; ?>">
            <input type="hidden" name="drop_filter_mob_name" value="<?php echo htmlspecialchars($filterMobName); ?>">
            <input type="hidden" name="drop_filter_map_id"   value="<?php echo $filterMapId; ?>">
            <input type="hidden" name="drop_filter_page"     value="<?php echo $filterPage; ?>">
            <div style="display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end;">
                <div style="display: flex; flex-direction: column; gap: 6px;"><label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">MobID Min</label><input type="number" name="mob_id_min" min="1" required style="width: 110px; background: rgba(0,0,0,0.4); border: 1px solid #333; color: #fff; border-radius: 4px; padding: 7px 10px; font-size: 13px; height: 38px;"></div>
                <div style="display: flex; flex-direction: column; gap: 6px;"><label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">MobID Max</label><input type="number" name="mob_id_max" min="1" required style="width: 110px; background: rgba(0,0,0,0.4); border: 1px solid #333; color: #fff; border-radius: 4px; padding: 7px 10px; font-size: 13px; height: 38px;"></div>
                <div style="display: flex; flex-direction: column; gap: 6px;"><label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">ItemOrder (1-9)</label><input type="number" name="item_order" min="1" max="9" required style="width: 80px; background: rgba(0,0,0,0.4); border: 1px solid #333; color: #fff; border-radius: 4px; padding: 7px 10px; font-size: 13px; height: 38px;"></div>
                <div style="display: flex; flex-direction: column; gap: 6px;"><label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Grade</label><input type="number" name="grade" min="0" required style="width: 100px; background: rgba(0,0,0,0.4); border: 1px solid #333; color: #e8c881; border-radius: 4px; padding: 7px 10px; font-size: 13px; height: 38px;"></div>
                <div style="display: flex; flex-direction: column; gap: 6px;"><label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">DropRate (raw)</label><input type="number" name="drop_rate" min="0" required style="width: 120px; background: rgba(0,0,0,0.4); border: 1px solid #333; color: #3498db; border-radius: 4px; padding: 7px 10px; font-size: 13px; height: 38px;"></div>
                <button type="submit" style="background: rgba(231,76,60,0.12); border: 1px solid rgba(231,76,60,0.3); color: #e74c3c; border-radius: 4px; padding: 0 22px; font-size: 12px; cursor: pointer; height: 38px; text-transform: uppercase; letter-spacing: 1px; white-space: nowrap;"><i class="fas fa-bolt" style="margin-right: 6px;"></i>APPLY RANGE</button>
            </div>
        </form>
    </div>

</div><!-- /max-width -->

<!-- ============================================================
     FAB — Floating Action Button
     ============================================================ -->
<div id="drops-fab">

    <!-- Sub 1: Session Changes -->
    <div class="fab-sub">
        <span class="fab-label">Session Changes<?php if ($changelogCount > 0): ?><span class="fab-badge-pill"><?php echo $changelogCount; ?></span><?php endif; ?></span>
        <button class="fab-icon-btn" id="fab-btn-changelog" title="Session changelog">
            <i class="fas fa-history"></i>
        </button>
    </div>

    <!-- Sub 2: Export SQL (visibile solo con mappa selezionata) -->
    <?php if ($filterMapId > 0): ?>
    <div class="fab-sub">
        <span class="fab-label">Export SQL — <?php echo htmlspecialchars($currentMapName); ?></span>
        <a class="fab-icon-btn" href="/admin?view=drops_editor&export=sql&map_id=<?php echo $filterMapId; ?>" title="Download SQL for this map" id="fab-btn-export">
            <i class="fas fa-file-download"></i>
        </a>
    </div>
    <?php else: ?>
    <div class="fab-sub">
        <span class="fab-label" style="opacity:0.45;">Export SQL — select a map first</span>
        <span class="fab-icon-btn fab-disabled" title="Select a map from the filters first">
            <i class="fas fa-file-download"></i>
        </span>
    </div>
    <?php endif; ?>

    <!-- Main button -->
    <button id="fab-main" title="Drops Editor tools">
        <i class="fas fa-dragon fi-dragon"></i>
        <i class="fas fa-times fi-close"></i>
    </button>
</div>

<!-- ============================================================
     CHANGELOG MODAL
     ============================================================ -->
<div id="modal-changelog" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:#111; border:1px solid rgba(232,200,129,0.2); border-radius:12px; width:800px; max-width:96vw; max-height:calc(100vh - 40px); display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.85);">

        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid rgba(255,255,255,0.06);">
            <div>
                <h3 style="color:#e8c881; margin:0 0 4px 0; font-size:16px;"><i class="fas fa-history" style="margin-right:10px;"></i>Session Changes</h3>
                <p style="color:#555; font-size:12px; margin:0;">Last 24h — <?php echo $changelogCount; ?> change<?php echo $changelogCount !== 1 ? 's' : ''; ?>. Click a row to navigate to the mob. UNDO restores the previous value.</p>
            </div>
            <button id="btn-changelog-close" style="background:none; border:none; color:#555; font-size:20px; cursor:pointer; padding:4px 8px;">&#x2715;</button>
        </div>

        <div style="overflow-y:auto; flex:1; padding:0 24px 16px;">
            <?php if (empty($changelogByMap)): ?>
            <div style="text-align:center; padding:60px 0; color:#444;">
                <i class="fas fa-clipboard" style="font-size:36px; opacity:0.2; margin-bottom:16px; display:block;"></i>
                No changes in this session.
            </div>
            <?php else: ?>

            <?php foreach ($changelogByMap as $mapGroup):
                $gMapId   = $mapGroup['map_id'];
                $gMapName = $mapGroup['map_name'];
                $gEntries = $mapGroup['entries'];
                $gCount   = count($gEntries);
            ?>
            <!-- Gruppo mappa -->
            <div class="cl-group" style="margin-top:20px;">
                <!-- Header mappa con tooltip immagine (cliccabile per collapse) -->
                <div class="cl-group-header" style="display:flex; align-items:center; justify-content:space-between; padding:8px 10px; background:rgba(232,200,129,0.04); border:1px solid rgba(232,200,129,0.08); border-radius:6px; margin-bottom:4px; transition:background 0.15s;">
                    <div class="cl-map-hover">
                        <i class="fas fa-map-marker-alt" style="color:#e8c881; font-size:12px;"></i>
                        <span style="color:#e8c881; font-size:13px; font-weight:500;"><?php echo htmlspecialchars($gMapName); ?></span>
                        <?php if ($gMapId > 0): ?>
                        <span style="color:#444; font-size:11px;">(MapID <?php echo $gMapId; ?>)</span>
                        <?php endif; ?>
                        <?php if ($gMapId > 0): ?>
                        <div class="cl-map-tooltip">
                            <img src="assets/minimaps/<?php echo $gMapId; ?>.webp" alt="<?php echo htmlspecialchars($gMapName); ?>" class="js-hide-onerror">
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="color:#555; font-size:11px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:2px 10px;"><?php echo $gCount; ?> change<?php echo $gCount !== 1 ? 's' : ''; ?></span>
                        <i class="fas fa-chevron-down cl-chevron"></i>
                    </div>
                </div>

                <!-- Righe del gruppo (collapsible) -->
                <div class="cl-group-entries">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">Time</th>
                            <th style="text-align:left; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">MobID</th>
                            <th style="text-align:left; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">Mob Name</th>
                            <th style="text-align:center; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">Order</th>
                            <th style="text-align:center; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">Grade</th>
                            <th style="text-align:center; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">DropRate</th>
                            <th style="border-bottom:1px solid rgba(255,255,255,0.04);"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gEntries as $cl):
                        $changedTime  = date('H:i:s', strtotime($cl['ChangedAt']));
                        $gradeChanged = $cl['OldGrade'] !== null && (int)$cl['OldGrade'] !== (int)$cl['NewGrade'];
                        $drChanged    = $cl['OldDropRate'] !== null && (int)$cl['OldDropRate'] !== (int)$cl['NewDropRate'];
                        $canUndo      = $cl['OldGrade'] !== null && $cl['OldDropRate'] !== null;
                        $navUrl       = '/admin?view=drops_editor&mob_id=' . (int)$cl['MobID'];
                        if ($gMapId > 0) $navUrl .= '&map_id=' . $gMapId;
                        $navUrl .= '#row-' . (int)$cl['RowID'];
                    ?>
                    <tr class="cl-row" data-nav="<?php echo htmlspecialchars($navUrl); ?>"
                        style="border-bottom:1px solid rgba(255,255,255,0.02); cursor:pointer; transition:background 0.15s;">
                        <td style="padding:8px 10px; color:#555; font-size:12px;"><?php echo $changedTime; ?></td>
                        <td style="padding:8px 10px; color:#aaa; font-size:13px;"><?php echo (int)$cl['MobID']; ?></td>
                        <td style="padding:8px 10px; color:#ccc; font-size:12px; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars((string)$cl['MobName']); ?>"><?php echo htmlspecialchars((string)$cl['MobName']); ?></td>
                        <td style="padding:8px 10px; color:#666; font-size:12px; text-align:center;"><?php echo (int)$cl['ItemOrder']; ?></td>
                        <td style="padding:8px 10px; text-align:center; font-size:12px;">
                            <?php if ($gradeChanged): ?><span style="color:#555;"><?php echo (int)$cl['OldGrade']; ?></span><span style="color:#444; margin:0 4px;">→</span><span style="color:#e8c881;"><?php echo (int)$cl['NewGrade']; ?></span>
                            <?php else: ?><span style="color:#444;"><?php echo (int)$cl['NewGrade']; ?></span><?php endif; ?>
                        </td>
                        <td style="padding:8px 10px; text-align:center; font-size:12px;">
                            <?php if ($drChanged): ?><span style="color:#555;"><?php echo (int)$cl['OldDropRate']; ?></span><span style="color:#444; margin:0 4px;">→</span><span style="color:#3498db;"><?php echo (int)$cl['NewDropRate']; ?></span>
                            <?php else: ?><span style="color:#444;"><?php echo (int)$cl['NewDropRate']; ?></span><?php endif; ?>
                        </td>
                        <td style="padding:8px 10px; text-align:right;" onclick="event.stopPropagation();">
                            <?php if ($canUndo): ?>
                            <form method="POST" action="/admin_actions" style="display:inline;">
                                <input type="hidden" name="action"               value="drops_undo_row">
                                <input type="hidden" name="log_id"               value="<?php echo (int)$cl['LogID']; ?>">
                                <input type="hidden" name="csrf_token"           value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="return_view"          value="DROPS_EDITOR">
                                <input type="hidden" name="drop_filter_mob_id"   value="<?php echo $filterMobId; ?>">
                                <input type="hidden" name="drop_filter_mob_name" value="<?php echo htmlspecialchars($filterMobName); ?>">
                                <input type="hidden" name="drop_filter_map_id"   value="<?php echo $filterMapId; ?>">
                                <input type="hidden" name="drop_filter_page"     value="<?php echo $filterPage; ?>">
                                <button type="submit" onclick="return confirm('Undo change on MobID <?php echo (int)$cl['MobID']; ?>?');"
                                    style="background:rgba(231,76,60,0.08); border:1px solid rgba(231,76,60,0.2); color:#e74c3c; border-radius:4px; padding:3px 10px; font-size:11px; cursor:pointer; white-space:nowrap;">UNDO</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div><!-- /cl-group-entries -->
            </div><!-- /cl-group -->
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="padding:16px 24px; border-top:1px solid rgba(255,255,255,0.06); display:flex; justify-content:flex-end;">
            <?php if ($changelogCount > 0): ?>
            <form method="POST" action="/admin_actions">
                <input type="hidden" name="action"               value="drops_clear_log">
                <input type="hidden" name="csrf_token"           value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="return_view"          value="DROPS_EDITOR">
                <input type="hidden" name="drop_filter_mob_id"   value="<?php echo $filterMobId; ?>">
                <input type="hidden" name="drop_filter_mob_name" value="<?php echo htmlspecialchars($filterMobName); ?>">
                <input type="hidden" name="drop_filter_map_id"   value="<?php echo $filterMapId; ?>">
                <button type="submit" onclick="return confirm('Clear all session changes?');"
                    style="background:rgba(231,76,60,0.1); border:1px solid rgba(231,76,60,0.25); color:#e74c3c; border-radius:6px; padding:8px 20px; font-size:12px; cursor:pointer; text-transform:uppercase; letter-spacing:1px;">
                    <i class="fas fa-trash-alt" style="margin-right:6px;"></i>Clear All
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    /* ── Modal ─────────────────────────────────────────────── */
    var modal = document.getElementById('modal-changelog');
    var closeBtn = document.getElementById('btn-changelog-close');
    document.body.appendChild(modal);

    function openModal()  { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    function closeModal() { modal.style.display = 'none';  document.body.style.overflow = ''; }

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeModal(); closeFab(); } });

    /* ── FAB ────────────────────────────────────────────────── */
    var fab     = document.getElementById('drops-fab');
    var fabMain = document.getElementById('fab-main');
    var fabCl   = document.getElementById('fab-btn-changelog');

    // Sposta FAB e modal su <body> per evitare problemi di position:fixed
    // con container che hanno overflow:hidden o transform
    document.body.appendChild(fab);

    function openFab()  { fab.classList.add('fab-open'); }
    function closeFab() { fab.classList.remove('fab-open'); }
    function toggleFab(){ fab.classList.contains('fab-open') ? closeFab() : openFab(); }

    fabMain.addEventListener('click', toggleFab);
    if (fabCl) {
        fabCl.addEventListener('click', function () { closeFab(); openModal(); });
    }

    // Chiudi FAB cliccando fuori
    document.addEventListener('click', function (e) {
        if (!fab.contains(e.target)) closeFab();
    });

    // Auto-apri modal se rate-limit
    <?php if (!empty($_SESSION['admin_error']) && strpos($_SESSION['admin_error'], 'Rate limit') !== false): ?>
    openFab(); setTimeout(openModal, 300);
    <?php endif; ?>

    /* ── Collapsible map groups ─────────────────────────────── */
    document.querySelectorAll('.cl-group-header').forEach(function (header) {
        header.addEventListener('click', function () {
            var group = this.closest('.cl-group');
            if (group) group.classList.toggle('collapsed');
        });
    });

    /* ── Navigate-to-row dal changelog ─────────────────────── */
    document.querySelectorAll('.cl-row').forEach(function (tr) {
        tr.addEventListener('mouseenter', function () { this.style.background = 'rgba(232,200,129,0.05)'; });
        tr.addEventListener('mouseleave', function () { this.style.background = ''; });
        tr.addEventListener('click', function () {
            var url = this.getAttribute('data-nav');
            if (url) { closeModal(); window.location.href = url; }
        });
    });

    /* ── Highlight riga dopo navigazione da changelog ──────── */
    var hash = window.location.hash;
    if (hash && hash.startsWith('#row-')) {
        var target = document.querySelector(hash);
        if (target) {
            setTimeout(function () {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.classList.add('drop-row-highlight');
                setTimeout(function () { target.classList.remove('drop-row-highlight'); }, 2000);
            }, 200);
        }
    }

    /* ── Keep-alive: sessione + CSRF sync ogni 8 min ──────────
       OWASP: per-session token è accettabile. Il keep-alive
       mantiene viva la sessione PHP (gc_maxlifetime 7200s) e
       sincronizza il token su tutti i form della pagina.
    ─────────────────────────────────────────────────────────── */
    var kaStatus = document.getElementById('ka-status');

    function dropsKeepAlive() {
        fetch('/admin?csrf_refresh=1', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && data.csrf_token) {
                    // Aggiorna TUTTI i [name="csrf_token"] sulla pagina e nel modal
                    document.querySelectorAll('[name="csrf_token"]').forEach(function(inp) {
                        inp.value = data.csrf_token;
                    });
                    if (kaStatus) {
                        kaStatus.textContent = '● session active';
                        kaStatus.style.color = '#48bb78';
                        setTimeout(function() {
                            kaStatus.style.color = '#333';
                            setTimeout(function() { kaStatus.textContent = ''; }, 600);
                        }, 2500);
                    }
                }
            })
            .catch(function() {
                if (kaStatus) {
                    kaStatus.textContent = '⚠ session expired — reload the page';
                    kaStatus.style.color = '#e74c3c';
                }
            });
    }

    // Prima chiamata dopo 8 min, poi ogni 8 min
    setInterval(dropsKeepAlive, 8 * 60 * 1000);

    /* ── Live update % ─────────────────────────────────────── */
    document.querySelectorAll('.drop-rate-input').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var pct = this.closest('form').querySelector('.pct-display');
            if (pct) pct.textContent = (parseFloat(this.value || 0) / 100000).toFixed(4) + '%';
        });
    });

    /* ── Range update confirm ───────────────────────────────── */
    var rangeForm = document.getElementById('form-range-update');
    if (rangeForm) {
        rangeForm.addEventListener('submit', function (e) {
            var min = this.querySelector('[name=mob_id_min]').value;
            var max = this.querySelector('[name=mob_id_max]').value;
            var ord = this.querySelector('[name=item_order]').value;
            if (!confirm('Update MobID ' + min + '-' + max + ' (ItemOrder ' + ord + ')?\nThis action cannot be undone.')) e.preventDefault();
        });
    }
})();
</script>
