# Task Scheduler Worker (Windows)

Worker locale Windows per il modulo scheduler del pannello admin. Il task principale del worker è il poller da un minuto; i trigger dei singoli job vengono creati localmente sullo stesso server Windows.

## Flusso

1. Il pannello inserisce azioni in `Web_TaskQueue`
2. `worker.ps1` legge queue via API firmata HMAC+nonce
3. Il worker crea/aggiorna task Windows (`ShaiyaWebJob_<JobID>`)
4. Il poller locale riattiva il worker ogni minuto, mentre i task dei singoli job hanno il loro trigger Windows configurato sul worker
5. Esito inviato alla web app e loggato su `Web_TaskJobRuns`

## Prerequisiti

- PowerShell 5.1+
- Permessi amministratore locale (creazione Scheduled Tasks)
- `WORKER_HMAC_SECRET` configurato in ambiente Apache/PHP uguale a `hmac_secret`
- `DB_WEBSITE_NAME` allineato al database che contiene `Web_Settings`/scheduler tables (default: `PS_WebSite`)

## Setup rapido

1. Copia `worker.config.sample.json` in `worker.config.json`
2. Aggiorna campi (`api_url`, `worker_id`, `hmac_secret`, `sql_connection_string`)
	 - `sql_connection_string` deve essere una connessione SQL completa (`key=value;...`), non solo nome DB.
	 - Se nel JSON trovi `\u0026`, rappresenta il carattere `&` (non è una password corrotta).
	 - Esempi validi:
		 - `Server=127.0.0.1;Database=securetech;Integrated Security=True;TrustServerCertificate=True;Encrypt=False;`
		 - `Server=127.0.0.1;Database=securetech;User ID=sa;Password=YOUR_PASSWORD;TrustServerCertificate=True;Encrypt=False;`
	 - Esempio non valido: `PS_WebSite`
	- Se usi `generate-worker-config.ps1` senza `-SqlConnectionString`, lo script usa automaticamente le variabili ambiente già presenti (`DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_WEBSITE_NAME`).
	- Lo script genera anche `worker-config-sync.sql` con upsert automatico di `TaskSchedulerWorkerSecret` e `TaskSchedulerWorkerId` in `Web_Settings`.
	- Se `WORKER_HMAC_SECRET` è già presente nell'ambiente, `generate-worker-config.ps1` lo riusa automaticamente per evitare mismatch firma.
	- Se `WORKER_HMAC_SECRET` non è presente, viene generato un nuovo `hmac_secret`: in quel caso aggiorna Apache/PHP e riavvia prima dei test.
3. Esegui `install-worker.ps1` come amministratore: registra direttamente il task Windows con polling a intervalli di minuti tramite `schtasks.exe`; se in Task Scheduler vedi un `TimeTrigger` con intervallo `PT1M`, il poller e' installato correttamente
	 - Lo script crea automaticamente la cartella `logs` accanto a `worker.ps1`.

### Sync SQL consigliato (dopo ogni rigenerazione config)

1. Esegui `generate-worker-config.ps1`.
2. Esegui in SQL Server il file generato `worker-config-sync.sql`.
3. Riavvia Apache/PHP (se necessario) e testa il worker.

## Modalità UX (Task Scheduler)

- `Global Module Status`: aggiorna il flag `TaskSchedulerEnabled` su `Web_Settings` del database sito configurato (`DB_WEBSITE_NAME`) e abilita/disabilita solo creazione/queueing dal pannello.
- `Run Now`: invia la richiesta in queue immediatamente; al ciclo successivo il worker esegue direttamente il job (senza attendere il trigger orario del task `ShaiyaWebJob_<JobID>`).
- `Quick Command Job`: form semplificata (`Job Name`, `Windows Schedule`, `Service`, `Service Command`, `Second Command` opzionale) con selettori `Database/Table/Column` validati su metadata reali.
- `Advanced Job`: form completa con `SERVICE_COMMAND` e `SQL_SCRIPT` (inline o upload `.sql`).
- Le schedule quick usano intervalli Windows-friendly (`every_10m`, `every_15m`, `every_30m`, `hourly`, `daily_00_00`, `daily_03_00`, `daily_06_00`, `daily_12_00`, `daily_18_00`).
- La lista `Database` in UX è dinamica: mostra i database online realmente accessibili dall'utente SQL del sito (non più una lista fissa a 3 voci).
- Esempi `Comparison` (quick mode):
	- `PS_GameDefs` + `EXPTable` + `EXP` + `>=` + `100`
	- `PS_GameDefs` + `EXPTable` + `NameDay` + `contains` + `Monday`
