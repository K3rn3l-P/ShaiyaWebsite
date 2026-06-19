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
                OldGrade SMALLINT NULL, NewGrade SMALLINT NULL,
                OldDropRate INT NULL, NewDropRate INT NULL,
                ChangedAt DATETIME NOT NULL DEFAULT GETDATE()
            );
            CREATE INDEX IX_Web_DropsEditLog_AdminUID ON PS_WebSite.dbo.Web_DropsEditLog(AdminUID, ChangedAt DESC);
        END
    ");
    // Migrazione: rende nullable NewGrade/NewDropRate se la tabella esisteva con NOT NULL
    try {
        $pdoAdmin->exec("
            IF EXISTS (SELECT * FROM PS_WebSite.sys.objects WHERE object_id = OBJECT_ID(N'PS_WebSite.dbo.Web_DropsEditLog') AND type = N'U')
            BEGIN
                IF EXISTS (SELECT 1 FROM PS_WebSite.sys.columns c JOIN PS_WebSite.sys.objects o ON o.object_id=c.object_id WHERE o.name='Web_DropsEditLog' AND c.name='NewGrade' AND c.is_nullable=0)
                    ALTER TABLE PS_WebSite.dbo.Web_DropsEditLog ALTER COLUMN NewGrade SMALLINT NULL;
                IF EXISTS (SELECT 1 FROM PS_WebSite.sys.columns c JOIN PS_WebSite.sys.objects o ON o.object_id=c.object_id WHERE o.name='Web_DropsEditLog' AND c.name='NewDropRate' AND c.is_nullable=0)
                    ALTER TABLE PS_WebSite.dbo.Web_DropsEditLog ALTER COLUMN NewDropRate INT NULL;
            END
        ");
    } catch (Throwable $e) {}
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
$filterMapId   = isset($_GET['map_id'])   && ctype_digit((string) $_GET['map_id'])   ? (int) $_GET['map_id']   : null;
$filterPage    = isset($_GET['page'])     && ctype_digit((string) $_GET['page'])      ? max(1, (int) $_GET['page']) : 1;
$filterBossOnly = isset($_GET['boss_only']) && $_GET['boss_only'] === '1';
$hasFilter     = $filterMobId > 0 || $filterMobName !== '' || $filterMapId !== null || $filterBossOnly;

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

// --- Query mob-list (sempre aggregata per mob) ---
$rows = []; $totalRows = 0;

// mob_id apre il modale automaticamente (via JS), non la vista inline
$mcAutoOpen = (int)($_GET['mc_open'] ?? 0);
if ($mcAutoOpen === 0 && $filterMobId > 0) $mcAutoOpen = $filterMobId;

// Carica boss MobIDs (query separata su PS_WebSite — nessun cross-DB nel GROUP BY)
$bossMobIds = [];
try {
    $bStmt = $pdoAdmin->query("SELECT MobID FROM PS_WebSite.dbo.Web_Bosses WHERE IsActive = 1");
    if ($bStmt) $bossMobIds = array_flip(array_column($bStmt->fetchAll(PDO::FETCH_ASSOC), 'MobID'));
} catch (Throwable $e) {}

if ($hasFilter) {
    // ── Mob-list: COUNT + DATA aggregati per mob ─────────────────
    $where = "WHERE m.Level > 0"; $params = [];
    if ($filterMobId > 0)     { $where .= " AND m.MobID = ?";      $params[] = $filterMobId; }
    if ($filterMobName !== '') { $where .= " AND m.MobName LIKE ?"; $params[] = '%' . $filterMobName . '%'; }
    if ($filterMapId !== null) { $where .= " AND m.MapID = ?";      $params[] = $filterMapId; }
    if ($filterBossOnly && !empty($bossMobIds)) {
        $bIds = implode(',', array_map('intval', array_keys($bossMobIds)));
        $where .= " AND m.MobID IN ($bIds)";
    } elseif ($filterBossOnly) {
        // Nessun boss registrato → risultato vuoto forzato
        $where .= " AND 1=0";
    }

    try {
        $cntStmt = $pdoAdmin->prepare("SELECT COUNT(*) FROM PS_GameDefs.dbo.Mobs m $where");
        $cntStmt->execute($params);
        $totalRows = (int)($cntStmt->fetchColumn() ?? 0);
    } catch (Throwable $e) {}

    $totalPages = max(1, (int) ceil($totalRows / $PER_PAGE));
    $filterPage = min($filterPage, $totalPages);
    $offset     = ($filterPage - 1) * $PER_PAGE;

    try {
        $dataStmt = $pdoAdmin->prepare("
            SELECT m.MobID, m.MobName, m.Level, m.MapID, mn.MapName,
                SUM(CASE WHEN mi.Grade > 0 THEN 1 ELSE 0 END) AS FilledSlots,
                SUM(CASE WHEN mi.ItemOrder BETWEEN 1 AND 6 AND mi.Grade > 0
                    THEN CAST(mi.DropRate AS FLOAT) ELSE 0 END) AS TotalPct16
            FROM PS_GameDefs.dbo.Mobs m
            LEFT JOIN PS_GameDefs.dbo.MapNames mn ON mn.MapID = m.MapID
            LEFT JOIN PS_GameDefs.dbo.MobItems mi ON mi.MobID = m.MobID
            $where
            GROUP BY m.MobID, m.MobName, m.Level, m.MapID, mn.MapName
            ORDER BY m.MobID ASC
            OFFSET $offset ROWS FETCH NEXT $PER_PAGE ROWS ONLY
        ");
        $dataStmt->execute($params);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
} else {
    $totalPages = 1;
}

// Batch query slot details per tooltip hover (tutti i mob della pagina corrente)
$slotsByMob = [];
if (!empty($rows)) {
    $pageIds = array_map('intval', array_column($rows, 'MobID'));
    $inList  = implode(',', $pageIds);
    try {
        $slotStmt = $pdoAdmin->query("
            SELECT mi.MobID, mi.ItemOrder, mi.Grade, mi.DropRate, i.ItemName
            FROM PS_GameDefs.dbo.MobItems mi
            OUTER APPLY (SELECT TOP 1 ItemName FROM PS_GameDefs.dbo.Items WHERE Grade = mi.Grade) i
            WHERE mi.MobID IN ($inList) AND mi.Grade > 0
            ORDER BY mi.MobID ASC, mi.ItemOrder ASC
        ");
        if ($slotStmt) {
            foreach ($slotStmt->fetchAll(PDO::FETCH_ASSOC) as $sr) {
                $slotsByMob[(int)$sr['MobID']][] = $sr;
            }
        }
    } catch (Throwable $e) {}
}

// Querystring base per redirect post-save
$_qsArr = ['view' => 'DROPS_EDITOR'];
if ($filterMobId > 0)      $_qsArr['mob_id']    = $filterMobId;
if ($filterMobName !== '')  $_qsArr['mob_name']  = $filterMobName;
if ($filterMapId !== null)  $_qsArr['map_id']    = $filterMapId;
if ($filterBossOnly)        $_qsArr['boss_only'] = '1';
if ($filterPage > 1)        $_qsArr['page']      = $filterPage;
$filterQs = http_build_query($_qsArr);
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

/* ── Mob-centric view ───────────────────────────────────────── */
.mc-header {
    background: rgba(232,200,129,0.05);
    border: 1px solid rgba(232,200,129,0.12);
    border-radius: 10px;
    padding: 18px 24px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.mc-mob-name { color: #e8c881; font-size: 18px; font-weight: 600; }
.mc-mob-meta { color: #555; font-size: 12px; margin-top: 4px; }
.mc-mob-attrib { display: inline-flex; align-items: center; gap: 4px; }
.mc-slot-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.mc-slot-table th {
    color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;
    padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.05);
    text-align: left; font-weight: 400;
}
.mc-slot-table td { padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; }
.mc-slot-table tr:last-child td { border-bottom: none; }
.mc-slot-table tr.mc-row-filled:hover td { background: rgba(255,255,255,0.02); }
.mc-slot-table tr.mc-row-empty td { opacity: 0.4; }
.mc-slot-num { color: #444; font-size: 12px; font-weight: 600; text-align: center; }
.mc-slot-num.mc-slot-high { color: #3498db; }
.mc-item-name { color: #ccc; font-size: 13px; }
.mc-item-grade { color: #555; font-size: 11px; margin-left: 6px; }
.mc-item-level { color: #444; font-size: 11px; margin-left: 4px; }
.mc-rate-wrap { display: flex; align-items: center; gap: 8px; }
.mc-rate-input {
    width: 110px; background: rgba(0,0,0,0.4); border: 1px solid #2a2a2a;
    color: #3498db; text-align: center; border-radius: 4px;
    padding: 5px 6px; font-size: 13px; height: 32px;
}
.mc-rate-input.mc-rate-high { border-color: rgba(52,152,219,0.3); }
.mc-pct-display { color: #555; font-size: 12px; min-width: 70px; }
.mc-btn-save {
    background: rgba(72,187,120,0.1); border: 1px solid rgba(72,187,120,0.25);
    color: #48bb78; border-radius: 4px; padding: 5px 14px; font-size: 11px;
    cursor: pointer; height: 32px; text-transform: uppercase; letter-spacing: 1px;
}
.mc-btn-save:hover { background: rgba(72,187,120,0.2); }
.mc-btn-remove {
    background: rgba(231,76,60,0.08); border: 1px solid rgba(231,76,60,0.2);
    color: #e74c3c; border-radius: 4px; padding: 5px 10px; font-size: 13px;
    cursor: pointer; height: 32px; line-height: 1;
}
.mc-btn-remove:hover { background: rgba(231,76,60,0.18); }
.mc-btn-add {
    background: rgba(52,152,219,0.08); border: 1px dashed rgba(52,152,219,0.25);
    color: #3498db; border-radius: 4px; padding: 6px 18px; font-size: 12px;
    cursor: pointer; letter-spacing: 0.5px;
}
.mc-btn-add:hover { background: rgba(52,152,219,0.15); border-style: solid; }
.mc-btn-help {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
    color: #888; border-radius: 6px; padding: 7px 16px; font-size: 12px;
    cursor: pointer; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;
}
.mc-btn-help:hover { background: rgba(255,255,255,0.08); color: #aaa; }
.mc-slot-type-badge {
    font-size: 10px; color: #3498db; background: rgba(52,152,219,0.1);
    border: 1px solid rgba(52,152,219,0.2); border-radius: 3px;
    padding: 1px 5px; margin-left: 6px; vertical-align: middle;
}
/* ── Slot hover tooltip trigger ─────────────────────────── */
.slot-tip-trigger { cursor: help; border-bottom: 1px dotted rgba(72,187,120,0.5); }
/* Boss row in mob-list */
.mob-row-boss {
    background: rgba(232,200,129,0.06) !important;
    border-left: 3px solid rgba(232,200,129,0.45) !important;
}
.mob-boss-crown {
    color: #e8c881; font-size: 10px; margin-right: 5px; vertical-align: middle; opacity: 0.85;
}
.mob-boss-badge {
    display: inline-block; font-size: 9px; color: #e8c881;
    background: rgba(232,200,129,0.1); border: 1px solid rgba(232,200,129,0.2);
    border-radius: 3px; padding: 1px 6px; margin-left: 6px; vertical-align: middle;
    text-transform: uppercase; letter-spacing: 0.5px;
}
/* Boss filter active */
.boss-filter-btn { display: flex; align-items: center; gap: 6px; height: 38px; padding: 0 14px;
    border-radius: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;
    white-space: nowrap; cursor: pointer; text-decoration: none; border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04); color: #555; transition: background 0.15s, color 0.15s; }
.boss-filter-btn:hover { background: rgba(232,200,129,0.08); color: #999; }
.boss-filter-btn.active { background: rgba(232,200,129,0.12); border-color: rgba(232,200,129,0.35); color: #e8c881; }
/* Modal generici */
.dr-modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    z-index: 9998; background: rgba(0,0,0,0.75); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
}
.dr-modal-overlay.active { display: flex; }
.dr-modal-box {
    background: #111; border: 1px solid rgba(232,200,129,0.2);
    border-radius: 12px; width: 560px; max-width: 96vw;
    max-height: calc(100vh - 40px); display: flex; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.85);
}
.dr-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.06);
}
.dr-modal-body { overflow-y: auto; flex: 1; padding: 20px 24px; }
.dr-modal-close {
    background: none; border: none; color: #555; font-size: 20px;
    cursor: pointer; padding: 4px 8px; line-height: 1;
}
.dr-modal-close:hover { color: #aaa; }
/* Search results in add-item modal */
.ai-search-input {
    width: 100%; background: rgba(0,0,0,0.4); border: 1px solid #333;
    color: #fff; border-radius: 6px; padding: 10px 14px; font-size: 14px;
    box-sizing: border-box; margin-bottom: 12px;
}
.ai-search-input:focus { outline: none; border-color: rgba(52,152,219,0.5); }
.ai-results { border: 1px solid rgba(255,255,255,0.06); border-radius: 6px; overflow: hidden; min-height: 40px; }
.ai-result-item {
    padding: 10px 14px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.04);
    display: flex; align-items: center; justify-content: space-between;
}
.ai-result-item:last-child { border-bottom: none; }
.ai-result-item:hover { background: rgba(52,152,219,0.08); }
.ai-result-item.selected { background: rgba(52,152,219,0.15); border-left: 2px solid #3498db; }
.ai-result-name { color: #ccc; font-size: 13px; }
.ai-result-meta { color: #555; font-size: 11px; }
.ai-no-results { padding: 20px; text-align: center; color: #444; font-size: 13px; }
.ai-selected-banner {
    background: rgba(52,152,219,0.08); border: 1px solid rgba(52,152,219,0.2);
    border-radius: 6px; padding: 10px 14px; margin-bottom: 14px;
    color: #3498db; font-size: 13px; display: none;
}
.ai-selected-banner.visible { display: block; }
.ai-rate-row { display: flex; align-items: center; gap: 12px; margin-top: 16px; }
.ai-rate-label { color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; white-space: nowrap; }
.ai-rate-input {
    flex: 1; background: rgba(0,0,0,0.4); border: 1px solid #333;
    color: #3498db; border-radius: 6px; padding: 8px 12px; font-size: 14px; text-align: center;
}
.ai-rate-pct { color: #555; font-size: 13px; white-space: nowrap; min-width: 70px; }
.ai-save-btn {
    width: 100%; margin-top: 16px; padding: 11px; background: rgba(72,187,120,0.12);
    border: 1px solid rgba(72,187,120,0.3); color: #48bb78; border-radius: 6px;
    font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
}
.ai-save-btn:hover { background: rgba(72,187,120,0.22); }
.ai-save-btn:disabled { opacity: 0.4; cursor: not-allowed; }
/* Help modal */
.help-table { width: 100%; border-collapse: collapse; margin: 12px 0; }
.help-table th { color: #888; font-size: 11px; text-transform: uppercase; padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.07); text-align: left; font-weight: 400; }
.help-table td { color: #bbb; font-size: 13px; padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: top; }
.help-table tr:last-child td { border-bottom: none; }
.help-code { background: rgba(255,255,255,0.05); border-radius: 3px; padding: 1px 5px; color: #e8c881; font-family: monospace; font-size: 12px; }
.help-step { display: flex; gap: 10px; margin-bottom: 8px; }
.help-step-num { color: #e8c881; font-weight: 700; min-width: 18px; }
.help-section-title { color: #e8c881; font-size: 13px; font-weight: 600; margin: 18px 0 8px; text-transform: uppercase; letter-spacing: 1px; }
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
                Edit Grade and DropRate in <code style="color:#777;">PS_GameDefs.dbo.MobItems</code> — Slot 1-6: DropRate% = raw value (0-100). Slot 7-9: DropRate% = raw / 10000 (0-1000000).
            </p>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <!-- Indicatore stato sessione (aggiornato da JS keep-alive) -->
            <span id="ka-status" style="font-size:11px; color:#333; transition:color 0.4s;"></span>
            <button class="mc-btn-help" id="btn-drop-help" style="white-space:nowrap;">
                <i class="fas fa-question-circle"></i> HELP
            </button>
        </div>
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
            <?php
            // Boss toggle link (preserva altri filtri, cambia solo boss_only)
            $_bossToggle = $_qsArr;
            unset($_bossToggle['page']); // reset pagina
            if ($filterBossOnly) unset($_bossToggle['boss_only']);
            else $_bossToggle['boss_only'] = '1';
            ?>
            <a href="/admin?<?php echo htmlspecialchars(http_build_query($_bossToggle)); ?>"
               class="boss-filter-btn<?php echo $filterBossOnly ? ' active' : ''; ?>">
                <i class="fas fa-crown"></i> Boss
            </a>
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
    <!-- ── Mob List (aggregata per mob) ──────────────────────── -->
    <div style="background: rgba(0,0,0,0.2); border: 1px solid rgba(232,200,129,0.05); border-radius: 8px; overflow: hidden; margin-bottom: 28px;">
        <!-- Header colonne -->
        <div style="display: grid; grid-template-columns: 72px 1fr 46px 1fr 78px 90px 80px; gap: 0; padding: 10px 16px; background: rgba(232,200,129,0.04); border-bottom: 1px solid rgba(255,255,255,0.05);">
            <span style="color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">MobID</span>
            <span style="color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Nome</span>
            <span style="color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">Lv</span>
            <span style="color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Mappa</span>
            <span style="color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">Slot</span>
            <span style="color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">Drop% 1-6</span>
            <span style="color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align:center;">Azione</span>
        </div>

        <?php foreach ($rows as $i => $row):
            $mlFilled = (int)($row['FilledSlots'] ?? 0);
            $mlPct    = round((float)($row['TotalPct16'] ?? 0), 2);
            $mlIsBoss = isset($bossMobIds[(int)$row['MobID']]);
            $mlBg     = $mlIsBoss ? '' : ($i % 2 === 0 ? 'rgba(255,255,255,0.01)' : 'rgba(0,0,0,0.15)');
            $mlClass  = $mlIsBoss ? ' class="mob-row-boss"' : '';
            // link EDIT: mob_id + filtri attuali
            $mlEditPs = ['view' => 'DROPS_EDITOR', 'mob_id' => (int)$row['MobID']];
            if ($filterMapId !== null)  $mlEditPs['map_id']    = $filterMapId;
            if ($filterMobName !== '')  $mlEditPs['mob_name']  = $filterMobName;
            if ($filterBossOnly)        $mlEditPs['boss_only'] = '1';
            if ($filterPage > 1)        $mlEditPs['page']      = $filterPage;
            $mlEditUrl = '/admin?' . http_build_query($mlEditPs);
        ?>
        <div<?php echo $mlClass; ?> style="display: grid; grid-template-columns: 72px 1fr 46px 1fr 78px 90px 80px; gap: 0; padding: 9px 16px; <?php echo $mlBg ? 'background:' . $mlBg . ';' : ''; ?> border-bottom: 1px solid rgba(255,255,255,0.03); align-items: center;">
            <span style="color: #666; font-size: 12px;"><?php echo (int)$row['MobID']; ?></span>
            <span style="color: <?php echo $mlIsBoss ? '#e8c881' : '#ccc'; ?>; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars((string)$row['MobName']); ?>">
                <?php if ($mlIsBoss): ?><i class="fas fa-crown mob-boss-crown"></i><?php endif; ?>
                <?php echo htmlspecialchars((string)$row['MobName']); ?>
                <?php if ($mlIsBoss): ?><span class="mob-boss-badge">Boss</span><?php endif; ?>
            </span>
            <span style="color: #666; font-size: 12px; text-align: center;"><?php echo (int)($row['Level'] ?? 0); ?></span>
            <span style="color: #555; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars((string)($row['MapName'] ?? '')); ?></span>
            <span style="font-size: 12px; text-align: center; color: <?php echo $mlFilled > 0 ? '#48bb78' : '#444'; ?>;">
                <?php
                $mobSlots = $slotsByMob[(int)$row['MobID']] ?? [];
                if ($mlFilled > 0 && !empty($mobSlots)):
                    $slotJson = htmlspecialchars(json_encode(array_values(array_map(function($sl) {
                        $hi  = (int)$sl['ItemOrder'] >= 7;
                        $pct = $hi ? round((float)$sl['DropRate'] / 10000, 4) : (float)$sl['DropRate'];
                        return ['name' => (string)($sl['ItemName'] ?? '???'), 'raw' => (int)$sl['DropRate'], 'pct' => $pct, 'grade' => (int)$sl['Grade'], 'hi' => $hi];
                    }, $mobSlots))), ENT_QUOTES);
                ?>
                <span class="slot-tip-trigger" data-slots="<?php echo $slotJson; ?>"><?php echo $mlFilled; ?>/9</span>
                <?php else: ?>
                <?php echo $mlFilled; ?>/9
                <?php endif; ?>
            </span>
            <span style="font-size: 12px; text-align: center; color: <?php echo $mlPct > 0 ? '#3498db' : '#444'; ?>;">
                <?php echo $mlPct > 0 ? $mlPct . '%' : '—'; ?>
            </span>
            <div style="text-align: center;">
                <a href="<?php echo htmlspecialchars($mlEditUrl); ?>"
                   data-mob-id="<?php echo (int)$row['MobID']; ?>"
                   class="mc-edit-link"
                   style="display: inline-block; background: rgba(232,200,129,0.08); border: 1px solid rgba(232,200,129,0.2); color: #e8c881; border-radius: 4px; padding: 4px 12px; font-size: 11px; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; transition: background 0.15s;"
                   onmouseover="this.style.background='rgba(232,200,129,0.18)'" onmouseout="this.style.background='rgba(232,200,129,0.08)'">
                    EDIT
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginazione mob -->
    <?php if ($totalPages > 1):
        $baseParams = ['view' => 'DROPS_EDITOR'];
        if ($filterMobName !== '')  $baseParams['mob_name']  = $filterMobName;
        if ($filterMapId !== null)  $baseParams['map_id']    = $filterMapId;
        if ($filterBossOnly)        $baseParams['boss_only'] = '1';
    ?>
    <div style="display: flex; gap: 8px; align-items: center; justify-content: center; margin-bottom: 30px;">
        <?php if ($filterPage > 1): ?>
        <a href="/admin?<?php echo http_build_query($baseParams + ['page' => $filterPage - 1]); ?>" style="padding: 7px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: #888; border-radius: 4px; text-decoration: none; font-size: 13px;">&laquo; Prec</a>
        <?php endif; ?>
        <span style="color: #666; font-size: 13px;">Pagina <?php echo $filterPage; ?> di <?php echo $totalPages; ?> — <?php echo $totalRows; ?> mob</span>
        <?php if ($filterPage < $totalPages): ?>
        <a href="/admin?<?php echo http_build_query($baseParams + ['page' => $filterPage + 1]); ?>" style="padding: 7px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: #888; border-radius: 4px; text-decoration: none; font-size: 13px;">Succ &raquo;</a>
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
                            <th style="text-align:center; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">Slot</th>
                            <th style="text-align:center; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">Op</th>
                            <th style="text-align:center; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">Grade</th>
                            <th style="text-align:center; padding:6px 10px; color:#444; font-size:10px; text-transform:uppercase; letter-spacing:1px; font-weight:400; border-bottom:1px solid rgba(255,255,255,0.04);">DropRate</th>
                            <th style="border-bottom:1px solid rgba(255,255,255,0.04);"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gEntries as $cl):
                        $changedTime  = date('H:i:s', strtotime($cl['ChangedAt']));
                        $clOldGrd = $cl['OldGrade']    === null ? null : (int)$cl['OldGrade'];
                        $clNewGrd = $cl['NewGrade']    === null ? null : (int)$cl['NewGrade'];
                        $clOldDr  = $cl['OldDropRate'] === null ? null : (int)$cl['OldDropRate'];
                        $clNewDr  = $cl['NewDropRate'] === null ? null : (int)$cl['NewDropRate'];
                        // Tipo operazione
                        $clIsInsert = ($clOldGrd === null && $clOldDr === null);
                        $clIsDelete = ($clNewGrd === null && $clNewDr === null);
                        $clIsUpdate = !$clIsInsert && !$clIsDelete;
                        $gradeChanged = $clIsUpdate && $clOldGrd !== $clNewGrd;
                        $drChanged    = $clIsUpdate && $clOldDr !== $clNewDr;
                        $navUrl = '/admin?view=drops_editor&mob_id=' . (int)$cl['MobID'];
                        if ($gMapId > 0) $navUrl .= '&map_id=' . $gMapId;
                        $navUrl .= '#row-' . (int)$cl['RowID'];
                    ?>
                    <tr class="cl-row" data-nav="<?php echo htmlspecialchars($navUrl); ?>"
                        style="border-bottom:1px solid rgba(255,255,255,0.02); cursor:pointer; transition:background 0.15s;">
                        <td style="padding:8px 10px; color:#555; font-size:12px;"><?php echo $changedTime; ?></td>
                        <td style="padding:8px 10px; color:#aaa; font-size:13px;"><?php echo (int)$cl['MobID']; ?></td>
                        <td style="padding:8px 10px; color:#ccc; font-size:12px; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars((string)$cl['MobName']); ?>"><?php echo htmlspecialchars((string)$cl['MobName']); ?></td>
                        <td style="padding:8px 10px; color:#666; font-size:12px; text-align:center;"><?php echo (int)$cl['ItemOrder']; ?></td>
                        <td style="padding:6px 10px; text-align:center;">
                            <?php if ($clIsInsert): ?>
                            <span style="background:rgba(72,187,120,0.12); border:1px solid rgba(72,187,120,0.25); color:#48bb78; border-radius:3px; padding:1px 6px; font-size:10px;">ADD</span>
                            <?php elseif ($clIsDelete): ?>
                            <span style="background:rgba(231,76,60,0.1); border:1px solid rgba(231,76,60,0.25); color:#e74c3c; border-radius:3px; padding:1px 6px; font-size:10px;">DEL</span>
                            <?php else: ?>
                            <span style="color:#555; font-size:11px;">UPD</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px; text-align:center; font-size:12px;">
                            <?php if ($clIsInsert): ?>
                            <span style="color:#48bb78;"><?php echo $clNewGrd ?? '—'; ?></span>
                            <?php elseif ($clIsDelete): ?>
                            <span style="color:#e74c3c; text-decoration:line-through;"><?php echo $clOldGrd ?? '—'; ?></span>
                            <?php elseif ($gradeChanged): ?>
                            <span style="color:#555;"><?php echo $clOldGrd; ?></span><span style="color:#444; margin:0 4px;">→</span><span style="color:#e8c881;"><?php echo $clNewGrd; ?></span>
                            <?php else: ?>
                            <span style="color:#444;"><?php echo $clNewGrd ?? '—'; ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px; text-align:center; font-size:12px;">
                            <?php if ($clIsInsert): ?>
                            <span style="color:#48bb78;"><?php echo $clNewDr ?? '—'; ?></span>
                            <?php elseif ($clIsDelete): ?>
                            <span style="color:#e74c3c; text-decoration:line-through;"><?php echo $clOldDr ?? '—'; ?></span>
                            <?php elseif ($drChanged): ?>
                            <span style="color:#555;"><?php echo $clOldDr; ?></span><span style="color:#444; margin:0 4px;">→</span><span style="color:#3498db;"><?php echo $clNewDr; ?></span>
                            <?php else: ?>
                            <span style="color:#444;"><?php echo $clNewDr ?? '—'; ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px; text-align:right;" onclick="event.stopPropagation();">
                            <form method="POST" action="/admin_actions" style="display:inline;">
                                <input type="hidden" name="action"               value="drops_undo_row">
                                <input type="hidden" name="log_id"               value="<?php echo (int)$cl['LogID']; ?>">
                                <input type="hidden" name="csrf_token"           value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="return_view"          value="DROPS_EDITOR">
                                <input type="hidden" name="drop_filter_mob_id"   value="<?php echo $filterMobId; ?>">
                                <input type="hidden" name="drop_filter_mob_name" value="<?php echo htmlspecialchars($filterMobName); ?>">
                                <input type="hidden" name="drop_filter_map_id"   value="<?php echo $filterMapId; ?>">
                                <input type="hidden" name="drop_filter_page"     value="<?php echo $filterPage; ?>">
                                <button type="submit" onclick="return confirm('Undo azione su MobID <?php echo (int)$cl['MobID']; ?>?');"
                                    style="background:rgba(231,76,60,0.08); border:1px solid rgba(231,76,60,0.2); color:#e74c3c; border-radius:4px; padding:3px 10px; font-size:11px; cursor:pointer; white-space:nowrap;">UNDO</button>
                            </form>
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

<!-- ============================================================
     MOB-CENTRIC MODAL (9 slot editor, caricato via AJAX)
     ============================================================ -->
<div id="modal-mc" class="dr-modal-overlay">
    <div class="dr-modal-box" style="width:800px; max-height:92vh; overflow-y:auto; padding:0;">
        <div class="dr-modal-header" style="position:sticky; top:0; z-index:1; background:#111;">
            <div id="mc-modal-mob-info">
                <div class="mc-mob-name" style="color:#666;">Caricamento…</div>
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                <div id="mc-modal-total" style="display:none; text-align:right; padding:6px 12px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:6px;">
                    <div style="color:#555; font-size:10px; text-transform:uppercase; letter-spacing:1px; margin-bottom:1px;">Slot 1-6</div>
                    <div id="mc-modal-total-val" style="font-size:15px; font-weight:600;"></div>
                </div>
                <button class="dr-modal-close" id="btn-mc-close">&#x2715;</button>
            </div>
        </div>
        <div class="dr-modal-body" style="padding:20px 24px;">
            <div id="mc-modal-loading" style="text-align:center; padding:50px 0; color:#555;">
                <i class="fas fa-spinner fa-spin" style="font-size:22px; display:block; margin-bottom:12px;"></i>
                Caricamento slot…
            </div>
            <div id="mc-modal-slots" style="display:none;"></div>
        </div>
    </div>
</div>

<!-- ============================================================
     ADD-ITEM MODAL (mob-centric)
     ============================================================ -->
<div id="modal-add-item" class="dr-modal-overlay">
    <div class="dr-modal-box" style="width:580px;">
        <div class="dr-modal-header">
            <div>
                <h3 style="color:#e8c881; margin:0 0 3px; font-size:16px;"><i class="fas fa-plus-circle" style="margin-right:10px;"></i>Add Drop Item</h3>
                <p style="color:#555; font-size:12px; margin:0;">Slot <strong id="ai-slot-label">?</strong> — MobID <strong id="ai-mob-label">?</strong></p>
            </div>
            <button class="dr-modal-close" id="btn-ai-close">&#x2715;</button>
        </div>
        <div class="dr-modal-body">
            <!-- Tabs ricerca -->
            <div style="display:flex; gap:0; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:6px; overflow:hidden; margin-bottom:14px;">
                <button id="ai-tab-name"  class="ai-tab-btn active"  style="flex:1; padding:8px; background:rgba(232,200,129,0.1); border:none; color:#e8c881; font-size:12px; cursor:pointer; border-right:1px solid rgba(255,255,255,0.06);">Per Nome</button>
                <button id="ai-tab-grade" class="ai-tab-btn" style="flex:1; padding:8px; background:transparent; border:none; color:#555; font-size:12px; cursor:pointer;">Per Grade / ItemID</button>
            </div>

            <input type="text" id="ai-search-input" class="ai-search-input"
                   placeholder="Cerca item per nome…" autocomplete="off">
            <input type="number" id="ai-grade-input" class="ai-search-input"
                   placeholder="Inserisci Grade o ItemID esatto" min="1" style="display:none;">

            <div id="ai-results" class="ai-results">
                <div class="ai-no-results" id="ai-hint">Digita almeno 2 caratteri per cercare.</div>
            </div>

            <div id="ai-selected-banner" class="ai-selected-banner">
                <i class="fas fa-check-circle" style="margin-right:8px;"></i>
                Selezionato: <strong id="ai-selected-name"></strong>
                <span style="color:#555; margin-left:8px;">Grade: <span id="ai-selected-grade"></span></span>
            </div>

            <div class="ai-rate-row" id="ai-rate-section" style="display:none;">
                <span class="ai-rate-label">DropRate<br><span id="ai-rate-hint" style="color:#444; font-size:10px; text-transform:none; letter-spacing:0;">(0-100)</span></span>
                <input type="number" id="ai-rate-input" class="ai-rate-input"
                       value="0" min="0" max="100" placeholder="0">
                <span class="ai-rate-pct" id="ai-rate-pct">0.0000%</span>
            </div>

            <form id="form-add-item" method="POST" action="/admin_actions">
                <input type="hidden" name="action"              value="drops_update_row">
                <input type="hidden" name="csrf_token"          value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="return_view"         value="DROPS_EDITOR">
                <input type="hidden" name="drop_filter_mob_id"  value="0">
                <input type="hidden" name="drop_filter_map_id"  value="<?php echo (int)$filterMapId; ?>">
                <input type="hidden" name="drop_filter_mob_name" value="<?php echo htmlspecialchars($filterMobName); ?>">
                <input type="hidden" name="drop_filter_page"    value="<?php echo (int)$filterPage; ?>">
                <input type="hidden" name="mc_open_mob_id"      id="ai-f-mc-open" value="">
                <input type="hidden" name="row_id"              id="ai-f-rowid" value="">
                <input type="hidden" name="grade"               id="ai-f-grade" value="">
                <input type="hidden" name="drop_rate"           id="ai-f-rate"  value="0">
                <button type="submit" id="ai-save-btn" class="ai-save-btn" disabled>
                    <i class="fas fa-plus" style="margin-right:8px;"></i>AGGIUNGI SLOT
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     HELP MODAL (mob-centric)
     ============================================================ -->
<div id="modal-drop-help" class="dr-modal-overlay">
    <div class="dr-modal-box" style="width:640px;">
        <div class="dr-modal-header">
            <h3 style="color:#e8c881; margin:0; font-size:16px;"><i class="fas fa-question-circle" style="margin-right:10px;"></i>Guida Drop Editor</h3>
            <button class="dr-modal-close" id="btn-help-close">&#x2715;</button>
        </div>
        <div class="dr-modal-body" style="padding:20px 28px;">

            <div class="help-section-title">Struttura Slot (1-9 per mob)</div>
            <table class="help-table">
                <thead><tr><th>Slot</th><th>Range DropRate</th><th>Formula %</th><th>Esempio</th></tr></thead>
                <tbody>
                    <tr><td><span class="help-code">1–6</span></td><td>0 – 100</td><td>DropRate = %</td><td>50 → 50.00%</td></tr>
                    <tr><td><span class="help-code">7–9</span> <span class="mc-slot-type-badge">×10k</span></td><td>0 – 1 000 000</td><td>DropRate ÷ 10 000</td><td>50 000 → 5.00%<br>1 000 000 → 100% (guaranteed)</td></tr>
                </tbody>
            </table>

            <div class="help-section-title">Come aggiungere un drop</div>
            <div class="help-step"><span class="help-step-num">1.</span><span>Filtra per <span class="help-code">Mappa</span>, <span class="help-code">Nome</span> o <span class="help-code">MobID</span> → vedi la lista mob → clicca <strong style="color:#e8c881;">EDIT</strong> su un mob.</span></div>
            <div class="help-step"><span class="help-step-num">2.</span><span>Si apre il pannello 9 slot. Slot vuoto → clicca <strong style="color:#3498db;">+ ADD ITEM</strong>.</span></div>
            <div class="help-step"><span class="help-step-num">3.</span><span>Cerca per <strong>Nome</strong> (≥ 2 caratteri) oppure inserisci <strong>Grade/ItemID</strong> esatto — seleziona dalla lista.</span></div>
            <div class="help-step"><span class="help-step-num">4.</span><span>Inserisci DropRate → clicca <strong style="color:#48bb78;">AGGIUNGI SLOT</strong>.</span></div>
            <div class="help-step"><span class="help-step-num">5.</span><span>Il pannello si riapre automaticamente con il nuovo slot. Usa <strong style="color:#e8c881;">SAVE</strong> per modificare il DropRate.</span></div>

            <div class="help-section-title">Come rimuovere un drop</div>
            <div class="help-step"><span class="help-step-num">1.</span><span>Slot pieno → clicca <strong style="color:#e74c3c;">✕</strong> → conferma.</span></div>
            <div class="help-step"><span class="help-step-num">2.</span><span>Lo slot torna vuoto. Puoi re-inserire subito un nuovo item.</span></div>

            <div class="help-section-title">Undo</div>
            <div class="help-step"><span class="help-step-num">•</span><span>Ogni ADD / REMOVE / SAVE viene loggato per 24h.</span></div>
            <div class="help-step"><span class="help-step-num">•</span><span>FAB in basso → <i class="fas fa-history"></i> Session Changes → UNDO su qualsiasi riga.</span></div>
            <div class="help-step"><span class="help-step-num">•</span><span>UNDO di un ADD = DELETE. UNDO di un REMOVE = re-INSERT.</span></div>

            <div class="help-section-title">Aggiungere nuovi items al DB</div>
            <div class="help-step"><span class="help-step-num">1.</span><span>Apri <strong>Shaiya File Converter V2</strong> → carica <span class="help-code">Item.SData</span> → esporta <span class="help-code">Item.csv</span>.</span></div>
            <div class="help-step"><span class="help-step-num">2.</span><span>Admin → Drops Management → sezione <strong>Upload CSV Items</strong>.</span></div>
            <div class="help-step"><span class="help-step-num">3.</span><span>Preview → Conferma → nuovi items subito ricercabili nel drop editor.</span></div>

            <div class="help-section-title">Export SQL</div>
            <div class="help-step"><span class="help-step-num">•</span><span>FAB → <i class="fas fa-file-download"></i> Export SQL — richiede filtro Map attivo.</span></div>
            <div class="help-step"><span class="help-step-num">•</span><span>Scarica file <span class="help-code">.sql</span> con tutti i drop della mappa selezionata.</span></div>

        </div>
    </div>
</div>

<script>
/* Variabili PHP → JS */
var PAGE_CSRF          = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
var MC_FILTER_MAP_ID   = <?php echo $filterMapId !== null ? (int)$filterMapId : -1; ?>;
var MC_FILTER_MOB_NAME = <?php echo json_encode($filterMobName); ?>;
var MC_FILTER_PAGE     = <?php echo (int)$filterPage; ?>;
var MC_FILTER_BOSS     = <?php echo $filterBossOnly ? '1' : '0'; ?>;
var MC_AUTO_OPEN       = <?php echo (int)($mcAutoOpen ?? 0); ?>;
</script>
<script>
(function () {
    /* ── Modal Changelog ───────────────────────────────────── */
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
            var pct   = this.closest('form').querySelector('.pct-display');
            var order = parseInt(this.closest('form').dataset.itemOrder || '1', 10);
            if (pct) {
                var raw = parseFloat(this.value || 0);
                var val = order >= 7 ? raw / 10000 : raw;
                pct.textContent = Math.min(100, Math.max(0, val)).toFixed(4) + '%';
            }
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

    /* ── Help modal ─────────────────────────────────────────── */
    var helpModal  = document.getElementById('modal-drop-help');
    var helpBtn    = document.getElementById('btn-drop-help');
    var helpClose  = document.getElementById('btn-help-close');
    if (helpModal) {
        document.body.appendChild(helpModal);
        function openHelp()  { helpModal.classList.add('active');    document.body.style.overflow = 'hidden'; }
        function closeHelp() { helpModal.classList.remove('active'); document.body.style.overflow = ''; }
        if (helpBtn)   helpBtn.addEventListener('click', openHelp);
        if (helpClose) helpClose.addEventListener('click', closeHelp);
        helpModal.addEventListener('click', function (e) { if (e.target === helpModal) closeHelp(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && helpModal.classList.contains('active')) closeHelp(); });
    }

    /* ── Mob-Centric Modal (AJAX) ───────────────────────────── */
    var mcModal      = document.getElementById('modal-mc');
    var mcClose      = document.getElementById('btn-mc-close');
    var mcLoading    = document.getElementById('mc-modal-loading');
    var mcSlots      = document.getElementById('mc-modal-slots');
    var mcMobInfo    = document.getElementById('mc-modal-mob-info');
    var mcTotal      = document.getElementById('mc-modal-total');
    var mcTotalVal   = document.getElementById('mc-modal-total-val');
    var mcCurrentMob = 0;

    if (mcModal) {
        document.body.appendChild(mcModal);

        function openMcModal(mobId) {
            mcCurrentMob = mobId;
            mcLoading.style.display = 'block';
            mcSlots.style.display   = 'none';
            mcMobInfo.innerHTML     = '<div class="mc-mob-name" style="color:#666;">Caricamento…</div>';
            mcTotal.style.display   = 'none';
            mcModal.classList.add('active');
            document.body.style.overflow = 'hidden';

            fetch('/admin_actions?action=drops_get_slots&mob_id=' + mobId + '&csrf_token=' + encodeURIComponent(PAGE_CSRF), {
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    mcLoading.innerHTML = '<p style="color:#e74c3c;">Errore: ' + (data.error || 'unknown') + '</p>';
                    return;
                }
                renderMcModal(data, mobId);
                mcLoading.style.display = 'none';
                mcSlots.style.display   = 'block';
            })
            .catch(function () {
                mcLoading.innerHTML = '<p style="color:#e74c3c;">Errore di rete</p>';
            });
        }

        function closeMcModal() {
            mcModal.classList.remove('active');
            document.body.style.overflow = '';
            mcCurrentMob = 0;
        }

        mcClose.addEventListener('click', closeMcModal);
        mcModal.addEventListener('click', function (e) { if (e.target === mcModal) closeMcModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && mcModal.classList.contains('active')) closeMcModal(); });

        function renderMcModal(data, mobId) {
            var mob   = data.mob;
            var slots = data.slots;
            var attribL = ['','Fire','Water','Earth','Wind','Lightning','Light','Dark'];
            var attrLbl = attribL[parseInt(mob.Attrib) || 0] || '';

            // Header
            var hHtml = '<div class="mc-mob-name">' + escHtml(mob.MobName) +
                '<span style="color:#555; font-size:14px; font-weight:400; margin-left:10px;">ID: ' + mob.MobID + '</span></div>' +
                '<div class="mc-mob-meta" style="margin-top:4px;">';
            if (mob.Level)   hHtml += '<span style="margin-right:14px;">Lv. <strong style="color:#aaa;">' + mob.Level + '</strong></span>';
            if (mob.HP)      hHtml += '<span style="margin-right:14px;">HP: <strong style="color:#aaa;">' + parseInt(mob.HP).toLocaleString() + '</strong></span>';
            if (mob.MapName) hHtml += '<span style="margin-right:14px;">Map: <strong style="color:#aaa;">' + escHtml(mob.MapName) + '</strong></span>';
            if (attrLbl)     hHtml += '<span>Element: <strong style="color:#aaa;">' + attrLbl + '</strong></span>';
            hHtml += '</div>';
            mcMobInfo.innerHTML = hHtml;

            // Total %
            var tot16 = 0;
            slots.forEach(function (s) { if (s.ord <= 6 && s.filled) tot16 += s.drop_rate; });
            if (tot16 > 0) {
                mcTotal.style.display = 'block';
                mcTotalVal.textContent = tot16.toFixed(2) + '%';
                mcTotalVal.style.color = tot16 > 100 ? '#e74c3c' : '#48bb78';
            } else {
                mcTotal.style.display = 'none';
            }

            // Hidden fields shared across all slot forms
            var hf = '<input type="hidden" name="csrf_token" value="' + escHtml(PAGE_CSRF) + '">' +
                     '<input type="hidden" name="return_view" value="DROPS_EDITOR">' +
                     '<input type="hidden" name="drop_filter_mob_id" value="0">' +
                     (MC_FILTER_MAP_ID >= 0 ? '<input type="hidden" name="drop_filter_map_id" value="' + MC_FILTER_MAP_ID + '">' : '') +
                     '<input type="hidden" name="drop_filter_mob_name" value="' + escHtml(MC_FILTER_MOB_NAME) + '">' +
                     '<input type="hidden" name="drop_filter_page" value="' + MC_FILTER_PAGE + '">' +
                     (MC_FILTER_BOSS ? '<input type="hidden" name="drop_filter_boss_only" value="1">' : '') +
                     '<input type="hidden" name="mc_open_mob_id" value="' + mobId + '">';

            // Build 9-slot table
            var html = '<table class="mc-slot-table"><thead><tr>' +
                '<th style="width:52px;">Slot</th><th>Item</th>' +
                '<th style="width:130px;">DropRate (raw)</th><th style="width:80px;">%</th>' +
                '<th style="width:90px;">Grade</th><th style="width:160px;">Azioni</th>' +
                '</tr></thead><tbody>';

            slots.forEach(function (s) {
                var hi   = s.ord >= 7;
                var snum = '<div class="mc-slot-num' + (hi ? ' mc-slot-high' : '') + '">' + s.ord +
                           (hi ? '<span class="mc-slot-type-badge">&times;10k</span>' : '') + '</div>';
                var sid  = 'mcms-' + s.ord;
                var did  = 'mcmd-' + s.ord;

                if (s.filled) {
                    var maxR = hi ? 1000000 : 100;
                    var pct  = hi ? (s.drop_rate / 10000).toFixed(4) : parseFloat(s.drop_rate).toFixed(4);
                    html += '<tr class="mc-row-filled">' +
                        '<td>' + snum + '</td>' +
                        '<td><span class="mc-item-name">' + escHtml(s.item_name || '???') + '</span>' +
                        (s.req_level > 0 ? '<span class="mc-item-level">Lv.' + s.req_level + '</span>' : '') + '</td>' +
                        '<td><div class="mc-rate-wrap"><input type="number" name="drop_rate" form="' + sid + '"' +
                        ' class="mc-rate-input' + (hi ? ' mc-rate-high' : '') + '"' +
                        ' data-high="' + (hi ? '1' : '0') + '" data-form="' + sid + '"' +
                        ' value="' + s.drop_rate + '" min="0" max="' + maxR + '"' +
                        ' title="' + (hi ? 'Valore 0-1000000 (1000000=100%)' : 'Valore 0-100 (% diretta)') + '"></div></td>' +
                        '<td><span class="mc-pct-display">' + pct + '%</span></td>' +
                        '<td style="color:#555; font-size:12px;"><span class="grade-tip-trigger" data-grade="' + s.grade + '" style="cursor:help;border-bottom:1px dotted #333;">' + s.grade + '</span></td>' +
                        '<td>' +
                        '<form id="' + sid + '" method="POST" action="/admin_actions" data-item-order="' + s.ord + '">' +
                        '<input type="hidden" name="action" value="drops_update_row">' +
                        '<input type="hidden" name="row_id" value="' + s.rowid + '">' +
                        '<input type="hidden" name="grade" value="' + s.grade + '">' +
                        hf +
                        '<button type="submit" class="mc-btn-save">SAVE</button>' +
                        '</form>' +
                        '<form id="' + did + '" method="POST" action="/admin_actions" style="display:inline; margin-left:6px;">' +
                        '<input type="hidden" name="action" value="drops_update_row">' +
                        '<input type="hidden" name="row_id" value="' + s.rowid + '">' +
                        '<input type="hidden" name="grade" value="0">' +
                        '<input type="hidden" name="drop_rate" value="0">' +
                        hf +
                        '<button type="submit" class="mc-btn-remove"' +
                        ' onclick="return confirm(\'Svuotare slot ' + s.ord + '? (Grade → 0, DropRate → 0)\')">&#x2715;</button>' +
                        '</form>' +
                        '</td></tr>';
                } else {
                    html += '<tr class="mc-row-empty">' +
                        '<td>' + snum + '</td>' +
                        '<td colspan="3" style="color:#333; font-size:13px; font-style:italic;">— slot vuoto —</td>' +
                        '<td></td>' +
                        '<td><button class="mc-btn-add" data-slot="' + s.ord + '" data-mob="' + mobId +
                        '" data-rowid="' + s.rowid + '" data-ishigh="' + (hi ? '1' : '0') + '">' +
                        '+ ADD ITEM</button></td></tr>';
                }
            });
            html += '</tbody></table>';
            mcSlots.innerHTML = html;

            // Attach live % listener to modal rate inputs
            mcSlots.querySelectorAll('.mc-rate-input').forEach(function (inp) {
                inp.addEventListener('input', function () {
                    var high = this.getAttribute('data-high') === '1';
                    var raw  = parseFloat(this.value || 0);
                    var val  = high ? raw / 10000 : raw;
                    val = Math.min(100, Math.max(0, val));
                    var pctEl = this.closest('tr').querySelector('.mc-pct-display');
                    if (pctEl) pctEl.textContent = val.toFixed(4) + '%';
                });
            });
        }

        // Intercetta link EDIT nella mob-list → apri modal
        document.addEventListener('click', function (e) {
            var link = e.target.closest('.mc-edit-link');
            if (link) {
                e.preventDefault();
                var mid = parseInt(link.getAttribute('data-mob-id'), 10);
                if (mid > 0) openMcModal(mid);
            }
        });

        // Event delegation per .mc-btn-add dentro il modal mob-centric
        mcSlots.addEventListener('click', function (e) {
            var btn = e.target.closest('.mc-btn-add');
            if (btn) {
                openAi(
                    btn.getAttribute('data-slot'),
                    btn.getAttribute('data-mob'),
                    btn.getAttribute('data-rowid'),
                    btn.getAttribute('data-ishigh') === '1',
                    mcCurrentMob
                );
            }
        });

        // Auto-open modal al caricamento pagina (dopo save o mob_id diretto)
        if (MC_AUTO_OPEN > 0) {
            window.addEventListener('load', function () {
                openMcModal(MC_AUTO_OPEN);
            });
        }
    }

    /* ── Add-Item modal ─────────────────────────────────────── */
    var aiModal    = document.getElementById('modal-add-item');
    var aiClose    = document.getElementById('btn-ai-close');
    var aiSearch   = document.getElementById('ai-search-input');
    var aiGradeInp = document.getElementById('ai-grade-input');
    var aiTabName  = document.getElementById('ai-tab-name');
    var aiTabGrade = document.getElementById('ai-tab-grade');
    var aiResults  = document.getElementById('ai-results');
    var aiBanner   = document.getElementById('ai-selected-banner');
    var aiSelName  = document.getElementById('ai-selected-name');
    var aiSelGrd   = document.getElementById('ai-selected-grade');
    var aiRateSec  = document.getElementById('ai-rate-section');
    var aiRateInp  = document.getElementById('ai-rate-input');
    var aiRatePct  = document.getElementById('ai-rate-pct');
    var aiRateHint = document.getElementById('ai-rate-hint');
    var aiSaveBtn  = document.getElementById('ai-save-btn');
    var aiFRowId   = document.getElementById('ai-f-rowid');
    var aiFGrade   = document.getElementById('ai-f-grade');
    var aiFRate    = document.getElementById('ai-f-rate');
    var aiFMcOpen  = document.getElementById('ai-f-mc-open');
    var aiSlotLbl  = document.getElementById('ai-slot-label');
    var aiMobLbl   = document.getElementById('ai-mob-label');
    var aiCurIsHigh = false;
    var aiUseGradeTab = false;
    var aiCsrfToken = document.querySelector('#form-add-item [name=csrf_token]').value;

    if (aiModal) {
        document.body.appendChild(aiModal);

        function openAi(slot, mob, rowid, isHigh, mobIdForReopen) {
            aiCurIsHigh = isHigh;
            aiSlotLbl.textContent = slot;
            aiMobLbl.textContent  = mob;
            aiFRowId.value        = rowid;
            aiFGrade.value        = '';
            aiFRate.value         = '0';
            aiFMcOpen.value       = mobIdForReopen || 0;
            aiSearch.value        = '';
            aiGradeInp.value      = '';
            aiResults.innerHTML   = '<div class="ai-no-results">Digita almeno 2 caratteri per cercare.</div>';
            aiBanner.classList.remove('visible');
            aiRateSec.style.display = 'none';
            aiSaveBtn.disabled      = true;
            aiRateInp.max           = isHigh ? 1000000 : 100;
            aiRateHint.textContent  = isHigh ? '0-1000000 (1000000=100%)' : '0-100 (%)';
            // Reset to name tab
            switchAiTab(false);
            aiModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(function () { aiSearch.focus(); }, 100);
        }

        function switchAiTab(useGrade) {
            aiUseGradeTab = useGrade;
            if (useGrade) {
                aiTabName.style.background  = 'transparent';
                aiTabName.style.color       = '#555';
                aiTabGrade.style.background = 'rgba(232,200,129,0.1)';
                aiTabGrade.style.color      = '#e8c881';
                aiSearch.style.display   = 'none';
                aiGradeInp.style.display = '';
                aiResults.innerHTML = '<div class="ai-no-results">Inserisci Grade o ItemID esatto.</div>';
            } else {
                aiTabGrade.style.background = 'transparent';
                aiTabGrade.style.color      = '#555';
                aiTabName.style.background  = 'rgba(232,200,129,0.1)';
                aiTabName.style.color       = '#e8c881';
                aiGradeInp.style.display = 'none';
                aiSearch.style.display   = '';
                aiResults.innerHTML = '<div class="ai-no-results">Digita almeno 2 caratteri per cercare.</div>';
            }
        }

        if (aiTabName)  aiTabName.addEventListener('click',  function () { switchAiTab(false); aiSearch.focus(); });
        if (aiTabGrade) aiTabGrade.addEventListener('click', function () { switchAiTab(true);  aiGradeInp.focus(); });

        function closeAi() {
            aiModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (aiClose) aiClose.addEventListener('click', closeAi);
        aiModal.addEventListener('click', function (e) { if (e.target === aiModal) closeAi(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && aiModal.classList.contains('active')) closeAi(); });

        // AJAX search per nome con debounce
        var aiDebTimer = null;
        aiSearch.addEventListener('input', function () {
            clearTimeout(aiDebTimer);
            var q = this.value.trim();
            if (q.length < 2) {
                aiResults.innerHTML = '<div class="ai-no-results">Digita almeno 2 caratteri per cercare.</div>';
                return;
            }
            aiResults.innerHTML = '<div class="ai-no-results"><i class="fas fa-circle-notch fa-spin"></i> Ricerca…</div>';
            aiDebTimer = setTimeout(function () {
                fetch('/admin_actions?action=drops_search_items&q=' + encodeURIComponent(q) + '&csrf_token=' + encodeURIComponent(aiCsrfToken), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { renderAiResults(d.items || []); })
                .catch(function () { aiResults.innerHTML = '<div class="ai-no-results" style="color:#e74c3c;">Errore di ricerca.</div>'; });
            }, 280);
        });

        // Ricerca per Grade esatto
        var aiGradeTimer = null;
        aiGradeInp.addEventListener('input', function () {
            clearTimeout(aiGradeTimer);
            var g = parseInt(this.value || 0);
            if (g <= 0) {
                aiResults.innerHTML = '<div class="ai-no-results">Inserisci Grade o ItemID esatto.</div>';
                return;
            }
            aiResults.innerHTML = '<div class="ai-no-results"><i class="fas fa-circle-notch fa-spin"></i> Ricerca…</div>';
            aiGradeTimer = setTimeout(function () {
                fetch('/admin_actions?action=drops_search_items&grade=' + g + '&csrf_token=' + encodeURIComponent(aiCsrfToken), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { renderAiResults(d.items || []); })
                .catch(function () { aiResults.innerHTML = '<div class="ai-no-results" style="color:#e74c3c;">Errore di ricerca.</div>'; });
            }, 150);
        });

        function renderAiResults(items) {
            if (!items.length) {
                aiResults.innerHTML = '<div class="ai-no-results">Nessun item trovato.</div>';
                return;
            }
            var html = '';
            items.forEach(function (it) {
                html += '<div class="ai-result-item" data-grade="' + it.Grade + '" data-name="' + escHtml(it.ItemName) + '">'
                      + '<span class="ai-result-name">' + escHtml(it.ItemName) + '</span>'
                      + '<span class="ai-result-meta">Gr.' + it.Grade + (it.Reqlevel > 0 ? ' · Lv.' + it.Reqlevel : '') + '</span>'
                      + '</div>';
            });
            aiResults.innerHTML = html;
            aiResults.querySelectorAll('.ai-result-item').forEach(function (el) {
                el.addEventListener('click', function () {
                    aiResults.querySelectorAll('.ai-result-item').forEach(function (x) { x.classList.remove('selected'); });
                    this.classList.add('selected');
                    aiFGrade.value = this.getAttribute('data-grade');
                    aiSelName.textContent = this.getAttribute('data-name');
                    aiSelGrd.textContent  = this.getAttribute('data-grade');
                    aiBanner.classList.add('visible');
                    aiRateSec.style.display = 'flex';
                    aiSaveBtn.disabled = false;
                    aiRateInp.focus();
                });
            });
        }

        if (aiRateInp) {
            aiRateInp.addEventListener('input', function () {
                var raw = parseFloat(this.value || 0);
                var pct = aiCurIsHigh ? raw / 10000 : raw;
                pct = Math.min(100, Math.max(0, pct));
                aiRatePct.textContent = pct.toFixed(4) + '%';
                aiFRate.value = this.value;
            });
        }
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    /* ── Slot tooltip (JS fixed, tutto inline per evitare CSS globale) ── */
    var slotTip = document.createElement('div');
    slotTip.style.cssText = [
        'display:none', 'position:fixed', 'z-index:9999', 'pointer-events:none',
        'width:320px', 'max-height:280px', 'overflow:hidden',
        'background:rgba(8,8,10,0.97)', 'border:1px solid rgba(232,200,129,0.22)',
        'border-radius:8px', 'box-shadow:0 12px 40px rgba(0,0,0,0.85)',
        'font-size:12px', 'font-family:sans-serif', 'line-height:1.4',
        'color:#ccc'
    ].join(';');
    document.body.appendChild(slotTip);

    var TD  = 'style="padding:6px 10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;font-weight:normal;"';
    var TDR = 'style="padding:6px 10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;font-weight:normal;text-align:right;"';
    var TH  = 'style="padding:7px 10px;font-size:10px;color:#555;text-transform:uppercase;letter-spacing:0.7px;font-weight:500;text-align:left;background:rgba(232,200,129,0.06);"';
    var THR = 'style="padding:7px 10px;font-size:10px;color:#555;text-transform:uppercase;letter-spacing:0.7px;font-weight:500;text-align:right;background:rgba(232,200,129,0.06);"';

    function posSlotTip(e) {
        var x = e.clientX + 16;
        var y = e.clientY - Math.round(slotTip.offsetHeight / 2);
        if (x + slotTip.offsetWidth > window.innerWidth  - 8) x = e.clientX - slotTip.offsetWidth - 16;
        if (y < 6) y = 6;
        if (y + slotTip.offsetHeight > window.innerHeight - 6) y = window.innerHeight - slotTip.offsetHeight - 6;
        slotTip.style.left = x + 'px';
        slotTip.style.top  = y + 'px';
    }

    document.addEventListener('mouseover', function(e) {
        var trig = e.target.closest('.slot-tip-trigger');
        if (!trig) return;
        var slots;
        try { slots = JSON.parse(trig.getAttribute('data-slots') || '[]'); } catch(x) { return; }
        if (!slots.length) return;

        var html = '<table style="width:100%;border-collapse:collapse;"><thead><tr>'
            + '<th ' + TH  + '>Item</th>'
            + '<th ' + THR + '>Raw</th>'
            + '<th ' + THR + '>%</th>'
            + '<th ' + THR + '>Grade</th>'
            + '</tr></thead><tbody>';
        slots.forEach(function(s) {
            var pctStr = (s.pct % 1 === 0) ? s.pct.toFixed(0) : s.pct.toFixed(4).replace(/\.?0+$/, '');
            html += '<tr>'
                + '<td ' + TD  + ' style="padding:6px 10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;color:#ddd;">' + escHtml(s.name) + '</td>'
                + '<td ' + TDR + ' style="padding:6px 10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;color:#555;text-align:right;">' + s.raw + (s.hi ? '<span style="font-size:9px;color:#3498db;margin-left:2px;">\xd710k</span>' : '') + '</td>'
                + '<td ' + TDR + ' style="padding:6px 10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;color:#48bb78;font-weight:600;text-align:right;">' + pctStr + '%</td>'
                + '<td ' + TDR + ' style="padding:6px 10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;color:#555;text-align:right;">' + s.grade + '</td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        slotTip.innerHTML = html;
        slotTip.style.display = 'block';
        posSlotTip(e);
    });

    document.addEventListener('mousemove', function(e) {
        if (slotTip.style.display !== 'none') {
            if (e.target.closest('.slot-tip-trigger')) posSlotTip(e);
            else slotTip.style.display = 'none';
        }
    });

    document.addEventListener('mouseleave', function() { slotTip.style.display = 'none'; });

    /* ── Grade items tooltip (scrollabile, sticky su hover) ── */
    var gradeTip = document.createElement('div');
    gradeTip.style.cssText = [
        'display:none', 'position:fixed', 'z-index:9999',
        'width:270px', 'max-height:260px', 'overflow-y:auto',
        'background:rgba(8,8,10,0.97)', 'border:1px solid rgba(52,152,219,0.25)',
        'border-radius:8px', 'box-shadow:0 12px 40px rgba(0,0,0,0.85)',
        'font-size:12px', 'font-family:sans-serif', 'line-height:1.4', 'color:#ccc',
        // scrollbar sottile
        'scrollbar-width:thin', 'scrollbar-color:rgba(52,152,219,0.3) transparent'
    ].join(';');
    document.body.appendChild(gradeTip);

    var gradeCache   = {};
    var gtHideTimer  = null;

    function posGradeTip(x, y) {
        gradeTip.style.left = (x + 14) + 'px';
        gradeTip.style.top  = y + 'px';
        requestAnimationFrame(function () {
            var r = gradeTip.getBoundingClientRect();
            if (r.right  > window.innerWidth  - 8) gradeTip.style.left = (x - r.width - 14) + 'px';
            if (r.bottom > window.innerHeight - 8) gradeTip.style.top  = (window.innerHeight - r.height - 8) + 'px';
        });
    }

    function renderGradeTip(grade, items) {
        if (!items.length) {
            gradeTip.innerHTML = '<div style="padding:12px;color:#444;font-size:12px;">Nessun item trovato</div>';
            return;
        }
        var hdr = '<div style="padding:8px 12px;position:sticky;top:0;background:rgba(8,8,10,0.97);border-bottom:1px solid rgba(52,152,219,0.15);font-size:10px;color:#3498db;text-transform:uppercase;letter-spacing:0.8px;">'
            + 'Grade ' + grade + ' &mdash; ' + items.length + ' item</div>';
        var rows = '';
        items.forEach(function (it) {
            rows += '<div style="display:flex;justify-content:space-between;padding:5px 12px;border-bottom:1px solid rgba(255,255,255,0.04);">'
                + '<span style="font-size:12px;color:#ccc;">' + escHtml(it.ItemName) + '</span>'
                + (it.Reqlevel > 0 ? '<span style="font-size:11px;color:#555;white-space:nowrap;margin-left:8px;">Lv.' + it.Reqlevel + '</span>' : '')
                + '</div>';
        });
        gradeTip.innerHTML = hdr + rows;
    }

    function showGradeTip(grade, x, y) {
        clearTimeout(gtHideTimer);
        posGradeTip(x, y);
        gradeTip.style.display = 'block';
        if (gradeCache[grade] !== undefined) {
            renderGradeTip(grade, gradeCache[grade]);
            return;
        }
        gradeTip.innerHTML = '<div style="padding:12px;color:#555;font-size:12px;"><i class="fas fa-circle-notch fa-spin"></i> Caricamento…</div>';
        fetch('/admin_actions?action=drops_search_items&grade=' + grade + '&csrf_token=' + encodeURIComponent(PAGE_CSRF), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { gradeCache[grade] = d.items || []; renderGradeTip(grade, gradeCache[grade]); })
            .catch(function ()  { gradeTip.innerHTML = '<div style="padding:12px;color:#e74c3c;">Errore</div>'; });
    }

    function hideGradeTip() {
        gtHideTimer = setTimeout(function () { gradeTip.style.display = 'none'; }, 150);
    }

    // Event delegation su mcSlots (content dinamico)
    if (mcSlots) {
        mcSlots.addEventListener('mouseover', function (e) {
            var trig = e.target.closest('.grade-tip-trigger');
            if (trig) showGradeTip(parseInt(trig.getAttribute('data-grade'), 10), e.clientX, e.clientY);
        });
        mcSlots.addEventListener('mouseout', function (e) {
            if (e.target.closest('.grade-tip-trigger')) hideGradeTip();
        });
    }
    gradeTip.addEventListener('mouseenter', function () { clearTimeout(gtHideTimer); });
    gradeTip.addEventListener('mouseleave', function () { hideGradeTip(); });
})();
</script>
