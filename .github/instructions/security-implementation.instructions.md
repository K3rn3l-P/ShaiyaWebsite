---
name: security-implementation
description: "Use when: implementing security features in the Shaiya website codebase, creating new database queries, handling authentication, building admin features, or processing user forms. Applies to all PHP files handling user input, database operations, authentication, and admin actions."
applyTo: "**/*.php"
---
PARLA SEMPRE IN ITALIANO.
# Linee Guida di Sicurezza per Shaiya Web Portal

## 🔍 RICERCA WEB MCP — REGOLA IMPRESCINDIBILE (PRIORITY 0)

**Prima di implementare qualsiasi security fix, feature o hardening, ricerca SEMPRE su fonti aggiornate via MCP.**

Questa regola garantisce soluzioni conformi a OWASP Top 10 2025, CISA KEV, RFC e best-practices vendor attuali.

### Pattern Obbligatorio (Non Opzionale)

```
Per ogni task di sicurezza:

1️⃣  DEFINISCI IL PROBLEMA SPECIFICO
    Esempio: "Implementare rate limiting su 429 response page"

2️⃣  RICERCA MCP (obbligatoria!)
    - Parole chiave OWASP/RFC: "OWASP rate limiting best practices"
    - Fetch pagine ufficiali: OWASP Cheat Sheets, RFC 6585, vendor docs
    - Raccogli: pattern diffusi, anti-pattern critici, nuove vulnerabilità

3️⃣  SINTETIZZA PER SHAIYA
    - Come adattare scoperte al contesto specifico?
    - Quali trade-off tra security/UX/performance?
    - Quali vincoli architetturali considerare?

4️⃣  IMPLEMENTA + VALIDA
    - Applica fix con regole codebase (PDO, CSRF, rate limit, etc.)
    - Esegui audit CLI: security-audit-advanced.ps1
    - Aggiorna istruzioni .github con delta reale
```

### Checklist MCP per Security Task

- [ ] Ricerca OWASP Cheat Sheet pertinente con `time_range="month"`
- [ ] Ricerca RFC ufficiale con `time_range="month"` (es. "RFC 6585 429")
- [ ] Raccogli anti-pattern e best-practice comuni (ultimi 30 giorni)
- [ ] Verifica CISA KEV con `time_range="day"` per CVE sfruttati recenti
- [ ] Documenta nel commento di implementazione la fonte + data ricerca
- [ ] Se scoperta nuova vulnerabilità: update .github/instructions/ e audit

### Documenta sempre le scoperte MCP (Con Data Dinamica)

```markdown
# Security Implementation Note
**MCP Search** (Data):
- Ricerca eseguita: [today's date dinamica, non hardcoded]
- Time filter: [day/month/year usato]
- Confermato pattern X da [OWASP/RFC]
- Best practice: Y preferibile a Z perché [motivo]
- Anti-pattern: W causa [rischio specifico]

**Adattamento Shaiya**:
- Scelto X perché [contesto locale]
- Trade-off: [guadagni] vs [perdite]

**Implementazione**:
- File: [path], Linee: [n-m]
- Validazione: [test/audit]
```

### Pattern Ricerca Dinamica (NO Hardcode Date)

```
# SEMPRE usa time_range per risultati recenti:
time_range="month"  → Default per task ordinari (security fix, feature, hardening)
time_range="day"    → Per intelligence CVE/security alerts (risultati 24h)
time_range="year"   → Solo se cerchi history/evolution su lungo termine

# Query SENZA date hardcoded:
✅ "SQL injection prevention latest best practices 2024 2025"
❌ "SQL injection prevention 2026"  (anno hardcoded, domani obsoleto)

✅ "OWASP rate limiting RFC 6585 latest patterns"
❌ "OWASP 2026 rate limiting"  (anno hardcoded)

✅ "Session management best practices recent HttpOnly Secure"
❌ "Session management 2025 best practices"  (anno hardcoded)
```

---

## Panoramica

Questo progetto eredita da un'architettura sicura verificata. Tutte le nuove funzionalità DEVONO seguire questi principi di sicurezza come fondamento, integrando il framework `security_bootstrap.php` come punto di partenza.

