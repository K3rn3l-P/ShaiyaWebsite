<?php
require_once 'security_bootstrap.php';
appApplySecurityHeaders();
ini_set('session.gc_maxlifetime', 7200); // admin: sessione server-side 2h
appInitSession();
ob_start();
require_once 'db.php';
require_once 'db_pdo.php';

function formatRelativeTime($date, $limit = 2)
{
    if (!$date || strpos($date, '1900') !== false)
        return "Never";
    $diff = time() - strtotime($date);
    if ($diff < 60)
        return "Just now";

    $days = floor($diff / 86400);
    $diff %= 86400;
    $hours = floor($diff / 3600);
    $diff %= 3600;
    $minutes = floor($diff / 60);

    $parts = [];
    if ($days > 0)
        $parts[] = $days . "d";
    if ($hours > 0)
        $parts[] = $hours . "h";
    if ($minutes > 0)
        $parts[] = $minutes . "m";

    if (empty($parts))
        return "Just now";

    return implode(", ", array_slice($parts, 0, $limit)) . " ago";
}

function getJobName($jobId)
{
    $jobs = [
        0 => 'Fighter/Warrior',
        1 => 'Defender/Guardian',
        2 => 'Ranger/Assassin',
        3 => 'Archer/Hunter',
        4 => 'Mage/Pagan',
        5 => 'Priest/Oracle'
    ];
    return $jobs[$jobId] ?? 'Unknown';
}

function getRaceName($familyId)
{
    $races = [
        0 => 'Human',
        1 => 'Elf',
        2 => 'Vail',
        3 => 'Deatheater'
    ];
    return $races[$familyId] ?? 'Unknown';
}

function formatPlaytime($seconds)
{
    if ($seconds <= 0)
        return "0h. 0m.";
    $days = floor($seconds / 86400);
    $seconds %= 86400;
    $hours = floor($seconds / 3600);
    $seconds %= 3600;
    $minutes = floor($seconds / 60);

    $res = "";
    if ($days > 0)
        $res .= $days . "d. ";
    if ($hours > 0 || $days > 0)
        $res .= $hours . "h. ";
    $res .= $minutes . "m.";
    return trim($res);
}

function getAdminRankIcon($kills)
{
    if ($kills < 1)
        return '';
    $ranks = [
        31 => 1000000,
        30 => 900000,
        29 => 850000,
        28 => 800000,
        27 => 750000,
        26 => 700000,
        25 => 650000,
        24 => 600000,
        23 => 550000,
        22 => 500000,
        21 => 450000,
        20 => 400000,
        19 => 350000,
        18 => 300000,
        17 => 250000,
        16 => 200000,
        15 => 150000,
        14 => 130000,
        13 => 110000,
        12 => 90000,
        11 => 70000,
        10 => 50000,
        9 => 36320,
        8 => 26320,
        7 => 19320,
        6 => 14320,
        71 => 10520,
        4 => 7720,
        3 => 5620,
        2 => 50,
        1 => 1
    ];
    foreach ($ranks as $rankNum => $minKills) {
        if ($kills >= $minKills) {
            $yPos = -($rankNum - 1) * 32;
            return '<span style="background: url(\'assets/ranks.png\') no-repeat 0px ' . $yPos . 'px; display: inline-block; width: 32px; height: 16px; vertical-align: middle; margin-left:8px; opacity:0.8;"></span>';
        }
    }
    return '';
}

function getMapName($mapid)
{
    $maps = [
        0 => 'D-Water',
        1 => 'Map 1 Light',
        2 => 'Map 1 Dark',
        3 => 'D1',
        4 => 'D1.2',
        5 => 'Cornwells 1',
        6 => 'Cornwells 2',
        7 => 'Argilla 1',
        8 => 'Argilla 2',
        9 => 'D2',
        10 => 'D2.2',
        11 => 'D2 Floor 3',
        12 => 'Cloron 1',
        13 => 'Cloron 2',
        14 => 'Cloron 3',
        15 => 'Fantasma 1',
        16 => 'Fantasma 2',
        17 => 'Fantasma 3',
        18 => 'Proelium',
        19 => 'Willieoseu',
        20 => 'Keuraijen',
        21 => 'Maitreian 1',
        22 => 'Maitreian 2',
        23 => 'Aidion 1',
        24 => 'Aidion 2',
        25 => 'Elemental Cave',
        26 => 'Ruber Chaos',
        27 => 'Ruber Chaos',
        28 => 'Map 3 Light',
        29 => 'Map 3 Dark',
        30 => 'Cantabilian',
        31 => '20-30 Dung Light',
        32 => '20-30 Dung Dark',
        33 => 'Fedion',
        34 => 'Kalamus',
        35 => 'Apulune',
        36 => 'Iris',
        37 => 'Stigma',
        38 => 'Aurizen',
        43 => 'Skulleron',
        44 => 'Astenes',
        45 => 'Deep Desert 1',
        46 => 'Deep Desert 2',
        47 => 'Jungle',
        70 => 'Kanos Illum',
        80 => 'Canyon of Greed'
    ];
    return $maps[$mapid] ?? "Map $mapid";
}

function getChatTypeName($type)
{
    $types = [
        0 => 'Whisper',
        1 => 'Normal',
        2 => 'Yell/Other',
        3 => 'Guild',
        4 => 'Party',
        5 => 'Trade',
        6 => 'Yell/Other',
        7 => 'Area',
        8 => 'GM'
    ];
    return $types[$type] ?? "Type $type";
}

function castToUInt($int)
{
    return $int < 0 ? 4294967296 + $int : $int;
}

function getCraftnameLabel($craft)
{
    if (empty($craft) || $craft === '00000000000000000000')
        return '';
    $s = [
        'STR' => (int) substr($craft, 0, 2),
        'DEX' => (int) substr($craft, 2, 2),
        'REC' => (int) substr($craft, 4, 2),
        'INT' => (int) substr($craft, 6, 2),
        'WIS' => (int) substr($craft, 8, 2),
        'LUC' => (int) substr($craft, 10, 2)
    ];
    $labels = [];
    foreach ($s as $k => $v)
        if ($v > 0)
            $labels[] = "$k+$v";
    return implode(' ', $labels);
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: /");
    exit;
}

// Enforce admin inactivity timeout to reduce the impact of unattended sessions.
$adminSessionTimeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity'] > $adminSessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: /admin?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

$username = $_SESSION['user'];
$clientIp = appGetClientIp();

// Secure fetch: Re-check Admin status (16) - use PDO with $pdoAdmin
$user_sql = "SELECT UserUID, Status FROM PS_UserData.dbo.Users_Master WHERE UserID = ?";
$user_stmt = $pdoAdmin->prepare($user_sql);
$user_stmt->execute([$username]);
$user_data = $user_stmt->fetch();
$adminUserUID = (int) ($user_data['UserUID'] ?? 0);
$userStatus = (int) ($user_data['Status'] ?? 0);

if (!in_array($userStatus, [16, 32, 48])) {
    header("Location: /account"); // Bounce non-admins back to safety
    exit;
}

// Enforce privileged account and IP whitelist when lists are populated.
$adminWhitelistUserCount = 0;
$adminWhitelistIpCount = 0;

// Count users in whitelist - use PDO
$countUsersStmt = $pdoAdmin->prepare("SELECT COUNT(*) AS Total FROM PS_UserData.dbo.AdminUserWhitelist");
$countUsersStmt->execute([]);
$countUsersRow = $countUsersStmt->fetch();
$adminWhitelistUserCount = (int) ($countUsersRow['Total'] ?? 0);

if ($adminWhitelistUserCount > 0) {
    // Check if user is whitelisted - use PDO
    $adminAllowedStmt = $pdoAdmin->prepare("SELECT TOP 1 UserUID FROM PS_UserData.dbo.AdminUserWhitelist WHERE UserUID = ? OR UserID = ?");
    $adminAllowed = false;
    $adminAllowedStmt->execute([$adminUserUID, $username]);
    if ($adminAllowedStmt->fetch()) {
        $adminAllowed = true;
    }

    if (!$adminAllowed) {
        appSecurityLog('Admin panel blocked: user not in AdminUserWhitelist. user=' . $username . ' uid=' . $adminUserUID . ' ip=' . $clientIp);
        appAuditLog('admin_panel_blocked_user_whitelist', [
            'admin' => $username,
            'admin_uid' => $adminUserUID,
            'ip' => $clientIp,
        ]);
        http_response_code(403);
        header('Location: /account');
        exit;
    }
}

// Count IPs in whitelist - use PDO
$countIpsStmt = $pdoAdmin->prepare("SELECT COUNT(*) AS Total FROM PS_UserData.dbo.AdminIPWhitelist");
$countIpsStmt->execute([]);
$countIpsRow = $countIpsStmt->fetch();
$adminWhitelistIpCount = (int) ($countIpsRow['Total'] ?? 0);

if ($adminWhitelistIpCount > 0) {
    // Check if IP is whitelisted - use PDO
    $ipAllowedStmt = $pdoAdmin->prepare("SELECT TOP 1 IPAddress FROM PS_UserData.dbo.AdminIPWhitelist WHERE IPAddress = ?");
    $ipAllowed = false;
    $ipAllowedStmt->execute([$clientIp]);
    if ($ipAllowedStmt->fetch()) {
        $ipAllowed = true;
    }

    if (!$ipAllowed) {
        appSecurityLog('Admin panel blocked: IP not in AdminIPWhitelist. user=' . $username . ' uid=' . $adminUserUID . ' ip=' . $clientIp);
        appAuditLog('admin_panel_blocked_ip_whitelist', [
            'admin' => $username,
            'admin_uid' => $adminUserUID,
            'ip' => $clientIp,
        ]);
        http_response_code(403);
        header('Location: /account');
        exit;
    }
}

// Load Permissions
$permissionSettings = fetchWebsiteSettings(['GM_Permissions', 'GMA_Permissions']);
$gm_perms = !empty($permissionSettings['GM_Permissions']) ? json_decode($permissionSettings['GM_Permissions'], true) : [];
$gma_perms = !empty($permissionSettings['GMA_Permissions']) ? json_decode($permissionSettings['GMA_Permissions'], true) : [];

$my_perms = [];
$is_full_admin = ($userStatus === 16);
if ($userStatus === 32) $my_perms = $gm_perms;
if ($userStatus === 48) $my_perms = $gma_perms;

function hasAdminAccess($reqView) {
    global $is_full_admin, $my_perms;
    if ($is_full_admin) return true;
    
    $reqView = strtoupper($reqView);
    if ($reqView === 'USEREDIT') $reqView = 'USERS';
    if ($reqView === 'CHAREDIT') $reqView = 'CHARS';
    if ($reqView === 'ITEMEDIT') $reqView = 'ITEMS';
    if ($reqView === 'ADMIN_WHITELIST') $reqView = 'PERMISSIONS_MANAGEMENT';
    
    return in_array($reqView, $my_perms);
}

// ✅ GLOBAL RATE LIMIT ON ADMIN PANEL ACCESS
$adminAccessRateKey = 'admin_access:' . strtolower((string) $username) . ':' . $clientIp;
$adminAccessRetryAfter = 0;
if (!appRateLimitAllow($adminAccessRateKey, 120, 600, $adminAccessRetryAfter)) { // Max 120 accesses per 10 minutes per admin+IP
    http_response_code(429);
    if ($adminAccessRetryAfter > 0) {
        header('Retry-After: ' . $adminAccessRetryAfter);
    }
    appSecurityLog('Admin panel rate limit exceeded for user=' . $username . ' ip=' . $clientIp . ' retry_after=' . (int) $adminAccessRetryAfter);
    
    $unlockTimestamp = time() + (int) $adminAccessRetryAfter;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Rate Limit | Admin Panel</title>
        <link rel="stylesheet" href="css/style.css">
        <style>
            .countdown-display {
                font-size: 2.5rem;
                font-weight: bold;
                color: var(--text-gold);
                font-family: 'Courier New', monospace;
                margin: 20px 0;
                letter-spacing: 2px;
            }
            .btn-small:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
        </style>
    </head>
    <body class="admin-page">
        <div class="main-wrapper" style="padding-top: 80px; padding-bottom: 80px;">
            <div class="content-area-box" style="max-width: 700px; margin: 0 auto; text-align: center;">
                <div class="content-header"><i class="fas fa-shield-alt"></i> Admin Protection</div>
                <div class="content-body" style="padding: 30px;">
                    <h2 style="color: var(--text-gold); margin-bottom: 15px;">Too Many Requests</h2>
                    <p style="margin-bottom: 10px;">You reached the admin navigation rate limit.</p>
                    <p style="margin-bottom: 20px; color: rgba(255,255,255,0.85);">Retry available in:</p>
                    <div class="countdown-display" id="countdownTimer" data-unlock="<?php echo (int) $unlockTimestamp; ?>">
                        <?php echo str_pad((int) ($adminAccessRetryAfter / 60), 2, '0', STR_PAD_LEFT); ?>:<?php echo str_pad((int) ($adminAccessRetryAfter % 60), 2, '0', STR_PAD_LEFT); ?>
                    </div>
                    <button id="retryBtn" class="btn-small" disabled>Try Again</button>
                    <a href="/account" class="btn-small" style="margin-left: 10px;">Back to Account</a>
                </div>
            </div>
        </div>
        
        <script>
        (function () {
            const timerEl = document.getElementById('countdownTimer');
            const retryBtn = document.getElementById('retryBtn');
            const unlockTs = parseInt(timerEl.getAttribute('data-unlock'), 10);
            
            retryBtn.addEventListener('click', function () {
                window.location.href = '/admin';
            });
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = Math.max(0, unlockTs - now);
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                
                timerEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                
                if (remaining <= 0) {
                    retryBtn.disabled = false;
                    retryBtn.style.opacity = '1';
                    retryBtn.style.cursor = 'pointer';
                    clearInterval(intervalId);
                } else {
                    retryBtn.disabled = true;
                    retryBtn.style.opacity = '0.5';
                    retryBtn.style.cursor = 'not-allowed';
                }
            }
            
            updateCountdown();
            const intervalId = setInterval(updateCountdown, 1000);
        })();
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Enforce Access
$current_view = strtoupper($_GET['view'] ?? 'DASHBOARD');
if (!hasAdminAccess($current_view)) {
    if (!$is_full_admin && count($my_perms) > 0) {
        $fallback = strtolower($my_perms[0]);
        header("Location: /admin?view=$fallback");
    } else {
        header("Location: /account");
    }
    exit;
}

