# Task Scheduler Worker (Windows)

Worker locale Windows per il modulo scheduler del pannello admin.

## Architettura

1. Il pannello inserisce azioni in `Web_TaskQueue`
2. `worker.ps1` legge la queue via API firmata HMAC+nonce (ogni minuto)
3. Il worker crea/aggiorna task Windows (`ShaiyaWebJob_<JobID>`) per i singoli job
4. Esito inviato alla web app e loggato su `Web_TaskJobRuns`

## Setup (dopo ogni reinstall server)

**Due comandi, nient'altro.**

### 1. SQL — esegui una volta su SQL Server

```sql
-- In SQL Server Management Studio, apri e lancia:
-- sql/create_web_tables.sql
-- Crea tutte le tabelle incluse Web_TaskJobs, Web_TaskQueue, Web_TaskJobRuns
```

### 2. PowerShell — esegui come Administrator sul server

Se `DB_USER` e `DB_PASSWORD` sono già impostati come env var di sistema (Apache già configurato) — host e DB usano i default `127.0.0.1` / `PS_WebSite`:

```powershell
.\setup-worker-complete.ps1
```

Altrimenti passa la connection string esplicitamente:

```powershell
.\setup-worker-complete.ps1 -SqlConnectionString "Server=127.0.0.1;Database=PS_WebSite;User ID=Ernoweb@;Password=YOURPASS;TrustServerCertificate=True;Encrypt=False;"
```

Lo script fa tutto in automatico:

- Testa connessione SQL
- Riusa il secret esistente (da env var o config) oppure ne genera uno nuovo
- Scrive `worker.config.json`
- Sincronizza il secret su `Web_Settings` nel DB
- Imposta `WORKER_HMAC_SECRET` come variabile d'ambiente di sistema Windows
- Riavvia Apache (per caricare la nuova variabile — niente più warning `WORKER_HMAC_SECRET is missing`)
- Registra il task `ShaiyaSchedulerWorker` nel Windows Task Scheduler (ogni 1 min, SYSTEM)
- Lancia un test heartbeat

Output atteso:

```
[OK]  worker.ps1 found
[OK]  SQL connection OK
[OK]  Reusing existing WORKER_HMAC_SECRET from system env: d433ed05...
[OK]  Written: ...\worker.config.json
[OK]  DB Web_Settings updated
[OK]  WORKER_HMAC_SECRET set at Machine level
[OK]  Apache service 'Apache2.4' restarted
[OK]  Task 'ShaiyaSchedulerWorker' registered (every 1 min, SYSTEM)
[OK]  Task triggered.
```

## File nella cartella

| File | Scopo |
|---|---|
| `setup-worker-complete.ps1` | **Setup all-in-one — usa questo** |
| `worker.ps1` | Worker principale (lanciato dal task scheduler) |
| `worker.config.json` | Config runtime (generata dallo script) |
| `worker.config.sample.json` | Template di riferimento |
| `generate-worker-config.ps1` | Legacy — rigenera solo il config |
| `install-worker.ps1` | Legacy — registra solo il task scheduler |
| `worker-config-sync.sql` | SQL generato da generate-worker-config.ps1 |
| `logs/worker-error.log` | Log errori worker (creato automaticamente) |

## Modalità UX (pannello admin)

- **Global Module Status** — abilita/disabilita la creazione di job (`TaskSchedulerEnabled` in `Web_Settings`)
- **Run Now** — invia `RUN_NOW` in queue; il worker esegue al ciclo successivo
- **Quick Command Job** — form semplificata: Job Name, Schedule, Service, Command, Second Command opzionale
- **Advanced Job** — form completa con `SERVICE_COMMAND` e `SQL_SCRIPT` (inline o upload `.sql`)

### Schedule disponibili

| Valore | Intervallo |
|---|---|
| `every_10m` | ogni 10 minuti |
| `every_15m` | ogni 15 minuti |
| `every_30m` | ogni 30 minuti |
| `hourly` | ogni ora |
| `daily_00_00` | ogni giorno alle 00:00 |
| `daily_03_00` | ogni giorno alle 03:00 |
| `daily_06_00` | ogni giorno alle 06:00 |
| `daily_12_00` | ogni giorno alle 12:00 |
| `daily_18_00` | ogni giorno alle 18:00 |

### Simple Meta (risoluzione dinamica valore)

Quick mode con `SERVICE_COMMAND = /exp2xenable` e selettori DB risolve il valore dal DB al momento dell'esecuzione:
- Se la tabella ha colonna `NameDay`, usa il valore del giorno corrente (priorità)
- Altrimenti usa la colonna con filtro/operatore configurato

Operatori supportati: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `contains`

Placeholder in Second Command: `{exp}`, `{value}`, `{day}`

Esempio: `/nt Giocatore, oggi è {day}. EXP: x{exp}. Buon gioco!`

## Verifica operativa rapida (post-install)

1. Admin panel → Task Scheduler → Global Module Status = ON
2. Crea job `SERVICE_COMMAND` → Run Now
3. Entro il minuto successivo verifica:
   - `Web_TaskQueue`: item `RUN_NOW` → `done`
   - `Web_TaskJobRuns`: nuova riga con esito
   - Log game service: comando ricevuto

### Test esecuzione diretta (senza queue)

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass `
  -File "C:\xampp\htdocs\tools\task-scheduler-worker\worker.ps1" `
  -ConfigPath "C:\xampp\htdocs\tools\task-scheduler-worker\worker.config.json" `
  -RunJobId 1 -Once
```

Se non esegue, controlla `logs\worker-error.log`.

## Sicurezza

- API `task_scheduler_worker_api.php` — solo localhost + firma HMAC-SHA256 + nonce anti-replay
- `TaskSchedulerCommandWhitelist` in `Web_Settings` — whitelist comandi SERVICE_COMMAND
- `TaskSchedulerAllowedAdminUIDs` — RBAC admin per scheduler
- `WORKER_HMAC_SECRET` come env var di sistema elimina il fallback DB e il warning in `security.log`

## Troubleshooting

| Sintomo | Causa | Fix |
|---|---|---|
| `WORKER_HMAC_SECRET is missing` in security.log | Env var non impostata | Esegui `setup-worker-complete.ps1` |
| `signature_mismatch` in security.log | Secret DB ≠ config.json | Esegui `setup-worker-complete.ps1` — risincronizza entrambi |
| Queue items rimangono `pending` | Task scheduler fermo | `schtasks /Run /TN ShaiyaSchedulerWorker` |
| `worker-error.log` con errore SQL | Connection string errata | Verifica `worker.config.json` → `sql_connection_string` |
