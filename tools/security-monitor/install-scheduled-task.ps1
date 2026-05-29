param(
    [string]$TaskName = "ShaiyaSecurityAlertMonitor",
    [int]$IntervalMinutes = 5,
    [string]$WebsiteRoot = "",
    [bool]$ApplyFirewallBlock = $false,
    [switch]$InstallAllTasks,
    [string]$TargetUrl = "https://duff.pinto-lime.ts.net",
    [string]$WebAuditRunAt = "03:15",
    [string]$ZapAuditRunAt = "03:30",
    [int]$ZapProxyPort = 8095
)

# Auto-detect WebsiteRoot from the script's location (tools\security-monitor -> htdocs)
if (-not $WebsiteRoot) {
    $WebsiteRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
}

$ErrorActionPreference = 'Stop'

if ($IntervalMinutes -lt 2 -or $IntervalMinutes -gt 5) {
    throw "IntervalMinutes must be between 2 and 5."
}

$monitorScript = Join-Path $WebsiteRoot "tools\security-monitor\security-alert-monitor.ps1"
if (-not (Test-Path -LiteralPath $monitorScript)) {
    throw "Monitor script not found: $monitorScript"
}

$applyFlag = if ($ApplyFirewallBlock) { "-ApplyFirewallBlock" } else { "" }

$argList = @(
    "-NoProfile",
    "-ExecutionPolicy", "Bypass",
    "-File", ('"{0}"' -f $monitorScript),
    "-AlertsLogPath", ('"{0}"' -f (Join-Path $WebsiteRoot "config\logs\alerts.log")),
    "-MinEventsInWindow", "12",
    "-WindowSeconds", "600",
    "-BlockMinutes", "60",
    $applyFlag
) | Where-Object { $_ -ne "" }

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument ($argList -join ' ')
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes $IntervalMinutes) `
    -RepetitionDuration (New-TimeSpan -Days 9999)

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null

Write-Host "Scheduled task '$TaskName' registered."
Write-Host "Interval: every $IntervalMinutes minute(s)."
Write-Host "Monitor script: $monitorScript"
Write-Host "Apply firewall block: $ApplyFirewallBlock"

# If -InstallAllTasks flag, also install web audit and ZAP audit tasks
if ($InstallAllTasks) {
    Write-Host ""
    Write-Host "Installing additional security audit tasks..."
    
    # Web Audit Task (daily)
    $webAuditScript = Join-Path $WebsiteRoot "tools\security-monitor\security-web-audit.ps1"
    if (Test-Path -LiteralPath $webAuditScript) {
        $webAuditArgs = @(
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-File", ('"{0}"' -f $webAuditScript),
            "-TargetUrl", $TargetUrl,
            "-EnableZAP",
            "-ZapProxyPort", $ZapProxyPort.ToString()
        ) -join ' '
        
        $webAuditAction = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $webAuditArgs
        $webAuditTrigger = New-ScheduledTaskTrigger -Daily -At $WebAuditRunAt
        $webAuditSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 120)
        
        Register-ScheduledTask -TaskName "ShaiyaSecurityWebAudit" -Action $webAuditAction -Trigger $webAuditTrigger -Settings $webAuditSettings -Force | Out-Null
        Write-Host "✓ Web audit task registered (daily at $WebAuditRunAt)"
    } else {
        Write-Host "✗ Web audit script not found: $webAuditScript"
    }
    
    # ZAP Audit Task (daily, 15 minutes after web audit)
    $zapAuditScript = Join-Path $WebsiteRoot "tools\security-monitor\security-zap-audit-automation.ps1"
    if (Test-Path -LiteralPath $zapAuditScript) {
        $zapAuditArgs = @(
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-File", ('"{0}"' -f $zapAuditScript),
            "-TargetUrl", $TargetUrl,
            "-ZapProxyPort", $ZapProxyPort.ToString(),
            "-EnableActiveScan",
            "-EnableAjaxSpider"
        ) -join ' '
        
        $zapAuditAction = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $zapAuditArgs
        $zapAuditTrigger = New-ScheduledTaskTrigger -Daily -At $ZapAuditRunAt
        $zapAuditSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 120)
        
        Register-ScheduledTask -TaskName "ShaiyaSecurityZAPAudit" -Action $zapAuditAction -Trigger $zapAuditTrigger -Settings $zapAuditSettings -Force | Out-Null
        Write-Host "✓ ZAP audit task registered (daily at $ZapAuditRunAt)"
    } else {
        Write-Host "✗ ZAP audit script not found: $zapAuditScript"
    }
    
    # Advanced Security Audit Task (daily)
    $advAuditScript = Join-Path $WebsiteRoot "tools\security-monitor\security-audit-advanced.ps1"
    if (Test-Path -LiteralPath $advAuditScript) {
        $advAuditArgs = @(
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-File", ('"{0}"' -f $advAuditScript)
        ) -join ' '
        
        $advAuditAction = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $advAuditArgs
        $advAuditTrigger = New-ScheduledTaskTrigger -Daily -At "02:30"
        $advAuditSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
        
        Register-ScheduledTask -TaskName "ShaiyaSecurityAdvancedAudit" -Action $advAuditAction -Trigger $advAuditTrigger -Settings $advAuditSettings -Force | Out-Null
        Write-Host "✓ Advanced security audit task registered (daily at 02:30)"
    } else {
        Write-Host "✗ Advanced audit script not found: $advAuditScript"
    }
    
    Write-Host ""
    Write-Host "All security tasks installed successfully."
}
