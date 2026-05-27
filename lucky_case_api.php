<?php
require_once 'security_bootstrap.php';
appApplySecurityHeaders();
appInitSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}
// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ✅ CSRF Protection
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!appVerifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}


require_once 'db.php';
require_once 'db_pdo.php';

// Check Lucky Chest Setting
$luckyChestEnabled = fetchWebsiteSettings(['LuckyChestEnabled'])['LuckyChestEnabled'] ?? '1';

if ($luckyChestEnabled != '1') {
    echo json_encode(['success' => false, 'message' => 'Lucky chest is currently disabled for maintenance.']);
    exit;
}

// Prepare necessary table just in case it is missing (runs silently)
$createTableSQL = "
IF NOT EXISTS (SELECT * FROM PS_WebSite.dbo.sysobjects WHERE name='Web_LuckyCase' AND xtype='U')
BEGIN
    CREATE TABLE PS_WebSite.dbo.Web_LuckyCase (
        UserUID int PRIMARY KEY,
        LastRollTime datetime NOT NULL
    )
END
ELSE
BEGIN
    -- Check if LastClaim exists and rename it to LastRollTime
    IF EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('PS_WebSite.dbo.Web_LuckyCase') AND name = 'LastClaim')
    BEGIN
        EXEC sp_rename 'PS_WebSite.dbo.Web_LuckyCase.LastClaim', 'LastRollTime', 'COLUMN';
    END
END
";
try {
    $pdoAdmin->exec($createTableSQL);
} catch (Throwable $e) {
}

// Get UserUID - use $pdoAdmin for cross-database query (PS_UserData)
$username = $_SESSION['user'];
$userStmt = $pdoAdmin->prepare("SELECT UserUID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?");
$userStmt->execute([$username]);
$userRow = $userStmt->fetch();

if (!$userRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User account not found in database.']);
    exit;
}
$userUID = (int) $userRow['UserUID'];

// ✅ RATE LIMITING - Max 10 requests per minute
$rateKey = 'lucky_case_api:' . appGetClientIp() . ':' . $userUID;
if (!appRateLimitAllow($rateKey, 10, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please wait.']);
    exit;
}

// Check Cooldown - use $pdoConn for PS_WebSite query
$timeQuery = "SELECT LastRollTime, DATEDIFF(second, LastRollTime, GETDATE()) AS SecondsPassed FROM PS_WebSite.dbo.Web_LuckyCase WHERE UserUID = ?";
$timeStmt = $pdoConn->prepare($timeQuery);
$timeStmt->execute([$userUID]);
$timeRow = $timeStmt->fetch();

if ($timeRow) {
    $secondsPassed = (int) $timeRow['SecondsPassed'];
    $cooldownSeconds = 6 * 3600; // 6 hours

    if ($secondsPassed < $cooldownSeconds) {
        $timeLeft = $cooldownSeconds - $secondsPassed;
        $hours = floor($timeLeft / 3600);
        $minutes = floor(($timeLeft % 3600) / 60);
        echo json_encode(['success' => false, 'message' => "You must wait $hours hours and $minutes minutes before opening another chest."]);
        exit;
    }
}

// Roll Rewards
$rewards = [
    ['id' => 100251, 'name' => '10 AP', 'icon' => 'gem', 'color' => '#bdc3c7', 'probability' => 20],        // Common
    ['id' => 100252, 'name' => '100 AP', 'icon' => 'gem', 'color' => '#3498db', 'probability' => 15],       // Rare
    ['id' => 100253, 'name' => '1000 AP', 'icon' => 'gem', 'color' => '#9b59b6', 'probability' => 10],       // Epic
    ['id' => 100254, 'name' => '10.000 AP', 'icon' => 'gem', 'color' => '#ff3f34', 'probability' => 5],     // Legendary
    ['id' => 0, 'name' => 'Nothing', 'icon' => 'times-circle', 'color' => '#7f8c8d', 'probability' => 2]    // Junk
];

// Determine Winning Item based on weighted probability
$totalProb = array_sum(array_column($rewards, 'probability'));
$rand = mt_rand(1, $totalProb);
$sum = 0;
$winningItem = null;

foreach ($rewards as $reward) {
    $sum += $reward['probability'];
    if ($rand <= $sum) {
        $winningItem = $reward;
        break;
    }
}

// Failsafe
if (!$winningItem)
    $winningItem = $rewards[0];

// Give item to Giftbox ONLY if they won something
if ($winningItem['id'] != 0) {
    // Shaiya max giftbox slots is 240 (0 to 239)
    // Use $pdoAdmin for PS_GameData query
    $slotQuery = "SELECT Slot FROM PS_GameData.dbo.UserStoredPointItems WHERE UserUID = ? ORDER BY Slot ASC";
    $slotStmt = $pdoAdmin->prepare($slotQuery);
    $slotStmt->execute([$userUID]);
    $slot = 0;
    while ($sRow = $slotStmt->fetch()) {
        if ($slot != (int) $sRow['Slot'])
            break;
        $slot++;
    }

    if ($slot >= 240) {
        echo json_encode(['success' => false, 'message' => 'Your Giftbox is full! Please clear it out first.']);
        exit;
    }

    // Insert into Bank - use $pdoAdmin for PS_GameData
    $insertGiftStmt = $pdoAdmin->prepare("INSERT INTO PS_GameData.dbo.UserStoredPointItems (UserUID, Slot, ItemID, ItemCount, BuyDate) VALUES (?, ?, ?, 1, GETDATE())");
    try {
        $insertGiftStmt->execute([$userUID, $slot, (int) $winningItem['id']]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error while delivering item.']);
        exit;
    }
}

// Update Tracker - use $pdoConn for PS_WebSite
if ($timeRow) {
    $updateTrackerStmt = $pdoConn->prepare("UPDATE PS_WebSite.dbo.Web_LuckyCase SET LastRollTime = GETDATE() WHERE UserUID = ?");
    try {
        $updateTrackerStmt->execute([$userUID]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error while updating chest tracker.']);
        exit;
    }
} else {
    $insertTrackerStmt = $pdoConn->prepare("INSERT INTO PS_WebSite.dbo.Web_LuckyCase (UserUID, LastRollTime) VALUES (?, GETDATE())");
    try {
        $insertTrackerStmt->execute([$userUID]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error while initializing chest tracker.']);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'item' => $winningItem
]);
exit;
