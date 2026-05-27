---
name: security-fixes-implementation
description: "Quick operator checklist for applying security fixes on active Shaiya codebase. Use when doing fast remediation without re-reading full policy docs."
applyTo: "**/*.php"
---
PARLA SEMPRE IN ITALIANO.
# 🔧 SECURITY FIXES — QUICK OPS (SNELLO)

**Status**: Attivo (ridotto per evitare ridondanze)  
**Last Updated**: 27 Maggio 2026  
**Scope**: solo codebase attiva root `htdocs` (legacy `htdocs/htdocs` esclusa)

---

## Fonte primaria (da non duplicare)

Questa guida è volutamente breve. I dettagli completi stanno in:

1. `security-implementation.instructions.md` (regole operative e pattern)
2. `security-audit-checklist.instructions.md` (stato vulnerabilità e anti-regressione)
3. `security-fixes-deployment-summary.instructions.md` (timeline/sessioni)

---

## Checklist rapida per ogni fix PHP

- [ ] `require_once 'security_bootstrap.php';` + `appApplySecurityHeaders();` + `appInitSession();`
- [ ] Prepared statements PDO su tutte le query (`$pdoConn` / `$pdoAdmin`)
- [ ] CSRF su tutte le mutazioni POST (`appVerifyCsrfToken`)
- [ ] Rate limit su endpoint sensibili (`appRateLimitAllow`)
- [ ] Input validation whitelist/regex + cast espliciti
- [ ] Output escaping (`htmlspecialchars`) in HTML
- [ ] Admin action: status check + CSRF + rate limit + audit log
- [ ] Nessuna esposizione errori tecnici all’utente finale

---

## Checklist rapida hardening web server

- [ ] Root `.htaccess` con blocco dotfiles/dot-directories (eccezione `/.well-known/`)
- [ ] Cartelle sensibili con `.htaccess` locale:
  - `config/` deny all
  - `tools/` deny all
  - `uploads/` no script execution
  - `sql/` deny all
  - `inc/` deny all

---

## Test minimi post-fix

- [ ] Nessun errore in editor/linter PHP
- [ ] Smoke test funzionale pagina/endpoint modificato
- [ ] Verifica rapida 403 CSRF quando token manca/errato
- [ ] Verifica 429 rate limit in burst
- [ ] Verifica redirect/accesso admin coerente ai permessi

---

## Nota anti-drift

Se trovi in altri file blocchi “PRIMA/DOPO” datati con ODBC legacy, non copiarli: usa direttamente le regole PDO-first della fonte primaria.

