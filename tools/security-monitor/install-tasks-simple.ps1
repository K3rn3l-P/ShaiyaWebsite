# Install all security tasks manually

$TargetUrl = "https://duff.pinto-lime.ts.net"
$ZapProxyPort = 8095
$WebsiteRoot = "C:\xampp\htdocs"

Write-Host "Installing security audit tasks..."

# Task 1: Alert Monitor (every 5 min)
$monitorScript = "$WebsiteRoot\tools\security-monitor\security-alert-monitor.ps1"
$alertsLog = "$WebsiteRoot\config\logs\alerts.log"
$argList = "-NoProfile -ExecutionPolicy Bypass -File `"$monitorScript`" -AlertsLogPath `"$alertsLog`" -MinEventsInWindow 12 -WindowSeconds 600 -BlockMinutes 60"
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $argList
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 9999)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName "ShaiyaSecurityAlertMonitor" -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "OK - ShaiyaSecurityAlertMonitor (every 5 minutes)"

# Task 2: Web Audit (daily 03:15)
$webAuditScript = "$WebsiteRoot\tools\security-monitor\security-web-audit.ps1"
$argList = "-NoProfile -ExecutionPolicy Bypass -File `"$webAuditScript`" -TargetUrl $TargetUrl -ZapProxyPort $ZapProxyPort"
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $argList
$trigger = New-ScheduledTaskTrigger -Daily -At "03:15"
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 120)
Register-ScheduledTask -TaskName "ShaiyaSecurityWebAudit" -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "OK - ShaiyaSecurityWebAudit (daily at 03:15)"

# Task 3: Advanced Audit (daily 02:30)
$advAuditScript = "$WebsiteRoot\tools\security-monitor\security-audit-advanced.ps1"
$argList = "-NoProfile -ExecutionPolicy Bypass -File `"$advAuditScript`""
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $argList
$trigger = New-ScheduledTaskTrigger -Daily -At "02:30"
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
Register-ScheduledTask -TaskName "ShaiyaSecurityAdvancedAudit" -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "OK - ShaiyaSecurityAdvancedAudit (daily at 02:30)"

# Task 4: ZAP Audit (daily 03:30)
$zapAuditScript = "$WebsiteRoot\tools\security-monitor\security-zap-audit-automation.ps1"
$argList = "-NoProfile -ExecutionPolicy Bypass -File `"$zapAuditScript`" -TargetUrl $TargetUrl -ZapProxyPort $ZapProxyPort -EnableActiveScan -EnableAjaxSpider"
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $argList
$trigger = New-ScheduledTaskTrigger -Daily -At "03:30"
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 120)
Register-ScheduledTask -TaskName "ShaiyaSecurityZAPAudit" -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Write-Host "OK - ShaiyaSecurityZAPAudit (daily at 03:30)"

Write-Host ""
Write-Host "All tasks installed successfully!"
Write-Host ""
Write-Host "Scheduled tasks:"
Get-ScheduledTask -TaskName "ShaiyaSecurity*" | Select-Object TaskName, State
