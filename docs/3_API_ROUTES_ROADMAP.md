# Wenando API Routes Roadmap

> **Base URL:** `https://api.wenando.com/api/v1`  
> **Auth:** Laravel Sanctum (SPA session cookie or `Authorization: Bearer` PAT)  
> **Format:** JSON envelope per `1_ARCHITECTURE_&_SECURITY.md`

All paths below are relative to `/api/v1`. Fields derived from `src/services/*`, `src/context/*`, `src/data/*`, and page components.

---

## Auth

Passwordless email OTP + portal validation. Replaces `authService.js`.

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/sanctum/csrf-cookie` | CSRF token for SPA (Laravel built-in) | No | — | Set-Cookie headers |
| POST | `/auth/otp/request` | Send 6-digit login code | No | `email`, `portal` (`consumer`\|`partner`), `captcha`: `{ honeypot, challenge_answer, expected_challenge, form_started_at, human_confirmed }` | `{ email, expires_in_ms }` — dev: `dev_code` only in local |
| POST | `/auth/otp/verify` | Verify code, establish session | No | `email`, `code` (6 chars) | `{ user: { id, email, name, user_type, onboarding_status }, redirect_to }` |
| POST | `/auth/logout` | Invalidate session | Yes | — | `{ success: true }` |
| GET | `/auth/me` | Current user profile | Yes | — | `{ user, company?, wallet_summary? }` |
| GET | `/auth/resend-cooldown` | Seconds until OTP resend allowed | No | `?email=` | `{ cooldown_seconds }` |

**Errors (mirror frontend):** invalid email, captcha failed, rate limit (`Troppi tentativi`), expired OTP, wrong portal for email type.

**Session TTL:** 7 days (frontend `expiresAt`).

---

## B2C_Wizard

Consumer intake, results, saved matches, user area. Replaces wizard state + `mockUserSearches.js` + `mockMatches.js`.

### Public wizard (pre-auth)

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/sectors/{slug}/wizard` | Wizard step config for sector | No | — | `{ id, title, steps[] }` — mirrors `wizardConfig.js` |
| POST | `/leads` | Submit wizard intake | Optional | `sector_slug` (default `senior-care`), `payload`: `{ autonomy, location, budget, contact }` | `{ lead: { uuid, status }, job_id? }` |
| GET | `/leads/{uuid}/status` | Poll processing state | Optional | — | `{ status, match_count? }` |
| GET | `/leads/{uuid}/results` | Diagnosis + ranked matches | Optional | — | `{ diagnosis, matches[], advisor }` |

**`POST /leads` payload (Senior Care):**

```json
{
  "sector_slug": "senior-care",
  "payload": {
    "autonomy": "parziale",
    "location": { "label": "Milano", "value": "milano" },
    "budget": { "min": 1500, "max": 2500 },
    "contact": { "nome": "Mario", "telefono": "+39 333 123 4567" }
  }
}
```

**`GET .../results` response (derived from `ResultsPage`, `mockMatches.js`):**

```json
{
  "diagnosis": {
    "recommendation": "…",
    "primary": "RSA",
    "secondary": "Assistenza domiciliare",
    "summary": "…"
  },
  "matches": [
    {
      "id": "…",
      "company_id": 1,
      "name": "Casa Serenità",
      "type": "Assistenza Domiciliare",
      "location": "Milano, Zona Navigli",
      "compatibility": 95,
      "image_url": "…",
      "description": "…",
      "pros": ["…"],
      "contact_hint": "…"
    }
  ],
  "advisor": {
    "name": "Marco",
    "role": "Consulente pari",
    "story": "…",
    "cta_label": "…"
  }
}
```

