# Shaiya Web Portal — Security Instructions & Workflows

**Purpose**: Centralized source of truth for security patterns, best practices, and operational workflows.

---

## 🔍 MANDATORY WORKFLOW: MCP-First → Implementation → Audit

**Every security task, new feature, or vulnerability fix MUST follow this pattern:**

```
┌────────────────────────────────────────────────┐
│ 1. IDENTIFY PROBLEM                            │
│    (security gap, new feature, fix needed)     │
├────────────────────────────────────────────────┤
│ 2. RESEARCH MCP (MANDATORY!)                   │
│    - OWASP Cheat Sheets, RFC docs, vendor     │
│    - Collect patterns, anti-patterns, risks   │
├────────────────────────────────────────────────┤
│ 3. SYNTHESIZE FOR SHAIYA                       │
│    - Adapt findings to local context          │
│    - Evaluate trade-offs                      │
│    - Document rationale                       │
├────────────────────────────────────────────────┤
│ 4. IMPLEMENT + AUDIT + DOCUMENT                │
│    - Apply fix with codebase rules            │
│    - Run security audit CLI                   │
│    - Update .github/instructions/ with delta  │
└────────────────────────────────────────────────┘
```

---

## 📚 Instruction Files

### **Primary Reference (Read First)**

| File | Purpose | When to Use |
|------|---------|-----------|
| [`security-implementation.instructions.md`](security-implementation.instructions.md) | **Main**: PHP security patterns, PDO rules, CSRF, rate limiting, auth, headers | Implementing security features, new database queries, admin features |
| [`security-audit-checklist.instructions.md`](security-audit-checklist.instructions.md) | **State tracking**: Audit findings, session summaries, vulnerability status, checklist | Before/after audits, tracking security debt, feature parity checks |
| [`security-fixes-step-by-step.instructions.md`](security-fixes-step-by-step.instructions.md) | **Quick ops**: Condensed checklist for fast remediation | Rapid security fixes, operator reference, avoiding detailed policy re-reads |

### **Operational Guides**

| File | Purpose |
|------|---------|
| [`security-fixes-deployment-summary.instructions.md`](security-fixes-deployment-summary.instructions.md) | Timeline, deployment checklist, risk assessment, testing scope |

### **Copilot Prompts** (`.github/prompts/`)

| File | Purpose |
|------|---------|
| `secure-php-implementation.prompt.md` | Task prompt for refactoring/implementing secure PHP code — **includes MCP-first rule** |

---

## 🚀 Quick Start for Developers

### I need to implement a security feature / fix a vulnerability

1. **Read**: `security-implementation.instructions.md` (main reference)
2. **Research**: Execute MCP search on OWASP/RFC/vendor docs (**mandatory!**)
3. **Implement**: Follow code patterns from the instruction file
4. **Validate**: Run `tools/security-monitor/security-audit-advanced.ps1`
5. **Document**: Update `security-audit-checklist.instructions.md` with session summary

### I need to quickly check/apply a fix

1. **Read**: `security-fixes-step-by-step.instructions.md` (condensed checklist)
2. **Reference**: Links point back to full docs if details needed

### I'm auditing the codebase

1. **Reference**: `security-audit-checklist.instructions.md` (status tracking, known issues)
2. **Verify**: Against patterns in `security-implementation.instructions.md`
3. **Update**: Add findings to audit checklist

---

## 📋 File Priority Order

When conflicts arise between instructions, this order applies:

1. `security-implementation.instructions.md` (technical rules, patterns)
2. `security-audit-checklist.instructions.md` (vulnerability state, sessions)
3. `security-fixes-deployment-summary.instructions.md` (deployment checklist)
4. `security-fixes-step-by-step.instructions.md` (condensed ops reference)

---

## 🔐 Core Rules (Always Apply)

### SQL Injection
- ✅ **All queries use prepared statements** (PDO or ODBC)
- ✅ **Cross-DB queries**: Use `$pdoAdmin` with `EMULATE_PREPARES=true`
- ✅ **TEXT columns**: Cast to `VARCHAR(MAX)` in comparisons

### CSRF Protection
- ✅ **All POST forms require CSRF token** via `appVerifyCsrfToken()`
- ✅ **Token in hidden field**: `<input type="hidden" name="csrf_token" value="...">`

### Rate Limiting
- ✅ **Sensitive endpoints**: Use `appRateLimitAllow(key, maxAttempts, windowSeconds)`
- ✅ **Admin actions**: `120 requests / 10 minutes` per admin+IP
- ✅ **Include Retry-After header** on 429 responses

### Security Headers
- ✅ **All pages start with**: 
  ```php
  require_once 'security_bootstrap.php';
  appApplySecurityHeaders();
  appInitSession();
  ```

### Input Validation
- ✅ **Whitelist patterns**: Regex or in_array() on known sets
- ✅ **Type casting**: Cast to explicit types before DB/output
- ✅ **Length checks**: Enforce min/max constraints

### Output Escaping
- ✅ **HTML**: `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`
- ✅ **JSON**: `json_encode($data)`
- ✅ **URL**: `urlencode($var)`

