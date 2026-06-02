<?php
if (defined('APP_SECURITY_BOOTSTRAP_LOADED')) {
    return;
}
define('APP_SECURITY_BOOTSTRAP_LOADED', true);

// Ensure PDO connections are available globally
if (!class_exists('PDO')) {
    require_once __DIR__ . '/db_pdo.php';
}

function appSecurityLog(string $message): void
{
    $logDir = __DIR__ . '/config/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $logFile = $logDir . '/security.log';
    appRotateAndPruneLogFile($logFile);
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);

    appSecurityAlertCheck($message);
}

function appAuditLog(string $action, array $context = []): void
{
    $logDir = __DIR__ . '/config/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $safeContext = [];
    foreach ($context as $key => $value) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $key);
        if (is_scalar($value) || $value === null) {
            $safeContext[$safeKey] = $value;
        } else {
            $safeContext[$safeKey] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    $entry = [
        'ts' => date('c'),
        'action' => $action,
        'ip' => appGetClientIp(),
        'user' => isset($_SESSION['user']) ? (string) $_SESSION['user'] : null,
        'context' => $safeContext,
    ];

    $logFile = $logDir . '/audit.log';
    appRotateAndPruneLogFile($logFile);
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function appAlertLog(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/config/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $logFile = $logDir . '/alerts.log';
    appRotateAndPruneLogFile($logFile);

    $payload = [
        'ts' => date('c'),
        'message' => $message,
        'ip' => appGetClientIp(),
        'context' => $context,
    ];

    @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function appGetClientIp(): string
{
    $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

    // Only trust proxy-injected headers (X-Forwarded-For, X-Real-IP) when the
    // direct connection originates from a known trusted proxy (loopback or a
    // configured list). This prevents IP spoofing by arbitrary clients, which
    // would bypass all rate limits and the admin IP whitelist.
    $trustedProxies = array_filter(array_map('trim', explode(',', (string) (getenv('TRUSTED_PROXY_IPS') ?: '127.0.0.1,::1'))));

    $isFromTrustedProxy = in_array($remoteAddr, $trustedProxies, true);

    if ($isFromTrustedProxy) {
        $forwardedFor = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwardedFor !== '') {
            // Take the leftmost (client) IP — rightmost would be the proxy itself
            $first = trim(explode(',', $forwardedFor)[0]);
            if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        $realIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }
    }

    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '0.0.0.0';
}

function appIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    return false;
}

function appBuildCspHeader(): string
{
    $nonce = appGetCspNonce();
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "form-action 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.quilljs.com https://cdnjs.cloudflare.com",
        "style-src 'self' 'nonce-{$nonce}' https://cdn.quilljs.com https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "style-src-attr 'unsafe-inline'",
        "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
        "img-src 'self' data: https:",
        "connect-src 'self' https://cdn.quilljs.com",
    ];

    return implode('; ', $directives);
}

function appBuildStrictCspReportOnlyHeader(): string
{
    $nonce = appGetCspNonce();
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "form-action 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.quilljs.com https://cdnjs.cloudflare.com",
        "style-src 'self' 'nonce-{$nonce}' https://cdn.quilljs.com https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "style-src-attr 'unsafe-inline'",
        "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
        "img-src 'self' data: https:",
        "connect-src 'self' https://cdn.quilljs.com",
    ];

    return implode('; ', $directives);
}

function appApplySecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Content-Security-Policy: ' . appBuildCspHeader());
    header('Content-Security-Policy-Report-Only: ' . appBuildStrictCspReportOnlyHeader());

    if (appIsHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function appInitSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 1800,
            'path' => '/',
            'secure' => appIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    appEnableCspNonceOutputBuffer();
}

function appRotateCsrfToken(): string
{
    appInitSession();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf_token'];
}

