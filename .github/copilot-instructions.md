# Copilot Instructions — Shaiya Web Portal (Fonte Principale)
PARLA SEMPRE ITALIANO.
## 🔍 RICERCA WEB MCP — REGOLA IMPRESCINDIBILE (PRIORITY 0)

**Prima di ogni azione tecnica significativa, esegui SEMPRE ricerca MCP su best practice aggiornate.**

Questa regola OBBLIGATORIA garantisce soluzioni in linea con standard OWASP 2026, CISA KEV, RFC ufficiali e vendor best-practices moderne.

### Pattern Obbligatorio: MCP-First → Synthesis → Action

```
┌─────────────────────────────────────────────────────────────┐
│ 1. IDENTIFICA IL PROBLEMA/TASK                              │
│    (Es: "Rate limit page UX", "SQL injection pattern", etc) │
├─────────────────────────────────────────────────────────────┤
│ 2. RICERCA MCP (non opzionale, obbligatoria)                │
│    - mcp_my-mcp-server_searxng_web_search + fetch_webpage   │
│    - Query specifiche: OWASP, RFC, vendor docs, best practice │
│    - Raccogli: pattern diffusi, anti-pattern, nuovi rischi   │
├─────────────────────────────────────────────────────────────┤
│ 3. SINTESI PERSONALE                                        │
│    - Adatta scoperte web al contesto Shaiya specifico       │
│    - Valuta trade-off security/UX/performance               │
│    - Documenta razionale decisioni                          │
├─────────────────────────────────────────────────────────────┤
│ 4. IMPLEMENTAZIONE                                          │
│    - Applica fix/feature con regole codebase                │
│    - Audit CLI di sicurezza post-deploy                     │
│    - Aggiorna istruzioni .github con delta reale            │
└─────────────────────────────────────────────────────────────┘
```

### Quando è Obbligatorio
- ✅ **SEMPRE**: Security fixes, hardening, vulnerability mitigation
- ✅ **SEMPRE**: New features handling user input/DB operations
- ✅ **SEMPRE**: Rate limiting, authentication, session management
- ✅ **SEMPRE**: HTTP headers, CSP, CSRF protection
- ✅ **SEMPRE**: Admin features, privileged endpoints
- ✅ **SEMPRE**: Performance-critical decisions (caching, async patterns)
- ✅ **SEMPRE**: Configuration changes, environment setup
- ❌ **Evitare ricerca MCP solo per**: Refactoring triviale, typo fixes, simple rewrites senza security impact

### Query MCP Efficaci + Filtri Temporali (Sempre Risultati Recenti)

Struttura query specifiche **SENZA hardcoding date**. Usa `time_range` per filtri dinamici:

```
# Pattern dinamico (Python/JS pseudocode)
def mcp_search_latest(topic, recency='month'):
    # Non scrivere: "OWASP 2026" (data hardcoded, domani sarà obsoleta)
    # Scrivi: topic + recency filter via time_range
    
    query = f"{topic} latest best practices 2024 2025"
    time_range = recency  # 'day', 'month', 'year'
    return searxng_web_search(query, time_range)

# Valori time_range supportati (non hardcoded, dinamici):
# - time_range="day"    → risultati ultimo 24h (più recente)
# - time_range="month"  → risultati ultimo mese (default consigliato)
# - time_range="year"   → risultati ultimo anno (standard)
```

### Query MCP Examples (Con Time Filters)

```
# Security Fixes (Filtro: ultimo mese)
time_range="month" + "OWASP rate limiting best practices, Retry-After header, 429 UX"
time_range="month" + "SQL injection prevention prepared statements parameterized queries"
time_range="month" + "CSRF protection mechanisms token validation SameSite cookies"

# Architecture Decisions (Filtro: ultimo mese)
time_range="month" + "RESTful API rate limiting patterns token bucket sliding window"
time_range="month" + "Session management best practices HttpOnly Secure SameSite"
time_range="month" + "Content Security Policy inline script blocking nonce generation"

# Performance/UX (Filtro: ultimo mese)
time_range="month" + "Countdown timer JavaScript patterns client-side auto-refresh"
time_range="month" + "Form validation UX patterns error messages accessibility"
time_range="month" + "Caching strategies CDN patterns performance optimization"

# CVE/Vulnerability Intelligence (Filtro: ultimo giorno - alert!)
time_range="day" + "CISA Known Exploited Vulnerabilities PHP SQL Server 2026"
time_range="day" + "CVE latest critical vulnerabilities web applications"
```

