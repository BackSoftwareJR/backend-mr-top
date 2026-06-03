# Wenando Backend — Indice documentazione

> **Progetto:** Wenando Trust Engine (B2C + B2B + Admin)  
> **Stack target:** Laravel 11 API @ `api.wenando.com` · React SPA @ `wenando.com`  
> **Stato:** Blueprint pre-implementazione — derivato da analisi frontend (`src/`)

---

## Ordine di lettura consigliato

| # | Documento | Per chi | Contenuto |
|---|-----------|---------|-----------|
| 1 | [1_ARCHITECTURE_&_SECURITY.md](./1_ARCHITECTURE_&_SECURITY.md) | Tech lead, DevOps | Panoramica sistema, Sanctum, CORS, rate limit, **ADR**, **threat model** |
| 2 | [2_DATABASE_SCHEMA.md](./2_DATABASE_SCHEMA.md) | Backend dev, DBA | Modello dati, **ER diagram**, indici, query patterns |
| 3 | [4_DOMAIN_MODEL_&_ENTITIES.md](./4_DOMAIN_MODEL_&_ENTITIES.md) | Backend dev | Catalogo entità, state machine, ownership B2C/B2B/Admin |
| 4 | [8_MATCHING_&_TRUST_ENGINE_LOGIC.md](./8_MATCHING_&_TRUST_ENGINE_LOGIC.md) | Backend + Product | Algoritmo matching, trust test, preview vs unlock |
| 5 | [3_API_ROUTES_ROADMAP.md](./3_API_ROUTES_ROADMAP.md) | Full-stack | Catalogo endpoint, **auth matrix**, versioning, idempotency |
| 6 | [6_API_CONTRACTS_&_PAYLOADS.md](./6_API_CONTRACTS_&_PAYLOADS.md) | Frontend + Backend | JSON request/response per ogni endpoint, validazione |
| 7 | [5_USER_FLOWS_&_SEQUENCE_DIAGRAMS.md](./5_USER_FLOWS_&_SEQUENCE_DIAGRAMS.md) | Product, QA | Sequence diagram, decision tree, error paths |
| 8 | [7_SECURITY_&_COMPLIANCE_GUIDELINES.md](./7_SECURITY_&_COMPLIANCE_GUIDELINES.md) | Security, Legal | GDPR, OTP, upload, audit, impersonation |
| 9 | [9_DEPLOYMENT_&_OPERATIONS_RUNBOOK.md](./9_DEPLOYMENT_&_OPERATIONS_RUNBOOK.md) | DevOps | Hostinger SSH, cron, env, backup, health |
| 10 | [10_SQL_REVIEW_&_GAPS.md](./10_SQL_REVIEW_&_GAPS.md) | DBA, Backend | Allineamento frontend ↔ SQL, gap, migrazioni |

---

## Schemi JSON (Draft-07)

Cartella [`schemas/`](./schemas/):

| File | Uso |
|------|-----|
| [wizard_senior_care_payload.json](./schemas/wizard_senior_care_payload.json) | Validazione `POST /leads` Senior Care |
| [company_onboarding_payload.json](./schemas/company_onboarding_payload.json) | PATCH onboarding B2B |
| [lead_match_crm_status.json](./schemas/lead_match_crm_status.json) | Enum + transizioni CRM |
| [api_error_response.json](./schemas/api_error_response.json) | Envelope errori standard |
| [sector_dynamic_attributes.json](./schemas/sector_dynamic_attributes.json) | Template attributi dinamici per settore |

---

## Artefatti correlati

| Path | Descrizione |
|------|-------------|
| [`../database_master.sql`](../database_master.sql) | DDL MySQL/MariaDB completo + seed reference |
| [`../../src/services/authService.js`](../../src/services/authService.js) | Mock auth (contratto OTP) |
| [`../../src/context/B2BContext.jsx`](../../src/context/B2BContext.jsx) | Mock wallet, unlock, CRM |
| [`../../docs/PERFORMANCE.md`](../../docs/PERFORMANCE.md) | Ottimizzazioni frontend (route split, bundle) |

---

## Legenda assunzioni

Nei documenti, le voci marcate **[ASSUNZIONE]** non sono implementate nel frontend mock ma sono inferite per coerenza di prodotto. Le voci **[VERIFICATO]** derivano direttamente da codice React.

---

## Gap noti (da risolvere in implementazione)

1. **`/admin` SPA non protetta** — nessun auth check lato client ([VERIFICATO] `App.jsx`).
2. **Wizard non persiste** — `answers` passano via `navigate state` only ([VERIFICATO] `Wizard.jsx`).
3. **Registrazione B2B senza OTP** — crea sessione immediata ([VERIFICATO] `Register.jsx`).
4. **Pagamenti mock** — ricarica wallet istantanea ([VERIFICATO] `B2BContext.rechargeWallet`).
5. **Location autocomplete mock** — 4 città hardcoded ([VERIFICATO] `WizardSteps.jsx`).

Vedi [10_SQL_REVIEW_&_GAPS.md](./10_SQL_REVIEW_&_GAPS.md) per elenco completo.
