#Requires -RunAsAdministrator
<#
.SYNOPSIS
    All-in-one setup for the Shaiya Task Scheduler Worker.
    Run ONCE after every server reinstall. No other steps needed.

.PARAMETER SqlConnectionString
    Full SQL connection string to PS_UserData DB.
    If omitted, auto-built from DB_HOST / DB_USER / DB_PASSWORD / DB_WEBSITE_NAME env vars (Machine level).
    Example: "Server=127.0.0.1;Database=PS_UserData;User ID=Ernoweb@;Password=YOURPASS;TrustServerCertificate=True;Encrypt=False;"

.PARAMETER WorkerSecret
    HMAC secret (64 hex chars). If omitted, reuses existing or generates new.

.PARAMETER WorkerId
    Worker identity string. Default: worker-main

.PARAMETER ApacheServiceName
    Windows service name for Apache. Default: Apache2.4

.PARAMETER PollMinutes
    Task Scheduler polling interval in minutes. Default: 1

.EXAMPLE
    # Zero params needed if DB_HOST/DB_USER/DB_PASSWORD are already set as system env vars:
    .\setup-worker-complete.ps1

.EXAMPLE
    .\setup-worker-complete.ps1 -SqlConnectionString "Server=127.0.0.1;Database=PS_UserData;User ID=Ernoweb@;Password=SECRET;TrustServerCertificate=True;Encrypt=False;"
#>
param(
    [string]$SqlConnectionString = '',
    [string]$WorkerSecret    = '',
    [string]$WorkerId        = 'worker-main',
    [string]$ApacheServiceName = 'Apache2.4',
    [int]$PollMinutes        = 1
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$PSScriptRootResolved = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($PSScriptRootResolved)) {
    $PSScriptRootResolved = Split-Path -Parent $MyInvocation.MyCommand.Path
}

$WorkerPs1      = Join-Path $PSScriptRootResolved 'worker.ps1'
$ConfigPath     = Join-Path $PSScriptRootResolved 'worker.config.json'
$LogDir         = Join-Path $PSScriptRootResolved 'logs'
$TaskName       = 'ShaiyaSchedulerWorker'
$ApiUrl         = 'http://127.0.0.1/task_scheduler_worker_api.php'

$stepOk   = '[OK]  '
$stepFail = '[FAIL]'
$stepSkip = '[SKIP]'
$errors   = @()

function Write-Step {
    param([string]$Icon, [string]$Msg, [string]$Color = 'White')
    Write-Host "$Icon $Msg" -ForegroundColor $Color
}

function New-RandomSecret {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    return -join ($bytes | ForEach-Object { $_.ToString('x2') })
}

function Invoke-SqlNonQuery {
    param([string]$ConnStr, [string]$Sql)
    $conn = New-Object System.Data.SqlClient.SqlConnection($ConnStr)
    $cmd  = $conn.CreateCommand()
    $cmd.CommandText = $Sql
    $cmd.CommandTimeout = 15
    try {
        $conn.Open()
        [void]$cmd.ExecuteNonQuery()
    }
    finally {
        $conn.Close()
        $conn.Dispose()
    }
}

function Test-SqlConnection {
    param([string]$ConnStr)
    $conn = New-Object System.Data.SqlClient.SqlConnection($ConnStr)
    $cmd  = $conn.CreateCommand()
    $cmd.CommandText = 'SELECT 1'
    $cmd.CommandTimeout = 10
    try {
        $conn.Open()
        [void]$cmd.ExecuteScalar()
        return $true
    }
    catch {
        return $false
    }
    finally {
        $conn.Close()
        $conn.Dispose()
    }
}

Write-Host ''
Write-Host '============================================================' -ForegroundColor Cyan
Write-Host '  Shaiya Scheduler Worker - Complete Setup' -ForegroundColor Cyan
Write-Host '============================================================' -ForegroundColor Cyan
Write-Host ''

