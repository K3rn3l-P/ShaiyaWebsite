<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../db_pdo.php';

// --- Server Stats Logic ---
$online_count = 0;
$aol_pct = 50;
$uof_pct = 50;

// Get Online Count based on LoginStatus
try {
    $online_stmt = $pdoAdmin->query("SELECT COUNT(*) AS online_count FROM PS_GameData.dbo.Chars WHERE Del = 0 AND LoginStatus = 1");
    if ($online_stmt && $row = $online_stmt->fetch(PDO::FETCH_ASSOC)) {
        $online_count = (int) $row['online_count'];
    }
} catch (Throwable $e) { }

// Get Faction Ratios
try {
    $faction_stmt = $pdoAdmin->query("
        SELECT 
            SUM(CASE WHEN umg.Country = 0 THEN 1 ELSE 0 END) AS aol_count,
            SUM(CASE WHEN umg.Country = 1 THEN 1 ELSE 0 END) AS uof_count
        FROM PS_GameData.dbo.Chars c
        INNER JOIN PS_GameData.dbo.UserMaxGrow umg ON c.UserUID = umg.UserUID
        WHERE c.Del = 0
    ");
    if ($faction_stmt && $faction_row = $faction_stmt->fetch(PDO::FETCH_ASSOC)) {
        $aol_count = (int) $faction_row['aol_count'];
        $uof_count = (int) $faction_row['uof_count'];
        $total_factions = $aol_count + $uof_count;

        if ($total_factions > 0) {
            $aol_pct = round(($aol_count / $total_factions) * 100);
            $uof_pct = round(($uof_count / $total_factions) * 100);
        }
    }
} catch (Throwable $e) { }
// --------------------------
?>
