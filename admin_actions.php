<?php
require_once 'security_bootstrap.php';
appApplySecurityHeaders();
appInitSession();
require_once 'db_pdo.php';
require_once 'db.php';


// Security: Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: /");
    exit;
}

$username = $_SESSION['user'];

// Security: Re-check Admin status (16) - use PDO
$user_sql = "SELECT Status, UserUID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?";
$user_stmt = $pdoAdmin->prepare($user_sql);
$user_stmt->execute([$username]);
$user_data = $user_stmt->fetch();
$adminStatus = (int) ($user_data['Status'] ?? 0);
$adminUID = (int) ($user_data['UserUID'] ?? 0);

if (!in_array($adminStatus, [16, 32, 48])) {
    header("Location: /account");
    exit;
}

$clientIp = appGetClientIp();

$whitelistUserCount = 0;
$whitelistIpCount = 0;

// Count users in whitelist - use PDO
$wlUserCountStmt = $pdoAdmin->prepare("SELECT COUNT(*) AS Total FROM PS_UserData.dbo.AdminUserWhitelist");
$wlUserCountStmt->execute([]);
$wlUserCountRow = $wlUserCountStmt->fetch();
$whitelistUserCount = (int) ($wlUserCountRow['Total'] ?? 0);

if ($whitelistUserCount > 0) {
    // Check if user is whitelisted - use PDO
    $wlUserCheckStmt = $pdoAdmin->prepare("SELECT TOP 1 UserUID FROM PS_UserData.dbo.AdminUserWhitelist WHERE UserUID = ? OR UserID = ?");
    $isAllowedAdmin = false;
    $wlUserCheckStmt->execute([$adminUID, $username]);
    if ($wlUserCheckStmt->fetch()) {
        $isAllowedAdmin = true;
    }

    if (!$isAllowedAdmin) {
        appSecurityLog('Admin action blocked: user not in AdminUserWhitelist. user=' . $username . ' uid=' . $adminUID . ' ip=' . $clientIp);
        appAuditLog('admin_action_blocked_user_whitelist', [
            'admin' => $username,
            'admin_uid' => $adminUID,
            'ip' => $clientIp,
        ]);
        $_SESSION['admin_error'] = 'Access denied by privileged account whitelist policy.';
        header('Location: /account');
        exit;
    }
}

// Count IPs in whitelist - use PDO
$wlIpCountStmt = $pdoAdmin->prepare("SELECT COUNT(*) AS Total FROM PS_UserData.dbo.AdminIPWhitelist");
$wlIpCountStmt->execute([]);
$wlIpCountRow = $wlIpCountStmt->fetch();
$whitelistIpCount = (int) ($wlIpCountRow['Total'] ?? 0);

