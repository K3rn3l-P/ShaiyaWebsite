<?php
// drops_export.php — incluso da admin.php PRIMA dell'HTML (intercept GET)
// Genera e scarica un file .sql con UPDATE statements per tutti i MobItems di una mappa.
// Contesto: $pdoAdmin, $username, $adminStatus, $adminUserUID già disponibili.

$exportMapId = isset($_GET['map_id']) && ctype_digit((string) $_GET['map_id']) ? (int) $_GET['map_id'] : 0;

if ($exportMapId <= 0) {
    http_response_code(400);
    echo 'Parametro map_id mancante o non valido.';
    exit;
}

// Trova nome mappa
$mapName = 'Map ' . $exportMapId;
try {
    $mnStmt = $pdoAdmin->prepare("SELECT MapName FROM PS_GameDefs.dbo.MapNames WHERE MapID = ?");
    $mnStmt->execute([$exportMapId]);
    $mnRow = $mnStmt->fetch(PDO::FETCH_ASSOC);
    if ($mnRow && !empty($mnRow['MapName'])) {
        $mapName = (string) $mnRow['MapName'];
    }
} catch (Throwable $e) {}

// Carica tutti i MobItems per la mappa (nessun limite di paginazione)
$exportRows = [];
$totalExportRows = 0;
try {
    $expStmt = $pdoAdmin->prepare("
        SELECT mi.RowID, mi.MobID, m.MobName, mi.ItemOrder, mi.Grade, mi.DropRate
        FROM PS_GameDefs.dbo.MobItems mi
        JOIN PS_GameDefs.dbo.Mobs m ON m.MobID = mi.MobID
        WHERE m.MapID = ?
        ORDER BY mi.MobID ASC, mi.ItemOrder ASC
    ");
    $expStmt->execute([$exportMapId]);
    $exportRows = $expStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalExportRows = count($exportRows);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Errore DB durante la generazione del file SQL.';
    exit;
}

// Audit log
appAuditLog('admin_drops_export', [
    'admin'    => $username,
    'map_id'   => $exportMapId,
    'map_name' => $mapName,
    'rows'     => $totalExportRows,
]);

// Genera SQL
$generated = date('Y-m-d H:i:s');
$filename  = 'drops_map' . $exportMapId . '_' . date('Ymd_His') . '.sql';

$lines = [];
$lines[] = '-- ============================================================';
$lines[] = '-- Drops Export - Map ' . $exportMapId . ' (' . $mapName . ')';
$lines[] = '-- Generated : ' . $generated;
$lines[] = '-- Admin     : ' . $username;
$lines[] = '-- Rows      : ' . $totalExportRows;
$lines[] = '-- Database  : PS_GameDefs.dbo.MobItems';
$lines[] = '-- Safe to re-run: only updates existing rows (no INSERT/DELETE)';
$lines[] = '-- DropRate% = DropRate / 100000';
$lines[] = '-- ============================================================';
$lines[] = '';

$lastMobId = -1;
foreach ($exportRows as $r) {
    $rowId    = (int) $r['RowID'];
    $mobId    = (int) $r['MobID'];
    $mobName  = $r['MobName'] ?? '';
    $order    = (int) $r['ItemOrder'];
    $grade    = (int) $r['Grade'];
    $dropRate = (int) $r['DropRate'];
    $pct      = number_format($dropRate / 100000, 4);

    if ($mobId !== $lastMobId) {
        $lines[] = '';
        $lines[] = '-- MobID ' . $mobId . ': ' . $mobName;
        $lastMobId = $mobId;
    }

    $lines[] = sprintf(
        'UPDATE PS_GameDefs.dbo.MobItems SET Grade = %d, DropRate = %d WHERE RowID = %d; -- Order %d | %.4f%%',
        $grade, $dropRate, $rowId, $order, $pct
    );
}

$lines[] = '';
$lines[] = '-- End of export';
$sql = implode("\n", $lines);

// Invia come file da scaricare
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql));
header('Cache-Control: no-store, no-cache');
echo $sql;
