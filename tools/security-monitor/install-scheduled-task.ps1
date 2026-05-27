param(
    [string]$TaskName = "ShaiyaSecurityAlertMonitor",
    [int]$IntervalMinutes = 5,
    [string]$WebsiteRoot = "\\win-acmn7b375u8\htdocs",
    [bool]$ApplyFirewallBlock = $false
)

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
    -RepetitionDuration ([TimeSpan]::MaxValue)

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
