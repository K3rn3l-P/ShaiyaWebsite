# Security Task Scheduler Setup

Automated security audits for Shaiya Web Portal via Windows Task Scheduler.

## Overview

Three security audit scripts run automatically on the server:

1. **Security Alert Monitor** - Every 5 minutes (real-time alerts)
2. **Security Advanced Audit** - Daily at 02:30 (codebase analysis)
3. **Security Web Audit + ZAP** - Daily at 03:15 (live endpoint security)
4. **Security ZAP Audit** - Daily at 03:30 (OWASP ZAP automation scan)

## Installation on Remote Server

### Step 1: Deploy Scripts via SCP

```powershell
# From local machine
$scripts = @(
    "install-scheduled-task.ps1",
    "security-alert-monitor.ps1",
    "security-web-audit.ps1",
    "security-zap-audit-automation.ps1",
    "security-audit-advanced.ps1"
)

foreach ($script in $scripts) {
    $local = "\\10.10.19.2\wd-p\Media\syncthing\Project-ServerShaiya\htdocs\tools\security-monitor\$script"
    $remote = "administrator@10.10.5.91:C:/xampp/htdocs/tools/security-monitor/$script"
    scp -i C:\Users\lol\.ssh\id_ed25519 -o IdentitiesOnly=yes $local $remote
}
```

### Step 2: Run Installation on Server

```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes administrator@10.10.5.91 \
  "powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\tools\security-monitor\install-scheduled-task.ps1 \
    -InstallAllTasks \
    -TargetUrl https://duff.pinto-lime.ts.net \
    -ZapProxyPort 8095"
```

This will create/register the following scheduled tasks:

- `ShaiyaSecurityAlertMonitor` - Every 5 minutes
- `ShaiyaSecurityAdvancedAudit` - Daily at 02:30
- `ShaiyaSecurityWebAudit` - Daily at 03:15 (includes ZAP baseline)
- `ShaiyaSecurityZAPAudit` - Daily at 03:30 (includes active scan + AJAX spider)

### Step 3: Verify Tasks

```bash
ssh -i ~/.ssh/id_ed25519 -o IdentitiesOnly=yes administrator@10.10.5.91 \
  "powershell -NoProfile -Command { Get-ScheduledTask -TaskName 'ShaiyaSecurity*' | Select-Object TaskName, State }"
```

Expected output:
```
TaskName                            State
--------                            -----
ShaiyaSecurityAlertMonitor         Ready
ShaiyaSecurityAdvancedAudit        Ready
ShaiyaSecurityWebAudit             Ready
ShaiyaSecurityZAPAudit             Ready
```

## Reports and Logs

All audit reports are saved to `C:\xampp\htdocs\config\logs\`:

- `security_audit_report.json` - Advanced codebase audit
- `security_web_audit_report.json` - Web endpoint audit
- `security_zap_audit_report.json` - ZAP scan results
- `alerts.log` - Real-time security alerts (5-min intervals)

## Manual Execution

Run any audit manually without waiting for scheduler:

```bash
# Web audit only
ssh administrator@10.10.5.91 "powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\tools\security-monitor\security-web-audit.ps1 -TargetUrl https://duff.pinto-lime.ts.net"

# ZAP audit with active scan
ssh administrator@10.10.5.91 "powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\tools\security-monitor\security-zap-audit-automation.ps1 -TargetUrl https://duff.pinto-lime.ts.net -EnableActiveScan -EnableAjaxSpider"

# Advanced codebase audit
ssh administrator@10.10.5.91 "powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\tools\security-monitor\security-audit-advanced.ps1"
```

## Configuration

Edit the following before running installation:

| Parameter | Default | Purpose |
|-----------|---------|---------|
| `-TargetUrl` | `https://duff.pinto-lime.ts.net` | Target domain for web/ZAP audits |
| `-WebAuditRunAt` | `03:15` | Daily web audit start time |
| `-ZapAuditRunAt` | `03:30` | Daily ZAP audit start time |
| `-ZapProxyPort` | `8095` | Proxy port for ZAP (avoid conflicts) |
| `-IntervalMinutes` | `5` | Alert monitor interval (2-5 minutes only) |

## Scheduling Timeline

```
02:30 - Security Advanced Audit (codebase analysis)
03:15 - Security Web Audit (endpoint headers, methods, TLS)
03:30 - ZAP Audit (passive + active + AJAX spider)

Every 5 min - Security Alert Monitor (rate limit, auth failures, anomalies)
```

## Troubleshooting

### Tasks not running?

1. Check PowerShell execution policy:
   ```bash
   ssh administrator@10.10.5.91 "powershell -Command Get-ExecutionPolicy"
   ```
   
   If not `Bypass` or `Unrestricted`, run:
   ```bash
   ssh administrator@10.10.5.91 "powershell -Command Set-ExecutionPolicy -ExecutionPolicy Bypass -Force"
   ```

2. Check Task Scheduler logs:
   ```bash
   ssh administrator@10.10.5.91 "Get-ScheduledTaskInfo -TaskName ShaiyaSecurityWebAudit"
   ```

3. Check report files exist:
   ```bash
   ssh administrator@10.10.5.91 "dir C:\xampp\htdocs\config\logs\security_*_report.*"
   ```

### Reports are old?

Check if Apache/site is running:
```bash
ssh administrator@10.10.5.91 "curl -k https://duff.pinto-lime.ts.net/"
```

If not responding, restart Apache:
```bash
ssh administrator@10.10.5.91 "powershell -Command Restart-Service Apache2.4 -Force"
```

## Next Steps

1. Deploy this setup to remote server
2. Monitor first run outputs in `config/logs/`
3. Adjust scheduling times if needed
4. Set up external reporting/alerting if needed

---

**Updated**: 2026-05-29
**Status**: ✓ Ready for deployment
