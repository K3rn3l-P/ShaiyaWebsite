param(
    [string]$TaskName = 'ShaiyaSecurityWebAudit',
    [Parameter(Mandatory = $true)]
    [string]$TargetUrl,
    [string]$WebsiteRoot = '',
    [string]$RunAt = '03:17',
    [bool]$EnableZAP = $false,
    [bool]$EnableNikto = $false
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not $WebsiteRoot) {
    $WebsiteRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
}

$auditScript = Join-Path $WebsiteRoot 'tools\security-monitor\security-web-audit.ps1'
if (-not (Test-Path -LiteralPath $auditScript)) {
    throw "Web audit script not found: $auditScript"
}

$reportJson = Join-Path $WebsiteRoot 'config\logs\security_web_audit_report.json'
$reportMd = Join-Path $WebsiteRoot 'config\logs\security_web_audit_report.md'

try {
    $triggerAt = [DateTime]::Today.Add([TimeSpan]::Parse($RunAt))
} catch {
    throw 'RunAt must be in HH:mm format, for example 03:17.'
}

$argList = @(
    '-NoProfile',
    '-ExecutionPolicy', 'Bypass',
    '-File', ('"{0}"' -f $auditScript),
    '-TargetUrl', ('"{0}"' -f $TargetUrl),
    '-ReportPath', ('"{0}"' -f $reportJson),
    '-MarkdownPath', ('"{0}"' -f $reportMd)
)

if ($EnableZAP) {
    $argList += '-EnableZAP'
}
if ($EnableNikto) {
    $argList += '-EnableNikto'
}

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ($argList -join ' ')
$trigger = New-ScheduledTaskTrigger -Daily -At $triggerAt
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 45)

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null

Write-Host "Scheduled task '$TaskName' registered."
Write-Host "Run time: daily at $RunAt"
Write-Host "Audit script: $auditScript"
Write-Host "Target URL: $TargetUrl"
Write-Host "Report JSON: $reportJson"
Write-Host "Report Markdown: $reportMd"
Write-Host "Enable ZAP: $EnableZAP"
Write-Host "Enable Nikto: $EnableNikto"