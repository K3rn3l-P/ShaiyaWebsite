#!/usr/bin/env powershell
# Deploy all security audit tasks to remote server

param(
    [string]$RemoteHost = "administrator@10.10.5.91",
    [string]$SshKey = "C:\Users\lol\.ssh\id_ed25519",
    [string]$LocalRoot = "\\10.10.19.2\wd-p\Media\syncthing\Project-ServerShaiya\htdocs",
    [string]$TargetUrl = "https://duff.pinto-lime.ts.net",
    [int]$ZapProxyPort = 8095
)

$ErrorActionPreference = 'Stop'

Write-Host "[DEPLOY] Security Audit Tasks to Remote Server" -ForegroundColor Cyan
Write-Host "[DEPLOY] Target: $RemoteHost"
Write-Host "[DEPLOY] Target URL: $TargetUrl"
Write-Host ""

# Scripts to deploy
$scripts = @(
    "install-scheduled-task.ps1",
    "security-alert-monitor.ps1",
    "security-web-audit.ps1",
    "security-zap-audit-automation.ps1",
    "security-audit-advanced.ps1",
    "apply-apache-hardening.ps1"
)

$toolsDir = Join-Path $LocalRoot "tools\security-monitor"

# Step 1: Deploy scripts via SCP
Write-Host "[DEPLOY] Step 1: Deploying scripts via SCP..."
foreach ($script in $scripts) {
    $local = Join-Path $toolsDir $script
    if (-not (Test-Path $local)) {
        Write-Host "  x Not found: $script" -ForegroundColor Yellow
        continue
    }
    
    Write-Host "  -> Uploading $script..." -NoNewline
    $remoteDir = "C:/xampp/htdocs/tools/security-monitor"
    $remote = "$RemoteHost`:$remoteDir/$script"
    
    # Use SCP directly
    & scp -i $SshKey -o IdentitiesOnly=yes $local $remote 2>&1 | Out-Null
    Write-Host " OK" -ForegroundColor Green
}

# Step 2: Run Apache hardening
Write-Host ""
Write-Host "[DEPLOY] Step 2: Applying Apache hardening..."
Write-Host "  Running hardening..." -NoNewline
& ssh -i $SshKey -o IdentitiesOnly=yes $RemoteHost "powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\tools\security-monitor\apply-apache-hardening.ps1" 2>&1 | Out-Null
Write-Host " OK" -ForegroundColor Green

# Step 3: Install scheduled tasks
Write-Host ""
Write-Host "[DEPLOY] Step 3: Installing scheduled tasks..."
Write-Host "  Registering tasks..." -NoNewline
$taskResult = & ssh -i $SshKey -o IdentitiesOnly=yes $RemoteHost "powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\tools\security-monitor\install-scheduled-task.ps1 -InstallAllTasks -TargetUrl $TargetUrl -ZapProxyPort $ZapProxyPort" 2>&1
Write-Host " OK" -ForegroundColor Green
$taskResult | Select-Object -Last 5 | ForEach-Object { Write-Host "    $_" }

# Step 4: Verify tasks
Write-Host ""
Write-Host "[DEPLOY] Step 4: Verifying scheduled tasks..."
$tasks = & ssh -i $SshKey -o IdentitiesOnly=yes $RemoteHost "powershell -NoProfile -Command `"Get-ScheduledTask -TaskName 'ShaiyaSecurity*' | Select-Object TaskName, State`"" 2>&1
Write-Host $tasks

# Step 5: Quick audit test
Write-Host ""
Write-Host "[DEPLOY] Step 5: Running quick audit verification..."
Write-Host "  Running web audit..." -NoNewline
$auditResult = & ssh -i $SshKey -o IdentitiesOnly=yes $RemoteHost "powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\tools\security-monitor\security-web-audit.ps1 -TargetUrl $TargetUrl" 2>&1
$lines = $auditResult -split "`r`n" 
$lastLine = ($lines | Where-Object { $_.Trim() } | Select-Object -Last 1).Trim()
Write-Host " OK" -ForegroundColor Green
Write-Host "  Result: $lastLine"

Write-Host ""
Write-Host "[DEPLOY] OK Deployment complete!" -ForegroundColor Green
Write-Host ""
Write-Host "All security audit tasks are now running automatically:"
Write-Host "  - Security Alert Monitor - Every 5 minutes"
Write-Host "  - Advanced Audit - Daily at 02:30"
Write-Host "  - Web Audit - Daily at 03:15"
Write-Host "  - ZAP Audit - Daily at 03:30"
Write-Host ""
Write-Host "Reports saved to: C:\xampp\htdocs\config\logs\security_AUDIT_report.json"