# ----------------------------------------------------------------
# STEP 1 — Validate prerequisites
# ----------------------------------------------------------------
Write-Host '--- Step 1: Prerequisites ---' -ForegroundColor Yellow

if (-not (Test-Path -LiteralPath $WorkerPs1)) {
    Write-Step $stepFail "worker.ps1 not found at: $WorkerPs1" 'Red'
    exit 1
}
Write-Step $stepOk 'worker.ps1 found' 'Green'

if ($PollMinutes -lt 1) { $PollMinutes = 1 }

# ----------------------------------------------------------------
# STEP 2 — Resolve SQL connection string
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 2: SQL Connection ---' -ForegroundColor Yellow

$SqlConnectionString = $SqlConnectionString.Trim()

if ([string]::IsNullOrWhiteSpace($SqlConnectionString)) {
    $envUser = [System.Environment]::GetEnvironmentVariable('DB_USER', 'Machine')
    $envPass = [System.Environment]::GetEnvironmentVariable('DB_PASSWORD', 'Machine')

    if ([string]::IsNullOrWhiteSpace($envUser) -or [string]::IsNullOrWhiteSpace($envPass)) {
        Write-Step $stepFail "SqlConnectionString not provided and DB_USER/DB_PASSWORD env vars not set at Machine level." 'Red'
        Write-Host "  Either pass -SqlConnectionString explicitly, or set DB_USER and DB_PASSWORD as system env vars." -ForegroundColor Yellow
        exit 1
    }

    $SqlConnectionString = "Server=127.0.0.1;Database=PS_UserData;User ID=$envUser;Password=$envPass;TrustServerCertificate=True;Encrypt=False;"
    Write-Step $stepOk "Auto-built connection string from env vars (DB_USER=$envUser, Server=127.0.0.1, DB=PS_UserData)" 'Green'
}

if ($SqlConnectionString -notmatch '=') {
    Write-Step $stepFail "SqlConnectionString must be a full connection string (key=value;...), not just a DB name." 'Red'
    exit 1
}

if (Test-SqlConnection -ConnStr $SqlConnectionString) {
    Write-Step $stepOk 'SQL connection OK' 'Green'
} else {
    Write-Step $stepFail 'SQL connection FAILED. Check SqlConnectionString.' 'Red'
    exit 1
}

# ----------------------------------------------------------------
# STEP 3 — Resolve HMAC secret
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 3: HMAC Secret ---' -ForegroundColor Yellow

$resolvedSecret = $WorkerSecret.Trim().ToLowerInvariant()

if ($resolvedSecret -match '^[a-f0-9]{64}$') {
    Write-Step $stepOk "Using provided WorkerSecret: $($resolvedSecret.Substring(0,8))..." 'Green'
}
elseif (-not [string]::IsNullOrWhiteSpace($resolvedSecret)) {
    Write-Step $stepFail "WorkerSecret must be 64 hex characters. Got: '$resolvedSecret'" 'Red'
    exit 1
}
else {
    $existingEnv = [System.Environment]::GetEnvironmentVariable('WORKER_HMAC_SECRET', 'Machine')
    if (-not [string]::IsNullOrWhiteSpace($existingEnv) -and $existingEnv.Trim() -match '^[a-f0-9]{64}$') {
        $resolvedSecret = $existingEnv.Trim().ToLowerInvariant()
        Write-Step $stepOk "Reusing existing WORKER_HMAC_SECRET from system env: $($resolvedSecret.Substring(0,8))..." 'Green'
    }
    elseif (Test-Path -LiteralPath $ConfigPath) {
        try {
            $existingCfg = Get-Content -LiteralPath $ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
            $existingCfgSecret = ([string]$existingCfg.hmac_secret).Trim().ToLowerInvariant()
            if ($existingCfgSecret -match '^[a-f0-9]{64}$') {
                $resolvedSecret = $existingCfgSecret
                Write-Step $stepOk "Reusing secret from existing worker.config.json: $($resolvedSecret.Substring(0,8))..." 'Green'
            }
        } catch { }
    }

    if ([string]::IsNullOrWhiteSpace($resolvedSecret)) {
        $resolvedSecret = New-RandomSecret
        Write-Step $stepOk "Generated new secret: $($resolvedSecret.Substring(0,8))..." 'Yellow'
    }
}