### Scope operativo (OBBLIGATORIO)

Lavora **solo** sulla codebase attiva root `htdocs`.

- Non analizzare né usare come sorgente la cartella legacy `htdocs/htdocs`.
- Non copiare pattern dal legacy salvo esplicita richiesta manuale del maintainer.
- Mantieni le modifiche minimali e coerenti con l'architettura corrente (`security_bootstrap.php` + PDO-first).
- Prima di introdurre fix security, hardening o nuove query sensibili, esegui SEMPRE lookup mirati su fonti aggiornate (OWASP Cheat Sheets, CISA KEV, documentazione vendor) e, se disponibile, usa il server MCP di web search/URL reading come canale operativo predefinito.
- Dopo ogni fix security, esegui SEMPRE audit CLI (`tools/security-monitor/security-audit-advanced.ps1`) e aggiorna le istruzioni `.github` con il delta reale.
- Vincolo di scope corrente: MFA/2FA è esplicitamente fuori perimetro implementativo finché non richiesto dal maintainer.

---

## 1. PROTEZIONE DAL SQL INJECTION

### Regola Obbligatoria
**Tutte le query al database DEVONO utilizzare prepared statements.** Nessuna eccezione, nemmeno per admin.

### Pattern Corretto (PDO - Consigliato)
```php
// ✅ CORRETTO: Prepared statement con bindParam
$query = "SELECT UserID FROM Users_Master WHERE UserID = ? AND Status = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $username, PDO::PARAM_STR);
$stmt->bindParam(2, 16, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Pattern Corretto (ODBC - Legacy, DEPRECATO)
```php
// ✅ CORRETTO: odbc_prepare + odbc_execute
$query = "SELECT UserID FROM Users_Master WHERE UserID = ? AND Status = ?";
$stmt = odbc_prepare($conn, $query);
odbc_execute($stmt, [$username, 16]);
while ($row = odbc_fetch_array($stmt)) {
    // Processa i dati
}
```

> Nota Sessione 15: ODBC è deprecato nella codebase attiva. Usare questo pattern solo quando si analizza codice storico in `htdocs/htdocs` o riferimenti legacy; per nuove implementazioni usare esclusivamente PDO.

### Pattern SCORRETTO (VIETATO)
```php
// ❌ VIETATO: String interpolation
$query = "SELECT * FROM Users_Master WHERE UserID = '" . $username . "'";
$result = odbc_exec($conn, $query);
```

### Linee Guida Aggiuntive
- Usa `?` placeholder (ODBC/PDO) o `:param` (PDO named)
- Specifica sempre il tipo di dato: `PDO::PARAM_INT`, `PDO::PARAM_STR`, etc.
- Per valori dinamici in `ORDER BY`, `LIMIT`, usa whitelist di stringhe consentite
- Non concatenare stringhe nelle query, mai

### PDO Migration Patterns (Session 10+)

When migrating queries to PDO or creating new PDO queries, follow these patterns:

#### Cross-Database Queries
Use `$pdoAdmin` from `db_pdo.php` with `ATTR_EMULATE_PREPARES=true` for complex queries spanning multiple databases:

```php
<?php
// Query ActionLog in PS_GameLog with user data from PS_UserData
$query = "SELECT UserID, COUNT(*) FROM PS_GameLog.dbo.ActionLog 
          WHERE CAST(UserID AS VARCHAR(MAX)) IN (?, ?, ?) AND ActionType = 108";
$stmt = $pdoAdmin->prepare($query);
$stmt->execute($userIDArray);
while ($row = $stmt->fetch()) {
    // Process row
}
?>
```

#### Single-Database Website Queries
Use `$pdoConn` for queries targeting PS_WebSite only (no EMULATE_PREPARES needed):

```php
<?php
require_once 'db_pdo.php';
$query = "SELECT SettingValue FROM PS_WebSite.dbo.Web_Settings WHERE SettingKey = ?";
$stmt = $pdoConn->prepare($query);
$stmt->execute(['LuckyChestEnabled']);
$row = $stmt->fetch();
?>
```

#### TEXT Column Type Handling in SQL Server (CRITICAL)
SQL Server TEXT columns require explicit type casting when used in comparisons with VARCHAR parameters:

```php
<?php
// ❌ WRONG: "The data types varchar and text are incompatible..."
$query = "SELECT * FROM PS_GameLog.dbo.ActionLog 
          LEFT JOIN PS_GameLog.dbo.ActionLog a2 ON a2.UserID = ActionLog.UserID 
          WHERE ActionLog.UserID = ?";

