<?php
require_once 'security_bootstrap.php';
appApplySecurityHeaders();
appInitSession();
require_once 'db.php';
require_once 'db_pdo.php';

function appSanitizeNewsContent(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = '<p><br><b><strong><i><em><u><s><blockquote><ul><ol><li><h1><h2><h3><a><img><span><div>';
    $clean = strip_tags($html, $allowedTags);

    $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
    $clean = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
    $clean = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*j[a]vascript:[^\2]*\2/i', ' $1="#"', $clean) ?? $clean;
    $clean = preg_replace('/\s(href|src)\s*=\s*("|\')\s*data:(?!image\/(png|jpeg|jpg|webp);base64,)[^\2]*\2/i', ' $1="#"', $clean) ?? $clean;

    return trim($clean);
}

// Initialization: Create Table if it doesn't exist
$createTableSQL = "
IF NOT EXISTS (SELECT * FROM PS_WebSite.INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Web_News')
BEGIN
    CREATE TABLE PS_WebSite.dbo.Web_News (
        NewsID INT IDENTITY(1,1) PRIMARY KEY,
        Title NVARCHAR(255) NOT NULL,
        Content NVARCHAR(MAX) NOT NULL,
        Author NVARCHAR(50) NOT NULL,
        CreatedAt DATETIME DEFAULT GETDATE(),
        IsHidden BIT DEFAULT 0
    )
END
ELSE
BEGIN
    -- Upgrade existing columns to NVARCHAR if they are still VARCHAR
    IF (SELECT DATA_TYPE FROM PS_WebSite.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Web_News' AND COLUMN_NAME = 'Content') = 'varchar'
        ALTER TABLE PS_WebSite.dbo.Web_News ALTER COLUMN Content NVARCHAR(MAX) NOT NULL;
    IF (SELECT DATA_TYPE FROM PS_WebSite.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Web_News' AND COLUMN_NAME = 'Title') = 'varchar'
        ALTER TABLE PS_WebSite.dbo.Web_News ALTER COLUMN Title NVARCHAR(255) NOT NULL;
    IF (SELECT DATA_TYPE FROM PS_WebSite.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Web_News' AND COLUMN_NAME = 'Author') = 'varchar'
        ALTER TABLE PS_WebSite.dbo.Web_News ALTER COLUMN Author NVARCHAR(50) NOT NULL;
END
";
try { $pdoAdmin->exec($createTableSQL); } catch (Throwable $e) { error_log('Web_News DDL error: ' . $e->getMessage()); }

// Security: Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: /");
    exit;
}

$username = $_SESSION['user'];

// Security: Check Admin status (16)
try {
    $user_stmt = $pdoAdmin->prepare("SELECT Status, UserUID FROM PS_UserData.dbo.Users_Master WHERE UserID = ?");
    $user_stmt->execute([$username]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $user_data = null;
}
$userStatus = (int) ($user_data['Status'] ?? 0);

if ($userStatus != 16) {
    header("Location: /");
    exit;
}

// All mutations are POST-only — GET dispatch is not allowed (CSRF prevention)
$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!appVerifyCsrfToken($csrfToken)) {
        appSecurityLog('CSRF validation failed in news_handler.php for user: ' . $username . ' from IP: ' . appGetClientIp());
        header('HTTP/1.1 403 Forbidden');
        exit('Invalid security token. Please refresh and retry.');
    }
    // Rate limit news mutations (H-01)
    if (in_array($action, ['create', 'edit', 'delete', 'toggle_visibility'], true)) {
        if (!appRateLimitAllow('news_mutation:' . appGetClientIp() . ':' . strtolower($username), 20, 300)) {
            appSecurityLog('News mutation rate limit exceeded for user: ' . $username . ' from IP: ' . appGetClientIp());
            header('HTTP/1.1 429 Too Many Requests');
            exit('Rate limit exceeded. Please wait before making more changes.');
        }
    }
}

switch ($action) {
    case 'create':
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? appSanitizeNewsContent((string) $_POST['content']) : '';
        
        if ($title && $content) {
            // Server-side limit: 45 characters for title
            $title = mb_substr($title, 0, 45);
            $isHidden = isset($_POST['is_hidden']) ? 1 : 0;
            try {
                $query = $pdoAdmin->prepare(
                    "INSERT INTO PS_WebSite.dbo.Web_News (Title, Content, Author, CreatedAt, IsHidden) 
                     VALUES (?, ?, ?, GETDATE(), ?)"
                );
                $query->execute([$title, $content, $username, $isHidden]);
            } catch (Throwable $e) { error_log('News create error: ' . $e->getMessage()); }
        }
        header("Location: /");
        break;

    case 'edit':
        $newsID = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? appSanitizeNewsContent((string) $_POST['content']) : '';
        
        if ($newsID && $title && $content) {
            // Server-side limit: 45 characters for title
            $title = mb_substr($title, 0, 45);
            try {
                $query = $pdoAdmin->prepare(
                    "UPDATE PS_WebSite.dbo.Web_News 
                     SET Title = ?, Content = ? 
                     WHERE NewsID = ?"
                );
                $query->execute([$title, $content, $newsID]);
            } catch (Throwable $e) { error_log('News edit error: ' . $e->getMessage()); }
        }
        header("Location: /");
        break;

    case 'delete':
        $newsID = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
        if ($newsID) {
            try {
                $query = $pdoAdmin->prepare("DELETE FROM PS_WebSite.dbo.Web_News WHERE NewsID = ?");
                $query->execute([$newsID]);
            } catch (Throwable $e) { error_log('News delete error: ' . $e->getMessage()); }
        }
        header("Location: /");
        break;

    case 'toggle_visibility':
        $newsID = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
        if ($newsID) {
            try {
                $query = $pdoAdmin->prepare(
                    "UPDATE PS_WebSite.dbo.Web_News 
                     SET IsHidden = CASE WHEN IsHidden = 1 THEN 0 ELSE 1 END 
                     WHERE NewsID = ?"
                );
                $query->execute([$newsID]);
            } catch (Throwable $e) { error_log('News toggle error: ' . $e->getMessage()); }
        }
        header("Location: /");
        break;

    case 'upload_image':
        header('Content-Type: application/json; charset=UTF-8');
        if (!appRateLimitAllow('news_upload:' . appGetClientIp() . ':' . strtolower($username), 20, 300)) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many upload attempts. Please wait.']);
            exit;
        }

        if (isset($_FILES['image'])) {
            $file = $_FILES['image'];
            $maxFileSize = 5 * 1024 * 1024;
            $allowedMimeToExt = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
            ];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Upload error.']);
                exit;
            }

            if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxFileSize) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file size.']);
                exit;
            }

            if (!is_uploaded_file($file['tmp_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid upload source.']);
                exit;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
            if ($finfo) {
                finfo_close($finfo);
            }

            if (!is_string($detectedMime) || !isset($allowedMimeToExt[$detectedMime])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }

            $target_dir = __DIR__ . '/uploads/news/';
            if (!is_dir($target_dir) && !@mkdir($target_dir, 0750, true)) {
                http_response_code(500);
                echo json_encode(['error' => 'Upload directory unavailable.']);
                exit;
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimeToExt[$detectedMime];
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                echo json_encode(['url' => 'uploads/news/' . $filename]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to move uploaded file.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded.']);
        }
        exit;
        break;

    default:
        header("Location: /");
        break;
}
exit;
?>
