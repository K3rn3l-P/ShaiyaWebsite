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

// --- Login server (ps_login) reachability + maintenance mode ---
// TCP probe of the login port. Cached ~10s in a temp file so page loads are not slowed
// (and the port is not hammered) — a localhost connect is instant when up, ~1s timeout when down.
// The SAME cache file/TTL also carries the maintenance flag (PS_UserData.dbo.MaintenanceMode,
// set by ShaiyaServerHub's "Manutenzione" card — see ShaiyaServerHub CLAUDE.md), so Updater/api/
// status.php (deliberately DB-free) can read it too without touching the database.
$server_online = false;
$maintenance = false;
$srv_host = '127.0.0.1';
$srv_port = 30800;
$srv_timeout = 1.0;
$srv_cache_ttl = 10;
$srv_cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shaiya_srv_status.json';
$srv_from_cache = false;
if (is_file($srv_cache_file)) {
    $c = json_decode(@file_get_contents($srv_cache_file), true);
    if (is_array($c) && isset($c['ts'], $c['up']) && (time() - (int) $c['ts']) < $srv_cache_ttl) {
        $server_online = (bool) $c['up'];
        $maintenance = isset($c['maintenance']) ? (bool) $c['maintenance'] : false;
        $srv_from_cache = true;
    }
}
if (!$srv_from_cache) {
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($srv_host, $srv_port, $errno, $errstr, $srv_timeout);
    if ($fp !== false) {
        $server_online = true;
        @fclose($fp);
    }

    // Maintenance flag. Table is created/managed manually via the SQL install guide (not by this
    // site) — may not exist yet, fail closed to "not in maintenance" so the banner never shows wrongly.
    try {
        $mnt_stmt = $pdoAdmin->query("IF OBJECT_ID('PS_UserData.dbo.MaintenanceMode') IS NULL SELECT CAST(0 AS BIT) AS Enabled ELSE SELECT Enabled FROM PS_UserData.dbo.MaintenanceMode");
        if ($mnt_stmt && $mnt_row = $mnt_stmt->fetch(PDO::FETCH_ASSOC)) {
            $maintenance = (bool) $mnt_row['Enabled'];
        }
    } catch (Throwable $e) { }

    @file_put_contents($srv_cache_file, json_encode(['ts' => time(), 'up' => $server_online, 'maintenance' => $maintenance]));
}
// --------------------------
?>