// ✅ CORRECT: Cast TEXT to VARCHAR(MAX) on BOTH sides of comparison
$query = "SELECT * FROM PS_GameLog.dbo.ActionLog 
          LEFT JOIN PS_GameLog.dbo.ActionLog a2 
            ON CAST(a2.UserID AS VARCHAR(MAX)) = CAST(ActionLog.UserID AS VARCHAR(MAX))
          WHERE CAST(ActionLog.UserID AS VARCHAR(MAX)) = ?";

$stmt = $pdoAdmin->prepare($query);
$stmt->execute([$userID]);
?>
```

**Rule**: When joining or comparing TEXT columns with parameters:
- Always use `CAST(column AS VARCHAR(MAX))` on both sides of JOIN condition

### Sessione 15 — Stato finale migrazione
- `appGetWebSetting()` in `security_bootstrap.php` non accetta più `$conn`.
- In `admin_actions.php` e `task_scheduler_worker_api.php` sono stati rimossi i call-site legacy con `$conn`.
- `admin.php` include ora la view `ACTIONLOG` completa (filtro + ricerca + paginazione) su query PDO preparate.
- Apply casting in WHERE clauses when comparing with parameters
- Use `VARCHAR(MAX)` (not just `VARCHAR`) for maximum compatibility with SQL Server

---

## 2. PROTEZIONE CSRF (Cross-Site Request Forgery)

### Modello di Riferimento: `security_bootstrap.php`

Tutte le form POST DEVONO includere e verificare un token CSRF:

### Implementazione in Pagina di Form
```php
<?php
// Nel file PHP che genera il form (es. register.php)
require_once 'security_bootstrap.php';
appInitSession();
?>

<form method="POST" action="/register">
    <!-- Token CSRF nascosto -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    
    <!-- Honeypot (protezione bot) -->
    <input type="hidden" name="hidden_field" style="display:none;" value="">
    
    <!-- Form fields -->
    <input type="text" name="username" required>
    <input type="password" name="password" required>
    <button type="submit">Registrati</button>
</form>
```

### Implementazione nel Processing
```php
<?php
require_once 'security_bootstrap.php';
appInitSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ Verifica CSRF token
    if (!appVerifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        die('Invalid security token. Session expired.');
    }
    
    // ✅ Verifica honeypot (protezione bot)
    if (!empty($_POST['hidden_field'])) {
        http_response_code(400);
        die('Invalid request detected.');
    }
    
    // Procedi con il resto della logica
}
?>
```

### Token Refresh
- Token CSRF viene generato automaticamente in `appInitSession()`
- Non rigenerare il token ad ogni richiesta (performance)
- Rigenerare SOLO al login/logout

---

## 3. RATE LIMITING (Protezione Brute Force)

### Implementazione Base
```php
<?php
require_once 'security_bootstrap.php';
appInitSession();

$loginRateKey = 'login:' . appGetClientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Massimo 5 tentativi in 15 minuti (900 secondi)
    if (!appRateLimitAllow($loginRateKey, 5, 900)) {
        $_SESSION['error'] = 'Too many login attempts. Please wait 15 minutes.';
        header('Location: /');
        exit;
    }
    
    // Procedi con validazione credenziali
}
?>
```

### Endpoint Sensibili con Rate Limit Critico
```php
<?php
// Registrazione: max 5 per IP in 15 min
$registerRateKey = 'register:' . appGetClientIp();
if (!appRateLimitAllow($registerRateKey, 5, 900)) {
    die('Too many registrations. Please wait.');
}

// Password recovery: max 3 per IP in 1 ora
$recoverRateKey = 'password_recover:' . appGetClientIp();
if (!appRateLimitAllow($recoverRateKey, 3, 3600)) {
    die('Too many recovery attempts. Please wait.');
}

