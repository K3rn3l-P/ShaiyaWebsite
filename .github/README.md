# Shaiya Website Fork

This repository is a fork of [Shaiya Website](https://github.com/ashershaiya/ShaiyaWebsite).

# .github Governance (Snello)

Questa cartella è stata razionalizzata per ridurre ridondanza e conflitti tra istruzioni AI.

## 🔍 MANDATORY WORKFLOW: MCP-First Research

**Leggi prima**: [`instructions/README.md`](instructions/README.md) per il pattern completo.

Ogni task di sicurezza o feature deve seguire sempre:
1. **Ricerca MCP** su OWASP/RFC/vendor docs
2. **Sintesi** al contesto Shaiya
3. **Implementazione** con regole codebase
4. **Audit** post-deploy
5. **Documentazione** in istruzioni

---

## File attivi

**Punto di partenza**: [`instructions/README.md`](instructions/README.md) — workflow onboarding e reference guide.

| File | Ruolo |
|------|-------|
| `copilot-instructions.md` | Comportamento Copilot nel repo + MCP-first rule |
| `instructions/security-implementation.instructions.md` | **Primaria**: regole tecniche, pattern PHP security-first |
| `instructions/security-audit-checklist.instructions.md` | Stato vulnerabilità, session summaries, audit history |
| `instructions/security-fixes-deployment-summary.instructions.md` | Timeline deploy, checklist deploy, assessment rischi |
| `instructions/security-fixes-step-by-step.instructions.md` | Quick ops minimale (no re-reads full policy) |
| `prompts/secure-php-implementation.prompt.md` | Task prompt per PHP refactoring **+ MCP-first** |

## File storici
- Archiviati in `.github/archive/` e `.github/archive/instructions/`
- Trattali come archivio informativo; non sono fonte normativa primaria.

## Regole manutenzione
- **MCP-First**: Ricerca sempre prima, implementazione dopo (non opzionale!)
- Evita duplicare regole di sicurezza in più file.
- Aggiorna la fonte primaria corretta invece di aggiungere nuovi "summary".
- Se un file diventa storico, spostalo in archivio o marcane la deprecazione.
- Mantieni istruzioni concise e con `applyTo` coerente.