# ----------------------------------------------------------------
# STEP 4 — Write worker.config.json
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 4: worker.config.json ---' -ForegroundColor Yellow

$cfgObj = [ordered]@{
    api_url               = $ApiUrl
    worker_id             = $WorkerId
    hmac_secret           = $resolvedSecret
    poll_limit            = 10
    sql_connection_string = $SqlConnectionString
}
$cfgJson = $cfgObj | ConvertTo-Json -Depth 4
$cfgJson = $cfgJson -replace '\\u0026','&' -replace '\\u003c','<' -replace '\\u003e','>'

try {
    if (-not (Test-Path -LiteralPath $LogDir)) {
        New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
    }
    Set-Content -LiteralPath $ConfigPath -Value $cfgJson -Encoding UTF8
    Write-Step $stepOk "Written: $ConfigPath" 'Green'
} catch {
    Write-Step $stepFail "Failed to write config: $($_.Exception.Message)" 'Red'
    $errors += 'config_write'
}

# ----------------------------------------------------------------
# STEP 5 — Sync secret to DB (Web_Settings)
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 5: Sync secret to DB ---' -ForegroundColor Yellow

$safeSecret   = $resolvedSecret.Replace("'", "''")
$safeWorkerId = $WorkerId.Replace("'", "''")

$syncSql = @"
UPDATE [PS_WebSite].dbo.Web_Settings SET SettingValue = '$safeSecret'  WHERE SettingKey = 'TaskSchedulerWorkerSecret';
IF @@ROWCOUNT = 0 INSERT INTO [PS_WebSite].dbo.Web_Settings (SettingKey, SettingValue) VALUES ('TaskSchedulerWorkerSecret', '$safeSecret');

UPDATE [PS_WebSite].dbo.Web_Settings SET SettingValue = '$safeWorkerId' WHERE SettingKey = 'TaskSchedulerWorkerId';
IF @@ROWCOUNT = 0 INSERT INTO [PS_WebSite].dbo.Web_Settings (SettingKey, SettingValue) VALUES ('TaskSchedulerWorkerId', '$safeWorkerId');

UPDATE [PS_WebSite].dbo.Web_Settings SET SettingValue = '1' WHERE SettingKey = 'TaskSchedulerEnabled';
IF @@ROWCOUNT = 0 INSERT INTO [PS_WebSite].dbo.Web_Settings (SettingKey, SettingValue) VALUES ('TaskSchedulerEnabled', '1');

UPDATE [PS_WebSite].dbo.Web_Settings SET SettingValue = 'queue_worker' WHERE SettingKey = 'TaskSchedulerMode';
IF @@ROWCOUNT = 0 INSERT INTO [PS_WebSite].dbo.Web_Settings (SettingKey, SettingValue) VALUES ('TaskSchedulerMode', 'queue_worker');
"@

try {
    Invoke-SqlNonQuery -ConnStr $SqlConnectionString -Sql $syncSql
    Write-Step $stepOk "DB Web_Settings updated (TaskSchedulerWorkerSecret, WorkerId, Enabled, Mode)" 'Green'
} catch {
    Write-Step $stepFail "DB sync failed: $($_.Exception.Message)" 'Red'
    $errors += 'db_sync'
}

# ----------------------------------------------------------------
# STEP 6 — Set WORKER_HMAC_SECRET as Windows system env var
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 6: Windows system env var (WORKER_HMAC_SECRET) ---' -ForegroundColor Yellow

