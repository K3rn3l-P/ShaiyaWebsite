# Security Monitor Tools

Questa cartella contiene gli script PowerShell operativi per monitoraggio, audit avanzato e autofix sicuro della codebase attiva root `htdocs`.

Scope operativo: la cartella legacy `htdocs/htdocs` è esclusa dagli audit e non deve essere usata come baseline.

## Script

- `security-alert-monitor.ps1`
- `security-audit-advanced.ps1`
- `security-autofix-safe.ps1`
- `security-web-audit.ps1`
- `install-scheduled-task.ps1`
- `install-web-audit-task.ps1`
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

### Web audit schedulabile (HTTP/TLS/headers/cookies/esposizioni)

```powershell
# Audit web safe/passive con report JSON + Markdown
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-web-audit.ps1 -TargetUrl "https://example.com"

# Audit con exit code non-zero se esistono finding HIGH
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-web-audit.ps1 -TargetUrl "https://example.com" -FailOnHigh

# Audit arricchito con ZAP Automation Framework locale
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-web-audit.ps1 -TargetUrl "https://example.com" -EnableZAP

# Runner ZAP standalone con scan passiva (porta proxy scelta automaticamente)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-zap-audit-automation.ps1 -TargetUrl "https://example.com"

# Runner ZAP con host/porta proxy espliciti
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-zap-audit-automation.ps1 -TargetUrl "https://example.com" -ZapProxyHost "127.0.0.1" -ZapProxyPort 8091

# Runner ZAP con active scan e retention personalizzata (mantieni ultime 3 run, max 14 giorni)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-zap-audit-automation.ps1 -TargetUrl "https://example.com" -EnableActiveScan -KeepLatestRuns 3 -MaxRunAgeDays 14

# Runner ZAP con finding informational inclusi nel report
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-zap-audit-automation.ps1 -TargetUrl "https://example.com" -IncludeInformational

# Audit arricchito con Nikto (se Nikto e' installato nel server)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-web-audit.ps1 -TargetUrl "https://example.com" -EnableNikto
```

Report predefiniti:

- `config/logs/security_web_audit_report.json`
- `config/logs/security_web_audit_report.md`

Controlli inclusi:

- presenza header di sicurezza su `/`, `/admin`, `/account`
- cookie flags (`HttpOnly`, `Secure`, `SameSite`)
- banner leakage (`Server`, `X-Powered-By`)
- redirect HTTP -> HTTPS
- metodi HTTP rischiosi esposti via `Allow`
- esposizione di path sensibili (`/.git/HEAD`, `/.env`, `/server-status`, `/config/`, `/tools/`, ecc.)
- scadenza certificato TLS
- integrazione opzionale con OWASP ZAP Automation Framework e Nikto se disponibili lato server

Note operative ZAP:

- `security-zap-audit-automation.ps1` usa ZAP Automation Framework locale, senza dipendere da Docker.
- il runner crea una run directory dedicata sotto `config/zap-home/security-zap-audit/` con home ZAP isolata per ogni esecuzione.
- **selezione porta dinamica**: se la porta proxy richiesta (`-ZapProxyPort`, default `8091`) è già in uso, il runner rileva automaticamente la prima porta libera nell'intervallo `+1..+50`. La porta effettivamente usata è salvata nel report JSON.
- **retention automatica**: al termine di ogni esecuzione vengono eliminate le run directory eccedenti `-KeepLatestRuns` (default 5) e quelle più vecchie di `-MaxRunAgeDays` giorni (default 7). Il cleanup avviene dopo la produzione del report, così l'ultima run è sempre integra.
- per default vengono importati finding `HIGH`, `MEDIUM`, `LOW`; gli `INFORMATIONAL` restano esclusi salvo `-IncludeInformational`.
- con `-EnableActiveScan` il runner separa scan `passive` e `active`, limitando l'active ai path critici dichiarati nello script.
- il runner fallback legacy (`security-zap-audit.ps1`) usa home e porta dedicata (`config\zap-home\security-zap-audit-legacy`, porta `8092`) per evitare conflitti con il runner primario.
- artefatti runtime ZAP (`config/zap-home/**`) sono ignorati via `.gitignore` (non tracciati in repo).

Nota operativa firewall: sul server live e' presente anche Simplewall. Se un audit ZAP/Nikto o un check verso il sito locale fallisce senza un errore applicativo chiaro, verificare prima la policy firewall del server e le eventuali eccezioni per loopback, Apache e le connessioni outbound richieste dallo scanner.

## Notes

- The app writes `alerts.log` automatically when suspicious events exceed threshold in `security_bootstrap.php`.
- Firewall blocking is optional and disabled by default.
- Recommended: schedule script every 2-5 minutes via Task Scheduler with least privilege required for your policy.
- `security-audit-advanced.ps1` e `security-autofix-safe.ps1` sono pensati per uso manuale o CI locale a fine implementazione/fix.
- `security-web-audit.ps1` e' pensato per scheduling giornaliero o post-deploy: e' un audit HTTP/TLS low-risk, adatto anche a produzione se usato senza scanner esterni aggressivi.
- validazione live confermata su `C:\xampp\htdocs`: runner ZAP standalone (passive) completato correttamente con porta proxy `8092` (fallback automatico da `8091` in uso).
- `security-web-audit.ps1 -EnableZAP`: completato correttamente con integrazione runner automation.
- `security-audit-advanced.ps1`: `High: 0 | Medium: 0 | Low: 15 | Total: 15`.
- Gli autofix sono volutamente conservativi: niente overwrite, niente refactor automatici, niente modifiche distruttive.

## Windows Task Scheduler (every 2-5 minutes)

```powershell
# Register task every 5 minutes (safe default, report-only)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-scheduled-task.ps1 -IntervalMinutes 5

# Register task every 2 minutes
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-scheduled-task.ps1 -IntervalMinutes 2

# Enable real firewall blocks (not dry-run)
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-scheduled-task.ps1 -IntervalMinutes 5 -ApplyFirewallBlock $true

# Register daily web audit at 03:17
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-web-audit-task.ps1 -TargetUrl "https://example.com" -RunAt "03:17"

# Register daily web audit with optional external scanners when available
powershell -ExecutionPolicy Bypass -File tools/security-monitor/install-web-audit-task.ps1 -TargetUrl "https://example.com" -RunAt "03:17" -EnableZAP $true -EnableNikto $true
```

Alternative import via XML:

```powershell
schtasks /Create /TN "ShaiyaSecurityAlertMonitor" /XML "tools\security-monitor\security-alert-monitor-task.xml" /F
```