function appVerifyCsrfToken(string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function appRateLimitAllow(string $key, int $maxAttempts = 5, int $windowSeconds = 900, ?int &$retryAfterSeconds = null): bool
{
    $logDir = __DIR__ . '/config/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $rateLimitFile = $logDir . '/rate_limit.json';
    $handle = @fopen($rateLimitFile, 'c+');

    if (!$handle) {
        appSecurityLog('Unable to open rate limit file: ' . $rateLimitFile);
        return true;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return true;
    }

    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    $now = time();
    $windowStart = $now - $windowSeconds;

    // Global prune: remove stale timestamps and empty keys automatically.
    foreach ($data as $entryKey => $timestamps) {
        if (!is_array($timestamps)) {
            unset($data[$entryKey]);
            continue;
        }

        $entryFiltered = [];
        foreach ($timestamps as $timestamp) {
            if (is_numeric($timestamp) && (int) $timestamp >= $windowStart) {
                $entryFiltered[] = (int) $timestamp;
            }
        }

        if (empty($entryFiltered)) {
            unset($data[$entryKey]);
            continue;
        }

        $data[$entryKey] = $entryFiltered;
    }

    if (!isset($data[$key]) || !is_array($data[$key])) {
        $data[$key] = [];
    }

    $filtered = $data[$key];

    if (count($filtered) >= $maxAttempts) {
        $retryAfterSeconds = max(1, ((int) min($filtered) + $windowSeconds) - $now);
        $data[$key] = $filtered;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $retryAfterSeconds = 0;
    $filtered[] = $now;
    $data[$key] = $filtered;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return true;
}

function appRateLimitReset(string $key): void
{
    $rateLimitFile = __DIR__ . '/config/logs/rate_limit.json';
    if (!file_exists($rateLimitFile)) {
        return;
    }

    $handle = @fopen($rateLimitFile, 'c+');
    if (!$handle) {
        return;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return;
    }

    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    unset($data[$key]);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function appAuthLockoutStatus(string $lockKey): array
{
    $stateFile = __DIR__ . '/config/logs/auth_lockouts.json';
    $now = time();
    $default = [
        'locked' => false,
        'remaining_seconds' => 0,
        'failures' => 0,
    ];

    $handle = @fopen($stateFile, 'c+');
    if (!$handle) {
        return $default;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return $default;
    }

    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    foreach ($data as $key => $value) {
        if (!is_array($value)) {
            unset($data[$key]);
            continue;
        }

        $lockUntil = (int) ($value['lock_until'] ?? 0);
        $updatedAt = (int) ($value['updated_at'] ?? 0);
        if (($lockUntil > 0 && $lockUntil < ($now - 3600)) || ($updatedAt > 0 && $updatedAt < ($now - 2592000))) {
            unset($data[$key]);
        }
    }

    $status = $default;
    $current = $data[$lockKey] ?? null;
    if (is_array($current)) {
        $failures = (int) ($current['failures'] ?? 0);
        $lockUntil = (int) ($current['lock_until'] ?? 0);
        if ($lockUntil > $now) {
            $status['locked'] = true;
            $status['remaining_seconds'] = $lockUntil - $now;
        }
        $status['failures'] = max(0, $failures);
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $status;
}

function appAuthLockoutRecordFailure(string $lockKey): array
{
    $stateFile = __DIR__ . '/config/logs/auth_lockouts.json';
    $now = time();
    $delayMap = [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 30,
        5 => 60,
        6 => 180,
        7 => 300,
        8 => 600,
    ];

    $handle = @fopen($stateFile, 'c+');
    if (!$handle) {
        return ['failures' => 0, 'lock_seconds' => 0];
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return ['failures' => 0, 'lock_seconds' => 0];
    }

    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    $current = $data[$lockKey] ?? [];
    if (!is_array($current)) {
        $current = [];
    }

    $failures = (int) ($current['failures'] ?? 0) + 1;
    $lockSeconds = $delayMap[$failures] ?? 900;
    if ($lockSeconds > 1800) {
        $lockSeconds = 1800;
    }

    $data[$lockKey] = [
        'failures' => $failures,
        'lock_until' => $lockSeconds > 0 ? ($now + $lockSeconds) : 0,
        'updated_at' => $now,
    ];

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return ['failures' => $failures, 'lock_seconds' => $lockSeconds];
}

function appAuthLockoutReset(string $lockKey): void
{
    $stateFile = __DIR__ . '/config/logs/auth_lockouts.json';
    if (!file_exists($stateFile)) {
        return;
    }

    $handle = @fopen($stateFile, 'c+');
    if (!$handle) {
        return;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return;
    }

    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    unset($data[$lockKey]);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function appRotateAndPruneLogFile(string $logFile): void
{
    $maxSizeBytes = (int) (getenv('APP_LOG_MAX_SIZE_BYTES') ?: (5 * 1024 * 1024));
    $maxSizeBytes = max(262144, $maxSizeBytes);
    $retentionDays = (int) (getenv('APP_LOG_RETENTION_DAYS') ?: 30);
    $retentionDays = max(3, $retentionDays);

    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    if (file_exists($logFile) && @filesize($logFile) >= $maxSizeBytes) {
        $backupName = $logFile . '.' . date('Ymd_His') . '.bak';
        @rename($logFile, $backupName);
        @touch($logFile);
        @chmod($logFile, 0640);
    }

    $pattern = $logFile . '.*.bak';
    $cutoff = time() - ($retentionDays * 86400);
    foreach (glob($pattern) ?: [] as $backupFile) {
        $modifiedAt = @filemtime($backupFile);
        if ($modifiedAt !== false && $modifiedAt < $cutoff) {
            @unlink($backupFile);
        }
    }
}

function appSecurityAlertCheck(string $message): void
{
    $normalized = strtolower($message);
    $isSuspicious = (
        strpos($normalized, 'csrf') !== false ||
        strpos($normalized, 'rate limit') !== false ||
        strpos($normalized, 'blocked') !== false ||
        strpos($normalized, 'invalid token') !== false ||
        strpos($normalized, 'signature verification failed') !== false
    );

    if (!$isSuspicious) {
        return;
    }

    $logDir = __DIR__ . '/config/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $stateFile = $logDir . '/security_alert_state.json';
    $windowSeconds = (int) (getenv('APP_ALERT_WINDOW_SECONDS') ?: 600);
    $threshold = (int) (getenv('APP_ALERT_THRESHOLD') ?: 12);
    $threshold = max(3, $threshold);
    $now = time();
    $ip = appGetClientIp();
    $stateKey = 'ip:' . $ip;

    $handle = @fopen($stateFile, 'c+');
    if (!$handle) {
        return;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return;
    }

    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    $windowStart = $now - $windowSeconds;
    foreach ($data as $key => $bucket) {
        if (!is_array($bucket) || !isset($bucket['events']) || !is_array($bucket['events'])) {
            unset($data[$key]);
            continue;
        }

        $events = [];
        foreach ($bucket['events'] as $ts) {
            if (is_numeric($ts) && (int) $ts >= $windowStart) {
                $events[] = (int) $ts;
            }
        }

        if (empty($events)) {
            unset($data[$key]);
            continue;
        }

        $data[$key]['events'] = $events;
    }

    if (!isset($data[$stateKey]) || !is_array($data[$stateKey])) {
        $data[$stateKey] = ['events' => [], 'last_alert' => 0];
    }

    $events = $data[$stateKey]['events'];
    if (!is_array($events)) {
        $events = [];
    }
    $events[] = $now;
    $data[$stateKey]['events'] = $events;

    $eventCount = count($events);
    $lastAlert = (int) ($data[$stateKey]['last_alert'] ?? 0);
    if ($eventCount >= $threshold && ($now - $lastAlert) >= $windowSeconds) {
        $data[$stateKey]['last_alert'] = $now;
        appAlertLog('Suspicious activity threshold reached', [
            'ip' => $ip,
            'events_in_window' => $eventCount,
            'window_seconds' => $windowSeconds,
            'threshold' => $threshold,
            'last_event' => $message,
        ]);
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function appGetCspNonce(): string
{
    static $nonce = null;
    if (is_string($nonce) && $nonce !== '') {
        return $nonce;
    }

    $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    return $nonce;
}

function appEnableCspNonceOutputBuffer(): void
{
    static $started = false;
    if ($started) {
        return;
    }

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return;
    }

    $started = true;
    @ob_start('appInjectCspNonceToHtml');
}

function appInjectCspNonceToHtml(string $buffer): string
{
    if ($buffer === '' || stripos($buffer, '<html') === false) {
        return $buffer;
    }

    $nonce = htmlspecialchars(appGetCspNonce(), ENT_QUOTES, 'UTF-8');

    $buffer = preg_replace_callback(
        '/<script\b([^>]*)>/i',
        static function (array $matches) use ($nonce): string {
            $attrs = (string) ($matches[1] ?? '');
            if (preg_match('/\snonce\s*=\s*["\"][^"\"]+["\"]/i', $attrs)) {
                return $matches[0];
            }
            return '<script' . $attrs . ' nonce="' . $nonce . '">';
        },
        $buffer
    ) ?? $buffer;

    $buffer = preg_replace_callback(
        '/<style\b([^>]*)>/i',
        static function (array $matches) use ($nonce): string {
            $attrs = (string) ($matches[1] ?? '');
            if (preg_match('/\snonce\s*=\s*["\"][^"\"]+["\"]/i', $attrs)) {
                return $matches[0];
            }
            return '<style' . $attrs . ' nonce="' . $nonce . '">';
        },
        $buffer
    ) ?? $buffer;

    return $buffer;
}

function appGetWebSetting(string $key, string $default = ''): string
{
    global $website_db_name, $pdoAdmin;

    // PDO is always available (loaded by security_bootstrap.php)
    if (isset($pdoAdmin) && $pdoAdmin instanceof PDO) {
        $dbName = trim((string) ($website_db_name ?? 'PS_WebSite'));
        if ($dbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            $dbName = 'PS_WebSite';
        }
        try {
            $stmt = $pdoAdmin->prepare("SELECT SettingValue FROM [{$dbName}].dbo.Web_Settings WHERE SettingKey = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row && isset($row['SettingValue'])) ? (string) $row['SettingValue'] : $default;
        } catch (Throwable $e) {
            error_log('appGetWebSetting error: ' . $e->getMessage());
            return $default;
        }
    }

    error_log('appGetWebSetting: PDO not available, returning default for key: ' . $key);
    return $default;
}

function appIsLoopbackIp(string $ip): bool
{
    return in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

function appWorkerSharedSecret(): string
{
    $secret = getenv('WORKER_HMAC_SECRET');
    if (!is_string($secret) || trim($secret) === '') {
        $serverSecret = $_SERVER['WORKER_HMAC_SECRET'] ?? '';
        if (is_string($serverSecret) && trim($serverSecret) !== '') {
            $secret = $serverSecret;
        }
    }

    if (!is_string($secret)) {
        return '';
    }

    $secret = trim($secret);
    if (strlen($secret) >= 2) {
        $first = $secret[0];
        $last = $secret[strlen($secret) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $secret = trim(substr($secret, 1, -1));
        }
    }

    return $secret;
}

function appConsumeWorkerNonce(string $nonce, int $timestamp, int $windowSeconds = 120): bool
{
    if ($nonce === '' || strlen($nonce) < 16 || strlen($nonce) > 128) {
        return false;
    }

    $now = time();
    if ($timestamp < ($now - $windowSeconds) || $timestamp > ($now + 30)) {
        return false;
    }

    $logDir = __DIR__ . '/config/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $nonceFile = $logDir . '/worker_nonces.json';
    $handle = @fopen($nonceFile, 'c+');
    if (!$handle) {
        return false;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }

    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    $minTs = $now - $windowSeconds;
    foreach ($data as $storedNonce => $storedTs) {
        if (!is_numeric($storedTs) || (int) $storedTs < $minTs) {
            unset($data[$storedNonce]);
        }
    }

    if (isset($data[$nonce])) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $data[$nonce] = $timestamp;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return true;
}

function appHashPbkdf2(string $password): string
{
    $salt = random_bytes(16);
    $raw = hash_pbkdf2('sha256', $password, $salt, 600000, 32, true);
    return 'pbkdf2sha256:600000:' . bin2hex($salt) . ':' . bin2hex($raw);
}

function appVerifyPbkdf2(string $password, string $stored): bool
{
    $parts = explode(':', $stored, 4);
    if (count($parts) !== 4 || $parts[0] !== 'pbkdf2sha256') {
        return false;
    }
    $iterations = (int) $parts[1];
    if ($iterations < 1) {
        return false;
    }
    $salt = hex2bin($parts[2]);
    $storedHash = hex2bin($parts[3]);
    if ($salt === false || $storedHash === false || strlen($storedHash) < 16) {
        return false;
    }
    $computed = hash_pbkdf2('sha256', $password, $salt, $iterations, strlen($storedHash), true);
    return hash_equals($storedHash, $computed);
}

function appVerifyWorkerSignature(string $rawBody, string $workerId, string $timestamp, string $nonce, string $signature, ?string &$failureReason = null, ?string $secretOverride = null): bool
{
    $failureReason = null;
    $secret = '';
    if (is_string($secretOverride) && trim($secretOverride) !== '') {
        $secret = trim($secretOverride);
    } else {
        $secret = appWorkerSharedSecret();
    }
    if ($secret === '') {
        appSecurityLog('Worker signature verification failed: WORKER_HMAC_SECRET missing');
        $failureReason = 'missing_secret';
        return false;
    }

    if ($workerId === '' || strlen($workerId) > 64 || !preg_match('/^[a-zA-Z0-9._\-]+$/', $workerId)) {
        $failureReason = 'invalid_worker_id';
        return false;
    }

    if (!ctype_digit($timestamp)) {
        $failureReason = 'invalid_timestamp';
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9._\-]{16,128}$/', $nonce)) {
        $failureReason = 'invalid_nonce_format';
        return false;
    }

    if (!preg_match('/^[a-fA-F0-9]{64}$/', $signature)) {
        $failureReason = 'invalid_signature_format';
        return false;
    }

    $ts = (int) $timestamp;
    if (!appConsumeWorkerNonce($nonce, $ts, 120)) {
        $failureReason = 'nonce_rejected';
        return false;
    }

    $payload = $workerId . '.' . $timestamp . '.' . $nonce . '.' . $rawBody;
    $expected = hash_hmac('sha256', $payload, $secret);
    $provided = strtolower($signature);
    if (!hash_equals($expected, $provided)) {
        $failureReason = 'signature_mismatch';
        return false;
    }

    return true;
}