// --- Handle Admin Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['admin_action']) || isset($_POST['action']))) {
    if (!appVerifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['admin_error'] = 'Invalid security token. Please refresh and try again.';
        header('Location: /admin');
        exit;
    }

    $action = $_POST['admin_action'] ?? $_POST['action'];
    $charID = isset($_POST['char_id']) ? (int) $_POST['char_id'] : 0;

    if ($action === 'save_permissions') {
        if ($userStatus === 16) { // Only ADM
            $p_32 = isset($_POST['perm_32']) && is_array($_POST['perm_32']) ? $_POST['perm_32'] : [];
            $p_48 = isset($_POST['perm_48']) && is_array($_POST['perm_48']) ? $_POST['perm_48'] : [];
            
            $j_32 = json_encode(array_map('strtoupper', $p_32));
            $j_48 = json_encode(array_map('strtoupper', $p_48));

            try {
                // GM
                $c32 = $pdoAdmin->prepare("SELECT 1 FROM PS_WebSite.dbo.Web_Settings WHERE SettingKey = 'GM_Permissions'");
                $c32->execute([]);
                if ($c32->fetch(PDO::FETCH_ASSOC)) {
                    $stmt = $pdoAdmin->prepare("UPDATE PS_WebSite.dbo.Web_Settings SET SettingValue = ? WHERE SettingKey = 'GM_Permissions'");
                    $stmt->execute([$j_32]);
                } else {
                    $stmt = $pdoAdmin->prepare("INSERT INTO PS_WebSite.dbo.Web_Settings (SettingKey, SettingValue) VALUES ('GM_Permissions', ?)");
                    $stmt->execute([$j_32]);
                }
                // GMA
                $c48 = $pdoAdmin->prepare("SELECT 1 FROM PS_WebSite.dbo.Web_Settings WHERE SettingKey = 'GMA_Permissions'");
                $c48->execute([]);
                if ($c48->fetch(PDO::FETCH_ASSOC)) {
                    $stmt = $pdoAdmin->prepare("UPDATE PS_WebSite.dbo.Web_Settings SET SettingValue = ? WHERE SettingKey = 'GMA_Permissions'");
                    $stmt->execute([$j_48]);
                } else {
                    $stmt = $pdoAdmin->prepare("INSERT INTO PS_WebSite.dbo.Web_Settings (SettingKey, SettingValue) VALUES ('GMA_Permissions', ?)");
                    $stmt->execute([$j_48]);
                }
                $_SESSION['admin_success'] = "Permissions have been successfully saved.";
            } catch (Throwable $e) {
                $_SESSION['admin_error'] = "Failed to save permissions.";
            }
        } else {
            $_SESSION['admin_error'] = "Only ADM (Status 16) can modify permissions.";
        }
        header("Location: /admin?view=permissions_management");
        exit;
    }

    if ($action === 'rename' && $charID > 0) {
        $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
        if (!empty($newName)) {
            // Update CharName in PS_GameData.dbo.Chars
            try {
                $stmt = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET CharName = ? WHERE CharID = ?");
                $stmt->execute([$newName, $charID]);
                header("Location: /admin?view=CharEdit&id=$charID&msg=renamed");
                exit;
            } catch (Throwable $e) { }
        }
    }

    if ($action === 'update_level' && $charID > 0) {
        $newLevel = isset($_POST['new_level']) ? (int) $_POST['new_level'] : 0;
        if ($newLevel >= 1 && $newLevel <= 80) { // Max level is now 80
            try {
                $stmt = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET [Level] = ? WHERE CharID = ?");
                $stmt->execute([$newLevel, $charID]);
                header("Location: /admin?view=CharEdit&id=$charID&msg=level_updated");
                exit;
            } catch (Throwable $e) { }
        }
    }

    if ($action === 'change_map' && $charID > 0) {
        $newMap = isset($_POST['new_map']) ? (int) $_POST['new_map'] : 0;
        if ($newMap >= 0 && $newMap <= 109) {
            // Update Map and reset coordinates to safe defaults
            try {
                $stmt = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET [Map] = ?, PosX = 50, PosY = 0, PosZ = 50 WHERE CharID = ?");
                $stmt->execute([$newMap, $charID]);
                header("Location: /admin?view=CharEdit&id=$charID&msg=map_changed");
                exit;
            } catch (Throwable $e) { }
        }
    }

    if ($action === 'send_gold' && $charID > 0) {
        $goldToAdd = isset($_POST['gold_amount']) ? (int) $_POST['gold_amount'] : 0;

        if ($goldToAdd > 500000000) {
            header("Location: /admin?view=CharEdit&id=$charID&msg=gold_limit");
            exit;
        }

        try {
            // Fetch current money
            $res = $pdoAdmin->prepare("SELECT Money FROM PS_GameData.dbo.Chars WHERE CharID = ?");
            $res->execute([$charID]);
            if ($currentData = $res->fetch(PDO::FETCH_ASSOC)) {
                $currentMoney = (int) $currentData['Money'];
                $newMoney = $currentMoney + $goldToAdd;

                // Clamp between 0 and 2,000,000,000 (Max INT is 2.1B)
                if ($newMoney < 0)
                    $newMoney = 0;
                if ($newMoney > 2000000000)
                    $newMoney = 2000000000;

                $stmt = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET Money = ? WHERE CharID = ?");
                $stmt->execute([$newMoney, $charID]);
                header("Location: /admin?view=CharEdit&id=$charID&msg=gold_sent");
                exit;
            }
        } catch (Throwable $e) { }
    }

    if ($action === 'delete_item' && isset($_POST['item_uid'])) {
        $itemUID = (int) $_POST['item_uid'];
        if ($itemUID > 0) {
            // Soft delete the item from inventory
            try {
                $stmt = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.CharItems SET Del = 1 WHERE ItemUID = ? AND CharID = ?");
                $stmt->execute([$itemUID, $charID]);
                header("Location: /admin?view=CharEdit&id=$charID&tab=Inventory&msg=item_deleted");
                exit;
            } catch (Throwable $e) { }
        }
    }

    if ($action === 'update_item' && isset($_POST['item_uid'])) {
        $itemUID = (int) $_POST['item_uid'];
        $charID = (int) $_POST['char_id'];

        $str = str_pad(min(50, max(0, (int) ($_POST['stat_str'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $dex = str_pad(min(50, max(0, (int) ($_POST['stat_dex'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $rec = str_pad(min(50, max(0, (int) ($_POST['stat_rec'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $int = str_pad(min(50, max(0, (int) ($_POST['stat_int'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $wis = str_pad(min(50, max(0, (int) ($_POST['stat_wis'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $luc = str_pad(min(50, max(0, (int) ($_POST['stat_luc'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $hp = str_pad(min(50, max(0, (int) ($_POST['stat_hp'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $sp = str_pad(min(50, max(0, (int) ($_POST['stat_sp'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $mp = str_pad(min(50, max(0, (int) ($_POST['stat_mp'] ?? 0))), 2, '0', STR_PAD_LEFT);
        $ench = str_pad(min(20, max(0, (int) ($_POST['stat_ench'] ?? 0))), 2, '0', STR_PAD_LEFT);

        $newCraftname = $str . $dex . $rec . $int . $wis . $luc . $hp . $sp . $mp . $ench;

        $gem1 = (int) ($_POST['gem1'] ?? 0);
        $gem2 = (int) ($_POST['gem2'] ?? 0);
        $gem3 = (int) ($_POST['gem3'] ?? 0);
        $gem4 = (int) ($_POST['gem4'] ?? 0);
        $gem5 = (int) ($_POST['gem5'] ?? 0);
        $gem6 = (int) ($_POST['gem6'] ?? 0);

        $updateSql = "UPDATE PS_GameData.dbo.CharItems 
                      SET Craftname = ?, Gem1 = ?, Gem2 = ?, Gem3 = ?, Gem4 = ?, Gem5 = ?, Gem6 = ? 
                      WHERE ItemUID = ? AND CharID = ?";
        try {
            $stmt = $pdoAdmin->prepare($updateSql);
            $stmt->execute([$newCraftname, $gem1, $gem2, $gem3, $gem4, $gem5, $gem6, $itemUID, $charID]);
            header("Location: /admin?view=CharEdit&id=$charID&tab=Inventory&msg=item_updated");
            exit;
        } catch (Throwable $e) { }
    }


    if ($action === 'delete_char' && $charID > 0) {
        try {
            $stmt = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET Del = 1 WHERE CharID = ?");
            $stmt->execute([$charID]);
            header("Location: /admin?view=CharEdit&id=$charID&msg=char_deleted");
            exit;
        } catch (Throwable $e) { }
    }

    if ($action === 'resurrect_char' && $charID > 0) {
        // Find UserUID first
        try {
            $q = $pdoAdmin->prepare("SELECT UserUID FROM PS_GameData.dbo.Chars WHERE CharID = ?");
            $q->execute([$charID]);
            if ($cData = $q->fetch(PDO::FETCH_ASSOC)) {
                $uUID = $cData['UserUID'];

                // Check occupied slots
                $occupied = [];
                $sRes = $pdoAdmin->prepare("SELECT [Slot] FROM PS_GameData.dbo.Chars WHERE UserUID = ? AND Del = 0");
                $sRes->execute([$uUID]);
                while ($sRow = $sRes->fetch(PDO::FETCH_ASSOC)) {
                    $occupied[] = (int) $sRow['Slot'];
                }

                $newSlot = -1;
                for ($i = 0; $i <= 4; $i++) {
                    if (!in_array($i, $occupied)) {
                        $newSlot = $i;
                        break;
                    }
                }

                if ($newSlot !== -1) {
                    $upd = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET Del = 0, [Slot] = ? WHERE CharID = ?");
                    $upd->execute([$newSlot, $charID]);
                    header("Location: /admin?view=CharEdit&id=$charID&msg=char_resurrected");
                    exit;
                } else {
                    header("Location: /admin?view=CharEdit&id=$charID&msg=resurrect_no_slot");
                    exit;
                }
            }
        } catch (Throwable $e) { }
    }
}

// === SQL Export intercept — PRIMA dell'HTML ===
if (strtoupper($_GET['view'] ?? '') === 'DROPS_EDITOR'
    && ($_GET['export'] ?? '') === 'sql'
    && $userStatus === 16) {
    include __DIR__ . '/adm-panel/drops_export.php';
    exit;
}

// === CSRF Keep-Alive endpoint — risposta JSON, nessun HTML ===
if (($_GET['csrf_refresh'] ?? '') === '1' && $userStatus === 16) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode([
        'ok'         => true,
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
    ]);
    exit;
}

// --- Fetch Top Bar Stats ---
$charsOnline = 0;
$usersToday = 0;
$totalAccounts = 0;
$totalChars = 0;

try {
    $qOnline = $pdoAdmin->query("SELECT COUNT(*) as Total FROM PS_GameData.dbo.Chars WHERE LoginStatus = 1");
    if ($row = $qOnline->fetch(PDO::FETCH_ASSOC)) $charsOnline = (int) $row['Total'];

    $qUsersToday = $pdoAdmin->query("SELECT COUNT(*) as Total FROM PS_UserData.dbo.Users_Master WHERE CAST(JoinDate AS DATE) = CAST(GETDATE() AS DATE)");
    if ($row = $qUsersToday->fetch(PDO::FETCH_ASSOC)) $usersToday = (int) $row['Total'];

    $qTotalAcc = $pdoAdmin->query("SELECT COUNT(*) as Total FROM PS_UserData.dbo.Users_Master");
    if ($row = $qTotalAcc->fetch(PDO::FETCH_ASSOC)) $totalAccounts = (int) $row['Total'];

    $qTotalChars = $pdoAdmin->query("SELECT COUNT(*) as Total FROM PS_GameData.dbo.Chars WHERE Del = 0");
    if ($row = $qTotalChars->fetch(PDO::FETCH_ASSOC)) $totalChars = (int) $row['Total'];
} catch (Throwable $e) {
    error_log('admin.php top bar stats failed: ' . $e->getMessage());
}
// --------------------------
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="assets/Duff.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Shaiya Duff</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>

<body class="admin-page">
    <?php $active_page = 'admin'; ?>
    <div class="main-wrapper">
        <?php include 'modules/header.php'; ?>
        <?php include 'modules/nav.php'; ?>

        <div class="content-area-box admin-container"
            style="padding: 0; overflow: hidden; border: 1px solid rgba(232, 200, 129, 0.1);">
            <!-- Admin Dashboard Header (Integrated) -->
            <div class="admin-top-stats">
                <div class="stats-left">
                    <div class="stat-badge bg-green"><?php echo ($charsOnline == 1) ? 'CHAR' : 'CHARS'; ?> ONLINE: <?php echo $charsOnline; ?></div>
                    <div class="stat-badge bg-blue">USERS REGISTERED TODAY: <?php echo $usersToday; ?></div>
                </div>
                <div class="stats-right">
                    <span>TOTAL ACCOUNTS: <b><?php echo $totalAccounts; ?></b></span>
                    <span>TOTAL CHARS: <b><?php echo $totalChars; ?></b></span>
                </div>
            </div>

            <!-- Horizontal Nav -->
            <nav class="admin-horizontal-nav">
                <?php
                // Note: User's selected view is now cached in $current_view
                function isActive($v, $current)
                {
                    return ($v === $current) ? 'active' : '';
                }
                ?>
                <ul>
                    <?php if (hasAdminAccess('DASHBOARD')): ?><li><a href="/admin?view=Dashboard" class="<?php echo isActive('DASHBOARD', $current_view); ?>">DASHBOARD</a></li><?php endif; ?>
                    <?php if (hasAdminAccess('SEND_NOTICE')): ?><li><a href="/admin?view=send_notice" class="<?php echo isActive('SEND_NOTICE', $current_view); ?>">SERVER NOTICE</a></li><?php endif; ?>
                    <?php if (hasAdminAccess('USERS')): ?><li><a href="/admin?view=Users" class="<?php echo isActive('USERS', $current_view); ?>">USERS</a></li><?php endif; ?>
                    <?php if (hasAdminAccess('CHARS')): ?><li><a href="/admin?view=Chars" class="<?php echo isActive('CHARS', $current_view); ?>">CHARS</a></li><?php endif; ?>
                    <?php if (hasAdminAccess('ITEMS')): ?><li><a href="/admin?view=Items" class="<?php echo isActive('ITEMS', $current_view); ?>">ITEMS</a></li><?php endif; ?>
                    <?php if (hasAdminAccess('GUILDS')): ?><li><a href="/admin?view=Guilds" class="<?php echo isActive('GUILDS', $current_view); ?>">GUILDS</a></li><?php endif; ?>
                    <?php if (hasAdminAccess('GIFTBOX')): ?><li><a href="/admin?view=Giftbox" class="<?php echo isActive('GIFTBOX', $current_view); ?>">GIFTBOX</a></li><?php endif; ?>
                    <?php if (hasAdminAccess('POINTS')): ?><li><a href="/admin?view=Points" class="<?php echo isActive('POINTS', $current_view); ?>">POINTS</a></li><?php endif; ?>
                    
                    <?php 
                    $hasWebsiteChild = hasAdminAccess('DROPS_EDITOR') || hasAdminAccess('DROPS_BLACKLIST_ADD') || hasAdminAccess('LUCKYCHEST_TOGGLE') || hasAdminAccess('DOWNLOADS_UPDATE') || hasAdminAccess('REGISTER_TOGGLE') || hasAdminAccess('PERMISSIONS_MANAGEMENT') || hasAdminAccess('ADMIN_WHITELIST') || hasAdminAccess('TASK_SCHEDULER');
                    if ($hasWebsiteChild): 
                    ?>
                    <li class="admin-has-dropdown">
                        <a href="#"
                            class="<?php echo ($current_view === 'OTHER' || strpos($current_view, 'DROPS') !== false || strpos($current_view, 'LUCKYCHEST') !== false || $current_view === 'DOWNLOADS_UPDATE' || $current_view === 'REGISTER_TOGGLE' || $current_view === 'PERMISSIONS_MANAGEMENT' || $current_view === 'ADMIN_WHITELIST' || $current_view === 'TASK_SCHEDULER') ? 'active' : ''; ?>">WEBSITE
                            <i class="fas fa-caret-down"></i></a>
                        <ul class="admin-dropdown-menu">
                            <?php if (hasAdminAccess('DROPS_EDITOR')): ?><li><a href="/admin?view=drops_editor">Drops Editor</a></li><?php endif; ?>
                            <?php if (hasAdminAccess('DROPS_BLACKLIST_ADD')): ?><li><a href="/admin?view=drops_blacklist_add">Drops Management</a></li><?php endif; ?>
                            <?php if (hasAdminAccess('LUCKYCHEST_TOGGLE')): ?><li><a href="/admin?view=luckychest_toggle">LuckyChest Management</a></li><?php endif; ?>
                            <?php if (hasAdminAccess('DOWNLOADS_UPDATE')): ?><li><a href="/admin?view=downloads_update">Downloads Management</a></li><?php endif; ?>
                            <?php if (hasAdminAccess('REGISTER_TOGGLE')): ?><li><a href="/admin?view=register_toggle">Register Management</a></li><?php endif; ?>
                            <?php if (hasAdminAccess('PERMISSIONS_MANAGEMENT')): ?><li><a href="/admin?view=permissions_management">Permissions Management</a></li><?php endif; ?>
                            <?php if (hasAdminAccess('ADMIN_WHITELIST')): ?><li><a href="/admin?view=admin_whitelist">Admin/IP Whitelist</a></li><?php endif; ?>
                            <?php if (hasAdminAccess('TASK_SCHEDULER')): ?><li><a href="/admin?view=task_scheduler">Task Scheduler</a></li><?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasAdminAccess('ACTIONLOG')): ?>
                        <li><a href="/admin?view=ActionLog"
                            class="<?php echo (strpos($current_view, 'LOG') !== false) ? 'active' : ''; ?>">LOGS</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <section class="admin-content-area" style="padding: 30px;">
                <?php if (isset($_SESSION['admin_success'])): ?>
                    <div class="alert alert-success"
                        style="background: rgba(72, 187, 120, 0.1); color: #48bb78; padding: 15px; border: 1px solid rgba(72, 187, 120, 0.2); margin-bottom: 20px; border-radius: 4px; font-size: 14px;">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['admin_success'];
                        unset($_SESSION['admin_success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['admin_error'])): ?>
                    <div class="alert alert-error"
                        style="background: rgba(229, 62, 62, 0.1); color: #e53e3e; padding: 15px; border: 1px solid rgba(229, 62, 62, 0.2); margin-bottom: 20px; border-radius: 4px; font-size: 14px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $_SESSION['admin_error'];
                        unset($_SESSION['admin_error']); ?>
                    </div>
                <?php endif; ?>
                <?php
                $view = $_GET['view'] ?? 'DASHBOARD';
                $view = strtoupper($view);
                $view_title = str_replace('_', ' ', $view);
                $view_title = str_replace(['TOGGLE', 'DOWNLOADS MANAGEMENT'], ['MANAGEMENT', 'DOWNLOADS MANAGEMENT'], $view_title);
                // Only replace standalone UPDATE or specific ones if needed, 
                // but let's just make it simpler:
                if (strpos($view, 'DOWNLOADS') !== false) {
                    $view_title = str_replace('UPDATE', 'MANAGEMENT', $view_title);
                }
                ?>


                <?php if ($view === 'DASHBOARD'): ?>
                    <?php
                    // --- Dashboard Stats ---
                    $topChars = [];
                    try {
                        $topCharsQ = $pdoAdmin->query("SELECT TOP 5 CharID, CharName, Level, Job, K1 as Kills FROM PS_GameData.dbo.Chars WHERE Del = 0 ORDER BY K1 DESC");
                        if ($topCharsQ) $topChars = $topCharsQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}

                    $recentUsers = [];
                    try {
                        $recentUsersQ = $pdoAdmin->query("SELECT TOP 8 UserID, Status, JoinDate, Point FROM PS_UserData.dbo.Users_Master ORDER BY UserUID DESC");
                        if ($recentUsersQ) $recentUsers = $recentUsersQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}
                    ?>

                    <!-- Bottom Grid: Top Killers + Recent Registrations -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">

                        <!-- Top Killers -->
                        <div style="background: rgba(10,10,10,0.5); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; overflow: hidden;">
                            <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-skull" style="color: #e8c881; font-size: 14px;"></i>
                                <span style="font-family: 'Futura PT', sans-serif; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #aaa;">Top Killers</span>
                            </div>
                            <table style="width: 100%; border-collapse: collapse; font-family: 'Futura PT', sans-serif;">
                                <thead>
                                    <tr style="background: rgba(0,0,0,0.3);">
                                        <th style="padding: 10px 20px; text-align: left; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">#</th>
                                        <th style="padding: 10px 20px; text-align: left; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Character</th>
                                        <th style="padding: 10px 20px; text-align: center; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Level</th>
                                        <th style="padding: 10px 20px; text-align: right; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Kills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topChars as $i => $tc): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                        <td style="padding: 13px 20px; color: #444; font-size: 12px; font-weight: 700;"><?php echo $i+1; ?></td>
                                        <td style="padding: 13px 20px;">
                                            <a href="/admin?view=CharEdit&id=<?php echo $tc['CharID']; ?>" class="user-link" style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($tc['CharName']); ?></a>
                                            <span style="margin-left: 8px; opacity: 0.5;"><img src="assets/class/<?php echo (int)$tc['Job']; ?>.webp" style="width: 16px; vertical-align: middle;"></span>
                                        </td>
                                        <td style="padding: 13px 20px; text-align: center; color: #e8c881; font-size: 13px;"><?php echo $tc['Level']; ?></td>
                                        <td style="padding: 13px 20px; text-align: right; color: #48bb78; font-weight: 700; font-size: 14px;"><?php echo number_format($tc['Kills']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($topChars)): ?>
                                    <tr><td colspan="4" style="padding: 40px; text-align: center; color: #444;">No data available.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Recent Registrations -->
                        <div style="background: rgba(10,10,10,0.5); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; overflow: hidden;">
                            <div style="padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-user-clock" style="color: #e8c881; font-size: 14px;"></i>
                                <span style="font-family: 'Futura PT', sans-serif; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #aaa;">Recent Registrations</span>
                            </div>
                            <table style="width: 100%; border-collapse: collapse; font-family: 'Futura PT', sans-serif;">
                                <thead>
                                    <tr style="background: rgba(0,0,0,0.3);">
                                        <th style="padding: 10px 20px; text-align: left; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Username</th>
                                        <th style="padding: 10px 20px; text-align: center; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Status</th>
                                        <th style="padding: 10px 20px; text-align: right; font-size: 10px; color: #555; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $ru): ?>
                                    <?php
                                        $ruStatus = (int)($ru['Status'] ?? 0);
                                        $ruLabel = 'Player'; $ruColor = '#555';
                                        if ($ruStatus == 16) { $ruLabel = 'ADM'; $ruColor = '#48bb78'; }
                                        elseif ($ruStatus == 32) { $ruLabel = 'GM'; $ruColor = '#3498db'; }
                                        elseif ($ruStatus == 48) { $ruLabel = 'GMA'; $ruColor = '#e8c881'; }
                                    ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                        <td style="padding: 13px 20px;">
                                            <a href="/admin?view=USERS&search_user=<?php echo urlencode($ru['UserID']); ?>" class="user-link" style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($ru['UserID']); ?></a>
                                        </td>
                                        <td style="padding: 13px 20px; text-align: center;">
                                            <span style="font-size: 10px; font-weight: 700; color: <?php echo $ruColor; ?>; text-transform: uppercase; letter-spacing: 1px;"><?php echo $ruLabel; ?></span>
                                        </td>
                                        <td style="padding: 13px 20px; text-align: right; color: #666; font-size: 12px;"><?php echo date('d M Y', strtotime($ru['JoinDate'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentUsers)): ?>
                                    <tr><td colspan="3" style="padding: 40px; text-align: center; color: #444;">No data available.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                <?php elseif ($view === 'PERMISSIONS_MANAGEMENT'): ?>
                    <?php
                    // Get current settings
                    $permissionSettings = fetchWebsiteSettings(['GM_Permissions', 'GMA_Permissions']);
                    $perms_32 = !empty($permissionSettings['GM_Permissions']) ? json_decode($permissionSettings['GM_Permissions'], true) : [];
                    $perms_48 = !empty($permissionSettings['GMA_Permissions']) ? json_decode($permissionSettings['GMA_Permissions'], true) : [];
                    
                    $pagesList = [
                        'DASHBOARD' => 'Dashboard Overview',
                        'SEND_NOTICE' => 'Server Notice (Announcement)',
                        'USERS' => 'Users Management & Edit',
                        'CHARS' => 'Chars Management & Edit',
                        'ITEMS' => 'Items Management',
                        'GUILDS' => 'Guilds Management',
                        'GIFTBOX' => 'Giftbox Management',
                        'POINTS' => 'Points Management',
                        'DROPS_EDITOR' => 'Drops Editor - Modifica drop rate mob',
                        'DROPS_BLACKLIST_ADD' => 'Drops Blacklist Management',
                        'LUCKYCHEST_TOGGLE' => 'LuckyChest Management',
                        'DOWNLOADS_UPDATE' => 'Downloads Management',
                        'REGISTER_TOGGLE' => 'Registration Toggle',
                        'PERMISSIONS_MANAGEMENT' => 'Permissions Management',
                        'ADMIN_WHITELIST' => 'Admin/IP Whitelist Management',
                        'TASK_SCHEDULER' => 'Task Scheduler Management',
                        'ACTIONLOG' => 'Action Logs Viewer'
                    ];
                    ?>
                    <div class="char-edit-section" style="max-width: 1000px; margin: 40px auto; padding: 30px; background: rgba(15,15,15,0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px;">
                        <h3 style="font-size: 20px; color: #e8c881; text-align: center; margin-bottom: 10px;">PERMISSIONS MANAGEMENT</h3>
                        <p style="color: #888; text-align: center; font-size: 13px; margin-bottom: 40px;">Check the boxes to allow staff ranks access to specific admin panel sections. ADM (16) always has full access.</p>
                        
                        <form action="/admin" method="POST">
                            <input type="hidden" name="admin_action" value="save_permissions">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; font-size: 14px;">Panel Section</th>
                                        <th style="text-align: center; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #3498db; font-size: 14px; width: 150px;">GM (Status 32)</th>
                                        <th style="text-align: center; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #e8c881; font-size: 14px; width: 150px;">GMA (Status 48)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagesList as $k => $label): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); background: rgba(255,255,255,0.01);">
                                        <td style="padding: 15px; color: #ccc; font-size: 13px;"><?php echo htmlspecialchars($label); ?> <span style="color:#555; font-size:10px; margin-left:10px;">(<?php echo $k; ?>)</span></td>
                                        <td style="padding: 15px; text-align: center; border-left: 1px solid rgba(255,255,255,0.02);">
                                            <input type="checkbox" name="perm_32[]" value="<?php echo $k; ?>" <?php echo in_array($k, $perms_32) ? 'checked' : ''; ?> style="transform: scale(1.3); cursor: pointer;">
                                        </td>
                                        <td style="padding: 15px; text-align: center; border-left: 1px solid rgba(255,255,255,0.02);">
                                            <input type="checkbox" name="perm_48[]" value="<?php echo $k; ?>" <?php echo in_array($k, $perms_48) ? 'checked' : ''; ?> style="transform: scale(1.3); cursor: pointer;">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div style="margin-top: 40px; text-align: center;">
                                <button type="submit" class="admin-btn" style="padding: 10px 40px; font-size: 14px;">SAVE PERMISSIONS</button>
                            </div>
                        </form>
                    </div>
                <?php elseif ($view === 'ADMIN_WHITELIST'): ?>
                    <?php
                    $adminWhitelistUsers = [];
                    $adminWhitelistIps = [];

                    try {
                        $wQ = $pdoAdmin->query("SELECT UserUID, UserID FROM PS_UserData.dbo.AdminUserWhitelist ORDER BY UserID ASC");
                        if ($wQ) $adminWhitelistUsers = $wQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}

                    try {
                        $wIpQ = $pdoAdmin->query("SELECT IPAddress FROM PS_UserData.dbo.AdminIPWhitelist ORDER BY IPAddress ASC");
                        if ($wIpQ) $adminWhitelistIps = $wIpQ->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}
                    ?>
                    <div class="char-edit-section" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
                        <div style="text-align: center; margin-bottom: 35px;">
                            <h3 style="margin-bottom: 10px; color: #e8c881;">ADMIN/IP WHITELIST MANAGEMENT</h3>
                            <p style="color: #666; font-size: 14px;">Add privileged admin accounts and trusted admin IPs. Removal is intentionally disabled in this panel.</p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                            <div style="display: flex; flex-direction: column; gap: 30px;">
                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 28px;">
                                    <h4 style="color: #fff; margin-bottom: 20px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Add Privileged Admin</h4>
                                    <form action="/admin_actions" method="POST" style="display: block;">
                                        <input type="hidden" name="action" value="admin_whitelist_add_user">
                                        <input type="hidden" name="return_view" value="ADMIN_WHITELIST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="char-input-group" style="margin-bottom: 14px;">
                                            <input type="text" name="admin_userid" class="char-input" maxlength="32" required placeholder="Enter admin UserID..." style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px;">
                                        </div>
                                        <button type="submit" class="btn-action btn-sm" style="width: 100%;">ADD ADMIN USER</button>
                                    </form>
                                </div>

                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 28px;">
                                    <h4 style="color: #fff; margin-bottom: 20px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Add Trusted Admin IP</h4>
                                    <form action="/admin_actions" method="POST" style="display: block;">
                                        <input type="hidden" name="action" value="admin_whitelist_add_ip">
                                        <input type="hidden" name="return_view" value="ADMIN_WHITELIST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="char-input-group" style="margin-bottom: 14px;">
                                            <input type="text" name="admin_ip" class="char-input" maxlength="45" required placeholder="Enter IPv4 or IPv6..." style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px;">
                                        </div>
                                        <button type="submit" class="btn-action btn-sm" style="width: 100%;">ADD ADMIN IP</button>
                                    </form>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 30px;">
                                <div style="background: rgba(0,0,0,0.25); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 24px; min-height: 250px;">
                                    <h4 style="color: #fff; margin-bottom: 18px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">Whitelisted Admin Accounts</h4>
                                    <?php if (!empty($adminWhitelistUsers)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 10px; max-height: 280px; overflow-y: auto; padding-right: 4px;">
                                            <?php foreach ($adminWhitelistUsers as $wUser): ?>
                                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                                                    <span style="color: #e8c881; font-weight: 600;"><?php echo htmlspecialchars((string) ($wUser['UserID'] ?? '')); ?></span>
                                                    <span style="color: #666; font-size: 11px;">UID: <?php echo (int) ($wUser['UserUID'] ?? 0); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; color: #555; padding: 60px 10px;">No admin users whitelisted yet.</div>
                                    <?php endif; ?>
                                </div>

                                <div style="background: rgba(0,0,0,0.25); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 24px; min-height: 250px;">
                                    <h4 style="color: #fff; margin-bottom: 18px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">Whitelisted Admin IPs</h4>
                                    <?php if (!empty($adminWhitelistIps)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 10px; max-height: 280px; overflow-y: auto; padding-right: 4px;">
                                            <?php foreach ($adminWhitelistIps as $wIp): ?>
                                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                                                    <span style="color: #e8c881; font-weight: 600;"><?php echo htmlspecialchars((string) ($wIp['IPAddress'] ?? '')); ?></span>
                                                    <i class="fas fa-network-wired" style="color: #444;"></i>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; color: #555; padding: 60px 10px;">No admin IPs whitelisted yet.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($view === 'GUILD_OVERVIEW'): ?>
                    <?php
                    $guildID = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                    $tab = $_GET['tab'] ?? 'Overview';
                    $g = false;
                    try {
                        $q = $pdoAdmin->prepare("SELECT * FROM PS_GameData.dbo.Guilds WHERE GuildID = ? AND Del = 0");
                        $q->execute([$guildID]);
                        $g = $q->fetch(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}

                    if (!$g) {
                        echo '<div class="admin-error">Guild not found.</div>';
                    } else {
                        // Get member count
                        $memberCount = 0;
                        try {
                            $countQ = $pdoAdmin->prepare("SELECT COUNT(*) as member_count FROM PS_GameData.dbo.GuildChars WHERE GuildID = ? AND Del = 0");
                            $countQ->execute([$guildID]);
                            $memberCount = (int) ($countQ->fetch(PDO::FETCH_ASSOC)['member_count'] ?? 0);
                        } catch (Throwable $e) {}
                        ?>
                        <div class="guild-overview-container" style="max-width: 1100px; margin: 20px auto;">
                            <!-- Header -->
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <h2
                                    style="color: #fff; font-size: 22px; font-weight: 800; font-family: 'Futura PT', sans-serif; letter-spacing: 2px; margin: 0; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($g['GuildName']); ?>
                                </h2>
                                <div class="faction-badge"
                                    style="background: <?php echo ($g['Country'] == 0 ? 'rgba(52, 152, 219, 0.1)' : 'rgba(231, 76, 60, 0.1)'); ?>; color: <?php echo ($g['Country'] == 0 ? '#3498db' : '#e74c3c'); ?>; padding: 5px 15px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid <?php echo ($g['Country'] == 0 ? 'rgba(52, 152, 219, 0.2)' : 'rgba(231, 76, 60, 0.2)'); ?>;">
                                    <img src="assets/<?php echo ($g['Country'] == 0 ? 'aol.webp' : 'uof.webp'); ?>"
                                        style="width: 14px; margin-right: 8px; vertical-align: middle;">
                                </div>

                            <!-- Tabs -->
                            <div class="admin-tabs"
                                style="margin-bottom: 30px; display: flex; gap: 5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 1px;">
                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $guildID; ?>&tab=Overview"
                                    class="tab-link <?php echo ($tab === 'Overview') ? 'active' : ''; ?>"
                                    style="padding: 12px 25px; color: <?php echo ($tab === 'Overview' ? '#e8c881' : '#666'); ?>; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid <?php echo ($tab === 'Overview' ? '#e8c881' : 'transparent'); ?>; transition: all 0.2s;">General
                                    Info</a>
                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $guildID; ?>&tab=Members"
                                    class="tab-link <?php echo ($tab === 'Members') ? 'active' : ''; ?>"
                                    style="padding: 12px 25px; color: <?php echo ($tab === 'Members' ? '#e8c881' : '#666'); ?>; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid <?php echo ($tab === 'Members' ? '#e8c881' : 'transparent'); ?>; transition: all 0.2s;">Members
                                    (<?php echo $memberCount; ?>)</a>
                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $guildID; ?>&tab=Warehouse"
                                    class="tab-link <?php echo ($tab === 'Warehouse') ? 'active' : ''; ?>"
                                    style="padding: 12px 25px; color: <?php echo ($tab === 'Warehouse' ? '#e8c881' : '#666'); ?>; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid <?php echo ($tab === 'Warehouse' ? '#e8c881' : 'transparent'); ?>; transition: all 0.2s;">Guild
                                    Warehouse</a>
                            </div>

                            <?php if ($tab === 'Overview'): ?>
                                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px;">
                                    <div>
                                        <div class="char-edit-section"
                                            style="margin-bottom: 30px; padding: 25px; background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.03); border-radius: 8px;">
                                            <h3 style="font-size: 14px; margin-bottom: 20px;">RENAME GUILD</h3>
                                            <form class="char-edit-form" action="/admin_actions" method="POST"
                                                style="display: flex; gap: 15px; align-items: flex-end;">
                                                <input type="hidden" name="action" value="guild_rename">
                                                <input type="hidden" name="guild_id" value="<?php echo $guildID; ?>">
                                                <input type="hidden" name="return_view" value="GUILD_OVERVIEW">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <div class="char-input-group" style="flex: 1; margin: 0;">
                                                    <input type="text" name="new_name" class="char-input"
                                                        value="<?php echo htmlspecialchars($g['GuildName']); ?>" required
                                                        style="height: 40px; background: rgba(0,0,0,0.3);">
                                                </div>
                                                <button type="submit" class="btn-action btn-sm"
                                                    style="height: 40px; min-width: 120px;">UPDATE</button>
                                            </form>
                                        </div>

                                        <div class="char-edit-section"
                                            style="padding: 25px; background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.03); border-radius: 8px;">
                                            <h3 style="font-size: 14px; margin-bottom: 20px;">CHANGE LEADER</h3>
                                            <form class="char-edit-form" action="/admin_actions" method="POST"
                                                style="display: flex; gap: 15px; align-items: flex-end;">
                                                <input type="hidden" name="action" value="guild_change_leader">
                                                <input type="hidden" name="guild_id" value="<?php echo $guildID; ?>">
                                                <input type="hidden" name="return_view" value="GUILD_OVERVIEW">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <div class="char-input-group" style="flex: 1; margin: 0;">
                                                    <select name="new_leader_id" class="char-input"
                                                        style="height: 40px; background: rgba(0,0,0,0.3);">
                                                        <?php
                                                        try {
                                                            $membersQ = $pdoAdmin->prepare("SELECT GC.CharID, C.CharName, C.Job FROM PS_GameData.dbo.GuildChars GC JOIN PS_GameData.dbo.Chars C ON C.CharID = GC.CharID WHERE GC.GuildID = ? AND GC.Del = 0 ORDER BY C.CharName ASC");
                                                            $membersQ->execute([$guildID]);
                                                            while ($m = $membersQ->fetch(PDO::FETCH_ASSOC)) {
                                                                $selected = ($m['CharID'] == $g['MasterCharID']) ? 'selected' : '';
                                                                echo "<option value='{$m['CharID']}' $selected>" . htmlspecialchars($m['CharName']) . " (ID: {$m['CharID']})</option>";
                                                            }
                                                        } catch (Throwable $e) {}
                                                        ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn-action btn-sm"
                                                    style="height: 40px; min-width: 120px; background: rgba(52, 152, 219, 0.2); border-color: #3498db;">CHANGE</button>
                                            </form>
                                        </div>
                                    </div>

                                    <div>
                                        <table class="char-info-table" style="margin: 0; background: rgba(0,0,0,0.2);">
                                            <thead>
                                                <tr>
                                                    <th colspan="2" class="header-main" style="font-size: 11px; padding: 12px;">
                                                        STATISTICS & INFO</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px; width: 40%;">Guild ID</td>
                                                    <td style="color: #eee; font-weight: 600;"><?php echo $guildID; ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Leader</td>
                                                    <td style="color: #e8c881; font-weight: 600;">
                                                        <a href="/admin?view=CharEdit&id=<?php echo $g['MasterCharID']; ?>"
                                                            class="user-link"
                                                            style="color: inherit;"><?php echo htmlspecialchars($g['MasterName']); ?></a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Members</td>
                                                    <td><?php echo $memberCount; ?> / 250</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Guild Points</td>
                                                    <td style="color: #48bb78;"><?php echo number_format($g['GuildPoint']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Etin Amount</td>
                                                    <td><?php echo number_format($g['Etin'] ?? 0); ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #666; font-size: 11px;">Created</td>
                                                    <td style="color: #888; font-size: 10px;">
                                                        <?php echo date('Y-m-d H:i', strtotime($g['CreateDate'])); ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            <?php elseif ($tab === 'Members'): ?>
                                <div class="chars-table-container">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px;">Slot</th>
                                                <th style="width: 40px;"></th>
                                                <th>Name</th>
                                                <th>Level</th>
                                                <th>Class</th>
                                                <th>Join Date</th>
                                                <th style="width: 100px; text-align: center;">Leader</th>
                                                <th style="text-align: right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $guildMembers = [];
                                            try {
                                                $membersQ = $pdoAdmin->prepare("SELECT GC.*, C.CharName, C.Job, C.Level, C.RegDate FROM PS_GameData.dbo.GuildChars GC JOIN PS_GameData.dbo.Chars C ON C.CharID = GC.CharID WHERE GC.GuildID = ? AND GC.Del = 0 ORDER BY GC.GuildLevel ASC, C.CharName ASC");
                                                $membersQ->execute([$guildID]);
                                                $guildMembers = $membersQ->fetchAll(PDO::FETCH_ASSOC);
                                            } catch (Throwable $e) {}
                                            foreach ($guildMembers as $m):
                                                $isLeader = ($m['CharID'] == $g['MasterCharID']);
                                                ?>
                                                <tr>
                                                    <td style="color: #555;"><?php echo $m['GuildLevel']; ?></td>
                                                    <td><img src="assets/class/<?php echo (int) $m['Job']; ?>.webp"
                                                            style="width: 20px; vertical-align: middle; opacity: 0.8;"></td>
                                                    <td><a href="/admin?view=CharEdit&id=<?php echo $m['CharID']; ?>"
                                                            class="user-link"><?php echo htmlspecialchars($m['CharName']); ?></a></td>
                                                    <td><?php echo $m['Level']; ?></td>
                                                    <td style="font-size: 11px; color: #888;"><?php echo getJobName((int) $m['Job']); ?>
                                                    </td>
                                                    <td style="color: #666; font-size: 11px;">
                                                        <?php echo date('Y-m-d', strtotime($m['RegDate'])); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if ($isLeader): ?>
                                                            <i class="fas fa-crown" style="color: #e8c881; font-size: 14px;"
                                                                title="Guild Master"></i>
                                                        <?php else: ?>
                                                            <span style="color: #333;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: right;">
                                                        <a href="/admin?view=CharEdit&id=<?php echo $m['CharID']; ?>"
                                                            class="inventory-icon-btn btn-edit" title="Edit Character"><i
                                                                class="fas fa-user-cog"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php elseif ($tab === 'Warehouse'): ?>
                                <div class="styled-inventory-container">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                        <h3
                                            style="font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: #e8c881; border-left: 3px solid #e8c881; padding-left: 15px;">
                                            Guild Bank Contents</h3>
                                    </div>

                                    <table class="users-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 350px; text-align: left; padding-left: 20px;">ITEM NAME</th>
                                                <th style="width: 80px; text-align: center;">SLOT</th>
                                                <th style="width: 80px; text-align: center;">COUNT</th>
                                                <th style="text-align: center;">SOCKETS / GEMS</th>
                                                <th style="width: 150px; text-align: center;">TIMESTAMP</th>
                                                <th style="width: 80px; text-align: center;">ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $warehouseSql = "
                                                SELECT 
                                                    ci.ItemUID, ci.ItemID, ci.Slot, ci.Count, ci.Maketime, ci.Craftname,
                                                    ci.Gem1, ci.Gem2, ci.Gem3, ci.Gem4, ci.Gem5, ci.Gem6,
                                                    i.ItemName, i.Type, i.TypeID, i.ReqLevel, i.Defensefighter, i.Defensemage,
                                                    i.Attrib, i.Range, i.ConstHP, i.ConstSP, i.ConstMP,
                                                    i.ConstStr, i.ConstDex, i.ConstRec, i.ConstInt, i.ConstWis, i.ConstLuc
                                                FROM [PS_GameData].[dbo].[GuildStoredItems] ci
                                                INNER JOIN [PS_GameDefs].[dbo].[Items] i ON i.ItemID = ci.ItemID
                                                WHERE ci.GuildID = ?
                                                ORDER BY ci.Slot ASC
                                            ";
                                            $wStmt = null;
                                            try {
                                                $wStmt = $pdoAdmin->prepare($warehouseSql);
                                                $wStmt->execute([$guildID]);
                                            } catch (Throwable $e) {}

                                            $foundItems = false;
                                            while ($wStmt && ($item = $wStmt->fetch(PDO::FETCH_ASSOC))):
                                                $foundItems = true;
                                                $gems = [];
                                                for ($i = 1; $i <= 6; $i++) {
                                                    $gemID = (int) $item["Gem$i"];
                                                    if ($gemID > 0) {
                                                        $actualGemID = 30000 + $gemID;
                                                        try {
                                                            $gQ = $pdoAdmin->prepare("SELECT ItemName FROM PS_GameDefs.dbo.Items WHERE ItemID = ?");
                                                            $gQ->execute([$actualGemID]);
                                                            if ($gRes = $gQ->fetch(PDO::FETCH_ASSOC)) {
                                                                $gems[] = ['name' => $gRes['ItemName'], 'id' => $gemID];
                                                            }
                                                        } catch (Throwable $e) {}
                                                    }
                                                }

                                                $baseStats = [
                                                    'HP' => (int) ($item['ConstHP'] ?? 0),
                                                    'SP' => (int) ($item['ConstSP'] ?? 0),
                                                    'MP' => (int) ($item['ConstMP'] ?? 0),
                                                    'STR' => (int) ($item['ConstStr'] ?? 0),
                                                    'DEX' => (int) ($item['ConstDex'] ?? 0),
                                                    'REC' => (int) ($item['ConstRec'] ?? 0),
                                                    'INT' => (int) ($item['ConstInt'] ?? 0),
                                                    'WIS' => (int) ($item['ConstWis'] ?? 0),
                                                    'LUC' => (int) ($item['ConstLuc'] ?? 0),
                                                ];

                                                $bonusStats = [
                                                    'Enchant' => 0,
                                                    'STR' => 0,
                                                    'DEX' => 0,
                                                    'REC' => 0,
                                                    'INT' => 0,
                                                    'WIS' => 0,
                                                    'LUC' => 0,
                                                    'HP' => 0,
                                                    'SP' => 0,
                                                    'MP' => 0
                                                ];

                                                if (!empty($item['Craftname'])) {
                                                    $cn = $item['Craftname'];
                                                    $bonusStats['STR'] = (int) substr($cn, 0, 2);
                                                    $bonusStats['DEX'] = (int) substr($cn, 2, 2);
                                                    $bonusStats['REC'] = (int) substr($cn, 4, 2);
                                                    $bonusStats['INT'] = (int) substr($cn, 6, 2);
                                                    $bonusStats['WIS'] = (int) substr($cn, 8, 2);
                                                    $bonusStats['LUC'] = (int) substr($cn, 10, 2);
                                                    $bonusStats['HP'] = (int) substr($cn, 12, 2) * 100;
                                                    $bonusStats['SP'] = (int) substr($cn, 14, 2) * 100;
                                                    $bonusStats['MP'] = (int) substr($cn, 16, 2) * 100;

                                                    $enchantVal = (int) substr($cn, 18, 2);
                                                    if ($enchantVal >= 50)
                                                        $enchantVal -= 50;
                                                    $bonusStats['Enchant'] = $enchantVal;
                                                }
                                                ?>
                                                <tr>
                                                    <td style="text-align: left; padding-left: 20px; overflow: visible;">
                                                        <div class="item-name-wrapper">
                                                            <div style="color: #eee; font-weight: 500; font-size: 13px;">
                                                                <?php echo htmlspecialchars($item['ItemName']); ?>
                                                                <?php if ($bonusStats['Enchant'] > 0): ?>
                                                                    <span
                                                                        class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div style="color: #555; font-size: 10px;">UID:
                                                                <?php echo $item['ItemUID']; ?> | ID: <?php echo $item['ItemID']; ?>
                                                            </div>

                                                            <div class="item-stats-tooltip">
                                                                <div class="tooltip-header">
                                                                    <span><?php echo htmlspecialchars($item['ItemName']); ?></span>
                                                                    <?php if ($bonusStats['Enchant'] > 0): ?>
                                                                        <span
                                                                            class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if (($item['Defensefighter'] ?? 0) > 0): ?>
                                                                    <div class="stat-line" style="color: #fff;">Phys. defense
                                                                        <?php echo $item['Defensefighter']; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (($item['Defensemage'] ?? 0) > 0): ?>
                                                                    <div class="stat-line" style="color: #fff;">Mag. defense
                                                                        <?php echo $item['Defensemage']; ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php
                                                                $allStats = ['HP' => 'hp', 'SP' => 'sp', 'MP' => 'mp', 'STR' => 'str', 'DEX' => 'dex', 'REC' => 'rec', 'INT' => 'int', 'WIS' => 'wis', 'LUC' => 'luc'];
                                                                foreach ($allStats as $label => $cssClass):
                                                                    $base = $baseStats[$label];
                                                                    $bonus = $bonusStats[$label];
                                                                    if ($base > 0 || $bonus > 0): ?>
                                                                        <div class="stat-line">
                                                                            <span
                                                                                class="stat-label-<?php echo $cssClass; ?>"><?php echo $label; ?></span>
                                                                            <span class="stat-v-base">+<?php echo $base + $bonus; ?></span>
                                                                            <?php if ($bonus > 0): ?><span
                                                                                    class="stat-v-bonus">+<?php echo $bonus; ?></span><?php endif; ?>
                                                                        </div>
                                                                    <?php endif; endforeach; ?>

                                                                <?php if (isset($item['ReqLevel']) && $item['ReqLevel'] > 0): ?>
                                                                    <div class="req-level"
                                                                        style="color:#eee; margin-top: 10px; font-size: 11px; opacity: 0.6;">
                                                                        Requires Level <?php echo $item['ReqLevel']; ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="text-align: center; color: #888; font-size: 11px;">
                                                        #<?php echo $item['Slot']; ?></td>
                                                    <td style="text-align: center; color: #da9f50; font-weight: 700;">
                                                        <?php echo number_format($item['Count']); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php if (empty($gems)): ?><span style="color: #333;">-</span><?php else: ?>
                                                            <div
                                                                style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                                                <?php foreach ($gems as $gem): ?>
                                                                    <div class="gem-badge"
                                                                        style="font-size: 9px; color: #e8c881; background: rgba(232, 200, 129, 0.05); padding: 2px 8px; border-radius: 10px; border: 1px solid rgba(232, 200, 129, 0.1); width: fit-content;">
                                                                        <i class="fas fa-gem"
                                                                            style="font-size: 7px; margin-right: 4px; opacity: 0.6;"></i>
                                                                        <?php echo htmlspecialchars($gem['name']); ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center; color: #666; font-size: 11px;">
                                                        <?php echo date('Y-m-d H:i', strtotime($item['Maketime'])); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <form action="/admin_actions" method="POST" class="js-confirm-submit"
                                                            data-confirm="PERMANENTLY delete this guild item?">
                                                            <input type="hidden" name="action" value="guild_warehouse_item_delete">
                                                            <input type="hidden" name="guild_id" value="<?php echo $guildID; ?>">
                                                            <input type="hidden" name="item_uid"
                                                                value="<?php echo $item['ItemUID']; ?>">
                                                            <input type="hidden" name="return_view" value="GUILD_OVERVIEW">
                                                            <input type="hidden" name="tab" value="Warehouse">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <button type="submit" class="inventory-icon-btn btn-delete"
                                                                title="Delete Permanent"><i class="fas fa-trash-alt"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                            <?php if (!$foundItems): ?>
                                                <tr>
                                                    <td colspan="6" style="text-align: center; padding: 60px; color: #444;">
                                                        <i class="fas fa-warehouse"
                                                            style="font-size: 30px; opacity: 0.1; margin-bottom: 15px;"></i><br>
                                                        Guild warehouse is empty.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php } ?>
                <?php elseif ($view === 'GUILDS'): ?>
                    <?php
                    $searchGuild = isset($_GET['search_guild']) ? trim($_GET['search_guild']) : '';
                    ?>
                    <div class="guilds-section">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px;">
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <form action="/admin" method="GET" style="display: flex; gap: 10px;">
                                    <input type="hidden" name="view" value="Guilds">
                                    <div style="position: relative;">
                                        <i class="fas fa-search"
                                            style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #444; font-size: 13px;"></i>
                                        <input type="text" name="search_guild" class="char-input"
                                            value="<?php echo htmlspecialchars($searchGuild); ?>"
                                            placeholder="Search Guild..."
                                            style="background: rgba(255,255,255,0.02); border-color: #222; color: #fff; height: 38px; padding-left: 45px; width: 250px; font-size: 13px; border-radius: 4px;">
                                    </div>
                                    <button type="submit" class="btn-action btn-sm">SEARCH</button>
                                </form>
                            </div>
                        </div>

                        <div class="chars-table-container">
                            <table class="chars-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th style="width: 80px;"></th>
                                        <th>Guild Name</th>
                                        <th>Guild Master</th>
                                        <th>Points</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $guildQuery = "SELECT GuildID, Country, GuildName, MasterName, MasterCharID, GuildPoint, CreateDate FROM PS_GameData.dbo.Guilds WHERE Del = 0";
                                    $guildParams = [];
                                    if ($searchGuild) {
                                        $guildQuery .= " AND GuildName LIKE ?";
                                        $guildParams[] = '%' . $searchGuild . '%';
                                    }
                                    $guildQuery .= " ORDER BY GuildPoint DESC";

                                    $guildResults = [];
                                    try {
                                        $q = $pdoAdmin->prepare($guildQuery);
                                        $q->execute($guildParams);
                                        $guildResults = $q->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Throwable $e) {}
                                    $found = false;
                                    foreach ($guildResults as $row) {
                                        $found = true;
                                        $factionImg = ($row['Country'] == 0) ? 'aol.webp' : 'uof.webp';
                                        $formattedDate = date('d M H:i', strtotime($row['CreateDate']));
                                        ?>
                                        <tr>
                                            <td style="color: #666;"><?php echo $row['GuildID']; ?></td>
                                            <td>
                                                <div class="faction-icon">
                                                    <img src="assets/<?php echo $factionImg; ?>"
                                                        style="width: 20px; vertical-align: middle;">
                                                </div>
                                            </td>
                                            <td>
                                                <a href="/admin?view=GUILD_OVERVIEW&id=<?php echo $row['GuildID']; ?>"
                                                    class="user-link"
                                                    style="color: #ccd; font-weight: 500; font-size: 14px; text-decoration: none;"><?php echo htmlspecialchars($row['GuildName']); ?></a>
                                            </td>
                                            <td>
                                                <a href="/admin?view=CharEdit&id=<?php echo $row['MasterCharID']; ?>"
                                                    class="user-link"><?php echo htmlspecialchars($row['MasterName']); ?></a>
                                            </td>
                                            <td style="font-family: 'Futura PT', sans-serif;">
                                                <?php echo number_format($row['GuildPoint']); ?>
                                            </td>
                                            <td style="color: #888; font-size: 13px;"><?php echo $formattedDate; ?></td>
                                        </tr>
                                        <?php
                                    }
                                    if (!$found) {
                                        echo '<tr><td colspan="6" style="text-align: center; padding: 50px; color: #444;">No guilds found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($view === 'GIFTBOX'): ?>
                    <?php
                    $searchItem = isset($_GET['search_item']) ? trim($_GET['search_item']) : '';
                    ?>
                    <div class="char-edit-section">
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                            <div>
                                <p style="color: #666; font-size: 13px;">Send items directly to a player's GiftBox
                                    (Bank/Mall storage).</p>
                            </div>

                        </div>

                        <div style="display: grid; grid-template-columns: 350px 1fr; gap: 40px; align-items: start;">
                            <!-- Column 1: Send Item Form -->
                            <div
                                style="background: rgba(255,255,255,0.02); padding: 35px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                <h4
                                    style="color: #fff; margin-bottom: 30px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                                    SEND ITEM</h4>
                                <form action="/admin_actions" method="POST" style="display: block;">
                                    <input type="hidden" name="action" value="giftbox">
                                    <input type="hidden" name="return_view" value="GIFTBOX">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">By
                                                    UserID</label>
                                                <input type="text" name="target_user" class="char-input"
                                                    placeholder="UserID..." value="<?php echo isset($_GET['target_user']) ? htmlspecialchars($_GET['target_user']) : ''; ?>"
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px;">
                                            </div>
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">By
                                                    Char Name</label>
                                                <input type="text" name="target_char" class="char-input"
                                                    placeholder="CharName..."
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px;">
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 80px; gap: 15px;">
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">ItemID</label>
                                                <input type="number" name="itemid" id="gift_itemid" class="char-input"
                                                    placeholder="Paste ID here..." required
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px;">
                                            </div>
                                            <div class="char-input-group" style="display: block;">
                                                <label
                                                    style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">Count</label>
                                                <input type="number" name="count" class="char-input" value="1" required
                                                    style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 40px; text-align: center;">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-action">SEND TO GIFTBOX</button>
                                    <div
                                        style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.2); border-radius: 4px; border: 1px solid rgba(255,255,255,0.03);">
                                        <p style="color: #666; font-size: 11px; line-height: 1.5; margin: 0;">Provide either
                                            UserID or Char Name. ItemID is required. Items are sent to the Mall storage tab.
                                        </p>
                                    </div>
                                </form>
                            </div>

                            <!-- Column 2: Item Search Helper -->
                            <div
                                style="background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 30px; display: flex; flex-direction: column; min-height: 500px;">
                                <h4
                                    style="color: #fff; margin-bottom: 25px; font-size: 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                                    ITEM SEARCH
                                    <i class="fas fa-search" style="color: #666; font-size: 16px;"></i>
                                </h4>

                                <form action="/admin" method="GET" style="margin-bottom: 25px;">
                                    <input type="hidden" name="view" value="Giftbox">
                                    <div style="display: flex; gap: 10px;">
                                        <input type="text" name="search_item" class="char-input"
                                            value="<?php echo htmlspecialchars($searchItem); ?>"
                                            placeholder="Search ItemName..."
                                            style="background: rgba(255,255,255,0.03); border-color: #333; color: #fff; height: 40px;">
                                        <button type="submit" class="btn-action btn-sm"><i
                                                class="fas fa-search"></i></button>
                                    </div>
                                </form>

                                <div class="blacklist-scroll"
                                    style="flex-grow: 1; max-height: 350px; overflow-y: auto; padding-right: 10px;">
                                    <?php if ($searchItem): ?>
                                        <?php
                                        $searchLike = '%' . $searchItem . '%';
                                        $itemSearchResults = [];
                                        try {
                                            $q = $pdoAdmin->prepare("SELECT TOP 30 ItemID, ItemName FROM PS_GameDefs.dbo.Items WHERE ItemName LIKE ? ORDER BY ItemName ASC");
                                            $q->execute([$searchLike]);
                                            $itemSearchResults = $q->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (Throwable $e) {}
                                        $found = false;
                                        foreach ($itemSearchResults as $iRow) {
                                            $found = true;
                                            echo '<div class="js-set-input-value js-hover-border" data-target-id="gift_itemid" data-value="' . (int) $iRow['ItemID'] . '" data-border-hover="#e8c881" data-border-default="rgba(255,255,255,0.05)" style="background: rgba(255,255,255,0.02); padding: 12px 15px; border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; cursor: pointer; transition: all 0.2s;">';
                                            echo '<span><span style="color: #e8c881; font-weight: 500;">' . htmlspecialchars($iRow['ItemName']) . '</span> <span style="color: #555; font-size: 11px; margin-left: 10px;">ID: ' . $iRow['ItemID'] . '</span></span>';
                                            echo '<i class="fas fa-plus-circle" style="color: #333; font-size: 14px;"></i>';
                                            echo '</div>';
                                        }
                                        if (!$found) {
                                            echo '<div style="text-align: center; padding: 50px 0; color: #444;">No items found.</div>';
                                        }
                                        ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 80px 0; color: #444;">
                                            <i class="fas fa-box-open"
                                                style="font-size: 40px; margin-bottom: 20px; opacity: 0.2;"></i><br>
                                            Search for items to get IDs.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($view === 'POINTS'): ?>
                    
                    <div class="char-edit-section">
                        <p style="color: #666; font-size: 13px; margin-bottom: 30px;">Manage user points and view transaction history.</p>

                        <!-- Two-Column Layout: Form | Transaction History -->
                        <div style="display: grid; grid-template-columns: 380px 1fr; gap: 35px; align-items: start;">

                            <!-- Column 1: Add Points Form -->
                            <div style="background: rgba(255,255,255,0.02); padding: 35px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                <h4 style="color: #fff; margin-bottom: 30px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
                                    <i class="fas fa-coins" style="color: #e8c881; margin-right: 10px;"></i>ADD POINTS</h4>
                                <form action="/admin_actions" method="POST" style="display: block;">
                                    <input type="hidden" name="action" value="points_add">
                                    <input type="hidden" name="return_view" value="POINTS">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div style="display: flex; flex-direction: column; gap: 22px; margin-bottom: 30px;">
                                        <div class="char-input-group" style="display: block;">
                                            <label style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">UserID</label>
                                            <input type="text" name="target_user" class="char-input"
                                                placeholder="Enter UserID..." list="user_list" required
                                                value="<?php echo isset($_GET['target_user']) ? htmlspecialchars($_GET['target_user']) : ''; ?>"
                                                style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px; font-size: 14px;">
                                            <datalist id="user_list">
                                                <?php
                                                try {
                                                    $uList = $pdoAdmin->query("SELECT TOP 100 UserID FROM PS_UserData.dbo.Users_Master ORDER BY UserID ASC");
                                                    if ($uList) foreach ($uList->fetchAll(PDO::FETCH_ASSOC) as $uRow) {
                                                        echo '<option value="' . htmlspecialchars($uRow['UserID']) . '">';
                                                    }
                                                } catch (Throwable $e) {}
                                                ?>
                                            </datalist>
                                        </div>

                                        <div class="char-input-group" style="display: block;">
                                            <label style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">SP (Amount)</label>
                                            <input type="number" name="point_amount" class="char-input" value="100" min="1" required
                                                style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px; text-align: center; font-size: 18px; font-weight: 700; color: #e8c881;">
                                        </div>

                                        <div class="char-input-group" style="display: block;">
                                            <label style="display: block; color: #888; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">Reason</label>
                                            <input type="text" name="reason" class="char-input" placeholder="Required reason..." required
                                                style="background: rgba(0,0,0,0.3); border-color: #333; color: #fff; width: 100%; height: 42px; font-size: 14px;">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-action" style="width: 100%;">ACCEPT</button>
                                </form>
                            </div>

                            <!-- Column 2: Transaction History -->
                            <div style="background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(232, 200, 129, 0.05); padding: 30px; display: flex; flex-direction: column;">
                                <h4 style="color: #fff; margin-bottom: 25px; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-history" style="color: #da9f50; font-size: 14px;"></i> Transaction History</h4>

                        <div class="chars-table-container">
                            <table class="chars-table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">#</th>
                                        <th style="text-align: left; padding-left: 20px;">UserID</th>
                                        <th>Date</th>
                                        <th>Points</th>
                                        <th style="text-align: left;">Reason</th>
                                        <th>Admin / GM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $perPage = 50;
                                    $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                    $offset = ($page - 1) * $perPage;

                                    $totalHistory = 0;
                                    $totalHistoryPages = 1;
                                    $pointHistory = [];
                                    try {
                                        $cRes = $pdoAdmin->query("SELECT COUNT(*) as Total FROM PS_WebSite.dbo.Web_PointHistory");
                                        if ($cRes) $totalHistory = (int) ($cRes->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                        $totalHistoryPages = max(1, (int) ceil($totalHistory / $perPage));

                                        // Robust fallback: avoid SQL Server ODBC pagination quirks (OFFSET/CTE + placeholders)
                                        // and paginate in PHP to ensure history is always visible when records exist.
                                        $histQ = $pdoAdmin->query("SELECT TOP 5000 RowID, UserID, [Date], PointsAdded, Reason, GM_Account FROM PS_WebSite.dbo.Web_PointHistory ORDER BY RowID DESC");
                                        $historyRows = $histQ ? $histQ->fetchAll(PDO::FETCH_ASSOC) : [];
                                        $pointHistory = array_slice($historyRows, $offset, $perPage);
                                    } catch (Throwable $e) {
                                        appSecurityLog('Points history load failed in admin.php: ' . $e->getMessage());
                                    }
                                    $count = 0;
                                    foreach ($pointHistory as $h):
                                        $count++;
                                        ?>
                                        <tr>
                                            <td style="font-family: 'monospace'; color: #888;">#<?php echo $h['RowID']; ?></td>
                                            <td style="text-align: left; padding-left: 20px;">
                                                <a href="/admin?view=USERS&search_user=<?php echo urlencode($h['UserID']); ?>" class="user-link"><?php echo htmlspecialchars($h['UserID']); ?></a>
                                            </td>
                                            <td>
                                                <?php echo date('Y M d', strtotime($h['Date'])); ?> <span style="color: #888; font-size: 11px;"><?php echo date('H:i', strtotime($h['Date'])); ?></span>
                                            </td>
                                            <td style="color: #e8c881; font-weight: 700;">
                                                +<?php echo number_format($h['PointsAdded']); ?>
                                            </td>
                                            <td style="text-align: left; color: #aaa; font-style: italic;">
                                                <?php echo htmlspecialchars($h['Reason'] ?: '-'); ?>
                                            </td>
                                            <td>
                                                <span class="status-badge" style="background: rgba(106, 142, 193, 0.1); color: #6a8ec1; border: 1px solid rgba(106, 142, 193, 0.2); font-size: 11px; padding: 3px 10px;">
                                                    <i class="fas fa-user-shield" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($h['GM_Account']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($count === 0): ?>
                                        <tr>
                                            <td colspan="6" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                <i class="fas fa-info-circle" style="margin-right: 10px;"></i> No transaction records found in database.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                                </div><!-- /transaction history column -->
                        </div><!-- /two-column grid -->
                    </div><!-- /char-edit-section -->
                    <?php elseif ($view === 'SEND_NOTICE'): ?>
                        <div class="char-edit-section" style="max-width: 800px; margin: 0 auto; padding: 20px;">
                            <div style="text-align: center; margin-bottom: 40px;">
                                <h3 style="margin-bottom: 10px; color: #e8c881;">SEND SERVER NOTICE</h3>
                                <p style="color: #666; font-size: 14px;">The message will be broadcasted to all online
                                    players
                                    instantly.</p>
                            </div>

                            <div class="item-editor-card"
                                style="padding: 40px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px;">
                                <form action="/admin_actions" method="POST">
                                    <input type="hidden" name="action" value="send_notice">
                                    <input type="hidden" name="return_view" value="SEND_NOTICE">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div class="char-input-group" style="margin-bottom: 30px; display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Notice
                                            Message (Max 150 chars)</label>
                                        <textarea name="notice_text" class="char-input"
                                            placeholder="Type your message here..." required maxlength="150"
                                            style="width: 100%; height: 120px; background: rgba(0,0,0,0.3); border-color: #333; color: #fff; padding: 15px; font-size: 15px; resize: none;"></textarea>
                                    </div>

                                    <div style="display: flex; justify-content: center;">
                                        <button type="submit" class="btn-action" style="width: 100%; max-width: 300px;">
                                            <i class="fas fa-bullhorn"></i> BROADCAST NOTICE
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php elseif (in_array($view, ['DROPS_BLACKLIST_ADD', 'DROPS_BLACKLIST_REMOVE', 'DROPS_CONFIG'])): ?>
                        
                        <div class="char-edit-section">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                                <div>
                                    <h3 style="margin-bottom: 5px; color: #e8c881;">DROPS MANAGEMENT</h3>
                                    <p style="color: #666; font-size: 13px;">Manage global drop settings and item blacklists
                                        from this central hub.</p>
                                </div>

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
                        </div>

                    <?php elseif (strpos($view, 'LUCKYCHEST') !== false): ?>
                        <?php
                        $luckySettings = fetchWebsiteSettings(['LuckyChestEnabled']);
                        $luckyEnabled = (string) ($luckySettings['LuckyChestEnabled'] ?? '0');
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

                    <?php elseif ($view === 'DOWNLOADS_UPDATE'): ?>
                        <?php
                        $settings = [];
                        try {
                            $q = $pdoAdmin->query("SELECT SettingKey, SettingValue FROM PS_WebSite.dbo.Web_Settings WHERE SettingKey IN ('DownloadsEnabled', 'Link_GoogleDrive', 'Link_OfficialMirror', 'Link_Mega')");
                            if ($q) foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                $settings[$row['SettingKey']] = $row['SettingValue'];
                            }
                        } catch (Throwable $e) {}
                        ?>
                        <div class="char-edit-section" style="max-width: 800px; margin: 0 auto; padding: 20px;">
                            <div style="text-align: center; margin-bottom: 40px;">
                                <h3 style="margin-bottom: 10px; color: #e8c881;">DOWNLOADS MANAGEMENT</h3>
                                <p style="color: #666; font-size: 14px;">Update client download links and page visibility
                                    settings.</p>
                            </div>

                            <form action="/admin_actions" method="POST" class="char-edit-form"
                                style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 40px; display: block;">
                                <input type="hidden" name="action" value="downloads_update">
                                <input type="hidden" name="return_view" value="DOWNLOADS_UPDATE">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <!-- Status Section -->
                                <div
                                    style="background: rgba(0,0,0,0.3); padding: 25px; border-radius: 8px; border: 1px solid rgba(232,200,129,0.1); display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; gap: 20px;">
                                    <div style="flex: 1;">
                                        <div style="color: #fff; font-size: 16px; font-weight: 600; margin-bottom: 4px;">
                                            Page
                                            Visibility</div>
                                        <div style="color: #666; font-size: 12px; line-height: 1.4;">When disabled, users
                                            cannot
                                            access the downloads page. This is useful during maintenance or client updates.
                                        </div>
                                    </div>
                                    <div style="flex-shrink: 0;">
                                        <label class="switch">
                                            <input type="checkbox" name="dl_enabled" <?php echo ($settings['DownloadsEnabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Links Section -->
                                <div style="display: flex; flex-direction: column; gap: 30px;">
                                    <div class="char-input-group" style="display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Google
                                            Drive Client</label>
                                        <div
                                            style="display: flex; align-items: center; background: rgba(0,0,0,0.3); border: 1px solid #333; border-radius: 4px; padding: 0 15px;">
                                            <i class="fab fa-google-drive"
                                                style="color: #444; font-size: 16px; margin-right: 15px;"></i>
                                            <input type="text" name="link_google" class="char-input"
                                                value="<?php echo htmlspecialchars($settings['Link_GoogleDrive'] ?? ''); ?>"
                                                style="border: none !important; background: transparent !important; padding: 12px 0 !important; width: 100%; box-shadow: none !important; color: #fff;">
                                        </div>
                                    </div>

                                    <div class="char-input-group" style="display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Official
                                            Web Mirror</label>
                                        <div
                                            style="display: flex; align-items: center; background: rgba(0,0,0,0.3); border: 1px solid #333; border-radius: 4px; padding: 0 15px;">
                                            <i class="fas fa-server"
                                                style="color: #444; font-size: 16px; margin-right: 15px;"></i>
                                            <input type="text" name="link_mirror" class="char-input"
                                                value="<?php echo htmlspecialchars($settings['Link_OfficialMirror'] ?? ''); ?>"
                                                style="border: none !important; background: transparent !important; padding: 12px 0 !important; width: 100%; box-shadow: none !important; color: #fff;">
                                        </div>
                                    </div>

                                    <div class="char-input-group" style="display: block;">
                                        <label
                                            style="display: block; color: #888; font-size: 11px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Mega.nz
                                            Storage</label>
                                        <div
                                            style="display: flex; align-items: center; background: rgba(0,0,0,0.3); border: 1px solid #333; border-radius: 4px; padding: 0 15px;">
                                            <i class="fas fa-cloud"
                                                style="color: #444; font-size: 16px; margin-right: 15px;"></i>
                                            <input type="text" name="link_mega" class="char-input"
                                                value="<?php echo htmlspecialchars($settings['Link_Mega'] ?? ''); ?>"
                                                style="border: none !important; background: transparent !important; padding: 12px 0 !important; width: 100%; box-shadow: none !important; color: #fff;">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn-action">
                                    SAVE DOWNLOAD CONFIGURATION
                                </button>
                            </form>
                        </div>

                    <?php elseif ($view === 'TASK_SCHEDULER'): ?>
                        <?php
                        $schedulerSettings = fetchWebsiteSettings(['TaskSchedulerEnabled', 'TaskSchedulerLastHeartbeat', 'TaskSchedulerWorkerId']);
                        $schedulerEnabled = (string) ($schedulerSettings['TaskSchedulerEnabled'] ?? '0');
                        $schedulerHeartbeat = (string) ($schedulerSettings['TaskSchedulerLastHeartbeat'] ?? '-');
                        $schedulerWorkerId = trim((string) ($schedulerSettings['TaskSchedulerWorkerId'] ?? 'worker-main'));
                        if ($schedulerWorkerId === '') {
                            $schedulerWorkerId = 'worker-main';
                        }
                        $activeWorkerId = '-';
                        if (strpos($schedulerHeartbeat, '|') !== false) {
                            $heartbeatParts = explode('|', $schedulerHeartbeat, 2);
                            $activeWorkerId = trim((string) ($heartbeatParts[1] ?? '-'));
                        }

                        $schedulerJobs = [];
                        try {
                            $qSchedulerJobs = $pdoAdmin->query("SELECT TOP 200 JobID, JobName, IsEnabled, Status, ScheduleMode, ScheduleValue, TaskType, ServiceName, CommandText, SqlPayload, SqlSource, CreatedBy, CreatedAt, UpdatedAt FROM PS_WebSite.dbo.Web_TaskJobs ORDER BY JobID DESC");
                            if ($qSchedulerJobs) $schedulerJobs = $qSchedulerJobs->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) {}

                        $schedulerRuns = [];
                        $schedulerDbName = trim((string) ($website_db_name ?? 'PS_WebSite'));
                        if ($schedulerDbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $schedulerDbName)) {
                            $schedulerDbName = 'PS_WebSite';
                        }
                        $schedulerDbEscaped = '[' . $schedulerDbName . ']';

                        $schedulerRunsPerPage = 20;
                        $schedulerRunsPage = isset($_GET['runs_page']) ? (int) $_GET['runs_page'] : 1;
                        if ($schedulerRunsPage < 1) {
                            $schedulerRunsPage = 1;
                        }

                        $schedulerRunsSearch = trim((string) ($_GET['runs_q'] ?? ''));
                        if (strlen($schedulerRunsSearch) > 120) {
                            $schedulerRunsSearch = substr($schedulerRunsSearch, 0, 120);
                        }
                        $schedulerRunsSearchPlain = trim(ltrim($schedulerRunsSearch, '#'));

                        $schedulerRunsStatus = strtolower(trim((string) ($_GET['runs_status'] ?? '')));
                        if (!in_array($schedulerRunsStatus, ['', 'success', 'done', 'failed', 'error', 'skipped'], true)) {
                            $schedulerRunsStatus = '';
                        }

                        $schedulerRunsWhereParts = [];
                        $schedulerRunsParams = [];
                        if ($schedulerRunsStatus !== '') {
                            $schedulerRunsWhereParts[] = 'LOWER(LTRIM(RTRIM(CAST(RunStatus AS VARCHAR(50))))) = ?';
                            $schedulerRunsParams[] = $schedulerRunsStatus;
                        }
                        if ($schedulerRunsSearch !== '') {
                            $searchLike = '%' . $schedulerRunsSearchPlain . '%';
                            $schedulerSearchClauseParts = [
                                'CAST(RunID AS VARCHAR(20)) LIKE ?',
                                'CAST(JobID AS VARCHAR(20)) LIKE ?',
                                'LOWER(CAST(RunStatus AS VARCHAR(50))) LIKE ?',
                                'CAST(ISNULL(Output, \'\') AS NVARCHAR(MAX)) LIKE ?',
                                'CAST(ISNULL(ErrorMessage, \'\') AS NVARCHAR(MAX)) LIKE ?',
                                'LOWER(CAST(ISNULL(TriggeredBy, \'\') AS VARCHAR(100))) LIKE ?',
                                'CAST(StartedAt AS VARCHAR(30)) LIKE ?',
                                'CAST(ISNULL(FinishedAt, StartedAt) AS VARCHAR(30)) LIKE ?'
                            ];
                            $schedulerSearchParams = [
                                $searchLike,
                                $searchLike,
                                strtolower($searchLike),
                                $searchLike,
                                $searchLike,
                                strtolower($searchLike),
                                $searchLike,
                                $searchLike,
                            ];

                            if ($schedulerRunsSearchPlain !== '' && ctype_digit($schedulerRunsSearchPlain)) {
                                array_unshift($schedulerSearchClauseParts, 'RunID = ? OR JobID = ?');
                                array_unshift($schedulerSearchParams, (int) $schedulerRunsSearchPlain, (int) $schedulerRunsSearchPlain);
                            }

                            $schedulerRunsWhereParts[] = '(' . implode(' OR ', $schedulerSearchClauseParts) . ')';
                            $schedulerRunsParams = array_merge($schedulerRunsParams, $schedulerSearchParams);
                        }

                        $schedulerRunsWhereSql = '';
                        if (!empty($schedulerRunsWhereParts)) {
                            $schedulerRunsWhereSql = ' WHERE ' . implode(' AND ', $schedulerRunsWhereParts);
                        }

                        $schedulerFilteredRunIds = [];
                        $schedulerRunsIdSql = "SELECT RunID FROM {$schedulerDbEscaped}.dbo.Web_TaskJobRuns{$schedulerRunsWhereSql} ORDER BY RunID DESC";
                        try {
                            $schedulerRunsIdStmt = $pdoAdmin->prepare($schedulerRunsIdSql);
                            $schedulerRunsIdStmt->execute($schedulerRunsParams);
                            while (($idRow = $schedulerRunsIdStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                                $idValue = (int) ($idRow['RunID'] ?? 0);
                                if ($idValue > 0) {
                                    $schedulerFilteredRunIds[] = $idValue;
                                }
                            }
                        } catch (Throwable $e) {}

                        $schedulerRunsTotalCount = count($schedulerFilteredRunIds);

                        $schedulerRunsTotalPages = $schedulerRunsTotalCount > 0 ? (int) ceil($schedulerRunsTotalCount / $schedulerRunsPerPage) : 1;
                        if ($schedulerRunsPage > $schedulerRunsTotalPages) {
                            $schedulerRunsPage = $schedulerRunsTotalPages;
                        }
                        if ($schedulerRunsPage < 1) {
                            $schedulerRunsPage = 1;
                        }

                        $schedulerRunsOffset = ($schedulerRunsPage - 1) * $schedulerRunsPerPage;
                        $schedulerPageRunIds = array_slice($schedulerFilteredRunIds, $schedulerRunsOffset, $schedulerRunsPerPage);
                        if (!empty($schedulerPageRunIds)) {
                            $schedulerRunIdPlaceholders = implode(', ', array_fill(0, count($schedulerPageRunIds), '?'));
                            $schedulerRunsDataSql = "SELECT RunID, JobID, RunStatus, StartedAt, FinishedAt, Output, ErrorMessage, TriggeredBy FROM {$schedulerDbEscaped}.dbo.Web_TaskJobRuns WHERE RunID IN ({$schedulerRunIdPlaceholders}) ORDER BY RunID DESC";
                            try {
                                $schedulerRunsDataStmt = $pdoAdmin->prepare($schedulerRunsDataSql);
                                $schedulerRunsDataStmt->execute($schedulerPageRunIds);
                                $schedulerRuns = $schedulerRunsDataStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}
                        }

                        $schedulerRunsSummaryText = 'No runs logged yet.';
                        if ($schedulerRunsTotalCount > 0) {
                            $schedulerRunsStart = $schedulerRunsOffset + 1;
                            $schedulerRunsEnd = min($schedulerRunsOffset + $schedulerRunsPerPage, $schedulerRunsTotalCount);
                            $schedulerRunsSummaryText = 'Showing ' . $schedulerRunsStart . '-' . $schedulerRunsEnd . ' of ' . $schedulerRunsTotalCount . ' runs';
                        }

                        $schedulerRunsRowsHtml = '';
                        ob_start();
                        foreach ($schedulerRuns as $run) {
                            $runOutput = trim((string) ($run['Output'] ?? ''));
                            $runError = trim((string) ($run['ErrorMessage'] ?? ''));
                            ?>
                            <tr>
                                <td style="color:#777; white-space: nowrap;">#<?php echo (int) $run['RunID']; ?></td>
                                <td style="white-space: nowrap;"><?php echo (int) $run['JobID']; ?></td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['RunStatus'] ?? '')); ?></td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['StartedAt'] ?? '')); ?></td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['FinishedAt'] ?? '-')); ?></td>
                                <td style="min-width: 360px; max-width: 620px; text-align: left; white-space: normal; word-break: break-word;">
                                    <?php if ($runOutput !== ''): ?>
                                        <details>
                                            <summary style="cursor:pointer; color:#b8b8b8;">Show output</summary>
                                            <pre style="margin:8px 0 0 0; white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($runOutput); ?></pre>
                                        </details>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 260px; max-width: 520px; text-align: left; white-space: normal; word-break: break-word;">
                                    <?php if ($runError !== ''): ?>
                                        <details>
                                            <summary style="cursor:pointer; color:#ff9a9a;">Show error</summary>
                                            <pre style="margin:8px 0 0 0; white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($runError); ?></pre>
                                        </details>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;"><?php echo htmlspecialchars((string) ($run['TriggeredBy'] ?? '-')); ?></td>
                            </tr>
                            <?php
                        }
                        if (empty($schedulerRuns)) {
                            ?>
                            <tr><td colspan="8" style="text-align:center; padding: 35px; color:#555;">No runs logged yet.</td></tr>
                            <?php
                        }
                        $schedulerRunsRowsHtml = (string) ob_get_clean();

                        $schedulerRunsBuildQuery = function (int $page) use ($schedulerRunsSearch, $schedulerRunsStatus) {
                            $query = [
                                'view' => 'task_scheduler',
                                'runs_page' => $page,
                            ];
                            if ($schedulerRunsSearch !== '') {
                                $query['runs_q'] = $schedulerRunsSearch;
                            }
                            if ($schedulerRunsStatus !== '') {
                                $query['runs_status'] = $schedulerRunsStatus;
                            }
                            if (isset($_GET['scheduler_form']) && (string) $_GET['scheduler_form'] === '1') {
                                $query['scheduler_form'] = '1';
                            }
                            if (isset($_GET['edit_job_id']) && (int) $_GET['edit_job_id'] > 0) {
                                $query['edit_job_id'] = (int) $_GET['edit_job_id'];
                            }
                            if (isset($_GET['job_mode']) && in_array((string) $_GET['job_mode'], ['simple', 'advanced'], true)) {
                                $query['job_mode'] = (string) $_GET['job_mode'];
                            }
                            return '/admin?' . http_build_query($query) . '#scheduler-runs-panel';
                        };

                        $schedulerRunsPaginationHtml = '';
                        ob_start();
                        if ($schedulerRunsTotalPages > 1) {
                            $prevPage = max(1, $schedulerRunsPage - 1);
                            $nextPage = min($schedulerRunsTotalPages, $schedulerRunsPage + 1);
                            ?>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <a href="<?php echo htmlspecialchars($schedulerRunsBuildQuery($prevPage)); ?>" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width:80px; padding:0 10px; opacity: <?php echo $schedulerRunsPage <= 1 ? '0.45' : '1'; ?>; pointer-events: <?php echo $schedulerRunsPage <= 1 ? 'none' : 'auto'; ?>;">Prev</a>
                                <?php
                                $pageWindowStart = max(1, $schedulerRunsPage - 2);
                                $pageWindowEnd = min($schedulerRunsTotalPages, $schedulerRunsPage + 2);
                                for ($pageIndex = $pageWindowStart; $pageIndex <= $pageWindowEnd; $pageIndex++) {
                                    $isCurrentPage = $pageIndex === $schedulerRunsPage;
                                    ?>
                                    <a href="<?php echo htmlspecialchars($schedulerRunsBuildQuery($pageIndex)); ?>" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width:46px; padding:0 10px; <?php echo $isCurrentPage ? 'background: rgba(232,200,129,0.34); border-color: rgba(232,200,129,0.75); color:#fff3c9; font-weight:700; box-shadow: 0 0 0 1px rgba(232,200,129,0.35), 0 0 14px rgba(232,200,129,0.22);' : ''; ?>"><?php echo $pageIndex; ?></a>
                                    <?php
                                }
                                ?>
                                <a href="<?php echo htmlspecialchars($schedulerRunsBuildQuery($nextPage)); ?>" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width:80px; padding:0 10px; opacity: <?php echo $schedulerRunsPage >= $schedulerRunsTotalPages ? '0.45' : '1'; ?>; pointer-events: <?php echo $schedulerRunsPage >= $schedulerRunsTotalPages ? 'none' : 'auto'; ?>;">Next</a>
                            </div>
                            <?php
                        }
                        $schedulerRunsPaginationHtml = (string) ob_get_clean();

                        if (isset($_GET['runs_partial']) && (string) $_GET['runs_partial'] === '1') {
                            header('Content-Type: application/json; charset=UTF-8');
                            echo json_encode([
                                'ok' => true,
                                'rows_html' => $schedulerRunsRowsHtml,
                                'pagination_html' => $schedulerRunsPaginationHtml,
                                'summary_text' => $schedulerRunsSummaryText,
                                'current_page' => $schedulerRunsPage,
                                'total_pages' => $schedulerRunsTotalPages,
                                'total_count' => $schedulerRunsTotalCount,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            exit;
                        }

                        $schedulerEditJobId = isset($_GET['edit_job_id']) ? (int) $_GET['edit_job_id'] : 0;
                        $schedulerEditJob = null;
                        if ($schedulerEditJobId > 0) {
                            foreach ($schedulerJobs as $schedulerJobItem) {
                                if ((int) ($schedulerJobItem['JobID'] ?? 0) === $schedulerEditJobId) {
                                    $schedulerEditJob = $schedulerJobItem;
                                    break;
                                }
                            }
                        }

                        $schedulerSimpleDatabases = [];
                        try {
                            $schedulerDbStmt = $pdoAdmin->prepare("SELECT name FROM sys.databases WHERE state = 0 AND HAS_DBACCESS(name) = 1 AND database_id > 4 ORDER BY name");
                            $schedulerDbStmt->execute([]);
                            foreach ($schedulerDbStmt->fetchAll(PDO::FETCH_ASSOC) as $schedulerDbRow) {
                                $schedulerDbNameTmp = trim((string) ($schedulerDbRow['name'] ?? ''));
                                if ($schedulerDbNameTmp !== '' && preg_match('/^[A-Za-z0-9_]+$/', $schedulerDbNameTmp)) {
                                    $schedulerSimpleDatabases[] = $schedulerDbNameTmp;
                                }
                            }
                        } catch (Throwable $e) {}

                        $runtimeDbName = trim((string) (getenv('DB_NAME') ?: 'PS_UserData'));
                        $runtimeWebsiteDbName = trim((string) (getenv('DB_WEBSITE_NAME') ?: 'PS_WebSite'));
                        if ($runtimeDbName !== '') {
                            $schedulerSimpleDatabases[] = $runtimeDbName;
                        }
                        if ($runtimeWebsiteDbName !== '') {
                            $schedulerSimpleDatabases[] = $runtimeWebsiteDbName;
                        }
                        $schedulerSimpleDatabases[] = 'PS_UserData';
                        $schedulerSimpleDatabases[] = 'PS_GameData';
                        $schedulerSimpleDatabases[] = 'PS_WebSite';

                        $schedulerSimpleDatabases = array_values(array_unique(array_filter($schedulerSimpleDatabases, function ($dbName) {
                            return preg_match('/^[A-Za-z0-9_]+$/', (string) $dbName) === 1;
                        })));

                        $schedulerSimpleTableSuggestions = [];
                        $schedulerSimpleColumnSuggestions = [];
                        $schedulerTablesByDatabase = [];
                        $schedulerColumnsByDatabaseAndTable = [];
                        foreach ($schedulerSimpleDatabases as $simpleDbName) {
                            $simpleDbEscaped = '[' . $simpleDbName . ']';
                            $metaStmt = null;
                            try {
                                $metaStmt = $pdoAdmin->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM {$simpleDbEscaped}.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' ORDER BY TABLE_NAME, COLUMN_NAME");
                                $metaStmt->execute([]);
                                while ($metaRow = $metaStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $tableName = trim((string) ($metaRow['TABLE_NAME'] ?? ''));
                                    $columnName = trim((string) ($metaRow['COLUMN_NAME'] ?? ''));
                                    if ($tableName === '' || $columnName === '') {
                                        continue;
                                    }
                                    $schedulerSimpleTableSuggestions[] = $simpleDbName . '.dbo.' . $tableName;
                                    $schedulerSimpleColumnSuggestions[] = $tableName . '.' . $columnName;

                                    if (!isset($schedulerTablesByDatabase[$simpleDbName])) {
                                        $schedulerTablesByDatabase[$simpleDbName] = [];
                                    }
                                    $schedulerTablesByDatabase[$simpleDbName][$tableName] = true;

                                    if (!isset($schedulerColumnsByDatabaseAndTable[$simpleDbName])) {
                                        $schedulerColumnsByDatabaseAndTable[$simpleDbName] = [];
                                    }
                                    if (!isset($schedulerColumnsByDatabaseAndTable[$simpleDbName][$tableName])) {
                                        $schedulerColumnsByDatabaseAndTable[$simpleDbName][$tableName] = [];
                                    }
                                    $schedulerColumnsByDatabaseAndTable[$simpleDbName][$tableName][$columnName] = true;
                                }
                            } catch (Throwable $e) {}
                        }
                        $schedulerSimpleTableSuggestions = array_values(array_unique($schedulerSimpleTableSuggestions));
                        $schedulerSimpleColumnSuggestions = array_values(array_unique($schedulerSimpleColumnSuggestions));

                        foreach ($schedulerTablesByDatabase as $dbName => $tablesMap) {
                            $tables = array_keys($tablesMap);
                            natcasesort($tables);
                            $schedulerTablesByDatabase[$dbName] = array_values($tables);
                        }

                        foreach ($schedulerColumnsByDatabaseAndTable as $dbName => $tablesMap) {
                            foreach ($tablesMap as $tableName => $columnsMap) {
                                $columns = array_keys($columnsMap);
                                natcasesort($columns);
                                $schedulerColumnsByDatabaseAndTable[$dbName][$tableName] = array_values($columns);
                            }
                        }

                        $schedulerModeParam = strtolower(trim((string) ($_GET['job_mode'] ?? '')));
                        $schedulerIsEditSimple = $schedulerEditJob && strtoupper((string) ($schedulerEditJob['TaskType'] ?? '')) === 'SERVICE_COMMAND' && strtolower((string) ($schedulerEditJob['SqlSource'] ?? '')) === 'simple_meta';
                        if (!in_array($schedulerModeParam, ['simple', 'advanced'], true)) {
                            $schedulerModeParam = $schedulerIsEditSimple ? 'simple' : 'advanced';
                        }

                        $schedulerEditSimpleMeta = [
                            'database' => 'PS_WebSite',
                            'table' => '',
                            'column' => '',
                            'operator' => 'eq',
                            'expected_value' => '',
                            'second_command' => ''
                        ];
                        if ($schedulerIsEditSimple) {
                            $decodedSimpleMeta = json_decode((string) ($schedulerEditJob['SqlPayload'] ?? ''), true);
                            if (is_array($decodedSimpleMeta)) {
                                $schedulerEditSimpleMeta = array_merge($schedulerEditSimpleMeta, $decodedSimpleMeta);
                            }
                        }

                        $schedulerFormAction = $schedulerEditJob ? 'task_scheduler_update_job' : 'task_scheduler_save_job';
                        $schedulerFormTitle = $schedulerEditJob ? 'Modify Job' : 'Create Job';
                        $schedulerSubmitLabelSimple = $schedulerEditJob ? 'UPDATE QUICK JOB' : 'SAVE QUICK JOB';
                        $schedulerSubmitLabelAdvanced = $schedulerEditJob ? 'UPDATE ADVANCED JOB' : 'SAVE ADVANCED JOB';

                        $schedulerDefaultJobName = $schedulerEditJob ? (string) ($schedulerEditJob['JobName'] ?? '') : '';
                        $schedulerDefaultSchedule = $schedulerEditJob ? (string) ($schedulerEditJob['ScheduleValue'] ?? 'hourly') : 'hourly';
                        $schedulerDefaultService = $schedulerEditJob ? strtolower((string) ($schedulerEditJob['ServiceName'] ?? 'ps_game')) : 'ps_game';
                        $schedulerDefaultCommand = $schedulerEditJob ? (string) ($schedulerEditJob['CommandText'] ?? '') : '';
                        $schedulerDefaultSimpleSecondCommand = (string) ($schedulerEditSimpleMeta['second_command'] ?? '');
                        $schedulerDefaultTaskType = $schedulerEditJob ? strtoupper((string) ($schedulerEditJob['TaskType'] ?? 'SERVICE_COMMAND')) : 'SERVICE_COMMAND';
                        $schedulerDefaultSqlPayload = $schedulerEditJob && $schedulerDefaultTaskType === 'SQL_SCRIPT' ? (string) ($schedulerEditJob['SqlPayload'] ?? '') : '';
                        $schedulerShowForm = $schedulerEditJob || (string) ($_GET['scheduler_form'] ?? '') === '1';
                        ?>
                        <div id="task-scheduler-top" class="char-edit-section" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
                            <div style="text-align: center; margin-bottom: 35px;">
                                <h3 style="margin-bottom: 8px; color: #e8c881;">TASK SCHEDULER</h3>
                                <p style="color: #666; font-size: 13px;">Configura job schedulati da pannello. Esecuzione demandata al worker Windows locale (queue-driven).</p>
                                <p style="color: #666; font-size: 12px; margin-top: 6px;">`Run Now` accoda la richiesta subito; al ciclo di polling successivo il worker esegue direttamente il job.</p>
                            </div>

                            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(232,200,129,0.18); border-radius: 10px; padding: 22px; margin-bottom: 20px; display:flex; align-items:center; justify-content: space-between; gap: 20px;">
                                <div>
                                    <div style="color: #fff; font-size: 18px; font-weight: 700; margin-bottom: 6px; letter-spacing: .4px;">Global Module Status</div>
                                    <div style="color:#bfc7d2; font-size: 13px; line-height:1.7;">Abilita/disabilita creazione e queueing dei task dal pannello admin. Il poller Windows locale resta separato e gira con il task schedulato sul worker.</div>
                                    <div style="color:#a9b3c1; font-size: 13px; margin-top: 4px; line-height:1.7;">Il toggle salva <code>TaskSchedulerEnabled</code> nel database sito configurato da <code>DB_WEBSITE_NAME</code> (default: <code>PS_WebSite</code>).</div>
                                    <div style="margin-top: 10px; color:#e5e7eb; font-size: 12px; display:flex; flex-wrap:wrap; gap: 10px 18px;">
                                        <span><b style="color:#e8c881;">Worker configurato (DB):</b> <?php echo htmlspecialchars($schedulerWorkerId); ?></span>
                                        <span><b style="color:#93c5fd;">Worker attivo (heartbeat):</b> <?php echo htmlspecialchars($activeWorkerId); ?></span>
                                        <span><b style="color:#f3f4f6;">Last Worker Heartbeat:</b> <?php echo htmlspecialchars($schedulerHeartbeat); ?></span>
                                    </div>
                                </div>
                                <form action="/admin_actions" method="POST" style="display:flex; align-items:center; gap: 12px;">
                                    <input type="hidden" name="action" value="task_scheduler_toggle">
                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <label class="switch">
                                        <input type="checkbox" name="scheduler_enabled" <?php echo $schedulerEnabled === '1' ? 'checked' : ''; ?> class="js-auto-submit-onchange">
                                        <span class="slider round"></span>
                                    </label>
                                    <span style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: <?php echo $schedulerEnabled === '1' ? '#48bb78' : '#e53e3e'; ?>;">
                                        <?php echo $schedulerEnabled === '1' ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </form>
                            </div>

                            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin: 0 0 22px 0; flex-wrap:wrap;">
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <a href="/admin?view=task_scheduler&scheduler_form=1#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 148px; padding: 0 14px;">Create Job</a>
                                    <?php if ($schedulerEditJob): ?>
                                        <a href="/admin?view=task_scheduler&edit_job_id=<?php echo (int) $schedulerEditJob['JobID']; ?>&scheduler_form=1#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 170px; padding: 0 14px;">Modify Job #<?php echo (int) $schedulerEditJob['JobID']; ?></a>
                                    <?php endif; ?>
                                    <?php if ($schedulerShowForm): ?>
                                        <a href="/admin?view=task_scheduler#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 130px; padding: 0 14px;">Close Form</a>
                                    <?php endif; ?>
                                </div>
                                <a href="#task-scheduler-guide" style="display:inline-flex; align-items:center; justify-content:center; padding: 10px 16px; border-radius: 8px; background: linear-gradient(180deg, rgba(232,200,129,0.18), rgba(232,200,129,0.08)); border: 1px solid rgba(232,200,129,0.28); color:#e8c881; text-decoration:none; font-size: 12px; font-weight: 600; letter-spacing: .3px; white-space: nowrap;">Open setup guide</a>
                            </div>

                            <div style="display:flex; flex-direction:column; gap: 20px;">
                                <?php if ($schedulerShowForm): ?>
                                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 25px;">
                                    <h4 style="color:#fff; font-size: 13px; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 14px;"><?php echo htmlspecialchars($schedulerFormTitle); ?></h4>
                                    <?php if ($schedulerEditJob): ?>
                                        <div style="margin-bottom: 12px; color:#8f98a3; font-size: 11px;">Editing job #<?php echo (int) $schedulerEditJob['JobID']; ?> — <a href="/admin?view=task_scheduler" style="color:#e8c881; text-decoration:none;">clear edit mode</a></div>
                                    <?php endif; ?>

                                    <div style="display:flex; gap:8px; margin-bottom: 14px;">
                                        <button id="scheduler-mode-simple-btn" type="button" class="btn-action btn-sm js-scheduler-mode" data-mode="simple">Quick Command Job</button>
                                        <button id="scheduler-mode-advanced-btn" type="button" class="btn-action btn-sm js-scheduler-mode" data-mode="advanced">Advanced Job</button>
                                    </div>

                                    <form id="scheduler-simple-form" action="/admin_actions" method="POST" enctype="multipart/form-data" style="display:block;">
                                        <input type="hidden" name="action" value="<?php echo htmlspecialchars($schedulerFormAction); ?>">
                                        <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="job_profile" value="simple">
                                        <?php if ($schedulerEditJob): ?>
                                            <input type="hidden" name="job_id" value="<?php echo (int) $schedulerEditJob['JobID']; ?>">
                                        <?php endif; ?>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Job Name</label>
                                            <input type="text" name="job_name" class="char-input" maxlength="100" required placeholder="EXP Recovery Monitor" value="<?php echo htmlspecialchars($schedulerDefaultJobName); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Windows Schedule</label>
                                            <select name="simple_interval" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <?php $scheduleOptions = ['every_10m' => 'Every 10 min', 'every_15m' => 'Every 15 min', 'every_30m' => 'Every 30 min', 'hourly' => 'Hourly', 'daily_00_00' => 'Daily 00:00', 'daily_03_00' => 'Daily 03:00', 'daily_06_00' => 'Daily 06:00', 'daily_12_00' => 'Daily 12:00', 'daily_18_00' => 'Daily 18:00']; ?>
                                                <?php foreach ($scheduleOptions as $scheduleKey => $scheduleLabel): ?>
                                                    <option value="<?php echo htmlspecialchars($scheduleKey); ?>" <?php echo $schedulerDefaultSchedule === $scheduleKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($scheduleLabel); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service</label>
                                            <select name="service_name" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <option value="ps_game" <?php echo $schedulerDefaultService === 'ps_game' ? 'selected' : ''; ?>>ps_game</option>
                                                <option value="ps_login" <?php echo $schedulerDefaultService === 'ps_login' ? 'selected' : ''; ?>>ps_login</option>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service Command</label>
                                            <input type="text" name="command_text" class="char-input" maxlength="400" placeholder="/exp2xenable 100" value="<?php echo htmlspecialchars($schedulerDefaultCommand); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Second Command (optional)</label>
                                            <input type="text" name="simple_second_command" class="char-input" maxlength="400" placeholder="/nt Player, today is {day}. EXP: x{exp}. Enjoy the game!" value="<?php echo htmlspecialchars($schedulerDefaultSimpleSecondCommand); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                            <div style="margin-top:6px; color:#707986; font-size:11px; line-height:1.5;">Supporta placeholder dinamici: <code>{exp}</code>, <code>{day}</code>, <code>{value}</code>. Se valorizzato, viene eseguito subito dopo il comando principale.</div>
                                        </div>

                                        <div style="margin-top: 2px; margin-bottom: 10px; color:#e8c881; font-size: 11px; letter-spacing: .4px; text-transform: uppercase;">Data selector check (real DB validation)</div>
                                        <div class="char-input-group" style="display:block; margin-bottom: 10px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Database</label>
                                            <select id="scheduler-simple-database" name="simple_database" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <?php foreach ($schedulerSimpleDatabases as $simpleDbName): ?>
                                                    <option value="<?php echo htmlspecialchars($simpleDbName); ?>" <?php echo (string) ($schedulerEditSimpleMeta['database'] ?? '') === $simpleDbName ? 'selected' : ''; ?>><?php echo htmlspecialchars($simpleDbName); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 10px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Table</label>
                                            <input type="text" id="scheduler-simple-table" name="simple_table" class="char-input" list="scheduler-simple-tables" maxlength="128" placeholder="Chars" value="<?php echo htmlspecialchars((string) ($schedulerEditSimpleMeta['table'] ?? '')); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 10px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Column</label>
                                            <input type="text" id="scheduler-simple-column" name="simple_column" class="char-input" list="scheduler-simple-columns" maxlength="128" placeholder="Level" value="<?php echo htmlspecialchars((string) ($schedulerEditSimpleMeta['column'] ?? '')); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                                            <div class="char-input-group" style="display:block;">
                                                <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Comparison</label>
                                                <select name="simple_operator" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                    <?php $simpleOperators = ['eq' => '=', 'neq' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', 'contains' => 'contains']; ?>
                                                    <?php foreach ($simpleOperators as $operatorKey => $operatorLabel): ?>
                                                        <option value="<?php echo htmlspecialchars($operatorKey); ?>" <?php echo (string) ($schedulerEditSimpleMeta['operator'] ?? 'eq') === $operatorKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($operatorLabel); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="char-input-group" style="display:block;">
                                                <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Expected Value</label>
                                                <input type="text" name="simple_expected_value" class="char-input" maxlength="255" placeholder="100" value="<?php echo htmlspecialchars((string) ($schedulerEditSimpleMeta['expected_value'] ?? '')); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                            </div>
                                        </div>

                                        <div style="margin-top:5px; color:#666; font-size:11px; margin-bottom: 8px;">I campi Database/Table/Column usano metadata reali e vengono verificati al salvataggio sul DB effettivo.</div>
                                        <div style="margin-top:0; color:#707986; font-size:11px; margin-bottom: 16px; line-height:1.6;">
                                            Esempi Comparison (vecchio flusso manuale):<br>
                                            • <code>PS_GameDefs</code> + <code>EXPTable</code> + <code>EXP</code> + <code>&gt;=</code> + <code>100</code><br>
                                            • <code>PS_GameDefs</code> + <code>EXPTable</code> + <code>NameDay</code> + <code>contains</code> + <code>Monday</code>
                                        </div>

                                        <button type="submit" class="btn-action"><?php echo htmlspecialchars($schedulerSubmitLabelSimple); ?></button>
                                    </form>

                                    <form id="scheduler-advanced-form" action="/admin_actions" method="POST" enctype="multipart/form-data" style="display:none; margin-top: 4px;">
                                        <input type="hidden" name="action" value="<?php echo htmlspecialchars($schedulerFormAction); ?>">
                                        <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="job_profile" value="advanced">
                                        <?php if ($schedulerEditJob): ?>
                                            <input type="hidden" name="job_id" value="<?php echo (int) $schedulerEditJob['JobID']; ?>">
                                        <?php endif; ?>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Job Name</label>
                                            <input type="text" name="job_name" class="char-input" maxlength="100" required placeholder="EXP Recovery Monitor" value="<?php echo htmlspecialchars($schedulerDefaultJobName); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Task Type</label>
                                            <select name="task_type" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <option value="SERVICE_COMMAND" <?php echo $schedulerDefaultTaskType === 'SERVICE_COMMAND' ? 'selected' : ''; ?>>Service Command</option>
                                                <option value="SQL_SCRIPT" <?php echo $schedulerDefaultTaskType === 'SQL_SCRIPT' ? 'selected' : ''; ?>>SQL Script</option>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Windows Schedule</label>
                                            <select name="simple_interval" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <?php foreach ($scheduleOptions as $scheduleKey => $scheduleLabel): ?>
                                                    <option value="<?php echo htmlspecialchars($scheduleKey); ?>" <?php echo $schedulerDefaultSchedule === $scheduleKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($scheduleLabel); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service</label>
                                            <select name="service_name" class="char-input" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                                <option value="ps_game" <?php echo $schedulerDefaultService === 'ps_game' ? 'selected' : ''; ?>>ps_game</option>
                                                <option value="ps_login" <?php echo $schedulerDefaultService === 'ps_login' ? 'selected' : ''; ?>>ps_login</option>
                                            </select>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 14px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">Service Command</label>
                                            <input type="text" name="command_text" class="char-input" maxlength="400" placeholder="/exp2xenable 100" value="<?php echo htmlspecialchars($schedulerDefaultCommand); ?>" style="width:100%; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; height: 40px;">
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 12px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">SQL Script (inline)</label>
                                            <textarea name="sql_payload" class="char-input" placeholder="Esempio rapido: DECLARE @DayOfWeek NVARCHAR(50) = DATENAME(dw, GETDATE()); SELECT EXP FROM PS_GameDefs.dbo.EXPTable WHERE NameDay = @DayOfWeek;" style="width:100%; height: 120px; background: rgba(0,0,0,0.3); border-color:#333; color:#fff; resize: vertical;"><?php echo htmlspecialchars($schedulerDefaultSqlPayload); ?></textarea>
                                        </div>

                                        <div class="char-input-group" style="display:block; margin-bottom: 18px;">
                                            <label style="display:block; color:#888; font-size: 11px; margin-bottom: 7px; text-transform: uppercase;">SQL File (.sql optional)</label>
                                            <input type="file" name="sql_file" accept=".sql" style="width:100%; color:#aaa; font-size: 12px;">
                                        </div>

                                        <button type="submit" class="btn-action"><?php echo htmlspecialchars($schedulerSubmitLabelAdvanced); ?></button>
                                    </form>

                                    <datalist id="scheduler-simple-tables"></datalist>
                                    <datalist id="scheduler-simple-columns"></datalist>
                                </div>
                                <?php endif; ?>

                                <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; min-width:0;">
                                    <h4 style="color:#fff; margin-bottom: 15px; font-size: 15px;">Configured Jobs</h4>
                                    <div class="chars-table-container" style="width: 100%; overflow-x: auto; overflow-y: hidden;">
                                        <table class="chars-table" style="min-width: 980px; width: 100%;">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Job</th>
                                                        <th>Type</th>
                                                        <th>Schedule</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($schedulerJobs as $job): ?>
                                                    <tr>
                                                        <td style="color:#777; white-space: nowrap;">#<?php echo (int) $job['JobID']; ?></td>
                                                        <td style="min-width: 180px; text-align: left;"><?php echo htmlspecialchars($job['JobName']); ?></td>
                                                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($job['TaskType']); ?></td>
                                                        <td style="white-space: nowrap;"><span style="color:#999;"><?php echo htmlspecialchars($job['ScheduleMode'] . ': ' . $job['ScheduleValue']); ?></span></td>
                                                        <td>
                                                            <?php $isEnabled = (int) ($job['IsEnabled'] ?? 0) === 1; ?>
                                                            <span class="status-badge" style="background: <?php echo $isEnabled ? 'rgba(72,187,120,0.12)' : 'rgba(229,62,62,0.12)'; ?>; color: <?php echo $isEnabled ? '#48bb78' : '#e53e3e'; ?>; border: 1px solid <?php echo $isEnabled ? 'rgba(72,187,120,0.2)' : 'rgba(229,62,62,0.2)'; ?>;">
                                                                <?php echo $isEnabled ? 'ACTIVE' : 'PAUSED'; ?>
                                                            </span>
                                                        </td>
                                                        <td style="min-width: 250px;">
                                                            <div style="display:flex; gap:6px; justify-content:flex-start; flex-wrap: nowrap;">
                                                                <a href="/admin?view=task_scheduler&edit_job_id=<?php echo (int) $job['JobID']; ?>#task-scheduler-top" class="btn-action btn-sm" style="text-decoration:none;">Edit</a>
                                                                <form action="/admin_actions" method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="task_scheduler_toggle_job">
                                                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
                                                                    <input type="hidden" name="enabled" value="<?php echo $isEnabled ? '0' : '1'; ?>">
                                                                    <button type="submit" class="btn-action btn-sm"><?php echo $isEnabled ? 'Pause' : 'Resume'; ?></button>
                                                                </form>
                                                                <form action="/admin_actions" method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="task_scheduler_run_now">
                                                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
                                                                    <button type="submit" class="btn-action btn-sm">Run Now</button>
                                                                </form>
                                                                <form action="/admin_actions" method="POST" style="display:inline;" class="js-confirm-submit" data-confirm="Delete this scheduler job?">
                                                                    <input type="hidden" name="action" value="task_scheduler_delete_job">
                                                                    <input type="hidden" name="return_view" value="TASK_SCHEDULER">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
                                                                    <button type="submit" class="btn-action btn-sm" style="background: rgba(165,39,39,0.8); border-color:#a52727;">Delete</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($schedulerJobs)): ?>
                                                    <tr><td colspan="6" style="text-align:center; padding: 40px; color:#555;">No scheduler jobs configured yet.</td></tr>
                                                <?php endif; ?>
                                                </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div id="scheduler-runs-panel" style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 20px; min-width:0;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom: 12px;">
                                        <h4 style="color:#fff; margin:0; font-size: 15px;">Recent Runs (Worker Feedback)</h4>
                                        <form id="scheduler-runs-filter-form" action="/admin" method="GET" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                            <input type="hidden" name="view" value="task_scheduler">
                                            <input type="hidden" name="runs_page" value="1">
                                            <?php if (isset($_GET['scheduler_form']) && (string) $_GET['scheduler_form'] === '1'): ?>
                                                <input type="hidden" name="scheduler_form" value="1">
                                            <?php endif; ?>
                                            <?php if (isset($_GET['edit_job_id']) && (int) $_GET['edit_job_id'] > 0): ?>
                                                <input type="hidden" name="edit_job_id" value="<?php echo (int) $_GET['edit_job_id']; ?>">
                                            <?php endif; ?>
                                            <?php if (isset($_GET['job_mode']) && in_array((string) $_GET['job_mode'], ['simple', 'advanced'], true)): ?>
                                                <input type="hidden" name="job_mode" value="<?php echo htmlspecialchars((string) $_GET['job_mode']); ?>">
                                            <?php endif; ?>

                                            <input type="text" id="scheduler-runs-search" name="runs_q" value="<?php echo htmlspecialchars($schedulerRunsSearch); ?>" maxlength="120" placeholder="Filter runs, output, error, by..." class="char-input" style="width: 280px; height: 34px; background: rgba(0,0,0,0.3); border-color:#333; color:#fff;">
                                            <select id="scheduler-runs-status" name="runs_status" class="char-input" style="width: 150px; height: 34px; background: rgba(0,0,0,0.3); border-color:#333; color:#fff;">
                                                <option value="" <?php echo $schedulerRunsStatus === '' ? 'selected' : ''; ?>>All status</option>
                                                <option value="success" <?php echo $schedulerRunsStatus === 'success' ? 'selected' : ''; ?>>Success</option>
                                                <option value="done" <?php echo $schedulerRunsStatus === 'done' ? 'selected' : ''; ?>>Done</option>
                                                <option value="failed" <?php echo $schedulerRunsStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                <option value="error" <?php echo $schedulerRunsStatus === 'error' ? 'selected' : ''; ?>>Error</option>
                                                <option value="skipped" <?php echo $schedulerRunsStatus === 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                                            </select>

                                            <button type="submit" class="btn-action btn-sm" style="width:auto; min-width: 86px; padding: 0 12px;">Apply</button>
                                            <a href="/admin?view=task_scheduler#scheduler-runs-panel" class="btn-action btn-sm" style="text-decoration:none; width:auto; min-width: 86px; padding: 0 12px;">Reset</a>
                                            <button type="button" id="scheduler-runs-refresh-btn" class="btn-action btn-sm" style="width:auto; min-width: 42px; padding: 0 12px;" title="Refresh recent runs" aria-label="Refresh recent runs">
                                                <i class="fas fa-redo-alt"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <div id="scheduler-runs-summary" style="color:#8f98a3; font-size: 12px; margin-bottom: 10px;"><?php echo htmlspecialchars($schedulerRunsSummaryText); ?></div>
                                    <div class="chars-table-container" style="width: 100%; overflow-x: auto; overflow-y: hidden;">
                                        <table class="chars-table" style="min-width: 1220px; width: 100%;">
                                            <thead>
                                                <tr>
                                                    <th>RunID</th>
                                                    <th>JobID</th>
                                                    <th>Status</th>
                                                    <th>Started</th>
                                                    <th>Finished</th>
                                                    <th>Output</th>
                                                    <th>Error</th>
                                                    <th>By</th>
                                                </tr>
                                            </thead>
                                            <tbody id="scheduler-runs-tbody"><?php echo $schedulerRunsRowsHtml; ?></tbody>
                                        </table>
                                    </div>

                                    <div style="display:flex; align-items:center; justify-content:flex-end; margin-top: 12px;">
                                        <div id="scheduler-runs-pagination"><?php echo $schedulerRunsPaginationHtml; ?></div>
                                    </div>
                                </div>
                            </div>

                            <div id="task-scheduler-guide" style="margin-top: 28px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 22px;">
                                <div style="display:flex; align-items:flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
                                    <div>
                                        <div style="color:#e8c881; font-size: 15px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; margin-bottom: 6px;">Worker setup guide</div>
                                        <div style="color:#b8c0cc; font-size: 13px; line-height: 1.8; max-width: 980px;">Questa guida serve solo quando devi installare o aggiornare il worker Windows. Il task di sistema sul server worker esegue il poll ogni minuto; il pannello salva la pianificazione del singolo job che il worker poi applica localmente.</div>
                                    </div>
                                    <a href="#task-scheduler-top" style="display:inline-flex; align-items:center; justify-content:center; padding: 9px 14px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color:#b8b8b8; text-decoration:none; font-size: 12px; font-weight: 600;">Back to top</a>
                                </div>

                                <details open style="background: rgba(0,0,0,0.18); border: 1px solid rgba(255,255,255,0.04); border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;">
                                    <summary style="cursor: pointer; color:#fff; font-size: 13px; font-weight: 700; list-style: none;">Quick start (worker Windows)</summary>
                                    <div style="color:#c0c7d2; font-size: 13px; line-height: 1.8; margin-top: 12px;">
                                        <div style="margin-bottom: 10px;">Ci sono due copie della stessa cartella:</div>
                                        <ul style="margin: 10px 0 0 18px; padding: 0;">
                                            <li><b style="color:#e8c881;">Copie nel sito web</b>: vivono sotto <code>C:\xampp\htdocs\tools\task-scheduler-worker\</code> e servono solo come riferimento/documentazione nel progetto. Questa copia non deve essere esposta al pubblico: la cartella usa <b>.htaccess</b> per bloccarne l'accesso.</li>
                                            <li><b style="color:#93c5fd;">Copie sul worker Windows</b>: la stessa cartella va copiata su un computer Windows che esegue il worker come processo locale. Lì stanno <b>worker.ps1</b>, <b>install-worker.ps1</b>, <b>generate-worker-config.ps1</b> e <b>worker.config.json</b>. Questa copia non passa da Apache e non è pubblica.</li>
                                        </ul>
                                        <ol style="margin: 12px 0 0 18px; padding: 0;">
                                            <li>Sul worker Windows copia i file della cartella in una directory locale dedicata, poi esegui <b>generate-worker-config.ps1</b> per creare un <code>hmac_secret</code> casuale e scrivere <b>worker.config.json</b>.</li>
                                            <li>Imposta <code>api_url</code> su <code>http://127.0.0.1/task_scheduler_worker_api.php</code> solo se il worker gira sulla stessa macchina del sito; altrimenti usa l'URL interno corretto e mantieni l'endpoint limitato al loopback lato server.</li>
                                            <li>Compila <code>sql_connection_string</code> con il database del sito web (es. <code>PS_WebSite</code>) e poi esegui <b>install-worker.ps1</b> come Administrator: il task Windows viene registrato con polling a intervalli di minuti tramite <code>schtasks.exe</code>. Se nell'XML vedi <code>&lt;TimeTrigger&gt;</code> con <code>PT1M</code>, il worker poller e' configurato correttamente.</li>
                                            <li>In <b>Permissions Management</b> abilita <code>TASK_SCHEDULER</code>, scegli <b>Quick Command Job</b> o <b>Advanced Job</b>, poi crea/modifica il job e controlla <b>heartbeat</b> e <b>run log</b>: il pannello prepara i job, il worker Windows li consuma e li traduce in task locali quando serve.</li>
                                            <li><b>Esempio pratico dal tuo script manuale</b>: i job <code>SERVICE_COMMAND</code> usano ora direttamente <code>PS_GameDefs.dbo.Command</code>, quindi puoi usare sia <code>/nt ...</code> sia <code>/exp2xenable 100</code> nel flusso scheduler standard.</li>
                                        </ol>
                                    </div>
                                </details>

                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                    <div style="background: rgba(0,0,0,0.18); border: 1px solid rgba(255,255,255,0.04); border-radius: 10px; padding: 14px 16px; color:#bfc7d2; font-size: 13px; line-height: 1.8;">
                                        <div style="color:#e8c881; font-weight: 600; margin-bottom: 8px;">Field guide</div>
                                        <div><b>Quick Command Job</b>: form semplificata con Job Name, Windows Schedule, Service, Command, Second Command opzionale e selettori DB/Table/Column validati su metadata reali.</div>
                                        <div><b>Advanced Job</b>: mantiene la form completa con <code>SERVICE_COMMAND</code> / <code>SQL_SCRIPT</code> e payload SQL inline/upload.</div>
                                        <div><b>Comparison</b>: filtra una condizione semplice (es. <code>EXP &gt;= 100</code> o <code>NameDay contains Monday</code>) come metadato operativo del quick job.</div>
                                        <div><b>Windows Schedule</b>: scegli una frequenza pronta nel pannello; il worker salva quel valore e crea il task locale con la pianificazione corretta.</div>
                                        <div><b>Worker Poller</b>: il task <code>ShaiyaSchedulerWorker</code> gira ogni minuto e sincronizza i job dal sito verso Task Scheduler.</div>
                                        <div><b>Job Trigger</b>: dopo il salvataggio del job, il worker crea o aggiorna il task locale <code>ShaiyaWebJob_&lt;JobID&gt;</code> con il suo trigger Windows.</div>
                                        <div><b>Service</b>: servizio target del comando, ad esempio <code>ps_game</code> o <code>ps_login</code>.</div>
                                        <div><b>Service Command</b>: comando principale inviato a <code>PS_GameDefs.dbo.Command</code>; in quick mode, <code>/exp2xenable</code> usa il valore dinamico risolto da DB (es. per giorno corrente da <code>NameDay</code>).</div>
                                        <div><b>Second Command (optional)</b>: comando aggiuntivo eseguito subito dopo il principale (tipico <code>/nt ...</code>) con placeholder <code>{day}</code> / <code>{exp}</code>.</div>
                                    </div>

                                    <div style="background: rgba(0,0,0,0.18); border: 1px solid rgba(255,255,255,0.04); border-radius: 10px; padding: 14px 16px; color:#bfc7d2; font-size: 13px; line-height: 1.8;">
                                        <div style="color:#e8c881; font-weight: 600; margin-bottom: 8px;">Example build: restore EXP notice</div>
                                        <div>Per il tuo caso reale puoi usare un solo quick job con doppio comando:</div>
                                        <div style="margin-top: 8px;"><b>Main command</b>: <code>/exp2xenable</code> (senza valore fisso), con selettori DB su <code>PS_GameDefs.EXPTable.EXP</code>.</div>
                                        <div><b>Second command</b>: <code>/nt Player, today is {day}. EXP: x{exp}. Enjoy the game!</code>.</div>
                                        <div><b>Runtime</b>: il worker risolve il valore del giorno corrente, esegue <code>/exp2xenable &lt;exp&gt;</code> e poi il comando notice con placeholder sostituiti.</div>
                                        <div style="margin-top: 8px;">Per logiche molto più complesse resta disponibile il profilo <b>Advanced</b> con <code>SQL_SCRIPT</code>.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($view === 'REGISTER_TOGGLE'): ?>
                        <?php
                        $registerSettings = fetchWebsiteSettings(['RegistrationEnabled']);
                        $regEnabled = (string) ($registerSettings['RegistrationEnabled'] ?? '1');
                        ?>
                        <div class="char-edit-section"
                            style="max-width: 600px; margin: 80px auto; text-align: center; padding: 20px;">
                            <h3 style="margin-bottom: 10px; font-size: 24px; color: #e8c881;">REGISTRATION CONTROL</h3>
                            <p style="color: #666; margin-bottom: 50px;">Global toggle for new account creation via the
                                website.
                            </p>

                            <form action="/admin_actions" method="POST" style="display: block;">
                                <input type="hidden" name="action" value="register_toggle">
                                <input type="hidden" name="return_view" value="REGISTER_TOGGLE">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                <div
                                    style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 50px; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                                    <div
                                        style="width: 70px; height: 70px; background: <?php echo $regEnabled == '1' ? 'rgba(72,187,120,0.1)' : 'rgba(229,62,62,0.1)'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; border: 1px solid <?php echo $regEnabled == '1' ? 'rgba(72,187,120,0.2)' : 'rgba(229,62,62,0.2)'; ?>;">
                                        <i class="fas fa-user-plus"
                                            style="font-size: 28px; color: <?php echo $regEnabled == '1' ? '#48bb78' : '#e53e3e'; ?>;"></i>
                                    </div>

                                    <div
                                        style="font-size: 18px; color: #fff; margin-bottom: 30px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">
                                        Registration is: <span
                                            style="color: <?php echo $regEnabled == '1' ? '#48bb78' : '#e53e3e'; ?>;"><?php echo $regEnabled == '1' ? 'OPEN' : 'CLOSED'; ?></span>
                                    </div>

                                    <div style="display: flex; justify-content: center; margin-bottom: 30px;">
                                        <label class="switch" style="transform: scale(1.8);">
                                            <input type="checkbox" name="reg_enabled" <?php echo $regEnabled == '1' ? 'checked' : ''; ?> class="js-auto-submit-onchange">
                                            <span class="slider round"></span>
                                        </label>
                                    </div>

                                    <div
                                        style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 6px; color: #555; font-size: 12px; line-height: 1.6;">
                                        <?php if ($regEnabled == '1'): ?>
                                            <i class="fas fa-info-circle" style="margin-right: 5px; color: #48bb78;"></i>
                                            PUBLIC:
                                            Users can sign up freely.
                                        <?php else: ?>
                                            <i class="fas fa-lock" style="margin-right: 5px; color: #e53e3e;"></i> PRIVATE: New
                                            account creation is disabled.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($view === 'USERS'): ?>
                        <?php
                        // Original searches for compatibility
                        $searchChar = isset($_GET['search_char']) ? trim($_GET['search_char']) : '';
                        $searchUser = isset($_GET['search_user']) ? trim($_GET['search_user']) : '';

                        // New Header Filters
                        $fUserID = isset($_GET['f_userid']) ? trim($_GET['f_userid']) : $searchUser;
                        $fCharName = isset($_GET['f_charname']) ? trim($_GET['f_charname']) : $searchChar;
                        $fUserUID = isset($_GET['f_useruid']) ? trim($_GET['f_useruid']) : '';
                        $fStatus = isset($_GET['f_status']) ? trim($_GET['f_status']) : '';
                        $fFaction = isset($_GET['f_faction']) ? trim($_GET['f_faction']) : '';

                        $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                        $perPage = 50;
                        $offset = ($page - 1) * $perPage;

                        // Build WHERE with PDO params — pdoAdmin uses EMULATE_PREPARES=true which
                        // handles parameter binding client-side, bypassing the ODBC legacy driver's
                        // SQLDescribeParameter limitations on aliased JOIN queries and subqueries.
                        $whereClauses = ["1=1"];
                        $whereParams = [];
                        if ($fCharName) {
                            $q = $pdoAdmin->prepare("SELECT DISTINCT UserUID FROM PS_GameData.dbo.Chars WHERE CharName LIKE ?");
                            $q->execute(['%' . $fCharName . '%']);
                            $uids = [];
                            while ($r = $q->fetch()) {
                                $uids[] = (int) $r['UserUID'];
                            }
                            if (count($uids) > 0) {
                                $inPlaceholders = implode(',', array_fill(0, count($uids), '?'));
                                $whereClauses[] = "U.UserUID IN ($inPlaceholders)";
                                $whereParams = array_merge($whereParams, $uids);
                            } else {
                                $whereClauses[] = "1=0";
                            }
                        }
                        if ($fUserID !== '') {
                            $whereClauses[] = "U.UserID LIKE ?";
                            $whereParams[] = '%' . $fUserID . '%';
                        }
                        if ($fUserUID !== '' && ctype_digit((string) $fUserUID)) {
                            $whereClauses[] = "U.UserUID = ?";
                            $whereParams[] = (int) $fUserUID;
                        }
                        if ($fStatus !== '' && ctype_digit((string) $fStatus)) {
                            $whereClauses[] = "U.Status = ?";
                            $whereParams[] = (int) $fStatus;
                        }
                        if ($fFaction !== '' && in_array((string) $fFaction, ['0', '1'], true)) {
                            $whereClauses[] = "MG.Country = ?";
                            $whereParams[] = (int) $fFaction;
                        }
                        $whereSql = "WHERE " . implode(" AND ", $whereClauses);

                        // Count total for pagination
                        $countSql = "SELECT COUNT(*) as Total FROM PS_UserData.dbo.Users_Master U LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = U.UserUID $whereSql";
                        $countStmt = $pdoAdmin->prepare($countSql);
                        $countStmt->execute($whereParams);
                        $countQArr = $countStmt->fetch();
                        $totalUsers = (int) ($countQArr['Total'] ?? 0);
                        $totalPages = ceil($totalUsers / $perPage);

                        // List query — OFFSET/FETCH values are (int), safe to inline
                        $userSql = "SELECT 
                            U.UserUID, U.UserID, U.Status, U.Point, U.JoinDate,
                            C.CharID as MainCharID,
                            C.CharName as MainChar,
                            C.K1 as Kills,
                            C.LoginStatus,
                            C.LeaveDate as OnlineLeaveDate,
                            MG.Country as Faction,
                            (SELECT SUM(UsePoint) FROM PS_GameData.dbo.PointLog WHERE UserUID = U.UserUID) as Spent
                        FROM PS_UserData.dbo.Users_Master U
                        LEFT JOIN PS_GameData.dbo.Chars C ON C.UserUID = U.UserUID AND C.Slot = 0 AND C.Del = 0
                        LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = U.UserUID
                        $whereSql
                        ORDER BY U.UserUID DESC
                        OFFSET $offset ROWS FETCH NEXT $perPage ROWS ONLY";
                        $userRows = [];
                        $userStmt = $pdoAdmin->prepare($userSql);
                        $userStmt->execute($whereParams);
                        while ($u = $userStmt->fetch()) {
                            $userRows[] = $u;
                        }

                        // Optimized batch playtime fetch for the current page
                        $pageUserIDs = [];
                        $allUsers = [];
                        foreach ($userRows as $u) {
                            $pageUserIDs[] = (string) $u['UserID'];
                            $allUsers[] = $u;
                        }

                        $sessionSecondsStmt = $pdoAdmin->prepare("SELECT TOP 1 DATEDIFF(SECOND, ActionTime, GETDATE()) as SessionSeconds FROM PS_GameLog.dbo.ActionLog WHERE CAST(UserID AS VARCHAR(18)) = CAST(? AS VARCHAR(18)) AND ActionType = 107 ORDER BY ActionTime DESC");

                        $playtimes = [];
                        if (count($pageUserIDs) > 0) {
                            // PDO with EMULATE_PREPARES=true handles ? in subqueries correctly
                            // CAST(a.UserID AS VARCHAR(18)): ActionLog.UserID is varchar(18) type; must cast for IN() comparison
                            $uidPlaceholders = implode(',', array_fill(0, count($pageUserIDs), 'CAST(? AS VARCHAR(18))'));
                            $p_sql = "SELECT UserID, SUM(DATEDIFF(SECOND, LoginTime, LogoutTime)) as TotalSeconds
                          FROM (
                              SELECT a.UserID, a.ActionTime as LogoutTime, MAX(l.ActionTime) as LoginTime
                              FROM PS_GameLog.dbo.ActionLog a
                              LEFT JOIN PS_GameLog.dbo.ActionLog l ON CAST(l.UserID AS VARCHAR(18)) = CAST(a.UserID AS VARCHAR(18)) AND l.ActionType = 107 AND l.ActionTime < a.ActionTime
                              WHERE CAST(a.UserID AS VARCHAR(18)) IN ($uidPlaceholders) AND a.ActionType = 108
                              GROUP BY a.UserID, a.ActionTime
                          ) t
                          WHERE LoginTime IS NOT NULL
                          GROUP BY UserID";
                            $p_stmt = $pdoAdmin->prepare($p_sql);
                            $p_stmt->execute($pageUserIDs);
                            while ($p_row = $p_stmt->fetch()) {
                                $playtimes[$p_row['UserID']] = (int) $p_row['TotalSeconds'];
                            }
                        }
                        ?>

                        <div class="chars-table-container">
                            <form method="GET" id="userHeaderFilters">
                                <input type="hidden" name="view" value="USERS">

                                <table class="chars-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;">Faction
                                                    <select name="f_faction" class="table-filter-select js-auto-submit-onchange">
                                                    <option value="">All</option>
                                                    <option value="0" <?php echo $fFaction === '0' ? 'selected' : ''; ?>>AOL
                                                    </option>
                                                    <option value="1" <?php echo $fFaction === '1' ? 'selected' : ''; ?>>UOF
                                                    </option>
                                                </select>
                                            </th>
                                            <th style="width: 80px;">UID
                                                <input type="text" name="f_useruid" class="table-filter-input js-auto-submit-onchange"
                                                    placeholder="ID" value="<?php echo htmlspecialchars($fUserUID); ?>">
                                            </th>
                                            <th style="width: 150px;">Username
                                                <input type="text" name="f_userid" class="table-filter-input js-auto-submit-onchange"
                                                    placeholder="User" value="<?php echo htmlspecialchars($fUserID); ?>">
                                            </th>
                                            <th style="width: 100px;">Status
                                                <select name="f_status" class="table-filter-select js-auto-submit-onchange">
                                                    <option value="">All</option>
                                                    <option value="16" <?php echo $fStatus === '16' ? 'selected' : ''; ?>>ADM
                                                    </option>
                                                    <option value="32" <?php echo $fStatus === '32' ? 'selected' : ''; ?>>GM
                                                    </option>
                                                    <option value="48" <?php echo $fStatus === '48' ? 'selected' : ''; ?>>GMA
                                                    </option>
                                                    <option value="0" <?php echo $fStatus === '0' ? 'selected' : ''; ?>>Player
                                                    </option>
                                                </select>
                                            </th>
                                            <th style="width: 100px;">Points</th>
                                            <th style="width: 100px;">Spent</th>
                                            <th style="width: 150px;">Main Char
                                                <input type="text" name="f_charname" class="table-filter-input js-auto-submit-onchange"
                                                    placeholder="Name" value="<?php echo htmlspecialchars($fCharName); ?>">
                                            </th>
                                            <th style="width: 150px;">Kills</th>
                                            <th style="width: 100px;">Playtime</th>
                                            <th style="width: 130px;">Created</th>
                                            <th style="width: 120px;">Last Login</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allUsers as $u): ?>
                                            <tr>
                                                <td>
                                                    <?php if ((int) $u['Faction'] === 1): ?>
                                                        <img src="assets/uof.webp" style="width: 20px;">
                                                    <?php elseif (isset($u['Faction']) && (int) $u['Faction'] === 0 && !empty($u['MainChar'])): ?>
                                                        <img src="assets/aol.webp" style="width: 20px;">
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td style="color: #666;"><?php echo $u['UserUID']; ?></td>
                                                <td>
                                                    <a href="/admin?view=UserEdit&uid=<?php echo $u['UserUID']; ?>"
                                                        class="user-link"><?php echo htmlspecialchars($u['UserID']); ?></a>
                                                </td>
                                                <td>
                                                    <?php
                                                    $uStatus = (int) ($u['Status'] ?? 0);
                                                    $sLabel = 'Player';
                                                    $sClass = '';
                                                    if ($uStatus == 16) {
                                                        $sLabel = 'ADM';
                                                        $sClass = 'status-active';
                                                    } elseif ($uStatus == 32) {
                                                        $sLabel = 'GM';
                                                        $sClass = 'status-active';
                                                    } elseif ($uStatus == 48) {
                                                        $sLabel = 'GMA';
                                                        $sClass = 'status-active';
                                                    }
                                                    ?>
                                                    <span
                                                        class="status-badge <?php echo $sClass; ?>"><?php echo $sLabel; ?></span>
                                                </td>
                                                <td><?php echo number_format($u['Point']); ?></td>
                                                <td><?php echo number_format($u['Spent'] ?? 0); ?></td>
                                                <td>
                                                    <?php if (!empty($u['MainChar'])): ?>
                                                        <a href="/admin?view=CharEdit&id=<?php echo $u['MainCharID']; ?>"
                                                            class="user-link"><?php echo htmlspecialchars($u['MainChar']); ?></a>
                                                    <?php else: ?>
                                                        <span style="color: #444; font-size: 13px;">No chars</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($u['Kills'] ?? 0); ?>
                                                    <?php echo getAdminRankIcon((int) ($u['Kills'] ?? 0)); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $u_esc = $u['UserID'];
                                                    $uPlaytime = $playtimes[$u_esc] ?? 0;
                                                    if ((int) ($u['LoginStatus'] ?? 0) === 1) {
                                                        try {
                                                            $sessionSecondsStmt->execute([$u_esc]);
                                                            $s_row = $sessionSecondsStmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($s_row) $uPlaytime += (int) $s_row['SessionSeconds'];
                                                        } catch (Throwable $e) {}
                                                    }
                                                    echo formatPlaytime($uPlaytime);
                                                    ?>
                                                </td>
                                                <td style="color: #888; font-size: 13px;">
                                                    <?php echo date('d M Y', strtotime($u['JoinDate'])); ?>
                                                </td>
                                                <td class="time-relative">
                                                    <?php echo formatRelativeTime($u['OnlineLeaveDate']); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </form>
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination"
                                    style="display: flex; justify-content: center; gap: 10px; margin-top: 30px;">
                                    <?php
                                    $queryParams = [
                                        'view' => 'USERS',
                                        'f_useruid' => $fUserUID,
                                        'f_userid' => $fUserID,
                                        'f_charname' => $fCharName,
                                        'f_status' => $fStatus,
                                        'f_faction' => $fFaction
                                    ];
                                    $queryString = http_build_query(array_filter($queryParams, function ($v) {
                                        return $v !== '';
                                    }));
                                    $url = "/admin?" . $queryString;

                                    if ($page > 1): ?>
                                        <a href="<?php echo $url; ?>&p=<?php echo $page - 1; ?>" class="btn-search"
                                            style="padding: 5px 15px;">PREV</a>
                                    <?php endif; ?>
                                    <span style="color: #888; padding: 5px;">Page <?php echo $page; ?> of
                                        <?php echo $totalPages; ?></span>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?php echo $url; ?>&p=<?php echo $page + 1; ?>" class="btn-search"
                                            style="padding: 5px 15px;">NEXT</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($view === 'USEREDIT'): ?>
                        <?php
                        $userUID = isset($_GET['uid']) ? (int) $_GET['uid'] : 0;
                        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'Overview';
                        $userData = null;
                        if ($userUID > 0) {
                            // Fetch basic account details
                            $sql = "SELECT U.*, 
                               (SELECT SUM(UsePoint) FROM PS_GameData.dbo.PointLog WHERE UserUID = U.UserUID) as SpentPoints
                        FROM PS_UserData.dbo.Users_Master U 
                        WHERE U.UserUID = ?";
                            try {
                                $stmt = $pdoAdmin->prepare($sql);
                                $stmt->execute([$userUID]);
                                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}
                        }

                        if (!$userData):
                            ?>
                            <div class="alert alert-error">Account not found (ID: <?php echo $userUID; ?>).</div>
                        <?php else:
                            // Fetch Main Character and Faction
                            $mainSql = "SELECT TOP 1 C.CharID, C.CharName, C.K1 as Kills, C.LeaveDate, C.LoginStatus, MG.Country as Faction
                            FROM PS_GameData.dbo.Chars C
                            LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID
                            WHERE C.UserUID = ? AND C.Del = 0
                            ORDER BY C.Slot ASC";
                            $mainChar = null;
                            try {
                                $mStmt = $pdoAdmin->prepare($mainSql);
                                $mStmt->execute([$userUID]);
                                $mainChar = $mStmt->fetch(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}

                            $isOnline = (int) ($mainChar['LoginStatus'] ?? 0) === 1;

                            // Fetch Reroll Runes Used (Text1 = 'Recreation Rune', Text2 = 'use_item')
                            $runeSql = "SELECT COUNT(*) as RuneCount FROM PS_GameLog.dbo.ActionLog WHERE Text1 = 'Recreation Rune' AND Text2 = 'use_item' AND UserID = ?";
                            $rerollRunesUsed = 0;
                            try {
                                $rStmt = $pdoAdmin->prepare($runeSql);
                                $rStmt->execute([$userData['UserID']]);
                                $runeData = $rStmt->fetch(PDO::FETCH_ASSOC);
                                $rerollRunesUsed = (int) ($runeData['RuneCount'] ?? 0);
                            } catch (Throwable $e) {}

                            // Fetch Log In Count (ActionType 107)
                            $loginSql = "SELECT COUNT(*) as LoginCount FROM PS_GameLog.dbo.ActionLog WHERE ActionType = 107 AND UserID = ?";
                            $loginCount = 0;
                            try {
                                $lStmt = $pdoAdmin->prepare($loginSql);
                                $lStmt->execute([$userData['UserID']]);
                                $loginData = $lStmt->fetch(PDO::FETCH_ASSOC);
                                $loginCount = (int) ($loginData['LoginCount'] ?? 0);
                            } catch (Throwable $e) {}

                            // Fetch Chat Message Count
                            $chatSql = "SELECT COUNT(*) as ChatCount FROM PS_ChatLog.dbo.ChatLog WHERE UserUID = ?";
                            $chatCount = 0;
                            try {
                                $cStmt = $pdoAdmin->prepare($chatSql);
                                $cStmt->execute([$userUID]);
                                $chatData = $cStmt->fetch(PDO::FETCH_ASSOC);
                                $chatCount = (int) ($chatData['ChatCount'] ?? 0);
                            } catch (Throwable $e) {}

                            // Calculate Total Playtime (Seconds)
                            // pdoAdmin uses EMULATE_PREPARES=true: ? works in subqueries/derived tables.
                            // CAST(a.UserID / l.UserID AS VARCHAR(18)): ActionLog.UserID is varchar(18) type
                            $playtimeSql = "SELECT SUM(DATEDIFF(SECOND, LoginTime, LogoutTime)) as TotalSeconds
                                FROM (
                                    SELECT 
                                        a.ActionTime as LogoutTime,
                                        MAX(l.ActionTime) as LoginTime
                                    FROM PS_GameLog.dbo.ActionLog a
                                    LEFT JOIN PS_GameLog.dbo.ActionLog l ON CAST(l.UserID AS VARCHAR(18)) = CAST(a.UserID AS VARCHAR(18)) AND l.ActionType = 107 AND l.ActionTime < a.ActionTime
                                    WHERE CAST(a.UserID AS VARCHAR(18)) = CAST(? AS VARCHAR(18)) AND a.ActionType = 108
                                    GROUP BY a.ActionTime
                                ) t
                                WHERE LoginTime IS NOT NULL";
                            $pStmt = $pdoAdmin->prepare($playtimeSql);
                            $pStmt->execute([$userData['UserID']]);
                            $pData = $pStmt->fetch();
                            $totalSeconds = (int) ($pData['TotalSeconds'] ?? 0);

                            // Add current session if online
                            if ($isOnline) {
                                $sStmt = $pdoAdmin->prepare("SELECT TOP 1 DATEDIFF(SECOND, ActionTime, GETDATE()) as SessionSeconds FROM PS_GameLog.dbo.ActionLog WHERE CAST(UserID AS VARCHAR(18)) = CAST(? AS VARCHAR(18)) AND ActionType = 107 ORDER BY ActionTime DESC");
                                $sStmt->execute([$userData['UserID']]);
                                if ($sData = $sStmt->fetch()) {
                                    $totalSeconds += (int) $sData['SessionSeconds'];
                                }
                            }
                            ?>
                            <!-- User Edit Header -->
                            <div class="char-edit-header">
                                <div class="char-title-section">
                                    <h1><?php echo htmlspecialchars($userData['UserID']); ?> | OVERVIEW</h1>
                                    <p>User Management</p>
                                </div>
                                <div class="char-actions-bar">
                                    <form action="/admin_actions" method="POST" style="display:inline;" class="js-confirm-submit"
                                        data-confirm="<?php echo htmlspecialchars('Kick user ' . (string) $userData['UserID'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="user_kick">
                                        <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="return_view" value="UserEdit">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="admin-btn btn-kick">USER KICK</button>
                                    </form>
                                    <a href="/admin?view=Giftbox&target_user=<?php echo urlencode($userData['UserID']); ?>"
                                        class="admin-btn btn-del-item">ADD ITEM</a>
                                    <a href="/admin?view=Points&target_user=<?php echo urlencode($userData['UserID']); ?>"
                                        class="admin-btn btn-del-item">ADD POINTS</a>
                                    <div class="char-status-badge"
                                        style="<?php echo $isOnline ? '' : 'background: rgba(229, 62, 62, 0.2); color: #e53e3e; border-color: rgba(229, 62, 62, 0.3);'; ?>">
                                        <?php echo $isOnline ? 'ONLINE' : 'OFFLINE'; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabs -->
                            <div class="char-tabs">
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Overview"
                                    class="char-tab <?php echo (!$tab || $tab === 'Overview') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Overview</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Characters"
                                    class="char-tab <?php echo ($tab === 'Characters') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Characters</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog"
                                    class="char-tab <?php echo ($tab === 'AuthLog') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Auth log</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog"
                                    class="char-tab <?php echo ($tab === 'PointLog') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Point log</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases"
                                    class="char-tab <?php echo ($tab === 'Purchases') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Purchases</a>

                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Warehouse"
                                    class="char-tab <?php echo ($tab === 'Warehouse') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Warehouse</a>
                                <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat"
                                    class="char-tab <?php echo ($tab === 'Chat') ? 'active' : ''; ?>"
                                    style="text-decoration:none;">Chat
                                    Log</a>
                            </div>

                            <?php if (!$tab || $tab === 'Overview'): ?>

                                <!-- Administrative Actions Area -->
                                <div
                                    style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 40px; background: rgba(0,0,0,0.2); padding: 30px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.03);">
                                    <!-- Status Change -->
                                    <form action="/admin_actions" method="POST"
                                        style="display: flex; align-items: center; gap: 20px;">
                                        <input type="hidden" name="action" value="user_update_status">
                                        <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="return_view" value="UserEdit">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 13px; width: 120px; text-align: right;">Status</label>
                                        <select name="new_status" class="char-input" style="width: 200px;">
                                            <option value="0" <?php echo (int) $userData['Status'] === 0 ? 'selected' : ''; ?>>Player
                                            </option>
                                            <option value="16" <?php echo (int) $userData['Status'] === 16 ? 'selected' : ''; ?>>ADM
                                                (16)
                                            </option>
                                            <option value="32" <?php echo (int) $userData['Status'] === 32 ? 'selected' : ''; ?>>GM
                                                (32)
                                            </option>
                                            <option value="48" <?php echo (int) $userData['Status'] === 48 ? 'selected' : ''; ?>>GMA
                                                (48)
                                            </option>
                                            <option value="-5" <?php echo (int) $userData['Status'] === -5 ? 'selected' : ''; ?>>
                                                Banned (-5)
                                            </option>
                                        </select>
                                        <button type="submit" class="btn-search"
                                            style="padding: 10px 20px; letter-spacing: 1px;">SET
                                            STATUS</button>
                                    </form>

                                    <!-- Password Change -->
                                    <form action="/admin_actions" method="POST"
                                        style="display: flex; align-items: center; gap: 20px;">
                                        <input type="hidden" name="action" value="user_change_password">
                                        <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="return_view" value="UserEdit">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 13px; width: 120px; text-align: right;">Password
                                            change</label>
                                        <input type="text" name="new_password" class="char-input" style="width: 200px;"
                                            placeholder="New password...">
                                        <button type="submit" class="btn-search"
                                            style="padding: 10px 20px; letter-spacing: 1px;">CHANGE
                                            PASSWORD</button>
                                    </form>


                                </div>

                                <!-- Info Grid -->
                                <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                                    <!-- Account Table -->
                                    <table class="char-info-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="header-main">Account</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="width: 30%;">UserUID</td>
                                                <td><?php echo $userUID; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Username</td>
                                                <td><?php echo htmlspecialchars($userData['UserID']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Status</td>
                                                <td><?php echo (int) $userData['Status']; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Points</td>
                                                <td><?php echo number_format($userData['Point']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Spent points</td>
                                                <td><?php echo number_format($userData['SpentPoints'] ?? 0); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Create date</td>
                                                <td style="color: #888;">
                                                    <?php echo date('l, F d, Y g:i A', strtotime($userData['JoinDate'])); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Last enter date</td>
                                                <td class="time-relative">
                                                    <?php echo formatRelativeTime($mainChar['LeaveDate'] ?? $userData['JoinDate']); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Last leave date</td>
                                                <td style="color: #888;">
                                                    <?php echo date('l, F d, Y g:i A', strtotime($mainChar['LeaveDate'] ?? $userData['JoinDate'])); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Main Table -->
                                    <table class="char-info-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="header-main" style="background: #e8c881;">Main</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="width: 30%;">Faction</td>
                                                <td>
                                                    <?php if (isset($mainChar['Faction'])): ?>
                                                        <img src="assets/<?php echo ($mainChar['Faction'] == 0 ? 'aol.webp' : 'uof.webp'); ?>"
                                                            style="width: 24px; vertical-align: middle;">
                                                    <?php else: ?>
                                                        <span style="color: #444;">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Main character</td>
                                                <td>
                                                    <?php if ($mainChar): ?>
                                                        <a href="/admin?view=CharEdit&id=<?php echo $mainChar['CharID']; ?>"
                                                            class="user-link"><?php echo htmlspecialchars($mainChar['CharName']); ?></a>
                                                    <?php else: ?>
                                                        <span style="color: lightblue;">No characters</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Online time</td>
                                                <td><?php echo formatPlaytime($totalSeconds); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Kills</td>
                                                <td><?php echo number_format($mainChar['Kills'] ?? 0); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Other Table (Placeholders) -->
                                    <table class="char-info-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="header-main" style="background: #e8c881;">Other</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <tr>
                                                <td>Reroll Runes used</td>
                                                <td><?php echo number_format($rerollRunesUsed); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Chat Message Sent</td>
                                                <td><?php echo number_format($chatCount); ?></td>
                                            </tr>

                                            <tr>
                                                <td>Log In Count</td>
                                                <td><?php echo number_format($loginCount); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($tab === 'Characters'): ?>
                                <?php
                                $charSql = "SELECT C.*, MG.Country as Faction
                                FROM PS_GameData.dbo.Chars C
                                LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID
                                WHERE C.UserUID = ?
                                ORDER BY C.Del ASC, C.Slot ASC";
                                $activeChars = [];
                                $deletedChars = [];
                                try {
                                    $cStmt = $pdoAdmin->prepare($charSql);
                                    $cStmt->execute([$userUID]);
                                    while ($c = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                                        if ((int) $c['Del'] === 0) {
                                            $activeChars[] = $c;
                                        } else {
                                            $deletedChars[] = $c;
                                        }
                                    }
                                } catch (Throwable $e) {}
                                ?>

                                <!-- Active Characters -->
                                <div style="margin-bottom: 50px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h2 style="color: #fff; font-size: 18px; text-transform: uppercase; letter-spacing: 2px;">
                                            Active
                                            Characters</h2>
                                    </div>
                                    <div class="chars-table-container">
                                        <table class="chars-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 8%;">Slot</th>
                                                    <th style="width: 5%;"></th>
                                                    <th style="text-align: left; padding-left: 10px; width: 22%;">Name</th>
                                                    <th style="width: 10%;">Lv</th>
                                                    <th style="width: 15%;">Race</th>
                                                    <th style="width: 15%;">Create date</th>
                                                    <th style="width: 15%;">Last enter</th>
                                                    <th style="text-align:right; width: 10%;">Actions</th>
                                                </tr>
                                            </thead>
                                        <tbody>
                                            <?php if (empty($activeChars)): ?>
                                                <tr>
                                                    <td colspan="8" style="text-align:center; color:#666; padding: 40px;">No active
                                                        characters
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($activeChars as $c): ?>
                                                    <tr>
                                                        <td><?php echo $c['Slot']; ?></td>
                                                        <td><img src="assets/class/<?php echo (int) $c['Job']; ?>.webp"
                                                                style="width: 24px; vertical-align: middle;"></td>
                                                        <td><a href="/admin?view=CharEdit&id=<?php echo $c['CharID']; ?>"
                                                                class="user-link"><?php echo htmlspecialchars($c['CharName']); ?></a></td>
                                                        <td><?php echo $c['Level']; ?></td>
                                                        <td><?php echo getRaceName((int) $c['Family']); ?></td>
                                                        <td style="color:#888;"><?php echo date('Y M d H:i', strtotime($c['RegDate'])); ?>
                                                        </td>
                                                        <td style="color: #e8c881;"><?php echo formatRelativeTime($c['LeaveDate']); ?></td>
                                                        <td style="text-align:right;">
                                                            <div style="display: flex; justify-content: flex-end; gap: 15px;">
                                                                <form action="/admin_actions" method="POST" class="js-confirm-submit"
                                                                    data-confirm="<?php echo htmlspecialchars('Delete character ' . (string) $c['CharName'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <input type="hidden" name="action" value="char_delete">
                                                                    <input type="hidden" name="char_id" value="<?php echo $c['CharID']; ?>">
                                                                    <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                                                    <input type="hidden" name="return_view" value="UserEdit">
                                                                    <input type="hidden" name="tab" value="Characters">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <button type="submit"
                                                                        style="background:none; border:none; color:#e53e3e; cursor:pointer; font-size: 18px;"
                                                                        title="Delete"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Deleted Characters -->
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h2 style="color: #fff; font-size: 18px; text-transform: uppercase; letter-spacing: 2px;">
                                            Deleted
                                            Characters</h2>
                                    </div>
                                    <div class="chars-table-container">
                                        <table class="chars-table">
                                            <thead>
                                                <tr>
                                                    <th style="text-align: left; padding-left: 10px; width: 20%;">Name</th>
                                                    <th style="width: 10%;">Lv</th>
                                                    <th style="width: 15%;">Race</th>
                                                    <th style="width: 15%;">Create date</th>
                                                    <th style="width: 15%;">Last enter</th>
                                                    <th style="width: 15%;">Delete date</th>
                                                    <th style="text-align:right; width: 10%;">Actions</th>
                                                </tr>
                                            </thead>
                                        <tbody>
                                            <?php if (empty($deletedChars)): ?>
                                                <tr>
                                                    <td colspan="7" style="text-align:center; color:#666; padding: 40px;">No deleted
                                                        characters
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($deletedChars as $c): ?>
                                                    <tr>
                                                        <td><a href="/admin?view=CharEdit&id=<?php echo $c['CharID']; ?>"
                                                                class="user-link"><?php echo htmlspecialchars($c['CharName']); ?></a></td>
                                                        <td><?php echo $c['Level']; ?></td>
                                                        <td><?php echo getRaceName((int) $c['Family']); ?></td>
                                                        <td style="color:#888;"><?php echo date('Y M d H:i', strtotime($c['RegDate'])); ?>
                                                        </td>
                                                        <td style="color: #e8c881;"><?php echo formatRelativeTime($c['LeaveDate']); ?></td>
                                                        <td style="color:#888;">
                                                            <?php echo $c['DeleteDate'] ? date('Y M d H:i', strtotime($c['DeleteDate'])) : 'N/A'; ?>
                                                        </td>
                                                        <td style="text-align:right;">
                                                            <form action="/admin_actions" method="POST" class="js-confirm-submit"
                                                                data-confirm="<?php echo htmlspecialchars('Restore character ' . (string) $c['CharName'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="hidden" name="action" value="char_restore">
                                                                <input type="hidden" name="char_id" value="<?php echo $c['CharID']; ?>">
                                                                <input type="hidden" name="user_uid" value="<?php echo $userUID; ?>">
                                                                <input type="hidden" name="return_view" value="UserEdit">
                                                                <input type="hidden" name="tab" value="Characters">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                <button type="submit"
                                                                    style="background:none; border:none; color:#48bb78; cursor:pointer;"
                                                                    title="Restore"><i class="fas fa-undo"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php elseif ($tab === 'AuthLog'): ?>
                                <?php
                                // Pagination for Auth Log
                                $alLimit = 30;
                                $alPage = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $alFilterType = isset($_GET['at']) ? $_GET['at'] : '';
                                $alSearchIP = isset($_GET['sip']) ? trim($_GET['sip']) : '';
                                $alOffset = ($alPage - 1) * $alLimit;

                                // Build WHERE clause
                                $alWhere = ["UserID = ?"];
                                $alParams = [$userData['UserID']];
                                if ($alFilterType === '107') {
                                    $alWhere[] = "ActionType = 107";
                                } elseif ($alFilterType === '108') {
                                    $alWhere[] = "ActionType = 108";
                                } else {
                                    $alWhere[] = "ActionType IN (107, 108)";
                                }
                                if ($alSearchIP !== '') {
                                    $alWhere[] = "Text1 LIKE ?";
                                    $alParams[] = '%' . $alSearchIP . '%';
                                }
                                $alWhereSql = "WHERE " . implode(" AND ", $alWhere);

                                // Total Count
                                $alTotal = 0;
                                $alTotalPages = 1;
                                $alCountSql = "SELECT COUNT(*) as Total FROM PS_GameLog.dbo.ActionLog $alWhereSql";
                                try {
                                    $alCountStmt = $pdoAdmin->prepare($alCountSql);
                                    $alCountStmt->execute($alParams);
                                    $alTotal = (int) ($alCountStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                    $alTotalPages = max(1, ceil($alTotal / $alLimit));
                                } catch (Throwable $e) {}

                                $alSql = "SELECT ActionType, ActionTime, Text1
                                          FROM PS_GameLog.dbo.ActionLog
                                          $alWhereSql
                                          ORDER BY ActionTime DESC
                                          OFFSET $alOffset ROWS FETCH NEXT $alLimit ROWS ONLY";
                                $alRows = [];
                                try {
                                    $alStmt = $pdoAdmin->prepare($alSql);
                                    $alStmt->execute($alParams);
                                    $alRows = $alStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                    <h4 style="color: #fff; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <i class="fas fa-sign-in-alt" style="color: #da9f50; font-size: 14px;"></i> Authentication Log
                                    </h4>
                                    <span style="color: #555; font-size: 12px;"><?php echo number_format($alTotal); ?> event<?php echo $alTotal !== 1 ? 's' : ''; ?></span>
                                </div>

                                <div class="chars-table-container">
                                    <form method="GET" id="authLogFilters">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="AuthLog">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 120px;">Type
                                                    <select name="at" class="table-filter-select js-auto-submit-onchange">
                                                        <option value="">All</option>
                                                        <option value="107" <?php echo $alFilterType === '107' ? 'selected' : ''; ?>>Login</option>
                                                        <option value="108" <?php echo $alFilterType === '108' ? 'selected' : ''; ?>>Logout</option>
                                                    </select>
                                                </th>
                                                <th>Date & Time</th>
                                                <th>IP Address
                                                    <input type="text" name="sip" class="table-filter-input js-auto-submit-onchange" placeholder="IP" value="<?php echo htmlspecialchars($alSearchIP); ?>">
                                                </th>
                                                <th>Relative</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $alCount = 0;
                                            $prevTime = null;
                                            foreach ($alRows as $al):
                                                $alCount++;
                                                $isLogin = (int) $al['ActionType'] === 107;
                                                $actionTime = strtotime($al['ActionTime']);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($isLogin): ?>
                                                            <span style="background: rgba(72, 187, 120, 0.1); color: #48bb78; border: 1px solid rgba(72, 187, 120, 0.2); font-size: 11px; padding: 3px 10px; border-radius: 4px; font-weight: 600;">
                                                                <i class="fas fa-sign-in-alt" style="margin-right: 4px;"></i> LOGIN
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="background: rgba(229, 62, 62, 0.1); color: #e53e3e; border: 1px solid rgba(229, 62, 62, 0.2); font-size: 11px; padding: 3px 10px; border-radius: 4px; font-weight: 600;">
                                                                <i class="fas fa-sign-out-alt" style="margin-right: 4px;"></i> LOGOUT
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('Y M d', $actionTime); ?> <span style="color: #e8c881; font-weight: 500;"><?php echo date('H:i:s', $actionTime); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($isLogin && !empty($al['Text1'])): ?>
                                                            <span style="color: #6a8ec1; font-family: monospace; font-size: 12px;">
                                                                <i class="fas fa-globe" style="margin-right: 5px; opacity: 0.5; font-size: 10px;"></i><?php echo htmlspecialchars($al['Text1']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="color: #333;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="color: #888;">
                                                        <?php echo formatRelativeTime($al['ActionTime']); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php if ($alCount === 0): ?>
                                                <tr>
                                                    <td colspan="4" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                        <i class="fas fa-shield-alt" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                        No authentication events found for this account.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </form>
                                </div>

                                <?php if ($alTotalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($alPage > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog&p=<?php echo $alPage - 1; ?><?php echo $alFilterType !== '' ? '&at=' . $alFilterType : ''; ?><?php echo $alSearchIP !== '' ? '&sip=' . urlencode($alSearchIP) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $alStartPage = max(1, $alPage - 2);
                                        $alEndPage = min($alTotalPages, $alPage + 2);
                                        for ($i = $alStartPage; $i <= $alEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog&p=<?php echo $i; ?><?php echo $alFilterType !== '' ? '&at=' . $alFilterType : ''; ?><?php echo $alSearchIP !== '' ? '&sip=' . urlencode($alSearchIP) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $alPage) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($alPage < $alTotalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=AuthLog&p=<?php echo $alPage + 1; ?><?php echo $alFilterType !== '' ? '&at=' . $alFilterType : ''; ?><?php echo $alSearchIP !== '' ? '&sip=' . urlencode($alSearchIP) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $alPage; ?> OF <?php echo $alTotalPages; ?> &bull; TOTAL
                                        <?php echo number_format($alTotal); ?> EVENTS
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'PointLog'): ?>
                                <?php
                                // Pagination for Point Log
                                $plLimit = 25;
                                $plPage = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $plSearchReason = isset($_GET['sr']) ? trim($_GET['sr']) : '';
                                $plOffset = ($plPage - 1) * $plLimit;

                                // Build WHERE
                                $plWhereParts = ["UserID = ?"];
                                $plParams = [$userData['UserID']];
                                if ($plSearchReason !== '') {
                                    $plWhereParts[] = "Reason LIKE ?";
                                    $plParams[] = '%' . $plSearchReason . '%';
                                }
                                $plWhereSql = implode(" AND ", $plWhereParts);

                                // Total Count
                                $plTotal = 0;
                                $plTotalPages = 1;
                                $plCountSql = "SELECT COUNT(*) as Total FROM PS_WebSite.dbo.Web_PointHistory WHERE $plWhereSql";
                                try {
                                    $plCountStmt = $pdoAdmin->prepare($plCountSql);
                                    $plCountStmt->execute($plParams);
                                    $plTotal = (int) ($plCountStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                    $plTotalPages = max(1, ceil($plTotal / $plLimit));
                                } catch (Throwable $e) {}

                                // Fetch Point History
                                $plSql = "SELECT RowID, UserID, Date, PointsAdded, Reason, GM_Account 
                                          FROM PS_WebSite.dbo.Web_PointHistory 
                                          WHERE $plWhereSql
                                          ORDER BY RowID DESC 
                                          OFFSET $plOffset ROWS FETCH NEXT $plLimit ROWS ONLY";
                                $plRows = [];
                                try {
                                    $plStmt = $pdoAdmin->prepare($plSql);
                                    $plStmt->execute($plParams);
                                    $plRows = $plStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                    <h4 style="color: #fff; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <i class="fas fa-history" style="color: #da9f50; font-size: 14px;"></i> Point Transaction History
                                    </h4>
                                    <span style="color: #555; font-size: 12px;"><?php echo number_format($plTotal); ?> record<?php echo $plTotal !== 1 ? 's' : ''; ?></span>
                                </div>

                                <div class="chars-table-container">
                                    <form method="GET" id="pointLogFilters">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="PointLog">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 80px;">#</th>
                                                <th>Date</th>
                                                <th>Points</th>
                                                <th style="text-align: left;">Reason
                                                    <input type="text" name="sr" class="table-filter-input js-auto-submit-onchange" style="text-align: left;" placeholder="Reason" value="<?php echo htmlspecialchars($plSearchReason); ?>">
                                                </th>
                                                <th>Admin / GM</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $plCount = 0;
                                            foreach ($plRows as $h):
                                                $plCount++;
                                                ?>
                                                <tr>
                                                    <td style="font-family: 'monospace'; color: #888;">#<?php echo $h['RowID']; ?></td>
                                                    <td>
                                                        <?php echo date('Y M d', strtotime($h['Date'])); ?> <span style="color: #888; font-size: 11px;"><?php echo date('H:i', strtotime($h['Date'])); ?></span>
                                                    </td>
                                                    <td style="color: #e8c881; font-weight: 700;">
                                                        +<?php echo number_format($h['PointsAdded']); ?>
                                                    </td>
                                                    <td style="text-align: left; color: #aaa; font-style: italic;">
                                                        <?php echo htmlspecialchars($h['Reason'] ?: '-'); ?>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge" style="background: rgba(106, 142, 193, 0.1); color: #6a8ec1; border: 1px solid rgba(106, 142, 193, 0.2); font-size: 11px; padding: 3px 10px;">
                                                            <i class="fas fa-user-shield" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($h['GM_Account']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if ($plCount === 0): ?>
                                                <tr>
                                                    <td colspan="5" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                        <i class="fas fa-info-circle" style="margin-right: 10px;"></i> No point transactions found for this account.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </form>
                                </div>

                                <?php if ($plTotalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($plPage > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog&p=<?php echo $plPage - 1; ?><?php echo $plSearchReason !== '' ? '&sr=' . urlencode($plSearchReason) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $plStartPage = max(1, $plPage - 2);
                                        $plEndPage = min($plTotalPages, $plPage + 2);
                                        for ($i = $plStartPage; $i <= $plEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog&p=<?php echo $i; ?><?php echo $plSearchReason !== '' ? '&sr=' . urlencode($plSearchReason) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $plPage) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($plPage < $plTotalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=PointLog&p=<?php echo $plPage + 1; ?><?php echo $plSearchReason !== '' ? '&sr=' . urlencode($plSearchReason) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $plPage; ?> OF <?php echo $plTotalPages; ?> &bull; TOTAL
                                        <?php echo number_format($plTotal); ?> TRANSACTIONS
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'Purchases'): ?>
                                <?php
                                // Pagination for Purchases (ActionType 113 from ActionLog)
                                $puLimit = 25;
                                $puPage = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $puSearchItem = isset($_GET['si']) ? trim($_GET['si']) : '';
                                $puOffset = ($puPage - 1) * $puLimit;

                                // Build WHERE (no alias for count, AL. prefix for join query)
                                // Build WHERE - using parameterized queries
                                $puWhereParts = ["AL.UserUID = ?", "AL.ActionType = 113"];
                                $puCountWhereParts = ["UserUID = ?", "ActionType = 113"];
                                $puParams = [$userUID];
                                if ($puSearchItem !== '') {
                                    $puWhereParts[] = "AL.Text1 LIKE ?";
                                    $puCountWhereParts[] = "Text1 LIKE ?";
                                    $puParams[] = '%' . $puSearchItem . '%';
                                }

                                // Total Count
                                $puTotal = 0;
                                $puTotalPages = 1;
                                $puCountSql = "SELECT COUNT(*) as Total FROM PS_GameLog.dbo.ActionLog WHERE " . implode(" AND ", $puCountWhereParts);
                                try {
                                    $puCountStmt = $pdoAdmin->prepare($puCountSql);
                                    $puCountStmt->execute($puParams);
                                    $puTotal = (int) ($puCountStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                    $puTotalPages = max(1, ceil($puTotal / $puLimit));
                                } catch (Throwable $e) {}

                                // Fetch Purchases with Character Name
                                $puSql = "SELECT AL.ActionTime, AL.CharID, AL.Text1, AL.Value4, AL.Value5, C.CharName
                                          FROM PS_GameLog.dbo.ActionLog AL
                                          LEFT JOIN PS_GameData.dbo.Chars C ON C.CharID = AL.CharID
                                          WHERE " . implode(" AND ", $puWhereParts) . "
                                          ORDER BY AL.ActionTime DESC
                                          OFFSET $puOffset ROWS FETCH NEXT $puLimit ROWS ONLY";
                                $puRows = [];
                                try {
                                    $puStmt = $pdoAdmin->prepare($puSql);
                                    $puStmt->execute($puParams);
                                    $puRows = $puStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                    <h4 style="color: #fff; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; border-left: 3px solid #da9f50; padding-left: 15px; display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <i class="fas fa-shopping-cart" style="color: #da9f50; font-size: 14px;"></i> Purchase History
                                    </h4>
                                    <span style="color: #555; font-size: 12px;"><?php echo number_format($puTotal); ?> purchase<?php echo $puTotal !== 1 ? 's' : ''; ?></span>
                                </div>

                                <div class="chars-table-container">
                                    <form method="GET" id="purchaseFilters">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="Purchases">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th style="text-align: left; padding-left: 20px;">Item
                                                    <input type="text" name="si" class="table-filter-input js-auto-submit-onchange" style="text-align: left;" placeholder="Item" value="<?php echo htmlspecialchars($puSearchItem); ?>">
                                                </th>
                                                <th>Character</th>
                                                <th>Qty</th>
                                                <th>Gold</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $puCount = 0;
                                            foreach ($puRows as $pu):
                                                $puCount++;
                                                $itemName = $pu['Text1'] ?: '-';
                                                $charName = $pu['CharName'] ?: '-';
                                                $qty = (int) ($pu['Value4'] ?? 0);
                                                $gold = number_format(abs((int) ($pu['Value5'] ?? 0)));
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php echo date('Y M d', strtotime($pu['ActionTime'])); ?> <span style="color: #888; font-size: 11px;"><?php echo date('H:i', strtotime($pu['ActionTime'])); ?></span>
                                                    </td>
                                                    <td style="text-align: left; padding-left: 20px;">
                                                        <span style="color: #e8c881; font-weight: 500;"><?php echo htmlspecialchars($itemName); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($pu['CharID'] && $charName !== '-'): ?>
                                                            <a href="/admin?view=CharEdit&id=<?php echo $pu['CharID']; ?>" class="user-link"><?php echo htmlspecialchars($charName); ?></a>
                                                        <?php else: ?>
                                                            <span style="color: #444;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="color: #aaa;">
                                                        x<?php echo $qty; ?>
                                                    </td>
                                                    <td style="color: #e8c881; font-weight: 600;">
                                                        <?php echo $gold; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php if ($puCount === 0): ?>
                                                <tr>
                                                    <td colspan="5" style="padding: 100px; color: #444; text-align: center; font-style: italic; letter-spacing: 1px;">
                                                        <i class="fas fa-shopping-cart" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                        No purchases found for this account.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </form>
                                </div>

                                <?php if ($puTotalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($puPage > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases&p=<?php echo $puPage - 1; ?><?php echo $puSearchItem !== '' ? '&si=' . urlencode($puSearchItem) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $puStartPage = max(1, $puPage - 2);
                                        $puEndPage = min($puTotalPages, $puPage + 2);
                                        for ($i = $puStartPage; $i <= $puEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases&p=<?php echo $i; ?><?php echo $puSearchItem !== '' ? '&si=' . urlencode($puSearchItem) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $puPage) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($puPage < $puTotalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Purchases&p=<?php echo $puPage + 1; ?><?php echo $puSearchItem !== '' ? '&si=' . urlencode($puSearchItem) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $puPage; ?> OF <?php echo $puTotalPages; ?> &bull; TOTAL
                                        <?php echo number_format($puTotal); ?> PURCHASES
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'Chat'): ?>
                                <?php
                                // Pagination for Chat
                                $limit = 25;
                                $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                                $chatFilterType = isset($_GET['ct']) ? $_GET['ct'] : '';
                                $offset = ($page - 1) * $limit;

                                // Build WHERE clause
                                $whereClauses = ["L.UserUID = ?"];
                                $queryParams = [$userUID];
                                if ($chatFilterType !== '') {
                                    $whereClauses[] = "L.ChatType = ?";
                                    $queryParams[] = (int) $chatFilterType;
                                }
                                $whereSql = "WHERE " . implode(" AND ", $whereClauses);

                                // Total Count
                                $countSql = "SELECT COUNT(*) as Total FROM PS_ChatLog.dbo.ChatLog L $whereSql";
                                    $totalResults = 0;
                                    $totalPages = 1;
                                    $chatRows = [];
                                    try {
                                        $cStmt = $pdoAdmin->prepare($countSql);
                                        $cStmt->execute($queryParams);
                                        $totalResults = (int) ($cStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                                        $totalPages = max(1, ceil($totalResults / $limit));
                                    } catch (Throwable $e) {}

                                    // Fetch Chat Logs - OFFSET and FETCH NEXT require SQL Server 2012+
                                    $chatSql = "SELECT L.CharID, L.ChatType, L.ChatData, L.MapID, L.ChatTime, C.CharName 
                                    FROM PS_ChatLog.dbo.ChatLog L
                                    LEFT JOIN PS_GameData.dbo.Chars C ON C.CharID = L.CharID
                                    $whereSql
                                    ORDER BY L.ChatTime DESC
                                    OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";

                                    try {
                                        $chatQuery = $pdoAdmin->prepare($chatSql);
                                        $chatQuery->execute($queryParams);
                                        $chatRows = $chatQuery->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Throwable $e) {}
                                ?>

                                <div style="margin-bottom: 25px; display: flex; align-items: flex-end;">
                                    <form method="GET" style="display: flex; flex-direction: column; gap: 8px;">
                                        <input type="hidden" name="view" value="UserEdit">
                                        <input type="hidden" name="uid" value="<?php echo $userUID; ?>">
                                        <input type="hidden" name="tab" value="Chat">

                                        <label
                                            style="color: #888; text-transform: uppercase; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; padding-left: 2px;">Type</label>
                                        <select name="ct"
                                            style="background: #050505; border: 1px solid #222; color: #ccc; padding: 8px 15px; font-size: 13px; outline: none; border-radius: 4px; min-width: 150px; cursor: pointer; transition: border-color 0.3s;"
                                            class="js-auto-submit-onchange js-hover-border" data-border-hover="#444" data-border-default="#222">
                                            <option value="">All</option>
                                            <?php
                                            $cTypesList = [1, 3, 4, 5, 7];
                                            foreach ($cTypesList as $ctId):
                                                ?>
                                                <option value="<?php echo $ctId; ?>" <?php echo ($chatFilterType !== '' && (int) $chatFilterType === $ctId) ? 'selected' : ''; ?>>
                                                    <?php echo getChatTypeName($ctId); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </div>

                                <div class="chars-table-container">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 160px; text-align: left; padding-left: 15px;">Time</th>
                                                <th style="width: 140px; text-align: left;">Character</th>
                                                <th style="width: 110px;">Type</th>
                                                <th style="text-align: left;">Message</th>
                                                <th style="width: 120px;">Map</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $hasChat = false;
                                                          foreach ($chatRows as $chat):
                                                $hasChat = true; ?>
                                                <tr>
                                                    <td>
                                                        <?php $ct = strtotime($chat['ChatTime']); ?>
                                                        <span style="color: #aaa;"><?php echo date('d M Y', $ct); ?></span>
                                                        <span style="color: #555; font-size: 11px;"><?php echo date('H:i', $ct); ?></span>
                                                     </td>
                                                    <td>
                                                        <?php if ($chat['CharName']): ?>
                                                            <a href="/admin?view=CharEdit&id=<?php echo $chat['CharID']; ?>" class="user-link"><?php echo htmlspecialchars($chat['CharName']); ?></a>
                                                        <?php else: ?>
                                                            <span><?php echo $chat['CharID']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $cType = (int) $chat['ChatType'];
                                                        $cColor = '#eee';
                                                        $cBg = 'rgba(255,255,255,0.04)';
                                                        if ($cType == 0) { $cColor = '#f6ad55'; $cBg = 'rgba(246,173,85,0.08)'; }      // Whisper
                                                        if ($cType == 1) { $cColor = '#aaa';    $cBg = 'rgba(255,255,255,0.04)'; }     // Normal
                                                        if ($cType == 3) { $cColor = '#da70d6'; $cBg = 'rgba(218,112,214,0.08)'; }     // Guild
                                                        if ($cType == 4) { $cColor = '#68d391'; $cBg = 'rgba(104,211,145,0.08)'; }     // Party
                                                        if ($cType == 5) { $cColor = '#e8c881'; $cBg = 'rgba(232,200,129,0.08)'; }     // Trade
                                                        if ($cType == 7) { $cColor = '#7b8cf7'; $cBg = 'rgba(123,140,247,0.08)'; }     // Area
                                                        ?>
                                                        <span style="display: inline-block; background: <?php echo $cBg; ?>; color: <?php echo $cColor; ?>; border: 1px solid <?php echo $cColor; ?>22; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; padding: 3px 8px; border-radius: 3px;">
                                                            <?php echo getChatTypeName($cType); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($chat['ChatData']); ?>
                                                    </td>
                                                    <td><?php echo getMapName($chat['MapID']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (!$hasChat): ?>
                                                <tr>
                                                    <td colspan="5" style="text-align:center; color:#555; padding: 40px;">No chat history found for this account</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>

                                </div>

                                <?php if ($totalPages > 1): ?>
                                    <div class="pagination"
                                        style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; align-items: center;">
                                        <?php if ($page > 1): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat&p=<?php echo $page - 1; ?><?php echo $chatFilterType !== '' ? '&ct=' . urlencode($chatFilterType) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                                        <?php endif; ?>

                                        <?php
                                        $chatStartPage = max(1, $page - 2);
                                        $chatEndPage = min($totalPages, $page + 2);
                                        for ($i = $chatStartPage; $i <= $chatEndPage; $i++):
                                            ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat&p=<?php echo $i; ?><?php echo $chatFilterType !== '' ? '&ct=' . urlencode($chatFilterType) : ''; ?>"
                                                class="btn-search"
                                                style="text-decoration: none; padding: 8px 15px; <?php echo ($i === $page) ? 'background: #e8c881; color: #000;' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $userUID; ?>&tab=Chat&p=<?php echo $page + 1; ?><?php echo $chatFilterType !== '' ? '&ct=' . urlencode($chatFilterType) : ''; ?>"
                                                class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: center; color: #555; font-size: 11px; margin-top: 10px;">
                                        PAGE <?php echo $page; ?> OF <?php echo $totalPages; ?> &bull; TOTAL
                                        <?php echo number_format($totalResults); ?> MESSAGES
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($tab === 'Warehouse'): ?>
                                <?php
                                $whRows = [];
                                try {
                                    $whStmt = $pdoAdmin->prepare("SELECT S.Slot, S.ItemID, S.ItemCount, S.BuyDate, I.ItemName FROM PS_GameData.dbo.UserStoredPointItems S LEFT JOIN PS_GameDefs.dbo.Items I ON I.ItemID = S.ItemID WHERE S.UserUID = ? ORDER BY S.Slot ASC");
                                    $whStmt->execute([$userUID]);
                                    $whRows = $whStmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Throwable $e) {}
                                ?>
                                <div class="chars-table-container">
                                    <table class="chars-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">Slot</th>
                                                <th>Item Name</th>
                                                <th style="width: 80px;">Item ID</th>
                                                <th style="width: 80px;">Count</th>
                                                <th style="width: 170px;">Buy Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($whRows) > 0): ?>
                                                <?php foreach ($whRows as $wh): ?>
                                                    <tr>
                                                        <td><?php echo (int) $wh['Slot']; ?></td>
                                                        <td><?php echo htmlspecialchars((string) ($wh['ItemName'] ?? 'Unknown')); ?></td>
                                                        <td><?php echo (int) $wh['ItemID']; ?></td>
                                                        <td><?php echo (int) $wh['ItemCount']; ?></td>
                                                        <td style="color: #888;"><?php echo htmlspecialchars((string) ($wh['BuyDate'] ?? '')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" style="text-align: center; padding: 60px; color: #444;">
                                                        <i class="fas fa-box-open" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                        Warehouse is empty.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php endif; ?> <!-- end tab switch (USEREDIT) -->
                        <?php endif; ?> <!-- end if (!$userData) ... else -->

                <?php elseif ($view === 'CHARS'): ?>
                    <?php
                    $fCharName = isset($_GET['f_charname']) ? trim($_GET['f_charname']) : '';
                    $fFaction  = isset($_GET['f_faction'])  ? trim($_GET['f_faction'])  : '';
                    $fOnline   = isset($_GET['f_online'])   ? trim($_GET['f_online'])   : '';
                    $charsPage    = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                    $charsPerPage = 50;
                    $charsOffset  = ($charsPage - 1) * $charsPerPage;
                    $charsWhere   = ["C.Del = 0"];
                    $charsParams  = [];
                    if ($fCharName !== '') {
                        $charsWhere[] = "C.CharName LIKE ?";
                        $charsParams[] = '%' . $fCharName . '%';
                    }
                    if ($fFaction !== '' && in_array($fFaction, ['0', '1'], true)) {
                        $charsWhere[] = "MG.Country = ?";
                        $charsParams[] = (int) $fFaction;
                    }
                    if ($fOnline === '1') {
                        $charsWhere[] = "C.LoginStatus = 1";
                    }
                    $charsWhereSql = "WHERE " . implode(" AND ", $charsWhere);
                    $charsTotalCount = 0;
                    $charsTotalPages = 1;
                    $charRows = [];
                    try {
                        $ccStmt = $pdoAdmin->prepare("SELECT COUNT(*) as Total FROM PS_GameData.dbo.Chars C LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID $charsWhereSql");
                        $ccStmt->execute($charsParams);
                        $charsTotalCount = (int) ($ccStmt->fetch(PDO::FETCH_ASSOC)['Total'] ?? 0);
                        $charsTotalPages = max(1, (int) ceil($charsTotalCount / $charsPerPage));
                        $charsSql = "SELECT C.CharID, C.CharName, C.Level, C.Map, C.Money, C.LoginStatus, C.UserUID, MG.Country as Faction, U.UserID
                            FROM PS_GameData.dbo.Chars C
                            LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID
                            LEFT JOIN PS_UserData.dbo.Users_Master U ON U.UserUID = C.UserUID
                            $charsWhereSql
                            ORDER BY C.CharID DESC
                            OFFSET $charsOffset ROWS FETCH NEXT $charsPerPage ROWS ONLY";
                        $chStmt = $pdoAdmin->prepare($charsSql);
                        $chStmt->execute($charsParams);
                        $charRows = $chStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}
                    ?>
                    <div class="chars-table-container">
                        <form method="GET" id="charsHeaderFilters">
                            <input type="hidden" name="view" value="CHARS">
                            <table class="chars-table">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Faction
                                            <select name="f_faction" class="table-filter-select js-auto-submit-onchange">
                                                <option value="">All</option>
                                                <option value="0" <?php echo $fFaction === '0' ? 'selected' : ''; ?>>AOL</option>
                                                <option value="1" <?php echo $fFaction === '1' ? 'selected' : ''; ?>>UOF</option>
                                            </select>
                                        </th>
                                        <th>Char Name
                                            <input type="text" name="f_charname" class="table-filter-input" placeholder="Search..." value="<?php echo htmlspecialchars($fCharName); ?>">
                                        </th>
                                        <th style="width: 60px;">Lvl</th>
                                        <th style="width: 60px;">Map</th>
                                        <th style="width: 130px;">Account</th>
                                        <th style="width: 80px;">Online
                                            <select name="f_online" class="table-filter-select js-auto-submit-onchange">
                                                <option value="">All</option>
                                                <option value="1" <?php echo $fOnline === '1' ? 'selected' : ''; ?>>Online</option>
                                            </select>
                                        </th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($charRows as $cr): ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <?php if (isset($cr['Faction'])): ?>
                                                <img src="assets/<?php echo $cr['Faction'] == 0 ? 'aol.webp' : 'uof.webp'; ?>" style="width: 20px;" alt="faction">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/admin?view=CharEdit&id=<?php echo $cr['CharID']; ?>" class="user-link">
                                                <?php echo htmlspecialchars($cr['CharName']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo (int) $cr['Level']; ?></td>
                                        <td><?php echo (int) $cr['Map']; ?></td>
                                        <td>
                                            <a href="/admin?view=UserEdit&uid=<?php echo $cr['UserUID']; ?>" class="user-link">
                                                <?php echo htmlspecialchars((string) ($cr['UserID'] ?? '')); ?>
                                            </a>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php echo (int) ($cr['LoginStatus'] ?? 0) === 1 ? '<span style="color:#48bb78;">&#9679;</span>' : '<span style="color:#444;">&#9679;</span>'; ?>
                                        </td>
                                        <td>
                                            <a href="/admin?view=CharEdit&id=<?php echo $cr['CharID']; ?>" class="btn-action btn-sm">EDIT</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($charRows) === 0): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 60px; color: #444;">
                                            <i class="fas fa-user-slash" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                            No characters found.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </form>
                        <?php if ($charsTotalPages > 1): ?>
                        <div class="pagination" style="display: flex; justify-content: center; gap: 10px; margin-top: 20px; align-items: center;">
                            <?php if ($charsPage > 1): ?>
                                <a href="/admin?view=CHARS&p=<?php echo $charsPage - 1; ?><?php echo $fCharName !== '' ? '&f_charname=' . urlencode($fCharName) : ''; ?><?php echo $fFaction !== '' ? '&f_faction=' . $fFaction : ''; ?><?php echo $fOnline !== '' ? '&f_online=' . $fOnline : ''; ?>" class="btn-search" style="text-decoration: none; padding: 8px 15px;">&laquo; Prev</a>
                            <?php endif; ?>
                            <span style="color: #888; padding: 8px;">Page <?php echo $charsPage; ?> of <?php echo $charsTotalPages; ?></span>
                            <?php if ($charsPage < $charsTotalPages): ?>
                                <a href="/admin?view=CHARS&p=<?php echo $charsPage + 1; ?><?php echo $fCharName !== '' ? '&f_charname=' . urlencode($fCharName) : ''; ?><?php echo $fFaction !== '' ? '&f_faction=' . $fFaction : ''; ?><?php echo $fOnline !== '' ? '&f_online=' . $fOnline : ''; ?>" class="btn-search" style="text-decoration: none; padding: 8px 15px;">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($view === 'ACTIONLOG'): ?>
                    <?php
                    $userIdSearch = isset($_GET['username']) ? trim((string) $_GET['username']) : '';
                    $charNameSearch = isset($_GET['charname']) ? trim((string) $_GET['charname']) : '';
                    $actionTypesSearch = isset($_GET['action-type']) && is_array($_GET['action-type']) ? $_GET['action-type'] : [];
                    $rowsLimit = isset($_GET['rows']) && (int) $_GET['rows'] > 0 ? (int) $_GET['rows'] : 25;
                    $rowsLimit = max(1, min(5000, $rowsLimit));
                    $dateStartSearch = isset($_GET['date-start-enabled'], $_GET['date-start']) ? trim((string) $_GET['date-start']) : '';
                    $dateEndSearch = isset($_GET['date-end-enabled'], $_GET['date-end']) ? trim((string) $_GET['date-end']) : '';

                    $ActionTypes = [
                        10 => 'Own Kill',
                        102 => 'Unknown 102',
                        103 => 'Kill/Win',
                        104 => 'Death/Lose',
                        105 => 'Res 105',
                        106 => 'Res 106',
                        107 => 'Game Login',
                        108 => 'Game Logout',
                        111 => 'Pick Item',
                        112 => 'Lose Item',
                        113 => 'Purchase',
                        114 => 'Sell to NPC',
                        115 => 'Trade In',
                        116 => 'Trade Out',
                        118 => 'Duel Trade',
                        119 => 'Lapis Link',
                        121 => 'WH Put',
                        122 => 'WH Take',
                        123 => 'Duel Lose',
                        124 => 'Duel Win',
                        131 => 'Take Quest',
                        133 => 'Finish Quest',
                        141 => 'Learn Skill',
                        146 => 'Level Up',
                        151 => 'Stat STR',
                        152 => 'Stat DEX',
                        153 => 'Stat INT',
                        154 => 'Stat WIS',
                        155 => 'Stat REC',
                        156 => 'Stat LUC',
                        163 => 'Money Transfer',
                        164 => 'Teleport',
                        173 => 'Boss Action',
                        180 => 'Admin Action',
                        210 => 'Party Join',
                        211 => 'Party Leave',
                        212 => 'Enchant',
                        213 => 'Rec (Reroll)',
                        216 => 'Auction Create',
                        217 => 'Auction Remove',
                        218 => 'Auction Buy',
                        219 => 'Auction Bet',
                        220 => 'Auction Take Item',
                        221 => 'Auction Take Money',
                        222 => 'Auction Sell',
                        223 => 'Auction Finish',
                        224 => 'Auction Get Bet',
                        226 => 'Auction Bet Cancel',
                    ];

                    $whereClauses = ['1=1'];
                    $queryParams = [];

                    if ($userIdSearch !== '') {
                        try {
                            $qUid = $pdoAdmin->prepare('SELECT UserUID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?');
                            $qUid->execute([$userIdSearch]);
                            $rUid = $qUid->fetch(PDO::FETCH_ASSOC);
                            if ($rUid && isset($rUid['UserUID'])) {
                                $whereClauses[] = 'AL.UserUID = ?';
                                $queryParams[] = (int) $rUid['UserUID'];
                            } else {
                                $whereClauses[] = '1=0';
                            }
                        } catch (Throwable $e) {
                            $whereClauses[] = '1=0';
                        }
                    }

                    if ($charNameSearch !== '') {
                        try {
                            $qCid = $pdoAdmin->prepare('SELECT CharID FROM PS_GameData.dbo.Chars WHERE CharName = ?');
                            $qCid->execute([$charNameSearch]);
                            $rCid = $qCid->fetch(PDO::FETCH_ASSOC);
                            if ($rCid && isset($rCid['CharID'])) {
                                $whereClauses[] = 'AL.CharID = ?';
                                $queryParams[] = (int) $rCid['CharID'];
                            } else {
                                $whereClauses[] = '1=0';
                            }
                        } catch (Throwable $e) {
                            $whereClauses[] = '1=0';
                        }
                    }

                    if (!empty($actionTypesSearch)) {
                        $validActionTypes = [];
                        foreach ($actionTypesSearch as $selectedType) {
                            $typeId = (int) $selectedType;
                            if (isset($ActionTypes[$typeId])) {
                                $validActionTypes[] = $typeId;
                            }
                        }
                        $validActionTypes = array_values(array_unique($validActionTypes));
                        if (!empty($validActionTypes)) {
                            $typePlaceholders = implode(',', array_fill(0, count($validActionTypes), '?'));
                            $whereClauses[] = 'AL.ActionType IN (' . $typePlaceholders . ')';
                            foreach ($validActionTypes as $typeParam) {
                                $queryParams[] = $typeParam;
                            }
                        }
                    }

                    if ($dateStartSearch !== '') {
                        $dStart = date('Y-m-d H:i:s', strtotime($dateStartSearch));
                        if ($dStart !== false) {
                            $whereClauses[] = 'AL.ActionTime >= ?';
                            $queryParams[] = $dStart;
                        }
                    }

                    if ($dateEndSearch !== '') {
                        $dEnd = date('Y-m-d H:i:s', strtotime($dateEndSearch));
                        if ($dEnd !== false) {
                            $whereClauses[] = 'AL.ActionTime <= ?';
                            $queryParams[] = $dEnd;
                        }
                    }

                    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
                    $logsRows = [];

                    try {
                        $logsSql = "SELECT TOP $rowsLimit AL.*, UM.UserID, UM.AdminLevel, C.CharName
                                    FROM PS_GameLog.dbo.ActionLog AL
                                    LEFT JOIN PS_UserData.dbo.Users_Master UM ON AL.UserUID = UM.UserUID
                                    LEFT JOIN PS_GameData.dbo.Chars C ON AL.CharID = C.CharID
                                    $whereSql
                                    ORDER BY AL.[row] DESC";
                        $logsStmt = $pdoAdmin->prepare($logsSql);
                        $logsStmt->execute($queryParams);
                        $logsRows = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {
                        $logsRows = [];
                    }
                    ?>

                    <div class="logs-filter-card" style="background: rgba(255,255,255,0.02); padding: 30px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 40px;">
                        <form action="/admin" method="GET" id="logsSearchForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px;">
                            <input type="hidden" name="view" value="ActionLog">

                            <div class="editor-field">
                                <label class="editor-label">UserID</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($userIdSearch); ?>" class="editor-input" placeholder="Search UserID...">
                                <div style="font-size: 10px; color: #444; margin-top: 5px; text-align: center;">-- OR --</div>
                                <label class="editor-label" style="margin-top: 5px;">Char Name</label>
                                <input type="text" name="charname" value="<?php echo htmlspecialchars($charNameSearch); ?>" class="editor-input" placeholder="Search Char Name...">
                            </div>

                            <div class="editor-field">
                                <label class="editor-label">Action Types</label>
                                <select name="action-type[]" multiple class="editor-select" style="height: 180px; padding: 2px; font-size: 12px; line-height: 1.2;">
                                    <?php foreach ($ActionTypes as $atid => $atname): ?>
                                        <option value="<?php echo $atid; ?>" <?php echo in_array((string) $atid, $actionTypesSearch, true) || in_array($atid, $actionTypesSearch, true) ? 'selected' : ''; ?> style="padding: 4px 8px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                                            [<?php echo $atid; ?>] <?php echo $atname; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div style="font-size: 9px; color: #666; cursor: pointer; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px;" class="js-reset-log-action-types">RESET SELECTION</div>
                            </div>

                            <div class="editor-field">
                                <label class="editor-label">Date Range</label>
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                                    <input type="checkbox" name="date-start-enabled" id="ds_en" <?php echo isset($_GET['date-start-enabled']) ? 'checked' : ''; ?>>
                                    <input type="datetime-local" name="date-start" id="dateStart" class="editor-input" style="flex:1;" value="<?php echo $dateStartSearch ? date('Y-m-d\TH:i', strtotime($dateStartSearch)) : date('Y-m-d\TH:i'); ?>">
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="checkbox" name="date-end-enabled" id="de_en" <?php echo isset($_GET['date-end-enabled']) ? 'checked' : ''; ?>>
                                    <input type="datetime-local" name="date-end" id="dateEnd" class="editor-input" style="flex:1;" value="<?php echo $dateEndSearch ? date('Y-m-d\TH:i', strtotime($dateEndSearch)) : date('Y-m-d\TH:i'); ?>">
                                </div>
                            </div>

                            <div class="editor-field" style="display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <label class="editor-label">Rows</label>
                                    <input type="number" name="rows" value="<?php echo $rowsLimit; ?>" min="1" max="5000" class="editor-input" style="text-align: center;">
                                </div>
                                <button type="submit" class="btn-action" style="padding: 10px;">APPLY FILTERS</button>
                            </div>
                        </form>
                    </div>

                    <div class="chars-table-container">
                        <table class="chars-table">
                            <thead>
                                <tr>
                                    <th style="width: 150px;">Date</th>
                                    <th style="width: 130px;">User</th>
                                    <th style="width: 130px;">Char Name</th>
                                    <th style="width: 150px;">Action</th>
                                    <th style="text-align: left;">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logsRows as $log): ?>
                                    <?php
                                    $actId = (int) ($log['ActionType'] ?? 0);
                                    $actName = $ActionTypes[$actId] ?? ('Action ' . $actId);
                                    $time = strtotime((string) ($log['ActionTime'] ?? ''));

                                    $details = '';
                                    switch ($actId) {
                                        case 107:
                                            $details = 'IP: <b>' . htmlspecialchars((string) ($log['Text1'] ?? '')) . '</b> | ' . htmlspecialchars((string) ($log['Text2'] ?? ''));
                                            break;
                                        case 111:
                                        case 112:
                                            $details = "<span style='color: #e8c881;'>" . htmlspecialchars((string) ($log['Text1'] ?? '')) . '</span> (x' . (int) ($log['Value3'] ?? 0) . ') | ID: ' . (int) ($log['Value2'] ?? 0);
                                            $craftLabel = getCraftnameLabel((string) ($log['Text3'] ?? ''));
                                            if ($craftLabel !== '') {
                                                $details .= " <span style='color: #666; font-size: 11px;'>[" . htmlspecialchars($craftLabel) . ']</span>';
                                            }
                                            break;
                                        case 113:
                                        case 114:
                                            $details = "<span style='color: #e8c881;'>" . htmlspecialchars((string) ($log['Text1'] ?? '')) . '</span> (x' . (int) ($log['Value4'] ?? 0) . ') | Gold: ' . number_format(castToUInt((int) ($log['Value5'] ?? 0)));
                                            break;
                                        case 163:
                                            $details = 'To: ' . htmlspecialchars((string) ($log['Text1'] ?? '')) . ' | Amount: ' . number_format(castToUInt((int) ($log['Value1'] ?? 0)));
                                            break;
                                        case 164:
                                            $details = 'Map: ' . htmlspecialchars((string) getMapName((int) ($log['Value3'] ?? 0))) . ' | Target: ' . htmlspecialchars((string) ($log['Text1'] ?? ''));
                                            break;
                                        default:
                                            $tStr = trim((string) (($log['Text1'] ?? '') . ' ' . ($log['Text2'] ?? '') . ' ' . ($log['Text3'] ?? '') . ' ' . ($log['Text4'] ?? '')));
                                            $vStr = 'V1:' . (int) ($log['Value1'] ?? 0) . ' V2:' . (int) ($log['Value2'] ?? 0);
                                            $details = $tStr !== '' ? htmlspecialchars($tStr) : htmlspecialchars($vStr);
                                            break;
                                    }
                                    ?>
                                    <tr>
                                        <td style="color: #666; font-size: 11px;">
                                            <?php echo $time ? date('d M, H:i', $time) : '-'; ?>
                                        </td>
                                        <td>
                                            <a href="/admin?view=USERS&f_userid=<?php echo urlencode((string) ($log['UserID'] ?? '')); ?>" class="user-link"><?php echo htmlspecialchars((string) ($log['UserID'] ?? '-')); ?></a>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['CharName'])): ?>
                                                <a href="/admin?view=CharEdit&id=<?php echo (int) ($log['CharID'] ?? 0); ?>" class="user-link" style="color: #fff;"><?php echo htmlspecialchars((string) $log['CharName']); ?></a>
                                            <?php else: ?>
                                                <span style="color: #444; font-size: 11px;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="color: #6a8ec1; font-weight: 500; font-size: 11px; text-transform: uppercase;" title="<?php echo 'ID: ' . $actId; ?>"><?php echo htmlspecialchars($actName); ?></span>
                                        </td>
                                        <td style="text-align: left; font-size: 12px; color: #888; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo $details; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($logsRows) === 0): ?>
                                    <tr>
                                        <td colspan="5" style="padding: 60px; color: #444; text-align: center;">No log rows found with current filters.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($view === 'ITEMS'): ?>
                    <?php
                    $selectedItemID = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
                    $searchIn = isset($_GET['search_in']) ? strtolower(trim((string) $_GET['search_in'])) : 'inventory';
                    if (!in_array($searchIn, ['inventory', 'warehouse'], true)) {
                        $searchIn = 'inventory';
                    }
                    $itemSearchTerm = isset($_GET['search_item_name']) ? trim((string) $_GET['search_item_name']) : '';

                    $matchingItems = [];
                    if ($itemSearchTerm !== '') {
                        try {
                            $qItems = $pdoAdmin->prepare("SELECT TOP 30 ItemID, ItemName FROM PS_GameDefs.dbo.Items WHERE ItemName LIKE ? ORDER BY ItemName ASC");
                            $qItems->execute(['%' . $itemSearchTerm . '%']);
                            $matchingItems = $qItems->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) {
                            $matchingItems = [];
                        }
                    }

                    $results = [];
                    if ($selectedItemID > 0) {
                        try {
                            if ($searchIn === 'inventory') {
                                $sql = "SELECT ci.ItemUID, ci.Type, ci.TypeID, i.ItemID, i.ItemName,
                                               ci.Craftname, ci.Quality, ci.Gem1, ci.Gem2, ci.Gem3, ci.Gem4, ci.Gem5, ci.Gem6,
                                               ci.Maketime,
                                               U.UserID as AccountName, U.UserUID as AccountUID, C.CharName, C.CharID,
                                               i.ReqLevel, i.ConstStr, i.ConstDex, i.ConstRec, i.ConstInt, i.ConstWis, i.ConstLuc,
                                               i.ConstHP, i.ConstSP, i.ConstMP, i.Defensefighter, i.Defensemage,
                                               1 as ItemCount
                                        FROM PS_GameData.dbo.CharItems ci
                                        JOIN PS_GameDefs.dbo.Items i ON ci.Type = i.Type AND ci.TypeID = i.TypeID
                                        JOIN PS_GameData.dbo.Chars C ON ci.CharID = C.CharID
                                        JOIN PS_UserData.dbo.Users_Master U ON C.UserUID = U.UserUID
                                        WHERE i.ItemID = ?
                                        ORDER BY ci.ItemUID DESC";
                                $qRes = $pdoAdmin->prepare($sql);
                                $qRes->execute([$selectedItemID]);
                                $results = $qRes->fetchAll(PDO::FETCH_ASSOC);
                            } else {
                                $sql = "SELECT ci.ItemUID, ci.Type, ci.TypeID, i.ItemID, i.ItemName,
                                               ci.Craftname, 0 as Quality, ci.Gem1, ci.Gem2, ci.Gem3, ci.Gem4, ci.Gem5, ci.Gem6,
                                               ci.Maketime,
                                               U.UserID as AccountName, U.UserUID as AccountUID, C.CharName, C.CharID,
                                               i.ReqLevel, i.ConstStr, i.ConstDex, i.ConstRec, i.ConstInt, i.ConstWis, i.ConstLuc,
                                               i.ConstHP, i.ConstSP, i.ConstMP, i.Defensefighter, i.Defensemage,
                                               ci.[Count] as ItemCount
                                        FROM PS_GameData.dbo.UserStoredItems ci
                                        JOIN PS_GameDefs.dbo.Items i ON ci.Type = i.Type AND ci.TypeID = i.TypeID
                                        JOIN PS_UserData.dbo.Users_Master U ON ci.UserUID = U.UserUID
                                        LEFT JOIN PS_GameData.dbo.Chars C ON C.UserUID = U.UserUID AND C.Slot = 0 AND C.Del = 0
                                        WHERE i.ItemID = ?
                                        ORDER BY ci.ItemUID DESC";
                                $qRes = $pdoAdmin->prepare($sql);
                                $qRes->execute([$selectedItemID]);
                                $results = $qRes->fetchAll(PDO::FETCH_ASSOC);
                            }
                        } catch (Throwable $e) {
                            $results = [];
                        }
                    }
                    ?>

                    <div class="items-search-card">
                        <div class="items-title">ITEMS LIST</div>

                        <div style="display: grid; grid-template-columns: 400px 1fr; gap: 40px; align-items: start;">
                            <div style="background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid rgba(255,255,255,0.03); padding: 25px;">
                                <label class="search-label" style="display: flex; justify-content: space-between; align-items: center;">Find Item</label>
                                <form action="/admin" method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
                                    <input type="hidden" name="view" value="ITEMS">
                                    <input type="text" name="search_item_name" class="char-input" value="<?php echo htmlspecialchars($itemSearchTerm); ?>" placeholder="Search ItemName..." style="background: rgba(255,255,255,0.03); height: 38px;">
                                    <button type="submit" class="btn-action btn-sm" style="height: 38px;"><i class="fas fa-search"></i></button>
                                </form>

                                <div class="blacklist-scroll" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                                    <?php if ($itemSearchTerm !== ''): ?>
                                        <?php if (empty($matchingItems)): ?>
                                            <div style="text-align: center; padding: 20px; color: #444; font-size: 11px;">No items found.</div>
                                        <?php else: ?>
                                            <?php foreach ($matchingItems as $m): ?>
                                                <div class="item-match-row js-select-item-result js-hover-border"
                                                    data-item-id="<?php echo (int) $m['ItemID']; ?>"
                                                    data-item-label="<?php echo htmlspecialchars((int) $m['ItemID'] . ' - ' . (string) $m['ItemName'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-border-hover="#e8c881"
                                                    data-border-default="rgba(255,255,255,0.03)"
                                                    style="background: rgba(255,255,255,0.02); padding: 10px 15px; border: 1px solid rgba(255,255,255,0.03); border-radius: 4px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; cursor: pointer; transition: all 0.2s;">
                                                    <span style="font-size: 13px;">
                                                        <span style="color: #ccc;"><?php echo htmlspecialchars((string) $m['ItemName']); ?></span>
                                                        <span style="color: #444; font-size: 10px; margin-left: 10px;">#<?php echo (int) $m['ItemID']; ?></span>
                                                    </span>
                                                    <i class="fas fa-plus-circle" style="color: #222; font-size: 12px;"></i>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 40px 0; color: #333;">
                                            <i class="fas fa-search" style="font-size: 24px; opacity: 0.1; margin-bottom: 10px;"></i>
                                            <p style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Search for items</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="padding-top: 5px;">
                                <form action="/admin" method="GET" id="itemsSearchForm">
                                    <input type="hidden" name="view" value="ITEMS">
                                    <div class="search-field" style="margin-bottom: 25px;">
                                        <label class="search-label">Selected Item</label>
                                        <div style="background: rgba(0,0,0,0.3); border: 1px solid #222; padding: 15px; border-radius: 6px; display: flex; align-items: center; gap: 20px;">
                                            <div style="flex: 1;">
                                                <input type="text" name="item_id" id="selectedItemId" value="<?php echo $selectedItemID; ?>" placeholder="ItemID..." class="char-input" readonly style="background: transparent; border: none; font-size: 16px; color: #e8c881; font-weight: 600; padding: 0; cursor: default;">
                                                <div id="selectedItemDisplay" style="color: #555; font-size: 11px; margin-top: 5px; text-transform: uppercase;">
                                                    <?php
                                                    if ($selectedItemID > 0) {
                                                        try {
                                                            $checkItem = $pdoAdmin->prepare("SELECT ItemName FROM PS_GameDefs.dbo.Items WHERE ItemID = ?");
                                                            $checkItem->execute([$selectedItemID]);
                                                            $rName = $checkItem->fetch(PDO::FETCH_ASSOC);
                                                            echo $rName ? htmlspecialchars((string) $rName['ItemName']) : 'UNKNOWN ITEM ID';
                                                        } catch (Throwable $e) {
                                                            echo 'UNKNOWN ITEM ID';
                                                        }
                                                    } else {
                                                        echo 'No Item Selected';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div style="height: 30px; width: 1px; background: rgba(255,255,255,0.05);"></div>
                                            <div style="flex: 1;">
                                                <label class="search-label" style="margin-bottom: 5px; font-size: 10px;">Search in</label>
                                                <select name="search_in" class="editor-select" style="width: 100%; min-width: 150px;">
                                                    <option value="inventory" <?php echo $searchIn === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                                                    <option value="warehouse" <?php echo $searchIn === 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: flex-end;">
                                        <button type="submit" class="btn-action" style="padding: 12px 50px;">ACCEPT</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($selectedItemID > 0): ?>
                        <div class="results-header">ITEMS</div>
                        <div class="users-table-container">
                            <table class="items-results-table">
                                <thead>
                                    <tr>
                                        <th style="width: 250px;">ItemUID</th>
                                        <th style="width: 100px;">ItemID</th>
                                        <th>Item Name</th>
                                        <th style="width: 250px;">Owner</th>
                                        <th style="width: 150px;">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($results)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 50px; color: #444;">No items found in the selected <?php echo htmlspecialchars($searchIn); ?>.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($results as $res): ?>
                                            <?php
                                            $craft = (string) ($res['Craftname'] ?? '00000000000000000000');
                                            if (strlen($craft) < 20) {
                                                $craft = str_pad($craft, 20, '0', STR_PAD_LEFT);
                                            }

                                            $bonusStats = [
                                                'STR' => (int) substr($craft, 0, 2),
                                                'DEX' => (int) substr($craft, 2, 2),
                                                'REC' => (int) substr($craft, 4, 2),
                                                'INT' => (int) substr($craft, 6, 2),
                                                'WIS' => (int) substr($craft, 8, 2),
                                                'LUC' => (int) substr($craft, 10, 2),
                                                'HP' => (int) substr($craft, 12, 2) * 100,
                                                'SP' => (int) substr($craft, 14, 2) * 100,
                                                'MP' => (int) substr($craft, 16, 2) * 100,
                                                'Enchant' => (int) substr($craft, 18, 2),
                                            ];

                                            $baseStats = [
                                                'STR' => (int) ($res['ConstStr'] ?? 0),
                                                'DEX' => (int) ($res['ConstDex'] ?? 0),
                                                'REC' => (int) ($res['ConstRec'] ?? 0),
                                                'INT' => (int) ($res['ConstInt'] ?? 0),
                                                'WIS' => (int) ($res['ConstWis'] ?? 0),
                                                'LUC' => (int) ($res['ConstLuc'] ?? 0),
                                                'HP' => (int) ($res['ConstHP'] ?? 0),
                                                'SP' => (int) ($res['ConstSP'] ?? 0),
                                                'MP' => (int) ($res['ConstMP'] ?? 0),
                                            ];
                                            ?>
                                            <tr>
                                                <td class="uid-cell"><?php echo htmlspecialchars((string) ($res['ItemUID'] ?? '')); ?></td>
                                                <td class="item-id-cell"><?php echo number_format((int) ($res['ItemID'] ?? 0)); ?></td>
                                                <td class="item-name-cell">
                                                    <div class="item-name-wrapper">
                                                        <?php echo htmlspecialchars((string) ($res['ItemName'] ?? 'Unknown')); ?>
                                                        <?php if ($bonusStats['Enchant'] > 0): ?>
                                                            <span class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                        <?php endif; ?>

                                                        <div class="item-stats-tooltip">
                                                            <div class="tooltip-header">
                                                                <span><?php echo htmlspecialchars((string) ($res['ItemName'] ?? 'Unknown Item')); ?></span>
                                                                <?php if ($bonusStats['Enchant'] > 0): ?>
                                                                    <span class="enchant-badge">[<?php echo $bonusStats['Enchant']; ?>]</span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if ((int) ($res['Quality'] ?? 0) > 0): ?>
                                                                <div class="stat-line" style="color: #eeee74;">damage Absorption +<?php echo (int) $res['Quality']; ?></div>
                                                            <?php endif; ?>
                                                            <?php if ((int) ($res['Defensefighter'] ?? 0) > 0): ?>
                                                                <div class="stat-line" style="color: #fff;">Phys. defense <?php echo (int) $res['Defensefighter']; ?></div>
                                                            <?php endif; ?>
                                                            <?php if ((int) ($res['Defensemage'] ?? 0) > 0): ?>
                                                                <div class="stat-line" style="color: #fff;">Mag. defense <?php echo (int) $res['Defensemage']; ?></div>
                                                            <?php endif; ?>

                                                            <?php
                                                            $allStats = ['HP' => 'hp', 'SP' => 'sp', 'MP' => 'mp', 'STR' => 'str', 'DEX' => 'dex', 'REC' => 'rec', 'INT' => 'int', 'WIS' => 'wis', 'LUC' => 'luc'];
                                                            foreach ($allStats as $label => $cssClass):
                                                                $base = (int) $baseStats[$label];
                                                                $bonus = (int) $bonusStats[$label];
                                                                if ($base > 0 || $bonus > 0):
                                                            ?>
                                                                    <div class="stat-line">
                                                                        <span class="stat-label-<?php echo $cssClass; ?>"><?php echo $label; ?></span>
                                                                        <span class="stat-v-base">+<?php echo $base + $bonus; ?></span>
                                                                        <?php if ($bonus > 0): ?><span class="stat-v-bonus">+<?php echo $bonus; ?></span><?php endif; ?>
                                                                    </div>
                                                            <?php
                                                                endif;
                                                            endforeach;
                                                            ?>

                                                            <?php if (isset($res['ReqLevel']) && (int) $res['ReqLevel'] > 0): ?>
                                                                <div class="req-level" style="color:#eee;">Lv. <?php echo (int) $res['ReqLevel']; ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="owner-info">
                                                    <div>
                                                        <span class="label">Account:</span>
                                                        <a href="/admin?view=USERS&f_userid=<?php echo urlencode((string) ($res['AccountName'] ?? '')); ?>" class="value"><?php echo htmlspecialchars((string) ($res['AccountName'] ?? '')); ?></a>
                                                    </div>
                                                    <div style="margin-top: 4px;">
                                                        <span class="label">Main char:</span>
                                                        <?php if (!empty($res['CharName'])): ?>
                                                            <a href="/admin?view=CharEdit&id=<?php echo (int) ($res['CharID'] ?? 0); ?>" class="value" style="color: #fff;"><?php echo htmlspecialchars((string) $res['CharName']); ?></a>
                                                        <?php else: ?>
                                                            <span class="value" style="color: #444; font-style: italic;">No characters</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ((int) ($res['ItemCount'] ?? 1) > 1): ?>
                                                        <div style="margin-top: 4px;">
                                                            <span class="label">Count:</span>
                                                            <span class="value" style="color: #e8c881; font-weight: bold;"><?php echo number_format((int) $res['ItemCount']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="date-cell"><?php echo isset($res['Maketime']) ? date('d M H:i', strtotime((string) $res['Maketime'])) : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($view === 'DROPS_EDITOR'):
                    if (!hasAdminAccess('DROPS_EDITOR')) {
                        echo '<div style="padding:40px;text-align:center;color:#e53e3e;"><i class="fas fa-lock" style="font-size:32px;margin-bottom:16px;display:block;opacity:0.4;"></i>Accesso non autorizzato.</div>';
                    } else {
                        include __DIR__ . '/adm-panel/drops_editor.php';
                    }
                ?>
                <?php elseif ($view === 'CHAREDIT' || $view === 'CharEdit'): ?>
                    <?php
                    $charID = isset($_GET['id']) ? (int) $_GET['id'] : 0;
                    $charTab = isset($_GET['tab']) ? trim($_GET['tab']) : '';
                    $char   = null;
                    if ($charID > 0) {
                        try {
                            $ceStmt = $pdoAdmin->prepare("SELECT C.CharID, C.CharName, C.Level, C.Map, C.Money, C.K1, C.LoginStatus, C.Del, C.UserUID, MG.Country as Faction, U.UserID FROM PS_GameData.dbo.Chars C LEFT JOIN PS_GameData.dbo.UserMaxGrow MG ON MG.UserUID = C.UserUID LEFT JOIN PS_UserData.dbo.Users_Master U ON U.UserUID = C.UserUID WHERE C.CharID = ?");
                            $ceStmt->execute([$charID]);
                            $char = $ceStmt->fetch(PDO::FETCH_ASSOC);
                        } catch (Throwable $e) {}
                    }
                    // Message from redirect
                    $ceMsg = '';
                    $ceMsgType = 'success';
                    $ceMsgMap = [
                        'renamed'           => ['Character renamed successfully.', 'success'],
                        'level_updated'     => ['Level updated successfully.', 'success'],
                        'map_changed'       => ['Map changed successfully.', 'success'],
                        'gold_sent'         => ['Gold sent successfully.', 'success'],
                        'item_deleted'      => ['Item deleted successfully.', 'success'],
                        'item_updated'      => ['Item updated successfully.', 'success'],
                        'char_deleted'      => ['Character deleted.', 'success'],
                        'char_resurrected'  => ['Character resurrected.', 'success'],
                        'error_rename'      => ['Rename failed.', 'error'],
                        'error_level'       => ['Level update failed.', 'error'],
                        'error_map'         => ['Map change failed.', 'error'],
                        'error_gold'        => ['Gold transfer failed.', 'error'],
                        'error_item_delete' => ['Item deletion failed.', 'error'],
                        'error_item_update' => ['Item update failed.', 'error'],
                        'error_char_delete' => ['Character deletion failed.', 'error'],
                        'error_resurrect'   => ['Character resurrection failed.', 'error'],
                    ];
                    if (isset($_GET['msg']) && isset($ceMsgMap[$_GET['msg']])) {
                        $ceMsg     = $ceMsgMap[$_GET['msg']][0];
                        $ceMsgType = $ceMsgMap[$_GET['msg']][1];
                    }
                    ?>

                    <?php if (!$char): ?>
                        <div class="alert alert-error" style="max-width: 600px;">
                            Character not found (ID: <?php echo $charID; ?>). <a href="/admin?view=CHARS" class="user-link">Browse all characters</a>.
                        </div>
                    <?php else: ?>
                        <div class="char-edit-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.06);">
                            <div>
                                <h1 style="font-size: 22px; color: #e8c881; margin-bottom: 6px;"><?php echo htmlspecialchars($char['CharName']); ?></h1>
                                <p style="color: #666; font-size: 13px;">
                                    Character ID: <?php echo $charID; ?> &bull;
                                    Account: <a href="/admin?view=UserEdit&uid=<?php echo $char['UserUID']; ?>" class="user-link"><?php echo htmlspecialchars((string) ($char['UserID'] ?? '')); ?></a>
                                    <?php if ($char['Faction'] !== null): ?>
                                    &bull; <img src="assets/<?php echo $char['Faction'] == 0 ? 'aol.webp' : 'uof.webp'; ?>" style="width: 14px; vertical-align: middle;" alt="faction"> <?php echo $char['Faction'] == 0 ? 'AOL' : 'UOF'; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($ceMsg): ?>
                            <div class="alert alert-<?php echo $ceMsgType; ?>" style="margin: 0;"><?php echo htmlspecialchars($ceMsg); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Tabs -->
                        <div class="user-tabs" style="margin-bottom: 25px;">
                            <a href="/admin?view=CharEdit&id=<?php echo $charID; ?>" class="user-tab <?php echo (!$charTab || $charTab === 'Overview') ? 'active' : ''; ?>" style="text-decoration: none;">Overview</a>
                            <a href="/admin?view=CharEdit&id=<?php echo $charID; ?>&tab=Inventory" class="user-tab <?php echo $charTab === 'Inventory' ? 'active' : ''; ?>" style="text-decoration: none;">Inventory</a>
                        </div>

                        <?php if (!$charTab || $charTab === 'Overview'): ?>
                            <div class="char-edit-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                <!-- Info Card -->
                                <div style="background: rgba(255,255,255,0.02); padding: 25px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                                    <h4 style="color: #e8c881; margin-bottom: 20px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Character Info</h4>
                                    <table class="char-info-table" style="width: 100%; border-collapse: collapse;">
                                        <tbody>
                                            <tr><td style="padding: 8px 0; color: #666; width: 40%;">Char ID</td><td style="color: #ccc;"><?php echo $charID; ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Level</td><td style="color: #ccc;"><?php echo (int) $char['Level']; ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Map</td><td style="color: #ccc;"><?php echo getMapName((int) $char['Map']); ?> (<?php echo (int) $char['Map']; ?>)</td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Gold</td><td style="color: #ccc;"><?php echo number_format((int) $char['Money']); ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Kills</td><td style="color: #ccc;"><?php echo number_format((int) $char['K1']); ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Status</td><td><?php echo (int) ($char['LoginStatus'] ?? 0) === 1 ? '<span style="color:#48bb78;">ONLINE</span>' : '<span style="color:#e53e3e;">OFFLINE</span>'; ?></td></tr>
                                            <tr><td style="padding: 8px 0; color: #666;">Deleted</td><td><?php echo (int) $char['Del'] === 1 ? '<span style="color:#e53e3e;">Yes</span>' : '<span style="color:#48bb78;">No</span>'; ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Actions Card -->
                                <div style="background: rgba(255,255,255,0.02); padding: 25px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; gap: 18px;">
                                    <h4 style="color: #e8c881; margin-bottom: 5px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Actions</h4>

                                    <!-- Rename -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="rename">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Rename</label>
                                        <input type="text" name="new_name" class="search-input" value="<?php echo htmlspecialchars($char['CharName']); ?>" style="height: 36px; width: 160px; flex: 1;">
                                        <button type="submit" class="btn-search">RENAME</button>
                                    </form>

                                    <!-- Level -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="update_level">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Level</label>
                                        <input type="number" name="new_level" class="search-input" value="<?php echo (int) $char['Level']; ?>" min="1" max="80" style="height: 36px; width: 80px;">
                                        <button type="submit" class="btn-search">SET LEVEL</button>
                                    </form>

                                    <!-- Map -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="change_map">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Map</label>
                                        <input type="number" name="new_map" class="search-input" value="<?php echo (int) $char['Map']; ?>" min="0" max="109" style="height: 36px; width: 80px;">
                                        <button type="submit" class="btn-search">CHANGE MAP</button>
                                    </form>

                                    <!-- Gold -->
                                    <form action="/admin" method="POST" style="display: flex; gap: 10px; align-items: center;">
                                        <input type="hidden" name="action" value="send_gold">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <label style="color: #888; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; min-width: 60px;">Gold</label>
                                        <input type="number" name="gold_amount" class="search-input" placeholder="Amount to add..." style="height: 36px; width: 160px; flex: 1;" max="500000000">
                                        <button type="submit" class="btn-search">SEND GOLD</button>
                                    </form>

                                    <!-- Delete / Resurrect -->
                                    <?php if ((int) $char['Del'] === 0): ?>
                                    <form action="/admin" method="POST" style="display: inline;" class="js-confirm-submit" data-confirm="<?php echo htmlspecialchars('Permanently delete character ' . (string) $char['CharName'] . '? This cannot be undone.', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="delete_char">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="admin-btn btn-kick" style="margin-top: 5px;">DELETE CHARACTER</button>
                                    </form>
                                    <?php else: ?>
                                    <form action="/admin" method="POST" style="display: inline;" class="js-confirm-submit" data-confirm="<?php echo htmlspecialchars('Resurrect character ' . (string) $char['CharName'] . '?', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="resurrect_char">
                                        <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="btn-action" style="margin-top: 5px; background: rgba(72,187,120,0.15); border-color: rgba(72,187,120,0.3); color: #48bb78;">RESURRECT CHARACTER</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif ($charTab === 'Inventory'): ?>
                            <?php
                            $invRows = [];
                            try {
                                $invStmt = $pdoAdmin->prepare("SELECT CI.ItemUID, CI.Slot, CI.ItemID, CI.ItemCount, CI.Craftname, I.ItemName FROM PS_GameData.dbo.CharItems CI LEFT JOIN PS_GameDefs.dbo.Items I ON I.ItemID = CI.ItemID WHERE CI.CharID = ? AND CI.Del = 0 ORDER BY CI.Slot ASC");
                                $invStmt->execute([$charID]);
                                $invRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Throwable $e) {}
                            ?>
                            <div class="chars-table-container">
                                <table class="chars-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 55px;">Slot</th>
                                            <th>Item Name</th>
                                            <th style="width: 75px;">Item ID</th>
                                            <th style="width: 65px;">Count</th>
                                            <th style="width: 80px;">Enchant</th>
                                            <th style="width: 80px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invRows as $invItem): ?>
                                        <tr>
                                            <td><?php echo (int) $invItem['Slot']; ?></td>
                                            <td><?php echo htmlspecialchars((string) ($invItem['ItemName'] ?? 'Unknown')); ?></td>
                                            <td><?php echo (int) $invItem['ItemID']; ?></td>
                                            <td><?php echo (int) $invItem['ItemCount']; ?></td>
                                            <td>
                                                <?php
                                                $cn = (string) ($invItem['Craftname'] ?? '');
                                                echo (strlen($cn) >= 20 ? substr($cn, 18, 2) : '0') . '+';
                                                ?>
                                            </td>
                                            <td>
                                                <form action="/admin" method="POST" style="display: inline;" class="js-confirm-submit" data-confirm="Delete this item from inventory?">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="char_id" value="<?php echo $charID; ?>">
                                                    <input type="hidden" name="item_uid" value="<?php echo (int) $invItem['ItemUID']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <button type="submit" style="background: none; border: none; color: #e53e3e; cursor: pointer;" title="Delete item"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($invRows) === 0): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 60px; color: #444;">
                                                <i class="fas fa-box-open" style="font-size: 30px; opacity: 0.15; margin-bottom: 15px; display: block;"></i>
                                                No items in inventory.
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?> <!-- end CharEdit tab switch -->
                    <?php endif; ?> <!-- end if (!$char) -->

                <?php endif; ?> <!-- end main view switch -->
            </section>
        </div><!-- /content-area-box -->
    </div><!-- /main-wrapper -->
    <?php include 'modules/footer.php'; ?>