### Admin Panel
- ✅ **Status check**: Verify `$_SESSION['user']['Status'] >= 16`
- ✅ **CSRF verification**: On every POST action
- ✅ **Rate limiting**: Per admin+IP
- ✅ **Audit logging**: Critical actions logged to file/DB

---

## 🔍 MCP Research Queries (Template + Dynamic Time Filters)

Copy & adapt these for common security tasks. **Always use `time_range` for fresh results—never hardcode dates.**

### Dynamic Search Pattern (NO Hardcoded Dates)

```python
# Pseudocode: Always use time_range, never hardcode year/date in query
def mcp_search_security(topic):
    # ✅ CORRECT: Generic terms + time_range filter
    query = f"{topic} latest best practices 2024 2025"
    time_range = "month"  # Results from last 30 days
    return searxng_web_search(query, time_range)

# Time range values (dynamic, not hardcoded):
# - time_range="day"    → Last 24h (most recent, alerts)
# - time_range="month"  → Last 30 days (default for security/features)
# - time_range="year"   → Last 365 days (history/evolution only)
```

### Query Examples (With Time Filters)

| Task | Query | Time Range |
|------|-------|-----------|
| **SQL Injection Prevention** | `"SQL injection prevention latest, prepared statements, parameterized queries, OWASP 2025"` | `month` |
| **Session Security** | `"Session management best practices, HttpOnly, Secure, SameSite flags, recent"` | `month` |
| **Rate Limiting / 429 UX** | `"Rate limiting best practices, 429 response, Retry-After header, countdown timer"` | `month` |
| **CSRF Protection** | `"CSRF prevention mechanisms, token validation, SameSite cookies, recent OWASP"` | `month` |
| **File Upload Security** | `"Secure file upload OWASP, MIME validation, magic bytes, recent patterns"` | `month` |
| **Admin Panel / RBAC** | `"Admin panel security, role-based access control, privilege escalation prevention"` | `month` |
| **CSP / XSS Prevention** | `"Content Security Policy latest, inline script blocking, nonce generation, recent"` | `month` |
| **CVE Intelligence** | `"CISA Known Exploited Vulnerabilities, PHP, SQL Server, critical"` | `day` |

### Best Practices for MCP Queries

1. **Never hardcode dates** in query text
   - ❌ Don't: `"OWASP 2026 rate limiting"` (obsolete next year)
   - ✅ Do: `"OWASP rate limiting best practices latest"` + `time_range="month"`

2. **Use time_range parameter** for fresh results
   - Default: `time_range="month"` for ordinary tasks
   - Alert mode: `time_range="day"` for CVE/vulnerability intel
   - Research: `time_range="year"` for evolution/history only

3. **Add specificity** with standards/vendor names
   - `"RFC 6585"` for HTTP status codes
   - `"OWASP Cheat Sheet"` for vulnerability patterns
   - `"CISA KEV"` for exploited vulnerabilities

4. **Document findings** with dynamic date capture
   ```markdown
   **MCP Search**: [today's date auto-calculated, not hardcoded]
   - Time range: month
   - Pattern confirmed from [OWASP/RFC/Vendor]
   - Source URL: [link]
   ```

---

## 📊 Session Summaries (Audit History)

Each major work session is documented in `security-audit-checklist.instructions.md` with:
- **Scope**: Files touched, vulnerability categories
- **Fixes applied**: What was changed and why
- **Audit results**: High/Medium/Low findings
- **Outcome**: UX/security improvements, new capabilities

Find session summaries in the audit checklist file.

---

## ✅ Pre-Deployment Checklist

Before pushing security work:

- [ ] Researched relevant OWASP/RFC via MCP
- [ ] Documented MCP findings in code/comments
- [ ] Implemented all required patterns (CSRF, rate limit, input validation, etc.)
- [ ] No SQL concatenation (prepared statements only)
- [ ] No inline event handlers (CSP compliance)
- [ ] Output escaped correctly (htmlspecialchars, json_encode, urlencode)
- [ ] Security headers applied (appApplySecurityHeaders)
- [ ] Audit run: `security-audit-advanced.ps1`
- [ ] Results: High=0, Medium=0 (or documented exceptions)
- [ ] Updated `.github/instructions/` with session summary

---

## 🔗 External References

- **OWASP Cheat Sheet Series**: https://cheatsheetseries.owasp.org/
- **OWASP Top 10 2026**: https://owasp.org/www-project-top-ten/
- **CISA Known Exploited Vulnerabilities**: https://www.cisa.gov/known-exploited-vulnerabilities
- **RFC 6585** (HTTP Status 429): https://tools.ietf.org/html/rfc6585
- **RFC 7231** (HTTP Semantics): https://tools.ietf.org/html/rfc7231
- **MDN Web Docs**: https://developer.mozilla.org/en-US/docs/Web/

---

## 📝 Notes

- **Scope**: Active codebase root only (`htdocs/`); excluded: `htdocs/htdocs/` (legacy)
- **Language**: Italian (sempre!)
- **Principles**: Secure-by-default, defense-in-depth, least privilege
- **Workflow**: MCP-first → synthesis → code → audit → document

**Last Updated**: 27 Maggio 2026