// Admin actions: max 120 azioni in 5 min per admin
$adminRateKey = 'admin:' . $userUID . ':' . appGetClientIp();
if (!appRateLimitAllow($adminRateKey, 120, 300)) {
    die('Rate limit exceeded.');
}
?>
```

### Reset Rate Limit (Dopo Successo)
```php
<?php
// Opzionale: ripulisci il counter dopo login riuscito
appRateLimitReset($loginRateKey);
?>
```

---

## 4. SECURITY HEADERS (Protezione Browser)

### Applicazione Automatica
Tutte le pagine PHP DEVONO includere all'inizio:

```php
<?php
require_once 'security_bootstrap.php';
appApplySecurityHeaders(); // Applica automaticamente tutti gli header
appInitSession();          // Inizializza sessione sicura
?>
```

### Header Applicati Automaticamente
```
X-Content-Type-Options: nosniff           // Previene MIME sniffing
X-Frame-Options: DENY                      // Disabilita clickjacking
X-XSS-Protection: 1; mode=block           // Protezione XSS legacy
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Strict-Transport-Security: max-age=31536000 (solo HTTPS)
```

### Header Aggiuntivi Custom (Se Necessario)
```php
<?php
// Aggiungi solo se necessario oltre agli header automatici
if (headers_sent() === false) {
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'');
}
?>
```

### Canonical URL & Rewrite Safety (OBBLIGATORIO)
- Usa URL canonici senza `.php` per link/form pubblici (es. `/register`, `/account`, `/change_password`).
- Mantieni rewrite server-side da URL clean a file `.php` solo internamente.
- I redirect canonici `.php -> clean` DEVONO essere applicati solo per `GET/HEAD`, mai su `POST`.
- Non applicare redirect che trasformano una richiesta `POST` (login/register/form) in `GET`.
- Per form critici usa action coerente con URL canonico corrente (`/`, `/register`, ecc.) per evitare loop/refresh.

---

## 5. SESSION MANAGEMENT (Sicurezza Sessione)

### Inizializzazione Sessione Sicura
```php
<?php
require_once 'security_bootstrap.php';
appInitSession(); // Configura automaticamente:
// - Cookie lifetime: 1800 secondi (30 min)
// - HttpOnly: true (previene accesso JS)
// - Secure: true (solo HTTPS)
// - SameSite: Lax (protezione CSRF)
// - CSRF token generation
?>
```

### Session Timeout (Admin Panel)
```php
<?php
// Timeout per panel admin: 30 minuti di inattività
define('SESSION_TIMEOUT', 1800);

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: /admin?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();
?>
```

### Session Regeneration (Solo al Login)
```php
<?php
// Al completamento login riuscito
if ($loginSuccessful) {
    session_regenerate_id(true); // Distruggi vecchia sessione
    $_SESSION['user'] = $userData;
    $_SESSION['last_activity'] = time();
}
?>
```

### Logout Sicuro
```php
<?php
function secureLogout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

secureLogout();
header('Location: /');
exit;
?>
```

---

## 6. VALIDAZIONE E SANITIZZAZIONE INPUT

### Input Validation Pattern
```php
<?php
$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

// Validazione lunghezza
if (strlen($username) < 4 || strlen($username) > 18) {
    die('Username must be 4-18 characters.');
}

// Validazione formato (whitelist characters)
if (!preg_match('/^[a-zA-Z0-9_@!#.-]+$/', $username)) {
    die('Username contains invalid characters.');
}

// Validazione email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email address.');
}
?>
```

### HTML Output Escaping
```php
<?php
// Quando stampi dati user-supplied nell'HTML:
echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

// Quando stampi in JSON:
json_encode($data, JSON_HAS_FORCED_UTF8);

// Quando stampi in URL:
echo urlencode($value);
?>
```

### File Upload Validation
```php
<?php
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedMimes = ['image/png', 'image/jpeg', 'image/webp'];
$uploadDir = __DIR__ . '/uploads/safe/';