try {
    [System.Environment]::SetEnvironmentVariable('WORKER_HMAC_SECRET', $resolvedSecret, 'Machine')
    Write-Step $stepOk 'WORKER_HMAC_SECRET set at Machine level (Apache will inherit on restart)' 'Green'
} catch {
    Write-Step $stepFail "Failed to set env var: $($_.Exception.Message)" 'Red'
    $errors += 'env_var'
}

# ----------------------------------------------------------------
# STEP 7 — Restart Apache to pick up new env var
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 7: Restart Apache ---' -ForegroundColor Yellow

$apacheSvc = Get-Service -Name $ApacheServiceName -ErrorAction SilentlyContinue
if ($null -eq $apacheSvc) {
    Write-Step $stepSkip "Service '$ApacheServiceName' not found. Restart Apache manually after setup." 'Yellow'
} else {
    try {
        Restart-Service -Name $ApacheServiceName -Force
        Write-Step $stepOk "Apache service '$ApacheServiceName' restarted" 'Green'
    } catch {
        Write-Step $stepFail "Apache restart failed: $($_.Exception.Message)" 'Red'
        $errors += 'apache_restart'
    }
}

# ----------------------------------------------------------------
# STEP 8 — Register/update Task Scheduler task
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 8: Windows Task Scheduler ---' -ForegroundColor Yellow

$workerCmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "{0}" -ConfigPath "{1}" -Once' -f $WorkerPs1, $ConfigPath
$schtasksArgs = @(
    '/Create',
    '/TN', $TaskName,
    '/TR', $workerCmd,
    '/SC', 'MINUTE',
    '/MO', $PollMinutes,
    '/RU', 'SYSTEM',
    '/RL', 'HIGHEST',
    '/F'
)

try {
    $result = & schtasks.exe @schtasksArgs 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "schtasks exited $LASTEXITCODE`: $result"
    }
    Write-Step $stepOk "Task '$TaskName' registered (every $PollMinutes min, SYSTEM)" 'Green'
} catch {
    Write-Step $stepFail "Task registration failed: $($_.Exception.Message)" 'Red'
    $errors += 'task_scheduler'
}

# ----------------------------------------------------------------
# STEP 9 — Initial run to verify heartbeat
# ----------------------------------------------------------------
Write-Host ''
Write-Host '--- Step 9: Test heartbeat ---' -ForegroundColor Yellow

Start-Sleep -Seconds 3

try {
    $runResult = & schtasks.exe /Run /TN $TaskName 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "schtasks /Run exited $LASTEXITCODE`: $runResult"
    }
    Write-Step $stepOk "Task triggered. Check logs\worker-error.log if issues arise." 'Green'
} catch {
    Write-Step $stepFail "Task trigger failed: $($_.Exception.Message)" 'Red'
    $errors += 'task_run'
}

# ----------------------------------------------------------------
# SUMMARY
# ----------------------------------------------------------------
Write-Host ''
Write-Host '============================================================' -ForegroundColor Cyan
if ($errors.Count -eq 0) {
    Write-Host '  SETUP COMPLETE - All steps passed!' -ForegroundColor Green
} else {
    Write-Host "  SETUP FINISHED WITH $($errors.Count) ERROR(S): $($errors -join ', ')" -ForegroundColor Red
}
Write-Host '============================================================' -ForegroundColor Cyan
Write-Host ''
Write-Host "  Worker ID  : $WorkerId" -ForegroundColor White
Write-Host "  Secret     : $($resolvedSecret.Substring(0,8))...$($resolvedSecret.Substring(56))" -ForegroundColor White
Write-Host "  Config     : $ConfigPath" -ForegroundColor White
Write-Host "  Task       : \$TaskName (every $PollMinutes min)" -ForegroundColor White
Write-Host "  Error log  : $LogDir\worker-error.log" -ForegroundColor White
Write-Host ''
