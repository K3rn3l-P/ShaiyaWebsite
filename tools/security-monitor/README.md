# Security Monitor Tools

Questa cartella contiene gli script PowerShell operativi per monitoraggio, audit avanzato e autofix sicuro della codebase attiva root `htdocs`.

Scope operativo: la cartella legacy `htdocs/htdocs` è esclusa dagli audit e non deve essere usata come baseline.

## Script

- `security-alert-monitor.ps1`
- `security-audit-advanced.ps1`
- `security-autofix-safe.ps1`
- `install-scheduled-task.ps1`
- `security-alert-monitor-task.xml`

## Usage

### Audit avanzato

```powershell
# Audit standard con report JSON
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-audit-advanced.ps1

# Audit con exit code non-zero se esistono finding HIGH
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-audit-advanced.ps1 -FailOnHigh
```

Report predefinito: `config/logs/security_audit_report.json`

Controlli inclusi:

- presenza `.htaccess` su root e cartelle sensibili
- hardening `uploads/.htaccess`
- scan PHP active-root-only per pattern legacy o rischiosi
- verifica bootstrap sicurezza, headers, session e CSRF sulle root page

### Autofix safe

```powershell
# Dry-run: pianifica solo i fix safe mancanti
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-autofix-safe.ps1

# Applica solo create safe e non distruttive
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-autofix-safe.ps1 -Apply
```

Report predefinito: `config/logs/security_autofix_report.json`

Regole autofix correnti:

- crea `.htaccess` baseline mancanti in `config/`, `tools/`, `sql/`, `inc/`, `uploads/`
- crea root `.htaccess` baseline solo se assente
- non sovrascrive file già esistenti
- usa dry-run di default

Workflow consigliato:

1. Eseguire `security-audit-advanced.ps1`
2. Se emergono missing baseline compatibili, eseguire `security-autofix-safe.ps1` in dry-run
3. Applicare con `-Apply` solo i fix safe approvati
4. Rieseguire `security-audit-advanced.ps1` per conferma

### Monitor alert runtime

```powershell
# Report-only (default WhatIf)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-alert-monitor.ps1

# Report-only with custom thresholds
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-alert-monitor.ps1 -MinEventsInWindow 10 -WindowSeconds 600

# Enforce firewall blocks (remove WhatIf explicitly)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-alert-monitor.ps1 -ApplyFirewallBlock $true
```

## Notes

- The app writes `alerts.log` automatically when suspicious events exceed threshold in `security_bootstrap.php`.
- Firewall blocking is optional and disabled by default.
- Recommended: schedule script every 2-5 minutes via Task Scheduler with least privilege required for your policy.
- `security-audit-advanced.ps1` e `security-autofix-safe.ps1` sono pensati per uso manuale o CI locale a fine implementazione/fix.
- Gli autofix sono volutamente conservativi: niente overwrite, niente refactor automatici, niente modifiche distruttive.

## Windows Task Scheduler (every 2-5 minutes)

```powershell
# Register task every 5 minutes (safe default, report-only)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-scheduled-task.ps1 -IntervalMinutes 5

# Register task every 2 minutes
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-scheduled-task.ps1 -IntervalMinutes 2

# Enable real firewall blocks (not dry-run)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-scheduled-task.ps1 -IntervalMinutes 5 -ApplyFirewallBlock $true
```

Alternative import via XML:

```powershell
schtasks /Create /TN "ShaiyaSecurityAlertMonitor" /XML "tools\security-monitor\security-alert-monitor-task.xml" /F
```