### Regola di Ricerca (Non Opzionale)

**SEMPRE**:
1. Usa `time_range="month"` per task ordinari (security fix, feature, hardening)
2. Usa `time_range="day"` per intelligence CVE/security alerts
3. **NON scrivere anno hardcoded** nella query (es. ~~"2026 rate limiting"~~)
4. Scrivi **termini generici + recency**: `"latest", "best practices", "2024 2025"` (il filtro temporale fa il resto)
5. Aggiungi sempre **specifiche OWASP/RFC/vendor** alla query (es. "RFC 6585", "OWASP Cheat Sheet")
6. Documenta fonte nella risposta: `**MCP Search**: pattern confermato da [OWASP/RFC]`

### Documenta sempre nel output
```markdown
**Ricerca MCP (Data)**:
- Confermato pattern X da [OWASP/RFC/Vendor]
- Best practice: Y è preferibile a Z perché [motivo specifico]
- Anti-pattern da evitare: W causa [rischio specifico]

**Adattamento Shaiya**:
- Scelto approccio X vs Y perché [contesto locale]
- Trade-off: [cosa guadagni] vs [cosa perdi]

**Implementazione**:
- Codice applicato a [file/linee]
- Validazione: [come è stato testato]
```

---

## Scope operativo
- Lavora solo sulla codebase attiva root `htdocs`.
- Non usare `htdocs/htdocs` come baseline, salvo richiesta esplicita del maintainer.
- Parla sempre in italiano.
- Cerca sempre sul web per best practice e pattern di sicurezza aggiornati, ma adatta sempre al contesto specifico del progetto.
- Se è disponibile un server MCP per web search/URL reading, usalo come canale preferito per raccogliere best practice, riferimenti OWASP/CISA e documentazione tecnica aggiornata prima di applicare fix o hardening rilevanti. In questo workspace è dichiarato il server HTTP `my-mcp-server-7c0b6bde`.
- **IMPORTANTE**: Quando usi MCP search, **non hardcodare MAI date nelle query** (es. ~~"2026 rate limiting"~~). Usa `time_range` parameter per filtri temporali:
  - `time_range="month"` → Default per task ordinari (security fix, feature, hardening)
  - `time_range="day"` → Per CVE/security alerts (risultati 24h più recenti)
  - Scrivi nella query: `"latest", "best practices", "2024 2025"` — il filtro temporale fa il resto
- Quando applichi fix o implementi funzionalità, aggiorna o crea istruzioni mirate per quel contesto, evitando di creare file troppo generici o troppo specifici.

## IMPORTANTE (vincolo permanente)
- Non analizzare la cartella `/htdocs/htdocs`: è un sito legacy archiviato e fuori scope operativo corrente.
- Quando modifichi codice o crei nuovi file/cartelle nel progetto, valuta sempre hardening web server e aggiorna/inserisci i `.htaccess` necessari come default sicuro.
- Per nuove cartelle sensibili, applica deny-by-default in produzione (anti-hacking, audit readiness, least privilege).
- Esegui sempre un audit di sicurezza della superficie toccata (file, cartelle, endpoint, `.htaccess`, esposizione pubblica, regressioni).
- A fine conversazione, implementazione, fix o refactor, esegui sempre da CLI `tools/security-monitor/security-audit-advanced.ps1` sulla root attiva del progetto.
- Se l'audit rileva gap di hardening baseline o mancanze sicure e deterministiche, esegui prima `tools/security-monitor/security-autofix-safe.ps1` in dry-run e usa `-Apply` solo per fix safe non distruttivi.
- Dopo ogni autofix, riesegui sempre `tools/security-monitor/security-audit-advanced.ps1` per confermare il delta e verificare eventuali finding residui.
- I due script sopra operano solo sulla codebase attiva root `htdocs` ed escludono esplicitamente `htdocs/htdocs`.
- Se trovi gap di sicurezza, correggili e documenta la mitigazione in un’istruzione dedicata (es. `security-implementation.instructions.md`), evitando di creare file di istruzioni troppo generici o troppo specifici.

## Priorità istruzioni
1. `.github/instructions/security-implementation.instructions.md` (regole tecniche)
2. `.github/instructions/security-audit-checklist.instructions.md` (stato rischi/checklist)
3. `.github/instructions/security-fixes-deployment-summary.instructions.md` (storico deploy)
4. `.github/instructions/security-fixes-step-by-step.instructions.md` (quick ops)

