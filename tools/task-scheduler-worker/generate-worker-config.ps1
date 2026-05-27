param(
    [string]$ConfigPath = "$PSScriptRoot\worker.config.json",
    [string]$SqlSyncPath = "$PSScriptRoot\worker-config-sync.sql",
    [string]$ApiUrl = 'http://127.0.0.1/task_scheduler_worker_api.php',
    [string]$WorkerId = 'worker-main',
    [string]$SqlConnectionString,
    [string]$SqlDatabase,
    [int]$PollLimit = 10,
    [switch]$Force
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function New-RandomSecret {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    return -join ($bytes | ForEach-Object { $_.ToString('x2') })
}

function Get-DatabaseNameFromConnectionString {
    param([string]$ConnectionString)

    if ([string]::IsNullOrWhiteSpace($ConnectionString)) {
        return ''
    }

    $parts = $ConnectionString -split ';'
    foreach ($part in $parts) {
        $segment = $part.Trim()
        if ([string]::IsNullOrWhiteSpace($segment)) {
            continue
        }

        $eqIndex = $segment.IndexOf('=')
        if ($eqIndex -lt 1) {
            continue
        }

        $key = $segment.Substring(0, $eqIndex).Trim().ToLowerInvariant()
        $value = $segment.Substring($eqIndex + 1).Trim()
        if (($key -eq 'database' -or $key -eq 'initial catalog') -and -not [string]::IsNullOrWhiteSpace($value)) {
            return $value
        }
    }

    return ''
}

function Escape-SqlLiteral {
    param([string]$Value)
    if ($null -eq $Value) {
        return ''
    }
    return $Value.Replace("'", "''")
}

if ((Test-Path -LiteralPath $ConfigPath) -and -not $Force) {
    throw "Config file already exists: $ConfigPath. Use -Force to overwrite it."
}

if ([string]::IsNullOrWhiteSpace($SqlConnectionString)) {
    $dbHost = $env:DB_HOST
    if ([string]::IsNullOrWhiteSpace($dbHost)) {
        $dbHost = '127.0.0.1'
    }

    $dbName = $SqlDatabase
    if ([string]::IsNullOrWhiteSpace($dbName)) {
        $dbName = $env:DB_WEBSITE_NAME
    }
    if ([string]::IsNullOrWhiteSpace($dbName)) {
        $dbName = 'PS_WebSite'
    }

    $dbUser = $env:DB_USER
    $dbPass = $env:DB_PASSWORD

    if (-not [string]::IsNullOrWhiteSpace($dbUser)) {
        if ([string]::IsNullOrWhiteSpace($dbPass)) {
            throw 'DB_USER is set but DB_PASSWORD is missing. Set DB_PASSWORD or pass -SqlConnectionString explicitly.'
        }
        $SqlConnectionString = "Server=$dbHost;Database=$dbName;User ID=$dbUser;Password=$dbPass;TrustServerCertificate=True;Encrypt=False;"
    }
    else {
        $SqlConnectionString = "Server=$dbHost;Database=$dbName;Integrated Security=True;TrustServerCertificate=True;Encrypt=False;"
    }
}

$SqlConnectionString = $SqlConnectionString.Trim()
if ($SqlConnectionString -notmatch '=') {
        throw @"
SqlConnectionString is invalid. Expected a full SQL connection string (key=value;...).
Do not pass only a DB name (example: PS_WebSite).

Examples:
    Server=127.0.0.1;Database=securetech;User ID=sa;Password=YOUR_PASSWORD;TrustServerCertificate=True;Encrypt=False;
    Server=127.0.0.1;Database=securetech;Integrated Security=True;TrustServerCertificate=True;Encrypt=False;
"@
}

$resolvedDatabaseName = $SqlDatabase
if ([string]::IsNullOrWhiteSpace($resolvedDatabaseName)) {
    $resolvedDatabaseName = Get-DatabaseNameFromConnectionString -ConnectionString $SqlConnectionString
}
if ([string]::IsNullOrWhiteSpace($resolvedDatabaseName)) {
    $resolvedDatabaseName = $env:DB_WEBSITE_NAME
}
if ([string]::IsNullOrWhiteSpace($resolvedDatabaseName)) {
    $resolvedDatabaseName = 'PS_WebSite'
}

if ($resolvedDatabaseName -notmatch '^[A-Za-z0-9_]+$') {
    throw "Resolved database name '$resolvedDatabaseName' is invalid. Use only letters, numbers, and underscore."
}

if ($PollLimit -lt 1) {
    $PollLimit = 1
}

if ($PollLimit -gt 30) {
    $PollLimit = 30
}

$existingWorkerSecret = $env:WORKER_HMAC_SECRET
if (-not [string]::IsNullOrWhiteSpace($existingWorkerSecret) -and $existingWorkerSecret -match '^[a-fA-F0-9]{64}$') {
    $hmacSecret = $existingWorkerSecret.Trim().ToLowerInvariant()
    Write-Host 'Using existing WORKER_HMAC_SECRET from environment.' -ForegroundColor Cyan
}
else {
    $hmacSecret = New-RandomSecret
    Write-Host 'Generated new hmac_secret (remember to update WORKER_HMAC_SECRET in Apache/PHP).' -ForegroundColor Yellow
}
$config = [ordered]@{
    api_url = $ApiUrl
    worker_id = $WorkerId
    hmac_secret = $hmacSecret
    poll_limit = $PollLimit
    sql_connection_string = $SqlConnectionString
}

$json = $config | ConvertTo-Json -Depth 4
$json = $json -replace '\\u0026', '&' -replace '\\u003c', '<' -replace '\\u003e', '>'
$targetDir = Split-Path -Parent $ConfigPath
if (-not [string]::IsNullOrWhiteSpace($targetDir) -and -not (Test-Path -LiteralPath $targetDir)) {
    New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
}

$logDir = Join-Path $PSScriptRoot 'logs'
if (-not (Test-Path -LiteralPath $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

Set-Content -LiteralPath $ConfigPath -Value $json -Encoding UTF8

$sqlWorkerId = Escape-SqlLiteral -Value $WorkerId
$sqlSecret = Escape-SqlLiteral -Value $hmacSecret
$sql = @"
UPDATE [$resolvedDatabaseName].dbo.Web_Settings
SET SettingValue = '$sqlSecret'
WHERE SettingKey = 'TaskSchedulerWorkerSecret';

IF @@ROWCOUNT = 0
INSERT INTO [$resolvedDatabaseName].dbo.Web_Settings (SettingKey, SettingValue)
VALUES ('TaskSchedulerWorkerSecret', '$sqlSecret');

UPDATE [$resolvedDatabaseName].dbo.Web_Settings
SET SettingValue = '$sqlWorkerId'
WHERE SettingKey = 'TaskSchedulerWorkerId';

IF @@ROWCOUNT = 0
INSERT INTO [$resolvedDatabaseName].dbo.Web_Settings (SettingKey, SettingValue)
VALUES ('TaskSchedulerWorkerId', '$sqlWorkerId');
"@

Set-Content -LiteralPath $SqlSyncPath -Value $sql -Encoding UTF8

Write-Host "Created config: $ConfigPath" -ForegroundColor Green
Write-Host "Created SQL sync: $SqlSyncPath" -ForegroundColor Green
Write-Host "Worker folder: $PSScriptRoot" -ForegroundColor Cyan
Write-Host "Generated hmac_secret:" -ForegroundColor Yellow
Write-Host $hmacSecret -ForegroundColor Yellow
Write-Host ''
Write-Host 'Keep worker.config.json next to worker.ps1 and install-worker.ps1.' -ForegroundColor Cyan
Write-Host 'Do not place these files in C:\xampp\htdocs.' -ForegroundColor Cyan