- Esempio operativo verificato:
	- I job `SERVICE_COMMAND` usano `PS_GameDefs.dbo.Command`.
	- Quick mode con `Service Command = /exp2xenable` e selettori DB su `PS_GameDefs.EXPTable.EXP` risolve automaticamente il valore del giorno corrente (preferenza `NameDay = DATENAME(dw, GETDATE())`).
	- `Second Command` opzionale viene eseguito subito dopo il comando principale (es. `/nt ...`).
	- Placeholder supportati nel secondo comando: `{exp}`, `{value}`, `{day}`.
	- Esempio: `/nt Player, today is {day}. EXP: x{exp}. Enjoy the game!`
	- Job Advanced opzionale: `SQL_SCRIPT` per logiche dinamiche totalmente custom.

## Verifica attesa

- Il task `ShaiyaSchedulerWorker` deve avere un trigger locale Windows con ripetizione ogni 1 minuto.
- Questo task non e' il job finale: serve a sincronizzare i job creati dal pannello.
- Dopo che salvi un job nel sito, il worker crea o aggiorna il task locale `ShaiyaWebJob_<JobID>` con il vero trigger operativo del job.

## Verifica operativa rapida (post-install)

1. In UX imposta `Global Module Status` su ON.
2. Crea un job `SERVICE_COMMAND` e usa `Run Now`.
3. Entro il ciclo di polling successivo verifica:
	- `Web_TaskQueue`: item `RUN_NOW` passa da `pending/processing` a `done` (o `failed` con messaggio).
	- `Web_TaskJobRuns`: nuova riga per il `JobID` con esito run.
	- log server game/service: presenza del comando inviato.

## Verifica diretta job (senza queue)

Per verificare subito l'esecuzione del comando lato worker, puoi lanciare manualmente il job:

`powershell.exe -NoProfile -ExecutionPolicy Bypass -File C:\task-scheduler-worker\worker.ps1 -ConfigPath C:\task-scheduler-worker\worker.config.json -RunJobId 1 -Once`

- Se questo comando esegue il service command nel log game, il blocco non è nel comando ma nel flusso queue/poller.
- Se non esegue, controlla `C:\task-scheduler-worker\logs\worker-error.log`.

## Nota secret HMAC

Ogni volta che rigeneri `worker.config.json` cambia `hmac_secret`.

- Aggiorna `WORKER_HMAC_SECRET` in Apache/PHP.
- Riavvia Apache/PHP prima di testare `Run Now`.
- Se ricevi ancora `Invalid signature`, controlla `config/logs/security.log`: il backend ora registra il motivo tecnico (`missing_secret`, `invalid_worker_id`, `invalid_timestamp`, `invalid_nonce_format`, `invalid_signature_format`, `nonce_rejected`, `signature_mismatch`).
- Fallback operativo: se `WORKER_HMAC_SECRET` non è disponibile in PHP, l'API usa `Web_Settings.TaskSchedulerWorkerSecret` (consigliato valorizzarlo con lo stesso secret del worker).

## Note sicurezza

- API `task_scheduler_worker_api.php` accetta solo localhost + firma HMAC + nonce anti-replay.
- La whitelist comandi lato web è gestita da `TaskSchedulerCommandWhitelist`.
- RBAC scheduler lato web è limitato ad admin allowlist (`TaskSchedulerAllowedAdminUIDs`).