if ($_FILES['image']['size'] > $maxFileSize) {
    die('File too large.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    die('File type not allowed.');
}

// Salva con estensione determinata dal MIME type
$ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mimeType];
$filename = uniqid() . '.' . $ext;
move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
?>
```

---

## 7. PROTEZIONE ADMIN PANEL

### Verifica Accesso Admin
```php
<?php
// Verifiche OBBLIGATORIE all'inizio di ogni pagina admin
require_once 'security_bootstrap.php';
appInitSession();
require_once 'db.php';

// ✅ Verifica status utente
if (!isset($_SESSION['user']) || $_SESSION['user']['Status'] < 16) {
    http_response_code(403);
    die('Access denied.');
}

// ✅ Verifica IP whitelist (opzionale ma consigliato)
if (!isIPWhitelisted($conn, appGetClientIp())) {
    appSecurityLog('Unauthorized admin access from ' . appGetClientIp());
    http_response_code(403);
    die('IP not whitelisted.');
}

// ✅ Verifica CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!appVerifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        die('Invalid security token.');
    }
}

// ✅ Verifica rate limiting azioni critiche
$adminRateKey = 'admin:' . $_SESSION['user']['UserUID'] . ':' . appGetClientIp();
if (!appRateLimitAllow($adminRateKey, 120, 300)) {
    appSecurityLog('Admin rate limit exceeded: ' . $_SESSION['user']['UserID']);
    die('Too many actions. Please wait.');
}
?>
```

### Azioni Critiche Admin
```php
<?php
// Per azioni critiche (ban, delete, point add) registra nel audit log
function logAdminAction($userUID, $userID, $action, $target, $details = '') {
    $logDir = __DIR__ . '/config/logs';
    $logFile = $logDir . '/admin_audit.log';
    
    $entry = date('Y-m-d H:i:s') . " | Admin: $userID | Action: $action | Target: $target";
    if ($details) $entry .= " | Details: $details";
    $entry .= " | IP: " . appGetClientIp() . "\n";
    
    file_put_contents($logFile, $entry, FILE_APPEND);
}

// Esempio uso
if ($_POST['action'] === 'kick_player') {
    $charID = (int) $_POST['char_id'];
    // ... esegui kick ...
    logAdminAction($_SESSION['user']['UserUID'], $_SESSION['user']['UserID'], 
                   'KICK_PLAYER', $charID, "Reason: {$_POST['reason']}");
}
?>
```

---

## 8. LOGGING E MONITORING

### Security Log
```php
<?php
require_once 'security_bootstrap.php';

// Log automaticamente eventi di sicurezza
appSecurityLog('User login successful: ' . $_SESSION['user']['UserID']);
appSecurityLog('CSRF token verification failed from IP: ' . appGetClientIp());
appSecurityLog('Rate limit exceeded for registration from: ' . appGetClientIp());
?>
```

### Audit Log (Admin Actions)
```php
<?php
function auditLog($action, $details) {
    $logFile = __DIR__ . '/config/logs/audit.log';
    $entry = date('Y-m-d H:i:s') . " | " . $action . " | " . json_encode($details) . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}
?>
```

### Monitoraggio File Log
```php
<?php
// Implementa rotazione log per evitare crescita infinita
function rotateLogIfNeeded($logFile, $maxSizeBytes = 10 * 1024 * 1024) {
    if (file_exists($logFile) && filesize($logFile) > $maxSizeBytes) {
        $backup = $logFile . '.' . date('Y-m-d-H-i-s');
        rename($logFile, $backup);
        
        // Opzionale: elimina backup più vecchi di 30 giorni
        $maxAge = 30 * 24 * 60 * 60;
        foreach (glob($logFile . '.*') as $oldBackup) {
            if (time() - filemtime($oldBackup) > $maxAge) {
                unlink($oldBackup);
            }
        }
    }
}
?>
```

---

## 9. CONFIGURAZIONE DATABASE SICURA

### Pattern db.php
```php
<?php
// ✅ Usa variabili d'ambiente per credenziali
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_name = getenv('DB_NAME') ?: 'PS_UserData';

// ✅ Validazione credenziali
if ($db_user === false || $db_user === '' || $db_pass === false || $db_pass === '') {
    error_log('Missing DB credentials in environment variables');
    http_response_code(500);
    exit('Database configuration error.');
}

