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
                    $hasWebsiteChild = hasAdminAccess('DROPS_EDITOR') || hasAdminAccess('DROPS_BLACKLIST_ADD') || hasAdminAccess('LUCKYCHEST_TOGGLE') || hasAdminAccess('DOWNLOADS_UPDATE') || hasAdminAccess('REGISTER_TOGGLE') || hasAdminAccess('PERMISSIONS_MANAGEMENT') || hasAdminAccess('ADMIN_WHITELIST') || hasAdminAccess('TASK_SCHEDULER') || hasAdminAccess('BOSSES_EDITOR');
                    if ($hasWebsiteChild): 
                    ?>
                    <li class="admin-has-dropdown">
                        <a href="#"
                            class="<?php echo ($current_view === 'OTHER' || strpos($current_view, 'DROPS') !== false || strpos($current_view, 'LUCKYCHEST') !== false || $current_view === 'DOWNLOADS_UPDATE' || $current_view === 'REGISTER_TOGGLE' || $current_view === 'PERMISSIONS_MANAGEMENT' || $current_view === 'ADMIN_WHITELIST' || $current_view === 'TASK_SCHEDULER' || $current_view === 'BOSSES_EDITOR') ? 'active' : ''; ?>">WEBSITE
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
                            <?php if (hasAdminAccess('BOSSES_EDITOR')): ?><li><a href="/admin?view=bosses_editor">Bosses Management</a></li><?php endif; ?>
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


                <?php if ($view === 'DASHBOARD'):
                    include __DIR__ . '/adm-panel/dashboard.php'; ?>
                <?php elseif ($view === 'PERMISSIONS_MANAGEMENT'):
                    include __DIR__ . '/adm-panel/permissions.php'; ?>
                <?php elseif ($view === 'ADMIN_WHITELIST'):
                    include __DIR__ . '/adm-panel/admin-whitelist.php'; ?>
                <?php elseif ($view === 'GUILD_OVERVIEW'):
                    include __DIR__ . '/adm-panel/guild-overview.php'; ?>
                <?php elseif ($view === 'GUILDS'):
                    include __DIR__ . '/adm-panel/guilds.php'; ?>
                <?php elseif ($view === 'GIFTBOX'):
                    include __DIR__ . '/adm-panel/giftbox.php'; ?>
                <?php elseif ($view === 'POINTS'):
                    include __DIR__ . '/adm-panel/points.php'; ?>
                    <?php elseif ($view === 'SEND_NOTICE'):
                        include __DIR__ . '/adm-panel/send-notice.php'; ?>
                    <?php elseif (in_array($view, ['DROPS_BLACKLIST_ADD', 'DROPS_BLACKLIST_REMOVE', 'DROPS_CONFIG'])):
                        include __DIR__ . '/adm-panel/drops-management.php'; ?>
                    <?php elseif (strpos($view, 'LUCKYCHEST') !== false):
                        include __DIR__ . '/adm-panel/luckychest.php'; ?>
                    <?php elseif ($view === 'DOWNLOADS_UPDATE'):
                        include __DIR__ . '/adm-panel/downloads.php'; ?>
                    <?php elseif ($view === 'TASK_SCHEDULER'):
                        include __DIR__ . '/adm-panel/task-scheduler.php'; ?>
                    <?php elseif ($view === 'REGISTER_TOGGLE'):
                        include __DIR__ . '/adm-panel/register.php'; ?>
                    <?php elseif ($view === 'USERS'):
                        include __DIR__ . '/adm-panel/users.php'; ?>
                    <?php elseif ($view === 'USEREDIT'):
                        include __DIR__ . '/adm-panel/user-edit.php'; ?>
                <?php elseif ($view === 'CHARS'):
                    include __DIR__ . '/adm-panel/characters.php'; ?>
                <?php elseif ($view === 'ACTIONLOG'):
                    include __DIR__ . '/adm-panel/action-log.php'; ?>
                <?php elseif ($view === 'ITEMS'):
                    include __DIR__ . '/adm-panel/items.php'; ?>
                <?php elseif ($view === 'DROPS_EDITOR'):
                    if (!hasAdminAccess('DROPS_EDITOR')) {
                        echo '<div style="padding:40px;text-align:center;color:#e53e3e;"><i class="fas fa-lock" style="font-size:32px;margin-bottom:16px;display:block;opacity:0.4;"></i>Accesso non autorizzato.</div>';
                    } else {
                        include __DIR__ . '/adm-panel/drops_editor.php';
                    }
                ?>
                <?php elseif ($view === 'CHAREDIT' || $view === 'CharEdit'):
                    include __DIR__ . '/adm-panel/character-edit.php'; ?>
                <?php elseif ($view === 'BOSSES_EDITOR'):
                    if (!hasAdminAccess('BOSSES_EDITOR')) {
                        echo '<div style="padding:40px;text-align:center;color:#e53e3e;"><i class="fas fa-lock" style="font-size:32px;margin-bottom:16px;display:block;opacity:0.4;"></i>Access denied.</div>';
                    } else {
                        include __DIR__ . '/adm-panel/bosses-editor.php';
                    }
                ?>

                <?php endif; ?> <!-- end main view switch -->
            </section>
        </div><!-- /content-area-box -->
    </div><!-- /main-wrapper -->
    <?php include 'modules/footer.php'; ?>