Se trovi conflitti tra file, prevale l'ordine sopra.

## Regole tecniche obbligatorie (PHP)
- Bootstrap sicurezza in apertura file: `security_bootstrap.php` + headers + session.
- Query DB solo con prepared statements PDO (`$pdoConn` / `$pdoAdmin`).
- Mutazioni POST: CSRF obbligatorio (`appVerifyCsrfToken`).
- Endpoint sensibili: rate limiting (`appRateLimitAllow`).
- Output HTML: escaping con `htmlspecialchars`.
- Admin actions: status check + CSRF + rate limit + audit log.
- Mai esporre dettagli tecnici di errore all’utente finale.
- Hardening web server: `.htaccess` con deny-by-default per cartelle sensibili, blocco dotfiles/dot-directories, blocco esecuzione script in `uploads/`.
- Per nuove cartelle, valuta sempre se devono essere pubbliche o protette e applica il `.htaccess` di conseguenza.

## Hardening directory baseline
- `config/`, `tools/`, `sql/`, `inc/`: deny-by-default con `.htaccess`.
- `uploads/`: blocco esecuzione script.
- Root `.htaccess`: blocco dotfiles/dot-directories (eccetto `/.well-known/`).
- Ogni nuova directory deve essere valutata: se non è pubblica, bloccarla esplicitamente via `.htaccess`.
- Se una directory è pubblica ma contiene file sensibili, applicare regole specifiche per proteggere quei file.
- Verifica sempre l’esposizione pubblica di nuove cartelle/file e applica hardening proattivo.
- Per cartelle con accesso pubblico ma contenuto dinamico (es. `uploads/`), applica regole di sicurezza specifiche (es. blocco esecuzione script) e monitora regolarmente per attività sospette.

## Best practice Copilot (strutturazione istruzioni)
- Mantieni poche fonti autorevoli e minimizza duplicazioni.
- Usa `applyTo` mirati solo quando serve; evita overlap non necessario.
- Preferisci file brevi e aggiornabili a “mega changelog” ridondanti.
- Ogni modifica security deve aggiornare solo la fonte primaria pertinente.
- Se un fix è specifico per un contesto (es. Task Scheduler), documentalo in un’istruzione dedicata a quel contesto, evitando di appesantire la fonte primaria con dettagli troppo specifici.
- Se trovi gap di sicurezza, correggili e documenta la mitigazione nella fonte primaria, evitando di creare file di istruzioni troppo generici o troppo specifici.

## Security intelligence cadence (leggera)
- Cadenza consigliata: review mensile delle fonti OWASP + monitoraggio CISA KEV.
- Fonti:
  - OWASP Top 10 / Cheat Sheets
  - CISA KEV catalog
- Se disponibile in ambiente, usa anche MCP HTTP configurato per web lookup mirati e lettura URL come supporto operativo leggero, evitando dump non filtrati.
- Documenta solo impatti reali sul progetto (evita dump raw di feed esterni).
- Se un nuovo rischio è rilevante per il progetto, correggilo e documenta la mitigazione in un’istruzione dedicata, evitando di creare file di istruzioni troppo generici o troppo specifici.
- Se un rischio non è rilevante o già mitigato, non è necessario documentarlo (evita di creare file di istruzioni troppo generici o troppo specifici).
- Se un rischio è mitigato da una regola già esistente, aggiorna la regola con un commento o esempio specifico, evitando di creare file di istruzioni troppo generici o troppo specifici.

## Workflow CLI obbligatorio post-task
- Esegui `powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-audit-advanced.ps1` al termine di ogni task che tocca codice, configurazione, endpoint, cartelle o `.htaccess`.
- Se serve hardening baseline automatico, esegui `powershell -ExecutionPolicy Bypass -File tools/security-monitor/security-autofix-safe.ps1` in dry-run; usa `-Apply` solo dopo review del piano.
- Se vuoi far fallire la pipeline locale in presenza di finding HIGH, usa `-FailOnHigh` con `security-audit-advanced.ps1`.
- Salva e consulta i report JSON in `config/logs/security_audit_report.json` e `config/logs/security_autofix_report.json`.
- Ogni finding corretto deve essere verificato con un nuovo audit e documentato nella fonte primaria pertinente sotto `.github/instructions/`.