if ($whitelistIpCount > 0) {
    // Check if IP is whitelisted - use PDO
    $wlIpCheckStmt = $pdoAdmin->prepare("SELECT TOP 1 IPAddress FROM PS_UserData.dbo.AdminIPWhitelist WHERE IPAddress = ?");
    $isAllowedIp = false;
    $wlIpCheckStmt->execute([$clientIp]);
    if ($wlIpCheckStmt->fetch()) {
        $isAllowedIp = true;
    }

    if (!$isAllowedIp) {
        appSecurityLog('Admin action blocked: IP not in AdminIPWhitelist. user=' . $username . ' uid=' . $adminUID . ' ip=' . $clientIp);
        appAuditLog('admin_action_blocked_ip_whitelist', [
            'admin' => $username,
            'admin_uid' => $adminUID,
            'ip' => $clientIp,
        ]);
        $_SESSION['admin_error'] = 'Access denied by admin IP whitelist policy.';
        header('Location: /account');
        exit;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$msg = "";
$status = "error";
$adminActionLockKey = 'admin_action_lock:' . $adminUID . ':' . $clientIp;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /admin");
    exit;
}

$adminActionLockStatus = appAuthLockoutStatus($adminActionLockKey);
if (!empty($adminActionLockStatus['locked'])) {
    $remaining = (int) ($adminActionLockStatus['remaining_seconds'] ?? 0);
    $_SESSION['admin_error'] = 'Too many failed admin actions. Retry in ' . $remaining . ' seconds.';
    header("Location: /admin");
    exit;
}

if (!appVerifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
    appAuthLockoutRecordFailure($adminActionLockKey);
    appSecurityLog('Admin action CSRF validation failed for admin=' . $username . ' uid=' . $adminUID . ' ip=' . $clientIp);
    $_SESSION['admin_error'] = "Invalid security token. Please refresh and retry.";
    header("Location: /admin");
    exit;
}

if (!appRateLimitAllow('admin_action:' . $adminUID . ':' . appGetClientIp(), 120, 300)) {
    $_SESSION['admin_error'] = "Too many admin actions in a short time. Please wait and retry.";
    header("Location: /admin");
    exit;
}

function schedulerQueueAction(int $jobId, string $queueAction, array $payload, string $createdBy): bool
{
    $allowedActions = ['UPSERT', 'PAUSE', 'RESUME', 'DELETE', 'RUN_NOW'];
    if (!in_array($queueAction, $allowedActions, true)) {
        return false;
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        $payloadJson = '{}';
    }

    $websiteDbEscaped = schedulerWebsiteDbEscaped();
    global $pdoAdmin;
    try {
        $queueStmt = $pdoAdmin->prepare("INSERT INTO {$websiteDbEscaped}.dbo.Web_TaskQueue (JobID, QueueAction, Payload, Status, CreatedBy) VALUES (?, ?, ?, 'pending', ?)");
        return $queueStmt->execute([$jobId, $queueAction, $payloadJson, $createdBy]);
    } catch (Throwable $e) {
        return false;
    }
}

function schedulerWebsiteDbEscaped(): string
{
    global $website_db_name;

    $dbName = trim((string) $website_db_name);
    if ($dbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        $dbName = 'PS_WebSite';
    }

    return '[' . $dbName . ']';
}

function schedulerIsAuthorizedAdmin(int $adminStatus): bool
{
    if (!in_array($adminStatus, [16, 32, 48], true)) {
        return false;
    }

    if ($adminStatus === 16) {
        return true;
    }

    $permSettingKey = $adminStatus === 32 ? 'GM_Permissions' : 'GMA_Permissions';
    $rawPerms = appGetWebSetting($permSettingKey, '[]');
    $decoded = json_decode($rawPerms, true);
    if (!is_array($decoded)) {
        return false;
    }

    $perms = array_map(static function ($value) {
        return strtoupper((string) $value);
    }, $decoded);

    return in_array('TASK_SCHEDULER', $perms, true);
}

function schedulerCommandAllowedByWhitelist(string $commandText): bool
{
    $whitelistCsv = trim(appGetWebSetting('TaskSchedulerCommandWhitelist', ''));
    if ($whitelistCsv === '') {
        return false;
    }

    $prefixes = array_filter(array_map('trim', explode(',', $whitelistCsv)), static function ($value) {
        return $value !== '';
    });

    $commandLower = strtolower($commandText);
    foreach ($prefixes as $prefix) {
        if ($prefix === '') {
            continue;
        }
        if (strpos($commandLower, strtolower($prefix)) === 0) {
            return true;
        }
    }

    return false;
}

function executeGameSlashCommand(PDO $pdoConn, string $serviceName, string $commandText): bool
{
    $stmt = $pdoConn->prepare("EXEC [PS_GameDefs].[dbo].[Command] @serviceName = ?, @cmmd = ?");
    if ($stmt === false) {
        return false;
    }

    return $stmt->execute([$serviceName, $commandText]);
}

function schedulerAllowedSimpleDatabases(): array
{
    $discovered = [];
    global $pdoAdmin;
    try {
        $dbStmt = $pdoAdmin->prepare("SELECT name FROM sys.databases WHERE state = 0 AND HAS_DBACCESS(name) = 1 AND database_id > 4 ORDER BY name");
        $dbStmt->execute([]);
        while ($dbRow = $dbStmt->fetch(PDO::FETCH_ASSOC)) {
            $dbName = trim((string) ($dbRow['name'] ?? $dbRow['NAME'] ?? ''));
            if ($dbName !== '' && preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
                $discovered[] = $dbName;
            }
        }
    } catch (Throwable $e) {
        // discovery failed, fallback to defaults below
    }

    $dbMain = trim((string) (getenv('DB_NAME') ?: 'PS_UserData'));
    $dbWebsite = trim((string) (getenv('DB_WEBSITE_NAME') ?: 'PS_WebSite'));
    $defaults = array_merge($discovered, ['PS_UserData', 'PS_GameData', 'PS_WebSite', $dbMain, $dbWebsite]);
    $allowed = [];
    foreach ($defaults as $dbName) {
        if ($dbName !== '' && preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            $allowed[] = $dbName;
        }
    }
    return array_values(array_unique($allowed));
}

function schedulerValidateSimpleDbTarget(string $databaseName, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
        return false;
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return false;
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
        return false;
    }

    $dbEscaped = '[' . $databaseName . ']';
    global $pdoAdmin;
    try {
        $tableStmt = $pdoAdmin->prepare("SELECT 1 FROM {$dbEscaped}.INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?");
        $tableStmt->execute([$tableName]);
        if (!$tableStmt->fetch(PDO::FETCH_ASSOC)) {
            return false;
        }

        $columnStmt = $pdoAdmin->prepare("SELECT 1 FROM {$dbEscaped}.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $columnStmt->execute([$tableName, $columnName]);
        return (bool) $columnStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function schedulerBuildSimpleMeta(array $postData, string &$errorMessage): ?array
{
    $databaseName = trim((string) ($postData['simple_database'] ?? ''));
    $tableName = trim((string) ($postData['simple_table'] ?? ''));
    $columnName = trim((string) ($postData['simple_column'] ?? ''));
    $operator = strtolower(trim((string) ($postData['simple_operator'] ?? 'eq')));
    $expectedValue = trim((string) ($postData['simple_expected_value'] ?? ''));
    $secondCommand = trim((string) ($postData['simple_second_command'] ?? ''));

    if ($databaseName === '' || $tableName === '' || $columnName === '') {
        $errorMessage = 'Simple mode requires database, table and column selection.';
        return null;
    }

    if (strpos($tableName, '.') !== false) {
        $tableParts = explode('.', $tableName);
        $tableName = trim((string) end($tableParts));
    }

    if (strpos($columnName, '.') !== false) {
        $columnParts = explode('.', $columnName);
        $columnName = trim((string) end($columnParts));
    }

    if (!in_array($databaseName, schedulerAllowedSimpleDatabases($conn), true)) {
        $errorMessage = 'Selected database is not allowed.';
        return null;
    }

    $allowedOperators = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains'];
    if (!in_array($operator, $allowedOperators, true)) {
        $errorMessage = 'Invalid comparison operator.';
        return null;
    }

    if (!schedulerValidateSimpleDbTarget($databaseName, $tableName, $columnName)) {
        $errorMessage = 'Invalid database/table/column selection on real database metadata.';
        return null;
    }

    if ($secondCommand !== '') {
        if (mb_strlen($secondCommand) > 400 || strpos($secondCommand, '/') !== 0) {
            $errorMessage = "Invalid optional second command. It must start with '/' and be max 400 chars.";
            return null;
        }

        if (!schedulerCommandAllowedByWhitelist($secondCommand)) {
            $errorMessage = 'Optional second command blocked by whitelist policy.';
            return null;
        }
    }

    return [
        'database' => $databaseName,
        'table' => $tableName,
        'column' => $columnName,
        'operator' => $operator,
        'expected_value' => $expectedValue,
        'second_command' => $secondCommand
    ];
}

function ensureWebsiteSettingsTable(): void
{
    global $pdoAdmin;
    try {
        $pdoAdmin->exec("IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[PS_WebSite].[dbo].[Web_Settings]') AND type in (N'U')) CREATE TABLE PS_WebSite.dbo.Web_Settings (SettingId INT IDENTITY(1,1) PRIMARY KEY, SettingKey VARCHAR(100) NOT NULL UNIQUE, SettingValue VARCHAR(MAX) NOT NULL)");
    } catch (Throwable $e) {
        error_log('ensureWebsiteSettingsTable failed: ' . $e->getMessage());
    }
}

function upsertWebsiteSetting(string $key, string $value): bool
{
    global $pdoAdmin;
    try {
        $checkStmt = $pdoAdmin->prepare("SELECT TOP 1 SettingKey FROM PS_WebSite.dbo.Web_Settings WHERE SettingKey = ?");
        $checkStmt->execute([$key]);
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            $updateStmt = $pdoAdmin->prepare("UPDATE PS_WebSite.dbo.Web_Settings SET SettingValue = ? WHERE SettingKey = ?");
            return $updateStmt->execute([$value, $key]);
        }
        $insertStmt = $pdoAdmin->prepare("INSERT INTO PS_WebSite.dbo.Web_Settings (SettingKey, SettingValue) VALUES (?, ?)");
        return $insertStmt->execute([$key, $value]);
    } catch (Throwable $e) {
        error_log('upsertWebsiteSetting failed key=' . $key . ': ' . $e->getMessage());
        return false;
    }
}

switch ($action) {
    case 'giftbox':
        $targetUser = isset($_POST['target_user']) ? trim($_POST['target_user']) : '';
        $targetChar = isset($_POST['target_char']) ? trim($_POST['target_char']) : '';
        $itemid = isset($_POST['itemid']) ? (int) $_POST['itemid'] : 0;
        $count = isset($_POST['count']) ? (int) $_POST['count'] : 1;

        if ((!$targetUser && !$targetChar) || !$itemid) {
            $msg = "UserID or CharName and ItemID are required.";
        } else {
            $targetUID = 0;
            $finalUser = "";

            if ($targetUser) {
                try {
                    $q = $pdoAdmin->prepare("SELECT UserUID, UserID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?");
                    $q->execute([$targetUser]);
                    if ($targetData = $q->fetch(PDO::FETCH_ASSOC)) {
                        $targetUID = $targetData['UserUID'];
                        $finalUser = $targetData['UserID'];
                    }
                } catch (Throwable $e) { }
            } else {
                try {
                    $q = $pdoAdmin->prepare("SELECT UserUID, UserID FROM PS_GameData.dbo.Chars WHERE CharName = ?");
                    $q->execute([$targetChar]);
                    if ($targetData = $q->fetch(PDO::FETCH_ASSOC)) {
                        $targetUID = $targetData['UserUID'];
                        $finalUser = "Char:$targetChar";
                    }
                } catch (Throwable $e) { }
            }

            if ($targetUID > 0) {
                // Find next free slot in GiftBox
                $slot = 0;
                try {
                    $slotQuery = $pdoAdmin->prepare("SELECT Slot FROM PS_GameData.dbo.UserStoredPointItems WHERE UserUID = ? ORDER BY Slot ASC");
                    $slotQuery->execute([$targetUID]);
                    while ($sRow = $slotQuery->fetch(PDO::FETCH_ASSOC)) {
                        if ($slot != (int) $sRow['Slot'])
                            break;
                        $slot++;
                    }
                } catch (Throwable $e) { }

                if ($slot >= 240) {
                    $msg = "User's GiftBox is full!";
                } else {
                    $giftOk = false;
                    try {
                        $insertGift = $pdoAdmin->prepare("INSERT INTO PS_GameData.dbo.UserStoredPointItems (UserUID, Slot, ItemID, ItemCount, BuyDate) VALUES (?, ?, ?, ?, GETDATE())");
                        $giftOk = $insertGift->execute([$targetUID, $slot, $itemid, $count]);
                    } catch (Throwable $e) { }
                    if ($giftOk) {
                        $msg = "Successfully added Item $itemid (x$count) to $finalUser's GiftBox (Slot $slot).";
                        $status = "success";
                        appAuditLog('admin_giftbox_add', [
                            'admin' => $username,
                            'target_user' => $finalUser,
                            'target_uid' => $targetUID,
                            'item_id' => $itemid,
                            'count' => $count,
                            'slot' => $slot,
                        ]);
                    } else {
                        $msg = "Error adding item to database.";
                    }
                }
            } else {
                $msg = "Target not found.";
            }
        }
        break;

    case 'guild_rename':
        $guildID = isset($_POST['guild_id']) ? (int) $_POST['guild_id'] : 0;
        $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';

        if (!$guildID || !$newName) {
            $msg = "GuildID and New Name are required.";
        } else {
            try {
                $checkName = $pdoAdmin->prepare("SELECT GuildID FROM PS_GameData.dbo.Guilds WHERE GuildName = ? AND Del = 0 AND GuildID <> ?");
                $checkName->execute([$newName, $guildID]);
                if ($checkName->fetch(PDO::FETCH_ASSOC)) {
                    $msg = "A guild with that name already exists.";
                } else {
                    $update = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Guilds SET GuildName = ? WHERE GuildID = ? AND Del = 0");
                    $update->execute([$newName, $guildID]);
                    $msg = "Guild renamed to '$newName' successfully.";
                    $status = "success";
                    appAuditLog('admin_guild_rename', [
                        'admin' => $username,
                        'guild_id' => $guildID,
                        'new_name' => $newName,
                    ]);
                }
            } catch (Throwable $e) {
                $msg = "Error renaming guild.";
            }
        }
        break;

    case 'guild_change_leader':
        $guildID = isset($_POST['guild_id']) ? (int) $_POST['guild_id'] : 0;
        $newLeaderID = isset($_POST['new_leader_id']) ? (int) $_POST['new_leader_id'] : 0;

        if (!$guildID || !$newLeaderID) {
            $msg = "GuildID and New Leader ID are required.";
        } else {
            try {
                // Get new leader details
                $q = $pdoAdmin->prepare("SELECT CharName, UserID, UserUID FROM PS_GameData.dbo.Chars WHERE CharID = ?");
                $q->execute([$newLeaderID]);
                $leader = $q->fetch(PDO::FETCH_ASSOC);
                if ($leader) {
                    $update = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Guilds SET MasterCharID = ?, MasterName = ?, MasterUserID = ? WHERE GuildID = ? AND Del = 0");
                    $update->execute([$newLeaderID, (string) $leader['CharName'], (string) $leader['UserID'], $guildID]);
                    $msg = "Guild leader changed to '{$leader['CharName']}' successfully.";
                    $status = "success";
                    appAuditLog('admin_guild_change_leader', [
                        'admin' => $username,
                        'guild_id' => $guildID,
                        'new_leader_id' => $newLeaderID,
                        'new_leader_name' => (string) $leader['CharName'],
                    ]);
                } else {
                    $msg = "New leader character not found.";
                }
            } catch (Throwable $e) {
                $msg = "Error updating guild master.";
            }
        }
        break;




    case 'register_toggle':
        if ($adminStatus != 16) {
            $msg = "Unauthorized. Only Status 16 can use this.";
            $status = "error";
            break;
        }

        ensureWebsiteSettingsTable();

        $regEnabled = isset($_POST['reg_enabled']) ? '1' : '0';

        $update = upsertWebsiteSetting('RegistrationEnabled', $regEnabled);

        if ($update) {
            $msg = "Registration status updated successfully.";
            $status = "success";
            appAuditLog('admin_register_toggle', [
                'admin' => $username,
                'enabled' => $regEnabled,
            ]);
        } else {
            $msg = "Failed to update registration status.";
        }
        break;

    case 'admin_whitelist_add_user':
        if ($adminStatus != 16) {
            $msg = "Unauthorized. Only Status 16 can update admin whitelist.";
            $status = "error";
            break;
        }

        $targetAdminUserId = trim((string) ($_POST['admin_userid'] ?? ''));
        if ($targetAdminUserId === '' || strlen($targetAdminUserId) > 32 || !preg_match('/^[a-zA-Z0-9_@!#.\-]+$/', $targetAdminUserId)) {
            $msg = "Invalid UserID format for whitelist.";
            break;
        }

        $findAdminStmt = $pdoAdmin->prepare("SELECT UserUID, UserID, Status FROM PS_UserData.dbo.Users_Master WHERE UserID = ?");
        try {
            $findAdminStmt->execute([$targetAdminUserId]);
        } catch (Throwable $e) {
            $msg = "Unable to validate UserID in database.";
            break;
        }

        $targetAdminRow = $findAdminStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetAdminRow) {
            $msg = "UserID not found.";
            break;
        }

        $targetAdminUid = (int) ($targetAdminRow['UserUID'] ?? 0);
        $targetAdminStatus = (int) ($targetAdminRow['Status'] ?? 0);
        if ($targetAdminUid <= 0 || !in_array($targetAdminStatus, [16, 32, 48], true)) {
            $msg = "Only privileged accounts (ADM/GM/GMA) can be added to whitelist.";
            break;
        }

        try {
            $checkWl = $pdoAdmin->prepare("SELECT TOP 1 UserUID FROM PS_UserData.dbo.AdminUserWhitelist WHERE UserUID = ? OR UserID = ?");
            $checkWl->execute([$targetAdminUid, $targetAdminUserId]);
            if ($checkWl->fetch(PDO::FETCH_ASSOC)) {
                $msg = "User is already in admin whitelist.";
                break;
            }
            $insWl = $pdoAdmin->prepare("INSERT INTO PS_UserData.dbo.AdminUserWhitelist (UserUID, UserID, AddedBy) VALUES (?, ?, ?)");
            $insWl->execute([$targetAdminUid, $targetAdminUserId, $username]);
        } catch (Throwable $e) {
            $msg = "Failed to add user to admin whitelist.";
            break;
        }

        appSecurityLog('Admin whitelist user added by ' . $username . ' target=' . $targetAdminUserId . ' uid=' . $targetAdminUid . ' ip=' . $clientIp);
        appAuditLog('admin_whitelist_add_user', [
            'admin' => $username,
            'target_user' => $targetAdminUserId,
            'target_uid' => $targetAdminUid,
        ]);
        $status = "success";
        $msg = "Privileged account added to admin whitelist.";
        break;

    case 'admin_whitelist_add_ip':
        if ($adminStatus != 16) {
            $msg = "Unauthorized. Only Status 16 can update admin IP whitelist.";
            $status = "error";
            break;
        }

        $targetAdminIp = trim((string) ($_POST['admin_ip'] ?? ''));
        if (!filter_var($targetAdminIp, FILTER_VALIDATE_IP)) {
            $msg = "Invalid IP address format.";
            break;
        }

        try {
            $checkWlIp = $pdoAdmin->prepare("SELECT TOP 1 IPAddress FROM PS_UserData.dbo.AdminIPWhitelist WHERE IPAddress = ?");
            $checkWlIp->execute([$targetAdminIp]);
            if ($checkWlIp->fetch(PDO::FETCH_ASSOC)) {
                $msg = "IP address is already in admin whitelist.";
                break;
            }
            $insWlIp = $pdoAdmin->prepare("INSERT INTO PS_UserData.dbo.AdminIPWhitelist (IPAddress, AddedBy) VALUES (?, ?)");
            $insWlIp->execute([$targetAdminIp, $username]);
        } catch (Throwable $e) {
            $msg = "Failed to add IP to admin whitelist.";
            break;
        }

        appSecurityLog('Admin whitelist IP added by ' . $username . ' target_ip=' . $targetAdminIp . ' source_ip=' . $clientIp);
        appAuditLog('admin_whitelist_add_ip', [
            'admin' => $username,
            'target_ip' => $targetAdminIp,
        ]);
        $status = "success";
        $msg = "Admin IP added to whitelist.";
        break;

    case 'downloads_update':
        if ($adminStatus != 16) {
            $msg = "Unauthorized. Only Status 16 can use this.";
            $status = "error";
            break;
        }

        $dlEnabled = isset($_POST['dl_enabled']) ? '1' : '0';
        $linkGoogle = isset($_POST['link_google']) ? trim($_POST['link_google']) : '';
        $linkMirror = isset($_POST['link_mirror']) ? trim($_POST['link_mirror']) : '';
        $linkMega   = isset($_POST['link_mega'])   ? trim($_POST['link_mega'])   : '';

        // Validate URL schemes — only http/https allowed to prevent script-URI payloads
        // being rendered in href attributes on the download page (H-04)
        $allowedSchemes = ['http', 'https'];
        foreach (['linkGoogle' => $linkGoogle, 'linkMirror' => $linkMirror, 'linkMega' => $linkMega] as $fieldName => $urlValue) {
            if ($urlValue !== '') {
                $parsed = parse_url($urlValue);
                if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), $allowedSchemes, true)) {
                    $msg = "Invalid URL scheme for $fieldName. Only http:// and https:// are allowed.";
                    $status = "error";
                    break 2;
                }
            }
        }

        ensureWebsiteSettingsTable($conn);

        $settings = [
            'DownloadsEnabled' => $dlEnabled,
            'Link_GoogleDrive' => $linkGoogle,
            'Link_OfficialMirror' => $linkMirror,
            'Link_Mega' => $linkMega
        ];

        $successCount = 0;
        foreach ($settings as $key => $val) {
            $upd = upsertWebsiteSetting((string) $key, (string) $val);
            if ($upd)
                $successCount++;
        }

        if ($successCount === count($settings)) {
            $msg = "Downloads configuration updated successfully.";
            $status = "success";
            appAuditLog('admin_downloads_update', [
                'admin' => $username,
                'downloads_enabled' => $dlEnabled,
            ]);
        } else {
            $msg = "Some settings failed to update.";
        }
        break;

    case 'luckychest_toggle':
        if ($adminStatus != 16) {
            $msg = "Unauthorized. Only Status 16 can use this.";
            $status = "error";
            break;
        }

        $luckyEnabled = isset($_POST['lucky_enabled']) ? '1' : '0';

        ensureWebsiteSettingsTable($conn);
        $upd = upsertWebsiteSetting('LuckyChestEnabled', $luckyEnabled);

        if ($upd) {
            $msg = "Lucky Chest status updated successfully.";
            $status = "success";
            appAuditLog('admin_luckychest_toggle', [
                'admin' => $username,
                'enabled' => $luckyEnabled,
            ]);
        } else {
            $msg = "Failed to update Lucky Chest status.";
        }
        break;

    case 'luckychest_reset_all':
        if ($adminStatus != 16) {
            $msg = "Unauthorized.";
            $status = "error";
            break;
        }

        try {
            $pdoAdmin->exec("DELETE FROM PS_WebSite.dbo.Web_LuckyCase");
            $msg = "All player lucky chest timers have been reset.";
            $status = "success";
            appAuditLog('admin_luckychest_reset_all', [
                'admin' => $username,
            ]);
        } catch (Throwable $e) {
            $msg = "Failed to reset timers. Ensure the table exists.";
        }
        break;

    case 'luckychest_reset_user':
        if ($adminStatus != 16) {
            $msg = "Unauthorized.";
            $status = "error";
            break;
        }

        $userUID = isset($_POST['user_uid']) ? (int) $_POST['user_uid'] : 0;
        if ($userUID <= 0) {
            $msg = "Invalid UserUID provided.";
            $status = "error";
            break;
        }

        try {
            $delStmt = $pdoAdmin->prepare("DELETE FROM PS_WebSite.dbo.Web_LuckyCase WHERE UserUID = ?");
            $delStmt->execute([$userUID]);
            $msg = "Lucky chest timer for UserUID $userUID has been reset.";
            $status = "success";
            appAuditLog('admin_luckychest_reset_user', [
                'admin' => $username,
                'user_uid' => $userUID,
            ]);
        } catch (Throwable $e) {
            $msg = "Failed to reset timer for UserUID $userUID.";
        }
        break;

    case 'drops_config':
        if ($adminStatus != 16) {
            $msg = "Unauthorized.";
            $status = "error";
            break;
        }

        $dropsMaxGrade = isset($_POST['drops_max_grade']) ? (int) $_POST['drops_max_grade'] : 3072;
        $dropsMaxLevel = isset($_POST['drops_max_level']) ? (int) $_POST['drops_max_level'] : 80;
        $dropsHideZero = isset($_POST['drops_hide_zero']) ? '1' : '0';
        $dropsHideEmpty = isset($_POST['drops_hide_empty']) ? '1' : '0';

        ensureWebsiteSettingsTable($conn);

        $settings = [
            'DropsMaxGrade' => $dropsMaxGrade,
            'DropsMaxLevel' => $dropsMaxLevel,
            'DropsHideZero' => $dropsHideZero,
            'DropsHideEmpty' => $dropsHideEmpty
        ];

        $successCount = 0;
        foreach ($settings as $key => $val) {
            $upd = upsertWebsiteSetting((string) $key, (string) $val);
            if ($upd)
                $successCount++;
        }

        if ($successCount === count($settings)) {
            $msg = "Drop Settings updated successfully.";
            $status = "success";
            appAuditLog('admin_drops_config_update', [
                'admin' => $username,
                'max_grade' => $dropsMaxGrade,
                'max_level' => $dropsMaxLevel,
                'hide_zero' => $dropsHideZero,
                'hide_empty' => $dropsHideEmpty,
            ]);
        } else {
            $msg = "Some settings failed to update.";
        }
        break;

    case 'drops_blacklist_add':
    case 'drops_blacklist_remove':
        if ($adminStatus != 16) {
            $msg = "Unauthorized.";
            $status = "error";
            break;
        }

        $targetItemId = isset($_POST['itemid']) ? (int) $_POST['itemid'] : 0;
        if ($targetItemId <= 0) {
            $msg = "Invalid Item ID.";
            break;
        }

        ensureWebsiteSettingsTable($conn);

        $blacklistStr = '98005,98006,98007,98009,98012,98018,98019,98020,98025,98022,98013,98010,98011,98001,98002,98014,98015,98026,98023,98024,98004,98008,98021,98017,98003,98016,38147,38149,38151,38150,38148,72037,87037,87017,87057,72017,72057,38170,38170,44032,44039,44033,44034,44035,44036,44037,44038,44040,44041,41164,44138,44141';
        $blacklistSettings = fetchWebsiteSettings(['DropsBlacklist']);
        if (!empty($blacklistSettings['DropsBlacklist'])) {
            $blacklistStr = (string) $blacklistSettings['DropsBlacklist'];
        }

        $arr = [];
        if (!empty($blacklistStr)) {
            $arr = array_map('trim', explode(',', $blacklistStr));
            $arr = array_filter($arr, function ($v) {
                return $v !== '';
            });
        }

        $local_error = false;
        if ($action == 'drops_blacklist_add') {
            if (!in_array((string) $targetItemId, $arr)) {
                array_unshift($arr, (string) $targetItemId);
                $msg = "ItemID $targetItemId added to blacklist.";
            } else {
                $msg = "ItemID $targetItemId is already in the blacklist.";
                $local_error = true;
            }
        } else {
            $key = array_search((string) $targetItemId, $arr);
            if ($key !== false) {
                unset($arr[$key]);
                $msg = "ItemID $targetItemId removed from blacklist.";
            } else {
                $msg = "ItemID $targetItemId not found in blacklist.";
                $local_error = true;
            }
        }

        if (!$local_error) {
            $status = "success";
            $newList = implode(',', $arr);
            if (!upsertWebsiteSetting('DropsBlacklist', $newList)) {
                $status = "error";
                $msg = "Failed to update blacklist setting.";
                break;
            }
            appAuditLog('admin_drops_blacklist_update', [
                'admin' => $username,
                'operation' => $action,
                'item_id' => $targetItemId,
            ]);
        } else {
            $status = "error";
        }
        break;


    case 'points_add':
        $targetUser = isset($_POST['target_user']) ? trim($_POST['target_user']) : '';
        $amount = isset($_POST['point_amount']) ? (int) $_POST['point_amount'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        $gmInfo = "$username($adminStatus)";

        if (!$targetUser || $amount <= 0 || !$reason) {
            $msg = "UserID, a positive Point amount, and a Reason are required.";
        } else {
            try {
                // Check if user exists
                $checkQ = $pdoAdmin->prepare("SELECT UserUID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?");
                $checkQ->execute([$targetUser]);
                if ($checkQ->fetch(PDO::FETCH_ASSOC)) {
                    // Update Points
                    $update = $pdoAdmin->prepare("UPDATE PS_UserData.dbo.Users_Master SET Point = Point + ? WHERE UserID = ?");
                    $update->execute([$amount, $targetUser]);
                    // Log to History
                    try {
                        $logSql = $pdoAdmin->prepare("INSERT INTO PS_WebSite.dbo.Web_PointHistory (UserID, PointsAdded, Reason, GM_Account, [Date]) VALUES (?, ?, ?, ?, GETDATE())");
                        $logSql->execute([$targetUser, $amount, $reason, 'Sent by ' . $gmInfo]);
                    } catch (Throwable $e) { }
                    $msg = "Successfully added " . number_format($amount) . " points to $targetUser.";
                    $status = "success";
                    appAuditLog('admin_points_add', [
                        'admin' => $username,
                        'target_user' => $targetUser,
                        'amount' => $amount,
                        'reason' => $reason,
                    ]);
                } else {
                    $msg = "UserID not found.";
                }
            } catch (Throwable $e) {
                $msg = "Error updating points in database.";
            }
        }
        break;

    case 'restore_dropped_item':
        $logRow = isset($_POST['log_row']) ? (int) $_POST['log_row'] : 0;
        $charID = isset($_POST['char_id']) ? (int) $_POST['char_id'] : 0;

        if (!$logRow || !$charID) {
            $msg = "Invalid parameters for restoration.";
            break;
        }

        // 1. Fetch character and UserUID
        $char = null;
        try {
            $charQ = $pdoAdmin->prepare("SELECT UserUID, LoginStatus FROM PS_GameData.dbo.Chars WHERE CharID = ?");
            $charQ->execute([$charID]);
            $char = $charQ->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { }
        if (!$char) {
            $msg = "Character not found.";
            break;
        }
        if ((int) $char['LoginStatus'] === 1) {
            $msg = "Player is currently online. Please kick them first.";
            break;
        }
        $targetUID = $char['UserUID'];

        // 2. Fetch log entry
        $log = null;
        try {
            $logQ = $pdoAdmin->prepare("SELECT * FROM PS_GameLog.dbo.ActionLog WHERE [row] = ?");
            $logQ->execute([$logRow]);
            $log = $logQ->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { }
        if (!$log || (int) $log['ActionType'] !== 112) {
            $msg = "Valid drop log entry not found.";
            break;
        }

        $itemUID = $log['Value1'];
        $itemID = $log['Value2'];
        $type = floor($itemID / 1000);
        $typeID = $itemID % 1000;
        $details = $log['Text2']; // Template: Gem1,Gem2,Gem3,Gem4,Gem5,Gem6(ItemID) : Craftname

        // 3. Safety Check: Does item already exist?
        try {
            $exists1 = $pdoAdmin->prepare("SELECT TOP 1 ItemUID FROM PS_GameData.dbo.CharItems WHERE ItemUID = ?");
            $exists1->execute([$itemUID]);
            if ($exists1->fetch(PDO::FETCH_ASSOC)) {
                $msg = "Item already exists in a character inventory.";
                break;
            }
            $exists2 = $pdoAdmin->prepare("SELECT TOP 1 ItemUID FROM PS_GameData.dbo.UserStoredItems WHERE ItemUID = ?");
            $exists2->execute([$itemUID]);
            if ($exists2->fetch(PDO::FETCH_ASSOC)) {
                $msg = "Item already exists in the warehouse.";
                break;
            }
            $exists3 = $pdoAdmin->prepare("SELECT TOP 1 ItemUID FROM PS_GameData.dbo.MarketItems WHERE ItemUID = ?");
            $exists3->execute([$itemUID]);
            if ($exists3->fetch(PDO::FETCH_ASSOC)) {
                $msg = "Item already exists in the market.";
                break;
            }
        } catch (Throwable $e) {
            $msg = "Error checking item existence.";
            break;
        }

        // 4. Parse Gems and Craftname
        $gems = [0, 0, 0, 0, 0, 0];
        $craftname = "";

        $bracketPos = strpos($details, "(");
        if ($bracketPos !== false) {
            $gemsPart = trim(substr($details, 0, $bracketPos));
            $gemsArr = explode(",", $gemsPart);
            for ($i = 0; $i < 6; $i++) {
                if (isset($gemsArr[$i]))
                    $gems[$i] = (int) $gemsArr[$i];
            }
        }

        $colonPos = strpos($details, ":");
        if ($colonPos !== false) {
            $craftname = trim(substr($details, $colonPos + 1, 20));
        }

        // 5. Find free Slot in Warehouse
        $slot = 0;
        try {
            $slotQ = $pdoAdmin->prepare("SELECT Slot FROM PS_GameData.dbo.UserStoredItems WHERE UserUID = ? ORDER BY Slot ASC");
            $slotQ->execute([$targetUID]);
            while ($s = $slotQ->fetch(PDO::FETCH_ASSOC)) {
                if ($slot != (int) $s['Slot'])
                    break;
                $slot++;
            }
        } catch (Throwable $e) { }
        if ($slot >= 240) {
            $msg = "User's Warehouse is full (Slot $slot).";
            break;
        }

        // 6. Restore Item
        $restoreOk = false;
        try {
            $insertRestoreStmt = $pdoAdmin->prepare("INSERT INTO PS_GameData.dbo.UserStoredItems (ServerID, UserUID, ItemID, ItemUID, Type, TypeID, Slot, Quality, Gem1, Gem2, Gem3, Gem4, Gem5, Gem6, Craftname, [Count], Maketime, Maketype, Del) VALUES (1, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, 1, GETDATE(), 'X', 0)");
            $restoreOk = $insertRestoreStmt->execute([$targetUID, $itemID, $itemUID, $type, $typeID, $slot, $gems[0], $gems[1], $gems[2], $gems[3], $gems[4], $gems[5], $craftname]);
        } catch (Throwable $e) { }

        if ($restoreOk) {
            $status = "success";
            $msg = "Item '{$log['Text1']}' successfully restored to Warehouse (Slot $slot).";
            appAuditLog('admin_restore_dropped_item', [
                'admin' => $username,
                'log_row' => $logRow,
                'char_id' => $charID,
                'target_uid' => $targetUID,
                'item_uid' => $itemUID,
                'item_id' => $itemID,
                'slot' => $slot,
            ]);
        } else {
            $msg = "Database error during restoration.";
        }
        break;

    case 'user_kick':
        $userUID = isset($_POST['user_uid']) ? (int) $_POST['user_uid'] : 0;
        if ($userUID <= 0) {
            $msg = "Invalid UserUID.";
            break;
        }

        // Update LoginStatus in DB for all characters of this user
        try {
            $updateStatus = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET LoginStatus = 0 WHERE UserUID = ?");
            $updateStatus->execute([$userUID]);
        } catch (Throwable $e) { }

        // Execute real-time kick via stored procedure if available
        try {
            $cmd = "/kickuid " . $userUID;
            if (executeGameSlashCommand($pdoConn, 'ps_game', $cmd)) {
                $status = "success";
                $msg = "Kick command sent successfully for UserUID $userUID.";
            } else {
                $status = "success";
                $msg = "User marked offline in database (Real-time kick command failed but Status was reset).";
            }
        } catch (Exception $e) {
            $status = "success";
            $msg = "User marked offline in database.";
        }
        break;

    case 'user_update_status':
        $userUID = isset($_POST['user_uid']) ? (int) $_POST['user_uid'] : 0;
        $newStatus = isset($_POST['new_status']) ? (int) $_POST['new_status'] : 0;

        if ($userUID <= 0) {
            $msg = "Invalid UserUID.";
            break;
        }

        try {
            $update = $pdoAdmin->prepare("UPDATE PS_UserData.dbo.Users_Master SET Status = ? WHERE UserUID = ?");
            $update->execute([$newStatus, $userUID]);
            $status = "success";
            $msg = "Account status updated to $newStatus.";
        } catch (Throwable $e) {
            $msg = "Failed to update account status.";
        }
        break;

    case 'user_change_password':
        $userUID = isset($_POST['user_uid']) ? (int) $_POST['user_uid'] : 0;
        $newPw = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

        if ($userUID <= 0 || empty($newPw)) {
            $msg = "UserUID and New Password are required.";
            break;
        }

        try {
            $update = $pdoAdmin->prepare("UPDATE PS_UserData.dbo.Users_Master SET Pw = ? WHERE UserUID = ?");
            $update->execute([$newPw, $userUID]);
            $status = "success";
            $msg = "Password changed successfully.";
        } catch (Throwable $e) {
            $msg = "Failed to update password.";
        }
        break;


    case 'char_kick':
        $charID = isset($_POST['char_id']) ? (int) $_POST['char_id'] : 0;
        if ($charID <= 0) {
            $msg = "Invalid CharID.";
            break;
        }

        // Get UserUID for real-time kick
        $charData = null;
        try {
            $q = $pdoAdmin->prepare("SELECT UserUID, CharName FROM PS_GameData.dbo.Chars WHERE CharID = ?");
            $q->execute([$charID]);
            $charData = $q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { }

        if ($charData) {
            $targetUID = $charData['UserUID'];
            // Update DB status
            try {
                $u = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET LoginStatus = 0 WHERE CharID = ?");
                $u->execute([$charID]);
            } catch (Throwable $e) { }

            // Real-time kick attempt
            try {
                $cmd = "/kickuid " . $targetUID;
                if (executeGameSlashCommand($pdoConn, 'ps_game', $cmd)) {
                    $status = "success";
                    $msg = "Character '{$charData['CharName']}' kicked successfully.";
                } else {
                    $status = "success";
                    $msg = "Character '{$charData['CharName']}' marked offline in database (Real-time kick failed).";
                }
            } catch (Exception $e) {
                $status = "success";
                $msg = "Character '{$charData['CharName']}' marked offline in database.";
            }
        } else {
            $msg = "Character not found.";
        }
        break;

    case 'char_delete':
        $charID = isset($_POST['char_id']) ? (int) $_POST['char_id'] : 0;
        if ($charID <= 0) {
            $msg = "Invalid CharID.";
            break;
        }

        try {
            $update = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET Del = 1, DeleteDate = GETDATE() WHERE CharID = ?");
            $update->execute([$charID]);
            $status = "success";
            $msg = "Character deleted successfully.";
        } catch (Throwable $e) {
            $msg = "Failed to delete character.";
        }
        break;

    case 'char_restore':
        $charID = isset($_POST['char_id']) ? (int) $_POST['char_id'] : 0;
        if ($charID <= 0) {
            $msg = "Invalid CharID.";
            break;
        }

        try {
            $update = $pdoAdmin->prepare("UPDATE PS_GameData.dbo.Chars SET Del = 0, DeleteDate = NULL WHERE CharID = ?");
            $update->execute([$charID]);
            $status = "success";
            $msg = "Character restored successfully.";
        } catch (Throwable $e) {
            $msg = "Failed to restore character.";
        }
        break;

    case 'warehouse_item_delete':
        $userUID = isset($_POST['user_uid']) ? (int) $_POST['user_uid'] : 0;
        $itemUID = isset($_POST['item_uid']) ? (int) $_POST['item_uid'] : 0;

        if ($userUID <= 0 || $itemUID <= 0) {
            $msg = "Invalid parameters for deletion.";
            break;
        }

        try {
            $delete = $pdoAdmin->prepare("DELETE FROM PS_GameData.dbo.UserStoredItems WHERE ItemUID = ? AND UserUID = ?");
            $delete->execute([$itemUID, $userUID]);
            $status = "success";
            $msg = "Item permanently deleted from warehouse.";
        } catch (Throwable $e) {
            $msg = "Failed to delete item.";
        }
        break;

    case 'guild_warehouse_item_delete':
        $guildID = isset($_POST['guild_id']) ? (int) $_POST['guild_id'] : 0;
        $itemUID = isset($_POST['item_uid']) ? (int) $_POST['item_uid'] : 0;

        if ($guildID <= 0 || $itemUID <= 0) {
            $msg = "Invalid parameters for guild item deletion.";
            break;
        }

        try {
            $delete = $pdoAdmin->prepare("DELETE FROM PS_GameData.dbo.GuildStoredItems WHERE ItemUID = ? AND GuildID = ?");
            $delete->execute([$itemUID, $guildID]);
            $status = "success";
            $msg = "Guild item permanently deleted.";
        } catch (Throwable $e) {
            $msg = "Failed to delete guild item.";
        }
        break;

    case 'send_notice':
        $noticeText = isset($_POST['notice_text']) ? trim($_POST['notice_text']) : '';
        if (empty($noticeText)) {
            $msg = "Notice text cannot be empty.";
            break;
        }

        // Limit to 150 characters
        $noticeText = substr($noticeText, 0, 150);

        try {
            $cmd = "/nt " . $noticeText;
            if (executeGameSlashCommand($pdoConn, 'ps_game', $cmd)) {
                $status = "success";
                $msg = "Server notice sent successfully!";
            } else {
                $msg = "Failed to execute notice command.";
            }
        } catch (Exception $e) {
            appSecurityLog('Send notice failed for admin ' . $username . ': ' . $e->getMessage());
            $msg = "Error sending notice: " . $e->getMessage();
        }
        break;

    case 'task_scheduler_toggle':
        if (!schedulerIsAuthorizedAdmin($adminStatus)) {
            $msg = "Unauthorized for Task Scheduler. Enable TASK_SCHEDULER in Permissions Management.";
            break;
        }

        $schedulerEnabled = isset($_POST['scheduler_enabled']) ? '1' : '0';
        $websiteDbEscaped = schedulerWebsiteDbEscaped();

        $updated = false;
        try {
            $updateStmt = $pdoAdmin->prepare("UPDATE {$websiteDbEscaped}.dbo.Web_Settings SET SettingValue = ? WHERE SettingKey = 'TaskSchedulerEnabled'");
            $updateStmt->execute([$schedulerEnabled]);
            $updated = true;
            if ($updateStmt->rowCount() <= 0) {
                $insertStmt = $pdoAdmin->prepare("INSERT INTO {$websiteDbEscaped}.dbo.Web_Settings (SettingKey, SettingValue) VALUES ('TaskSchedulerEnabled', ?)");
                $insertStmt->execute([$schedulerEnabled]);
            }
        } catch (Throwable $e) {
            $updated = false;
            appSecurityLog('Task scheduler toggle failed for admin ' . $username . ': ' . $e->getMessage());
        }

        if ($updated) {
            $status = "success";
            $msg = $schedulerEnabled === '1' ? "Task Scheduler enabled." : "Task Scheduler disabled.";
        } else {
            $msg = "Failed to update Task Scheduler status.";
        }
        break;

    case 'task_scheduler_save_job':
        if (!schedulerIsAuthorizedAdmin($adminStatus)) {
            $msg = "Unauthorized for Task Scheduler. Enable TASK_SCHEDULER in Permissions Management.";
            break;
        }

        $websiteDbEscaped = schedulerWebsiteDbEscaped();

        $enabledSettings = fetchWebsiteSettings(['TaskSchedulerEnabled']);
        $enabledValue = (string) ($enabledSettings['TaskSchedulerEnabled'] ?? '0');
        if ($enabledValue !== '1') {
            $msg = "Task Scheduler module is disabled. Enable it first.";
            break;
        }

        $jobProfile = strtolower(trim((string) ($_POST['job_profile'] ?? 'advanced')));
        if (!in_array($jobProfile, ['simple', 'advanced'], true)) {
            $jobProfile = 'advanced';
        }

        $jobName = trim((string) ($_POST['job_name'] ?? ''));
        $taskType = $jobProfile === 'simple' ? 'SERVICE_COMMAND' : strtoupper(trim((string) ($_POST['task_type'] ?? '')));
        $simpleInterval = trim((string) ($_POST['simple_interval'] ?? 'hourly'));
        $serviceName = strtolower(trim((string) ($_POST['service_name'] ?? 'ps_game')));
        $commandText = trim((string) ($_POST['command_text'] ?? ''));
        $inlineSql = trim((string) ($_POST['sql_payload'] ?? ''));

        if ($jobName === '' || mb_strlen($jobName) > 100 || !preg_match('/^[a-zA-Z0-9 _\-\.\[\]\(\)#]+$/', $jobName)) {
            $msg = "Invalid job name. Use letters/numbers/spaces and basic symbols only (max 100).";
            break;
        }

        $allowedTaskTypes = ['SERVICE_COMMAND', 'SQL_SCRIPT'];
        if (!in_array($taskType, $allowedTaskTypes, true)) {
            $msg = "Invalid task type.";
            break;
        }

        $allowedSimpleIntervals = ['hourly', 'every_30m', 'every_15m', 'every_10m', 'daily_00_00', 'daily_03_00', 'daily_06_00', 'daily_12_00', 'daily_18_00'];
        $scheduleMode = 'simple';
        $scheduleValue = in_array($simpleInterval, $allowedSimpleIntervals, true) ? $simpleInterval : 'hourly';

        $sqlPayload = '';
        $sqlSource = 'none';

        if ($taskType === 'SERVICE_COMMAND') {
            $allowedServices = ['ps_game', 'ps_login'];
            if (!in_array($serviceName, $allowedServices, true)) {
                $msg = "Invalid service name.";
                break;
            }

            if ($commandText === '' || mb_strlen($commandText) > 400 || strpos($commandText, '/') !== 0) {
                $msg = "Invalid command. It must start with '/' and be max 400 chars.";
                break;
            }

            if (!schedulerCommandAllowedByWhitelist($commandText)) {
                $msg = "Command blocked by whitelist policy.";
                break;
            }
        }

        if ($taskType === 'SQL_SCRIPT') {
            $uploadedSql = '';
            if (isset($_FILES['sql_file']) && is_array($_FILES['sql_file']) && (int) ($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadError = (int) ($_FILES['sql_file']['error'] ?? UPLOAD_ERR_OK);
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $msg = "SQL upload failed (error $uploadError).";
                    break;
                }

                $tmpName = (string) ($_FILES['sql_file']['tmp_name'] ?? '');
                $fileName = (string) ($_FILES['sql_file']['name'] ?? '');
                $fileSize = (int) ($_FILES['sql_file']['size'] ?? 0);

                if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                    $msg = "Invalid SQL upload request.";
                    break;
                }

                if ($fileSize <= 0 || $fileSize > 262144) {
                    $msg = "SQL file must be between 1 byte and 256 KB.";
                    break;
                }

                if (!preg_match('/\.sql$/i', $fileName)) {
                    $msg = "Only .sql files are allowed.";
                    break;
                }

                $uploadedContent = file_get_contents($tmpName);
                if ($uploadedContent === false) {
                    $msg = "Unable to read uploaded SQL file.";
                    break;
                }

                $uploadedSql = trim((string) $uploadedContent);
            }

            if ($inlineSql !== '') {
                $sqlPayload = $inlineSql;
                $sqlSource = 'inline';
            } elseif ($uploadedSql !== '') {
                $sqlPayload = $uploadedSql;
                $sqlSource = 'upload';
            } else {
                $msg = "SQL Script task requires inline SQL or uploaded .sql file.";
                break;
            }

            if (mb_strlen($sqlPayload) > 200000) {
                $msg = "SQL payload too large (max 200000 chars).";
                break;
            }
        }

        if ($jobProfile === 'simple') {
            $simpleMetaError = '';
            $simpleMeta = schedulerBuildSimpleMeta($_POST, $simpleMetaError);
            if ($simpleMeta === null) {
                $msg = $simpleMetaError !== '' ? $simpleMetaError : 'Invalid simple mode database selectors.';
                break;
            }

            $encodedSimpleMeta = json_encode($simpleMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $sqlPayload = $encodedSimpleMeta === false ? '' : $encodedSimpleMeta;
            $sqlSource = 'simple_meta';
        }

        $serviceValue = $taskType === 'SERVICE_COMMAND' ? $serviceName : null;
        $commandValue = $taskType === 'SERVICE_COMMAND' ? $commandText : null;
        $sqlValue = ($taskType === 'SQL_SCRIPT' || $jobProfile === 'simple') ? $sqlPayload : null;

        $insertStmt = $pdoAdmin->prepare("INSERT INTO {$websiteDbEscaped}.dbo.Web_TaskJobs (JobName, IsEnabled, Status, ScheduleMode, ScheduleValue, TaskType, ServiceName, CommandText, SqlPayload, SqlSource, CreatedBy) VALUES (?, 1, 'active', ?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $insertStmt->execute([$jobName, $scheduleMode, $scheduleValue, $taskType, $serviceValue, $commandValue, $sqlValue, $sqlSource, $username]);
        } catch (Throwable $e) {
            $msg = "Failed to create scheduler job.";
            break;
        }

        $newJobId = 0;
        try {
            $idStmt = $pdoAdmin->prepare("SELECT TOP 1 JobID FROM {$websiteDbEscaped}.dbo.Web_TaskJobs WHERE CreatedBy = ? AND JobName = ? ORDER BY JobID DESC");
            $idStmt->execute([$username, $jobName]);
            if ($idRow = $idStmt->fetch(PDO::FETCH_ASSOC)) {
                $newJobId = (int) ($idRow['JobID'] ?? 0);
            }
        } catch (Throwable $e) { }

        if ($newJobId > 0) {
            schedulerQueueAction($newJobId, 'UPSERT', ['reason' => 'job_saved'], $username);
        }

        appSecurityLog('Task scheduler job created by ' . $username . ' name=' . $jobName . ' type=' . $taskType);
        $status = "success";
        $msg = "Scheduler job saved and queued for worker sync.";
        break;

    case 'task_scheduler_update_job':
        if (!schedulerIsAuthorizedAdmin($adminStatus)) {
            $msg = "Unauthorized for Task Scheduler. Enable TASK_SCHEDULER in Permissions Management.";
            break;
        }

        $websiteDbEscaped = schedulerWebsiteDbEscaped();

        $enabledSettings = fetchWebsiteSettings(['TaskSchedulerEnabled']);
        $enabledValue = (string) ($enabledSettings['TaskSchedulerEnabled'] ?? '0');
        if ($enabledValue !== '1') {
            $msg = "Task Scheduler module is disabled. Enable it first.";
            break;
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            $msg = "Invalid job id.";
            break;
        }

        $jobProfile = strtolower(trim((string) ($_POST['job_profile'] ?? 'advanced')));
        if (!in_array($jobProfile, ['simple', 'advanced'], true)) {
            $jobProfile = 'advanced';
        }

        $jobName = trim((string) ($_POST['job_name'] ?? ''));
        $taskType = $jobProfile === 'simple' ? 'SERVICE_COMMAND' : strtoupper(trim((string) ($_POST['task_type'] ?? '')));
        $simpleInterval = trim((string) ($_POST['simple_interval'] ?? 'hourly'));
        $serviceName = strtolower(trim((string) ($_POST['service_name'] ?? 'ps_game')));
        $commandText = trim((string) ($_POST['command_text'] ?? ''));
        $inlineSql = trim((string) ($_POST['sql_payload'] ?? ''));

        if ($jobName === '' || mb_strlen($jobName) > 100 || !preg_match('/^[a-zA-Z0-9 _\-\.\[\]\(\)#]+$/', $jobName)) {
            $msg = "Invalid job name. Use letters/numbers/spaces and basic symbols only (max 100).";
            break;
        }

        $allowedTaskTypes = ['SERVICE_COMMAND', 'SQL_SCRIPT'];
        if (!in_array($taskType, $allowedTaskTypes, true)) {
            $msg = "Invalid task type.";
            break;
        }

        $allowedSimpleIntervals = ['hourly', 'every_30m', 'every_15m', 'every_10m', 'daily_00_00', 'daily_03_00', 'daily_06_00', 'daily_12_00', 'daily_18_00'];
        $scheduleMode = 'simple';
        $scheduleValue = in_array($simpleInterval, $allowedSimpleIntervals, true) ? $simpleInterval : 'hourly';

        $sqlPayload = '';
        $sqlSource = 'none';

        if ($taskType === 'SERVICE_COMMAND') {
            $allowedServices = ['ps_game', 'ps_login'];
            if (!in_array($serviceName, $allowedServices, true)) {
                $msg = "Invalid service name.";
                break;
            }

            if ($commandText === '' || mb_strlen($commandText) > 400 || strpos($commandText, '/') !== 0) {
                $msg = "Invalid command. It must start with '/' and be max 400 chars.";
                break;
            }

            if (!schedulerCommandAllowedByWhitelist($commandText)) {
                $msg = "Command blocked by whitelist policy.";
                break;
            }
        }

        if ($taskType === 'SQL_SCRIPT') {
            $uploadedSql = '';
            if (isset($_FILES['sql_file']) && is_array($_FILES['sql_file']) && (int) ($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadError = (int) ($_FILES['sql_file']['error'] ?? UPLOAD_ERR_OK);
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $msg = "SQL upload failed (error $uploadError).";
                    break;
                }

                $tmpName = (string) ($_FILES['sql_file']['tmp_name'] ?? '');
                $fileName = (string) ($_FILES['sql_file']['name'] ?? '');
                $fileSize = (int) ($_FILES['sql_file']['size'] ?? 0);

                if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                    $msg = "Invalid SQL upload request.";
                    break;
                }

                if ($fileSize <= 0 || $fileSize > 262144) {
                    $msg = "SQL file must be between 1 byte and 256 KB.";
                    break;
                }

                if (!preg_match('/\.sql$/i', $fileName)) {
                    $msg = "Only .sql files are allowed.";
                    break;
                }

                $uploadedContent = file_get_contents($tmpName);
                if ($uploadedContent === false) {
                    $msg = "Unable to read uploaded SQL file.";
                    break;
                }

                $uploadedSql = trim((string) $uploadedContent);
            }

            if ($inlineSql !== '') {
                $sqlPayload = $inlineSql;
                $sqlSource = 'inline';
            } elseif ($uploadedSql !== '') {
                $sqlPayload = $uploadedSql;
                $sqlSource = 'upload';
            } else {
                try {
                    $existingSqlStmt = $pdoAdmin->prepare("SELECT SqlPayload, SqlSource FROM {$websiteDbEscaped}.dbo.Web_TaskJobs WHERE JobID = ?");
                    $existingSqlStmt->execute([$jobId]);
                    if ($existingSqlRow = $existingSqlStmt->fetch(PDO::FETCH_ASSOC)) {
                        $sqlPayload = (string) ($existingSqlRow['SqlPayload'] ?? '');
                        $sqlSource = (string) ($existingSqlRow['SqlSource'] ?? 'none');
                    }
                } catch (Throwable $e) { }
            }

            if (mb_strlen($sqlPayload) > 200000) {
                $msg = "SQL payload too large (max 200000 chars).";
                break;
            }
        }

        if ($jobProfile === 'simple') {
            $simpleMetaError = '';
            $simpleMeta = schedulerBuildSimpleMeta($_POST, $simpleMetaError);
            if ($simpleMeta === null) {
                $msg = $simpleMetaError !== '' ? $simpleMetaError : 'Invalid simple mode database selectors.';
                break;
            }

            $encodedSimpleMeta = json_encode($simpleMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $sqlPayload = $encodedSimpleMeta === false ? '' : $encodedSimpleMeta;
            $sqlSource = 'simple_meta';
        }

        $serviceValue = $taskType === 'SERVICE_COMMAND' ? $serviceName : null;
        $commandValue = $taskType === 'SERVICE_COMMAND' ? $commandText : null;
        $sqlValue = ($taskType === 'SQL_SCRIPT' || $jobProfile === 'simple') ? $sqlPayload : null;

        try {
            $updateStmt = $pdoAdmin->prepare("UPDATE {$websiteDbEscaped}.dbo.Web_TaskJobs SET JobName = ?, ScheduleMode = ?, ScheduleValue = ?, TaskType = ?, ServiceName = ?, CommandText = ?, SqlPayload = ?, SqlSource = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE JobID = ?");
            $updateStmt->execute([$jobName, $scheduleMode, $scheduleValue, $taskType, $serviceValue, $commandValue, $sqlValue, $sqlSource, $username, $jobId]);
        } catch (Throwable $e) {
            $msg = "Failed to update scheduler job.";
            break;
        }

        schedulerQueueAction($jobId, 'UPSERT', ['reason' => 'job_updated'], $username);
        appSecurityLog('Task scheduler job updated by ' . $username . ' job_id=' . $jobId . ' type=' . $taskType);
        $status = "success";
        $msg = "Scheduler job updated and queued for worker sync.";
        break;

    case 'task_scheduler_toggle_job':
        if (!schedulerIsAuthorizedAdmin($adminStatus)) {
            $msg = "Unauthorized for Task Scheduler. Enable TASK_SCHEDULER in Permissions Management.";
            break;
        }

        $websiteDbEscaped = schedulerWebsiteDbEscaped();

        $jobId = (int) ($_POST['job_id'] ?? 0);
        $enabled = isset($_POST['enabled']) && (string) $_POST['enabled'] === '1' ? 1 : 0;
        if ($jobId <= 0) {
            $msg = "Invalid job id.";
            break;
        }

        $newStatus = $enabled === 1 ? 'active' : 'paused';
        try {
            $toggleJobStmt = $pdoAdmin->prepare("UPDATE {$websiteDbEscaped}.dbo.Web_TaskJobs SET IsEnabled = ?, Status = ?, UpdatedBy = ?, UpdatedAt = GETDATE() WHERE JobID = ?");
            $toggleJobStmt->execute([$enabled, $newStatus, $username, $jobId]);
        } catch (Throwable $e) {
            $msg = "Failed to update job status.";
            break;
        }

        schedulerQueueAction($jobId, $enabled === 1 ? 'RESUME' : 'PAUSE', ['enabled' => $enabled], $username);
        appSecurityLog('Task scheduler job toggled by ' . $username . ' job_id=' . $jobId . ' enabled=' . $enabled);
        $status = "success";
        $msg = $enabled === 1 ? "Job resumed." : "Job paused.";
        break;

    case 'task_scheduler_delete_job':
        if (!schedulerIsAuthorizedAdmin($adminStatus)) {
            $msg = "Unauthorized for Task Scheduler. Enable TASK_SCHEDULER in Permissions Management.";
            break;
        }

        $websiteDbEscaped = schedulerWebsiteDbEscaped();

        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            $msg = "Invalid job id.";
            break;
        }

        schedulerQueueAction($jobId, 'DELETE', ['reason' => 'admin_delete'], $username);
        try {
            $deleteStmt = $pdoAdmin->prepare("DELETE FROM {$websiteDbEscaped}.dbo.Web_TaskJobs WHERE JobID = ?");
            $deleteStmt->execute([$jobId]);
        } catch (Throwable $e) {
            $msg = "Failed to delete scheduler job.";
            break;
        }

        appSecurityLog('Task scheduler job deleted by ' . $username . ' job_id=' . $jobId);
        $status = "success";
        $msg = "Scheduler job deleted and delete queued for worker.";
        break;

    case 'task_scheduler_run_now':
        if (!schedulerIsAuthorizedAdmin($adminStatus)) {
            $msg = "Unauthorized for Task Scheduler. Enable TASK_SCHEDULER in Permissions Management.";
            break;
        }

        $websiteDbEscaped = schedulerWebsiteDbEscaped();

        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            $msg = "Invalid job id.";
            break;
        }

        $jobExists = false;
        $jobEnabled = false;
        try {
            $jobCheckStmt = $pdoAdmin->prepare("SELECT JobID, IsEnabled FROM {$websiteDbEscaped}.dbo.Web_TaskJobs WHERE JobID = ?");
            $jobCheckStmt->execute([$jobId]);
            if ($jobRow = $jobCheckStmt->fetch(PDO::FETCH_ASSOC)) {
                $jobExists = true;
                $jobEnabled = (int) ($jobRow['IsEnabled'] ?? 0) === 1;
            }
        } catch (Throwable $e) { }

        if (!$jobExists) {
            $msg = "Scheduler job not found.";
            break;
        }

        if (!$jobEnabled) {
            $msg = "Cannot run now: job is paused.";
            break;
        }

        if (!schedulerQueueAction($jobId, 'RUN_NOW', ['requested_by' => $username], $username)) {
            $msg = "Failed to enqueue run request.";
            break;
        }

        appSecurityLog('Task scheduler run-now queued by ' . $username . ' job_id=' . $jobId);
        $status = "success";
        $msg = "Run request queued successfully.";
        break;

    case 'drops_update_row':
        if ($adminStatus != 16) {
            $msg = "Unauthorized.";
            $status = "error";
            break;
        }
        $rowId    = isset($_POST['row_id'])    ? (int) $_POST['row_id']    : 0;
        $grade    = isset($_POST['grade'])     ? (int) $_POST['grade']     : 0;
        $dropRate = isset($_POST['drop_rate']) ? (int) $_POST['drop_rate'] : -1;
        if ($rowId <= 0 || $dropRate < 0 || $grade < 0) {
            $msg = "Parametri non validi.";
            $status = "error";
            break;
        }
        if (!appRateLimitAllow('drops_edit:' . $username, 60, 60)) {
            $msg = "Rate limit raggiunto. Attendi prima di continuare — apri il changelog per vedere le modifiche effettuate.";
            $status = "error";
            break;
        }
        try {
            // Leggi valori attuali + MobID/MobName prima dell'UPDATE
            $oldStmt = $pdoAdmin->prepare("SELECT mi.Grade, mi.DropRate, mi.MobID, mi.ItemOrder, m.MobName FROM PS_GameDefs.dbo.MobItems mi LEFT JOIN PS_GameDefs.dbo.Mobs m ON m.MobID = mi.MobID WHERE mi.RowID = ?");
            $oldStmt->execute([$rowId]);
            $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $drStmt = $pdoAdmin->prepare("UPDATE PS_GameDefs.dbo.MobItems SET Grade = ?, DropRate = ? WHERE RowID = ?");
            $drStmt->execute([$grade, $dropRate, $rowId]);
            $affected = $drStmt->rowCount();

            if ($affected > 0) {
                $mobId   = $oldRow ? (int) $oldRow['MobID']    : 0;
                $mobName = $oldRow ? (string) $oldRow['MobName'] : '';
                $itemOrd = $oldRow ? (int) $oldRow['ItemOrder'] : 0;
                $oldGrd  = $oldRow ? (int) $oldRow['Grade']     : null;
                $oldDr   = $oldRow ? (int) $oldRow['DropRate']  : null;

                $msg = "MobID $mobId" . ($mobName ? " ($mobName)" : '') . " — row $rowId aggiornato (Grade=$grade, DropRate=$dropRate).";
                $status = "success";

                // Inserisci nel changelog di sessione
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
                    $logStmt = $pdoAdmin->prepare("INSERT INTO PS_WebSite.dbo.Web_DropsEditLog (AdminUID,AdminID,RowID,MobID,MobName,ItemOrder,OldGrade,NewGrade,OldDropRate,NewDropRate) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $logStmt->execute([$adminUID, $username, $rowId, $mobId, $mobName, $itemOrd, $oldGrd, $grade, $oldDr, $dropRate]);
                } catch (Throwable $e) {}

                appAuditLog('admin_drops_row_update', [
                    'admin'     => $username,
                    'row_id'    => $rowId,
                    'mob_id'    => $mobId,
                    'grade'     => $grade,
                    'drop_rate' => $dropRate,
                ]);
            } else {
                $msg = "Nessuna riga aggiornata. RowID $rowId non trovato.";
                $status = "error";
            }
        } catch (Throwable $e) {
            $msg = "Errore DB durante aggiornamento drop row.";
            $status = "error";
        }
        break;

    case 'drops_undo_row':
        if ($adminStatus != 16) { $msg = "Unauthorized."; $status = "error"; break; }
        $logId = isset($_POST['log_id']) ? (int) $_POST['log_id'] : 0;
        if ($logId <= 0) { $msg = "LogID non valido."; $status = "error"; break; }
        if (!appRateLimitAllow('drops_edit:' . $username, 60, 60)) {
            $msg = "Rate limit raggiunto."; $status = "error"; break;
        }
        try {
            $undoFetch = $pdoAdmin->prepare("SELECT RowID, MobID, MobName, ItemOrder, OldGrade, OldDropRate, NewGrade, NewDropRate FROM PS_WebSite.dbo.Web_DropsEditLog WHERE LogID = ? AND AdminUID = ?");
            $undoFetch->execute([$logId, $adminUID]);
            $undoRow = $undoFetch->fetch(PDO::FETCH_ASSOC);
            if (!$undoRow || $undoRow['OldGrade'] === null || $undoRow['OldDropRate'] === null) {
                $msg = "Impossibile annullare: valori precedenti non disponibili.";
                $status = "error";
                break;
            }
            $undoStmt = $pdoAdmin->prepare("UPDATE PS_GameDefs.dbo.MobItems SET Grade = ?, DropRate = ? WHERE RowID = ?");
            $undoStmt->execute([$undoRow['OldGrade'], $undoRow['OldDropRate'], $undoRow['RowID']]);
            // Rimuovi la voce dal log (annullata)
            $pdoAdmin->prepare("DELETE FROM PS_WebSite.dbo.Web_DropsEditLog WHERE LogID = ?")->execute([$logId]);
            $msg = "UNDO MobID {$undoRow['MobID']} ({$undoRow['MobName']}) — Grade ripristinato a {$undoRow['OldGrade']}, DropRate a {$undoRow['OldDropRate']}.";
            $status = "success";
            appAuditLog('admin_drops_undo', ['admin' => $username, 'log_id' => $logId, 'row_id' => $undoRow['RowID']]);
        } catch (Throwable $e) {
            $msg = "Errore DB durante UNDO."; $status = "error";
        }
        break;

    case 'drops_clear_log':
        if ($adminStatus != 16) { $msg = "Unauthorized."; $status = "error"; break; }
        try {
            $pdoAdmin->prepare("DELETE FROM PS_WebSite.dbo.Web_DropsEditLog WHERE AdminUID = ?")->execute([$adminUID]);
            $msg = "Changelog svuotato.";
            $status = "success";
        } catch (Throwable $e) {
            $msg = "Errore durante pulizia log."; $status = "error";
        }
        break;

    case 'drops_update_range':
        if ($adminStatus != 16) {
            $msg = "Unauthorized.";
            $status = "error";
            break;
        }
        $mobIdMin  = isset($_POST['mob_id_min'])  ? (int) $_POST['mob_id_min']  : 0;
        $mobIdMax  = isset($_POST['mob_id_max'])  ? (int) $_POST['mob_id_max']  : 0;
        $itemOrder = isset($_POST['item_order'])  ? (int) $_POST['item_order']  : 0;
        $grade     = isset($_POST['grade'])       ? (int) $_POST['grade']       : 0;
        $dropRate  = isset($_POST['drop_rate'])   ? (int) $_POST['drop_rate']   : -1;
        if ($mobIdMin <= 0 || $mobIdMax <= 0 || $mobIdMin > $mobIdMax || $itemOrder < 1 || $itemOrder > 9 || $dropRate < 0 || $grade < 0) {
            $msg = "Parametri range non validi.";
            $status = "error";
            break;
        }
        if (!appRateLimitAllow('drops_edit:' . $username, 60, 60)) {
            $msg = "Rate limit raggiunto.";
            $status = "error";
            break;
        }
        try {
            $drRangeStmt = $pdoAdmin->prepare("UPDATE PS_GameDefs.dbo.MobItems SET Grade = ?, DropRate = ? WHERE MobID BETWEEN ? AND ? AND ItemOrder = ?");
            $drRangeStmt->execute([$grade, $dropRate, $mobIdMin, $mobIdMax, $itemOrder]);
            $affected = $drRangeStmt->rowCount();
            $msg = "Range aggiornato: $affected righe (MobID $mobIdMin-$mobIdMax, ItemOrder $itemOrder, Grade=$grade, DropRate=$dropRate).";
            $status = "success";
            appAuditLog('admin_drops_range_update', [
                'admin'       => $username,
                'mob_id_min'  => $mobIdMin,
                'mob_id_max'  => $mobIdMax,
                'item_order'  => $itemOrder,
                'grade'       => $grade,
                'drop_rate'   => $dropRate,
                'rows_updated'=> $affected,
            ]);
        } catch (Throwable $e) {
            $msg = "Errore DB durante range update.";
            $status = "error";
        }
        break;

    default:
        $msg = "Invalid action.";
}

// Global redirect logic
if ($status == 'success') {
    appAuthLockoutReset($adminActionLockKey);
    appRotateCsrfToken();
    $_SESSION['admin_success'] = $msg;
} else {
    $_SESSION['admin_error'] = $msg;
}

$redirect = "/admin";
$returnView = isset($_POST['return_view']) ? $_POST['return_view'] : 'Dashboard';
if ($returnView) {
    $redirect .= "?view=" . urlencode($returnView);
    // Add additional parameters for specific views
    if ($returnView === 'GUILD_OVERVIEW' && isset($_POST['guild_id'])) {
        $redirect .= "&id=" . (int) $_POST['guild_id'];
    }
    if ($returnView === 'CharEdit' && isset($_POST['char_id'])) {
        $redirect .= "&id=" . (int) $_POST['char_id'];
        if (isset($_POST['tab'])) {
            $redirect .= "&tab=" . urlencode($_POST['tab']);
        }
    }
    if ($returnView === 'UserEdit' && isset($_POST['user_uid'])) {
        $redirect .= "&uid=" . (int) $_POST['user_uid'];
        if (isset($_POST['tab'])) {
            $redirect .= "&tab=" . urlencode($_POST['tab']);
        }
    }
    if ($returnView === 'DROPS_EDITOR') {
        if (!empty($_POST['drop_filter_mob_id']))   $redirect .= "&mob_id="   . (int) $_POST['drop_filter_mob_id'];
        if (!empty($_POST['drop_filter_mob_name']))  $redirect .= "&mob_name=" . urlencode((string) $_POST['drop_filter_mob_name']);
        if (!empty($_POST['drop_filter_map_id']))   $redirect .= "&map_id="   . (int) $_POST['drop_filter_map_id'];
        if (!empty($_POST['drop_filter_page']) && (int) $_POST['drop_filter_page'] > 1)
            $redirect .= "&page=" . (int) $_POST['drop_filter_page'];
    }
}
header("Location: $redirect");
exit;
?>