// ✅ Gestione errori senza leak di informazioni
$conn = @odbc_connect($dsn, $db_user, $db_pass);
if (!$conn) {
    error_log('ODBC connection failed: ' . odbc_errormsg());
    http_response_code(500);
    exit('Database connection failed.'); // Non mostrare errore tecnico all'utente
}
?>
```

### .env (Non commitare!)
```
DB_HOST=127.0.0.1
DB_USER=sa
DB_PASSWORD=your_password_here
DB_NAME=PS_UserData
ODBC_DRIVER=SQL Server
```

### .gitignore
```
.env
.env.local
config/logs/*
!config/logs/.gitkeep
uploads/*
!uploads/.gitkeep
```

---

## 10. CHECKLIST PER NUOVE FEATURES

Prima di aprire una PR, verifica:

- [ ] Tutte le query database usano prepared statements
- [ ] Tutte le form POST verificano CSRF token
- [ ] Input user-supplied è validato con whitelist
- [ ] Output HTML è escaped con `htmlspecialchars()`
- [ ] File upload (se presente) valida MIME type
- [ ] Endpoint admin verifica status + CSRF + rate limit
- [ ] Azioni critiche registrate in audit log
- [ ] Nessuna password/token in commit
- [ ] Headers di sicurezza applicati
- [ ] Rate limiting appropriato per endpoint
- [ ] Session timeout per admin panel implementato
- [ ] Errori non espongono informazioni tecniche
- [ ] File log ruotati per evitare crescita infinita
- [ ] Codice non contiene `odbc_exec()` con valori dinamici
- [ ] Cartelle sensibili protette con `.htaccess` locale (`config/`, `tools/`, `uploads/`, `sql/`, `inc/`)
- [ ] Root `.htaccess` blocca dotfiles/dot-directories (eccezione `/.well-known/`)

---

## 11. RIFERIMENTI E RISORSE

### File Security Fondamentali
- `security_bootstrap.php` - Framework di sicurezza centralizzato
- `db.php` / `db_pdo.php` - Connessioni database sicure
- `config/config.php` - Configurazione di sessione e validazione

### Librerie Consigliate
- **PHPMailer** (già incluso) - Email sicure
- **Monolog** - Logging avanzato
- **Symfony Security** - Protezione aggiuntiva se necessario

### Best Practices OWASP
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [SQL Injection Prevention](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html)
- [CSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)

---

## 12. CONTRATTO SICURO WEB ↔ WORKER (HMAC + NONCE)

Per integrazioni con worker locali Windows (es. scheduler):

- Endpoint worker **localhost-only** + verifica firma HMAC SHA-256.
- Header richiesti: `X-Worker-Id`, `X-Worker-Ts`, `X-Worker-Nonce`, `X-Worker-Sig`.
- Payload firmato: `workerId.timestamp.nonce.rawBody`.
- Secret condiviso via env (`WORKER_HMAC_SECRET`), mai hardcoded/committato.
- Verifica timestamp breve (es. ±120s) + nonce anti-replay one-time.
- Rifiuta richieste non firmate o con firma/nonce invalidi (`403`).

### RBAC Scheduler
- Le azioni scheduler devono essere consentite solo ad admin autorizzati.
- Usa la logica centralizzata di `PERMISSIONS MANAGEMENT` con permission `TASK_SCHEDULER` per rank GM/GMA.
- ADM (Status 16) mantiene accesso completo.
- Logga ogni azione scheduler critica (`create/update/delete/run_now`).

### Task Whitelist
- Comandi servizio scheduler devono passare una whitelist prefissi (es. `TaskSchedulerCommandWhitelist`).
- Non consentire comandi arbitrari fuori whitelist.

### Apache Hardening (.htaccess)
- Endpoint `task_scheduler_worker_api.php` deve essere accessibile solo da loopback (`127.0.0.1`, `::1`).
- Cartella `tools/task-scheduler-worker/` deve essere `Require all denied` (niente accesso pubblico).
- Non affidarti solo ai check PHP: applica sempre anche il blocco web server.

---

## 12-BIS. HARDENING DIRECTORY DEFAULT (OBBLIGATORIO)

Per ogni nuova cartella creata nella codebase attiva (`htdocs`), applica default-deny dove non è necessaria esposizione pubblica.

### Cartelle da proteggere SEMPRE
- `config/` → deny all
- `tools/` → deny all (tranne endpoint esplicitamente necessari e protetti)
- `uploads/` → deny esecuzione script (`-ExecCGI`, `php engine off`, `RemoveHandler`)
- `sql/` → deny all
- `inc/` → deny all

### Root `.htaccess` baseline richiesto
- blocco dotfiles e dot-directories (es. `.env`, `.git`, `.github`) con eccezione `/.well-known/`
- blocco file sensibili (`.sql`, `.ini`, `.log`, `.bak`, `.md` interni quando non pubblici)
- canonical rewrite solo `GET/HEAD` (mai alterare `POST`)

### Policy operativa
- Se una risorsa deve essere pubblica, aprire esplicitamente il minimo necessario (principio least privilege).
- Evitare wildcard permissive (`Require all granted`) in subtree sensibili.
- Documentare ogni eccezione in `security-fixes-deployment-summary.instructions.md`.

### UX Scheduler (chiarezza operativa)
- Mostra in sola lettura il `TaskSchedulerWorkerId` atteso e il worker attivo da heartbeat.
- Includi note brevi in UI per first setup (nuovo Windows): copia config, install script, verifica heartbeat.
- Nei campi form aggiungi esempi rapidi (Service command e SQL inline/upload) per evitare errori operativi.
- Ogni miglioramento UI del modulo scheduler deve mantenere stile e tema esistenti del sito (stessa palette, stessi componenti e pattern visivi), evitando redesign o elementi fuori sistema.

---

## 12-TER. CSP-SAFE UI EVENT MODEL (OBBLIGATORIO)

Per pagine admin e frontend con CSP hardening attivo:

- Vietato usare attributi inline `onclick`, `onchange`, `onsubmit`, `onmouseover`, `onmouseout`.
- Vietato introdurre URI `javascript:` in `href/src/action` o payload dinamici.
- Usare pattern standardizzato:
    - classi `js-*` per i target interattivi
    - attributi `data-*` per parametri runtime
    - listener centralizzati in `js/script.js` con event delegation (`document.addEventListener(...)`)
- Conferme azioni distruttive: usare `class="js-confirm-submit"` + `data-confirm="..."` su form POST.
- Auto-submit filtri: usare `class="js-auto-submit-onchange"` su input/select.
- Per UX hover dinamica, preferire classi CSS; se dinamica runtime necessaria, usare data attributes (`data-border-hover` / `data-border-default`) e listener JS centralizzato.

### Obiettivo
- Compatibilità con CSP strict (o report-only strict) senza regressioni funzionali.
- Riduzione superficie DOM XSS legata a inline JS.

---

## 12-QUATER. SECURITY INTELLIGENCE MCP (OPERATIVO)

Prima di fix/hardening security rilevanti, eseguire lookup mirato via MCP (quando disponibile) su:

- OWASP Cheat Sheets pertinenti (CSP, XSS, Session, File Upload, Logging)
- CISA KEV feed ufficiale (JSON) per awareness su CVE sfruttate in-the-wild

Regole pratiche:
- Evitare dump raw lunghi: annotare solo impatti concreti sulla codebase.
- Tradurre i finding in azioni implementabili (fix code, hardening `.htaccess`, checklist aggiornata).
- Aggiornare i file `.github/instructions/` al termine del fix con delta audit e backlog residuo.
- Questo passaggio è obbligatorio per nuove remediation security (non opzionale).

---

## 13. REPORTING VULNERABILITÀ

Se trovi una vulnerabilità:

1. NON aprir un'issue pubblica
2. Contatta i maintainer privatamente
3. Fornisci:
   - Descrizione della vulnerabilità
   - Passi per riprodurla
   - Impatto potenziale
   - Suggerimento di fix (opzionale)

---

**Versione**: 1.0  
**Data**: 2026-05-25  
**Autore**: Security Review Team
