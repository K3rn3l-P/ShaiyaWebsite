param(
    [string]$ConfigPath = "$PSScriptRoot\worker.config.json",
    [string]$WorkerTaskName = 'ShaiyaSchedulerWorker',
    [int]$PollMinutes = 1
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $ConfigPath)) {
    throw "Config file not found: $ConfigPath`nCreate it from worker.config.sample.json first."
}

$configRaw = Get-Content -LiteralPath $ConfigPath -Raw -Encoding UTF8
$config = $configRaw | ConvertFrom-Json
if (-not $config.sql_connection_string -or [string]::IsNullOrWhiteSpace([string]$config.sql_connection_string)) {
    throw 'worker.config.json is missing sql_connection_string.'
}

$connString = ([string]$config.sql_connection_string).Trim()
if ($connString -notmatch '=') {
    throw @"
Invalid sql_connection_string in worker.config.json.
Expected a full SQL connection string (key=value;...), but got: $connString

Example:
Server=127.0.0.1;Database=securetech;Integrated Security=True;TrustServerCertificate=True;Encrypt=False;
"@
}

if ($PollMinutes -lt 1) {
    throw 'PollMinutes must be >= 1'
}

$workerScript = Join-Path $PSScriptRoot 'worker.ps1'
if (-not (Test-Path -LiteralPath $workerScript)) {
    throw "worker.ps1 not found in $PSScriptRoot"
}

$logDir = Join-Path $PSScriptRoot 'logs'
if (-not (Test-Path -LiteralPath $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

$workerCommand = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "{0}" -ConfigPath "{1}" -Once' -f $workerScript, $ConfigPath
$createArgs = @(
    '/Create',
    '/TN', $WorkerTaskName,
    '/TR', $workerCommand,
    '/SC', 'MINUTE',
    '/MO', $PollMinutes,
    '/RU', 'SYSTEM',
    '/RL', 'HIGHEST',
    '/F'
)

& schtasks.exe @createArgs | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Failed to register the scheduled task with schtasks.exe.'
}

& schtasks.exe /Run /TN $WorkerTaskName | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Scheduled task was created, but it could not be started.'
}

Write-Host "Worker task installed: $WorkerTaskName" -ForegroundColor Green
Write-Host "Polling every $PollMinutes minute(s)."
Write-Host "Ensure Apache/PHP has WORKER_HMAC_SECRET set equal to hmac_secret in worker.config.json"
Write-Host "Ensure sql_connection_string in worker.config.json is a full SQL connection string (not only DB name)."
Write-Host "If you changed hmac_secret, restart Apache/PHP before testing queue execution."