### Consumer authenticated (`/user/*`)

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/user/searches` | List past wizard submissions | Consumer | `?page=` | `{ searches: [{ id, title, location, date, status, match_count, answers }] }` |
| GET | `/user/searches/{id}` | Single search detail | Consumer | — | `{ search, matches? }` |
| GET | `/user/home` | Dashboard summary | Consumer | — | `{ latest_search, display_name }` |
| PATCH | `/user/profile` | Update name, phone | Consumer | `name`, `phone` | `{ user }` |
| GET | `/user/saved-matches` | Bookmarked companies | Consumer | — | `{ ids[] }` |
| POST | `/user/saved-matches` | Toggle save | Consumer | `company_id` or `lead_match_id` | `{ saved: true/false }` |
| POST | `/advisor-bookings` | Book peer advisor call | Consumer | `lead_uuid?`, `name`, `date`, `time` | `{ booking_id }` |

**Search status values:** `processing`, `completed` (from `mockUserSearches.js`).

---

## B2B_CRM

Partner registration, onboarding, marketplace, wallet, CRM, calendar, billing. Replaces `b2bOnboardingService.js`, `B2BContext.jsx`, `mockB2B.js`.

### Registration & onboarding (pre-approval)

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| POST | `/b2b/register` | Create partner account | No | `email`, `organization_name`, `legal_name` | `{ user, company, session }` |
| GET | `/b2b/onboarding` | Get onboarding state + data | B2B | — | `{ status, step, data: { vat, sdi, visura, identity_doc, dynamic, schedule, trust_answers } }` |
| PATCH | `/b2b/onboarding` | Save step progress | B2B | Partial onboarding `data` patch | `{ data, status }` |
| POST | `/b2b/onboarding/documents` | Upload visura / identity | B2B | `multipart`: `type`, `file` | `{ type, file_name, path }` |
| POST | `/b2b/onboarding/submit` | Submit for admin review | B2B | — | `{ status: "pending_review" }` |
| GET | `/b2b/onboarding/status` | Vetting status only | B2B | — | `{ status, redirect_to }` |

**Onboarding `dynamic` (Senior Care):** `sector`, `capacity`, `nonSelfSufficient`, `nightStaff`, `notes`  
**`schedule`:** `{ mon: { open, slots }, … }`  
**`trust_answers`:** `emergency`, `fall`, `family`, `quality`

**Statuses:** `in_progress` → `pending_review` → `approved` (blocks `/pro/*` until approved — `B2BProtectedRoute`).

### Partner dashboard (approved B2B only)

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/b2b/dashboard` | KPIs + activity | B2B + approved | — | `{ stats: { leads_unlocked, conversion_rate, monthly_spend }, activity_feed[], notifications_unread }` |
| GET | `/b2b/wallet` | Balance & summary | B2B | — | `{ balance_credits, total_spent, currency }` |
| POST | `/b2b/wallet/recharge` | Add credits | B2B | `amount`, `payment_method` (`card`, `transfer`) | `{ transaction, wallet }` |
| GET | `/b2b/wallet/transactions` | Invoice/history list | B2B | `?page=` | `{ transactions: [{ id, date, description, amount, status }] }` |

### Lead marketplace

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/b2b/marketplace/leads` | Scored leads for partner | B2B | `?unlocked_only=` | `{ leads: [{ id, match_score, budget, location, name?, phone?, email?, need, unlock_cost, unlocked }] }` |
| POST | `/b2b/marketplace/leads/{id}/unlock` | Pay credits to reveal PII | B2B | — | `{ lead, wallet, crm_client }` — **403** if insufficient credits |

**Unlocked lead → CRM client** (from `B2BContext.unlockLead`):

```json
{
  "crm_client": {
    "id": "CRM-…",
    "cliente": "Maria Rossi",
    "stato": "Nuovo",
    "esigenza": "…",
    "budget": "2.400€",
    "phone": "+39 …",
    "email": "…",
    "location": "Milano (MI)",
    "marketplace_id": "ML-2048"
  }
}
```

### Smart CRM

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/b2b/crm/clients` | Pipeline list | B2B | `?stato=` | `{ clients[] }` |
| PATCH | `/b2b/crm/clients/{id}` | Update CRM status | B2B | `stato`: `Nuovo`\|`Contattato`\|`Visita Fissata`\|`Perso`\|`Chiuso` | `{ client }` |

### Calendar

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/b2b/appointments` | Scheduled visits | B2B | `?from=&to=` | `{ appointments: [{ id, client_id, cliente, date, time, note }] }` |
| POST | `/b2b/appointments` | Schedule visit | B2B | `client_id`, `date`, `time`, `note?` | `{ appointment, client }` — sets CRM `Visita Fissata` |

### Notifications

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/b2b/notifications` | Partner notifications | B2B | — | `{ notifications[], unread_count }` |
| PATCH | `/b2b/notifications/{id}/read` | Mark read | B2B | — | `{ notification }` |
| POST | `/b2b/notifications/read-all` | Mark all read | B2B | — | `{ success: true }` |

---

## Admin_GodMode

Platform operations at `/admin` (SPA currently **unauthenticated** — API must enforce `super_admin`).

### Dashboard & analytics

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/admin/metrics` | God Mode KPIs | SuperAdmin | — | `{ mrr, active_leads_today, active_partners, pending_approvals, churn, conversion_rate, avg_deal_size }` |
| GET | `/admin/revenue/timeline` | Revenue chart | SuperAdmin | `?days=7` | `{ points: [{ day, amount }] }` |
| GET | `/admin/leads/flow` | Lead volume chart | SuperAdmin | `?days=14` | `{ points: [{ day, leads, revenue }] }` |
| GET | `/admin/portfolio/summary` | AUM overview | SuperAdmin | — | `{ total_aum, revenue_under_management, monthly_growth, active_contracts }` |
| GET | `/admin/portfolio/allocation` | Sector/region/tier donuts | SuperAdmin | — | `{ by_sector[], by_region[], by_tier[] }` |
| GET | `/admin/portfolio/partners` | Partner portfolio cards | SuperAdmin | — | `{ partners: [{ id, nome, tier, aum, revenue_share, trend[], risk }] }` |
| GET | `/admin/risk-indicators` | Risk widgets | SuperAdmin | — | `{ indicators[] }` |

### Transactions

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/admin/transactions` | Global transaction list | SuperAdmin | `?status=&page=` | `{ summary: { today, week, month }, transactions[] }` |
| GET | `/admin/transactions/{id}` | Detail drawer | SuperAdmin | — | `{ id, partner, importo, stato, data, tipo, metodo, riferimento, note }` |

**Transaction `stato`:** `Completata`, `In attesa`, `Fallita`  
**`tipo`:** `Abbonamento mensile`, `Lead bundle`, `Commissione`, `Add-on CRM`, `Lead singolo`

### Partner management

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/admin/partners` | List registrations | SuperAdmin | `?stato=Pending` | `{ partners: [{ id, nome_struttura, partita_iva, stato, citta, submitted_at }] }` |
| GET | `/admin/partners/{id}` | Partner detail + docs | SuperAdmin | — | `{ company, documents, trust_test, trust_score }` |
| POST | `/admin/partners/{id}/approve` | Approve vetting | SuperAdmin | — | `{ company: { vetting_status: "approved" } }` |
| POST | `/admin/partners/{id}/reject` | Reject application | SuperAdmin | `reason?` | `{ company: { vetting_status: "rejected" } }` |
| POST | `/admin/partners/{id}/suspend` | Suspend active partner | SuperAdmin | `reason?` | `{ company: { vetting_status: "suspended" } }` |
| POST | `/admin/partners/{id}/impersonate` | Issue short-lived partner session | SuperAdmin | — | `{ impersonation_token, expires_at }` |

**Partner `stato` (admin UI):** `Pending`, `Active`, `Suspended`

### Lead router

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/admin/leads` | All platform leads | SuperAdmin | `?stato=&page=` | `{ leads: [{ id, utente, esigenza, ai_match, stato, email, telefono, partner_assegnato, note, created_at }] }` |
| GET | `/admin/leads/{id}` | Lead detail | SuperAdmin | — | Full lead + matches |
| PATCH | `/admin/leads/{id}/assign` | Manual partner assignment | SuperAdmin | `partner_id` (company) | `{ lead, assignment }` |
| POST | `/admin/leads/{id}/reroute` | Trigger AI re-match | SuperAdmin | — | `{ job_id }` |

**Lead admin `stato`:** `In routing`, `Assegnato`, `In attesa`, `Chiuso`

### Settings & sectors

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/admin/settings` | Platform config | SuperAdmin | — | `{ security, automations, notifications }` |
| PATCH | `/admin/settings` | Update config | SuperAdmin | Partial settings JSON | `{ settings }` |
| GET | `/admin/sectors` | List sectors | SuperAdmin | — | `{ sectors[] }` |
| PATCH | `/admin/sectors/{id}` | Update schemas/rules | SuperAdmin | `wizard_schema`, `matching_rules`, … | `{ sector }` |

### Admin notifications

| Method | Path | Purpose | Auth | Key request | Key response |
|--------|------|---------|------|-------------|--------------|
| GET | `/admin/notifications` | God Mode alerts | SuperAdmin | — | `{ notifications[] }` |

---

## Cross-cutting endpoints (future)

| Method | Path | Purpose | Auth |
|--------|------|---------|------|
| GET | `/health` | Load balancer health | No |
| POST | `/webhooks/payments/{provider}` | Payment confirmation | Signature |
| GET | `/locations/autocomplete` | City search for wizard | No |

---

## Implementation priority

| Phase | Domains | Rationale |
|-------|---------|-----------|
| **P0** | Auth, `POST /leads`, `GET /leads/{uuid}/results` | Unblock B2C production |
| **P1** | B2B register/onboarding, admin partner approve | Partner supply |
| **P2** | Marketplace unlock, wallet, CRM | Monetization loop |
| **P3** | Admin metrics, lead router, transactions | God Mode operations |

---

## Assumptions

| Topic | Assumption |
|-------|------------|
| API versioning | `/api/v1` prefix on all routes |
| Pagination | Cursor or `page`/`per_page` — not in mock UI |
| File uploads | Multipart to API; store path in `company_documents` |
| i18n | Italian user messages; English `code` strings |
| Impersonate | Audit log entry required; token expires in 15 min |
| Webhooks | Stripe/Mollie for `wallet/recharge` — UI mocks instant success today |

---

## Auth matrix (endpoint × role)

Legend: **P** = Public · **C** = Consumer · **B** = B2B approved partner · **Bo** = B2B onboarding (not approved) · **A** = Super Admin · **—** = Forbidden

| Method | Path | P | C | Bo | B | A |
|--------|------|---|---|----|----|---|
| GET | `/sanctum/csrf-cookie` | ✓ | ✓ | ✓ | ✓ | ✓ |
| POST | `/auth/otp/request` | ✓ | ✓ | ✓ | ✓ | ✓ |
| POST | `/auth/otp/verify` | ✓ | ✓ | ✓ | ✓ | ✓ |
| POST | `/auth/logout` | — | ✓ | ✓ | ✓ | ✓ |
| GET | `/auth/me` | — | ✓ | ✓ | ✓ | ✓ |
| GET | `/sectors/{slug}/wizard` | ✓ | ✓ | ✓ | ✓ | ✓ |
| POST | `/leads` | ✓ | ✓ | — | — | — |
| GET | `/leads/{uuid}/results` | ✓ | ✓ | — | — | A* |
| GET | `/user/*` | — | ✓ | — | — | — |
| POST | `/b2b/register` | ✓ | — | — | — | — |
| GET/PATCH | `/b2b/onboarding*` | — | — | ✓ | — | — |
| GET | `/b2b/dashboard`, marketplace, crm, … | — | — | — | ✓ | — |
| GET | `/admin/*` | — | — | — | — | ✓ |

\* Admin read of consumer results [ASSUNZIONE] for support only.

### Portal enforcement [VERIFICATO]

| `portal` param | Allowed `user_type` after verify |
|----------------|----------------------------------|
| `consumer` | `consumer` only — reject `b2b` with 403 |
| `partner` | `b2b` only — reject `consumer` with 403 |
| `admin` [ASSUNZIONE] | `superadmin` only |

---

## API versioning strategy

| Rule | Detail |
|------|--------|
| **Prefix** | All routes under `/api/v1/` |
| **Breaking changes** | New major version `/api/v2/`; v1 maintained ≥ 6 months |
| **Additive changes** | New optional JSON fields — no version bump |
| **Deprecation** | `Sunset` header + changelog; remove after grace period |
| **Frontend pin** | `VITE_API_BASE_URL` includes `/api/v1` |
| **Accept header** | Optional `Accept: application/vnd.wenando.v1+json` [ASSUNZIONE] |

---

## Idempotency keys

Required on **financial** and **irreversible** mutations:

| Endpoint | Header | TTL | Behavior |
|----------|--------|-----|----------|
| `POST /b2b/marketplace/leads/{id}/unlock` | `Idempotency-Key: <uuid>` | 24h | Same key → return original unlock response; no double debit |
| `POST /b2b/wallet/recharge` | `Idempotency-Key: <uuid>` | 24h | Prevent duplicate charges |
| `POST /b2b/appointments` | `Idempotency-Key: <uuid>` | 1h | Prevent duplicate visits |
| `POST /admin/partners/{id}/approve` | `Idempotency-Key: <uuid>` | 24h | Safe retry on network failure |

Storage: `idempotency_keys` table [ASSUNZIONE] or cache DB with `{ key, user_id, route, response_hash, created_at }`.

Duplicate request with same key + different body → **422** `IDEMPOTENCY_KEY_MISMATCH`.
