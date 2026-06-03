# Wenando — User Flows & Sequence Diagrams

> Flussi derivati dal frontend React. Error/retry paths inclusi dove [VERIFICATO] nel mock.

---

## 1. OTP Auth — Consumer (`/accedi`)

[VERIFICATO] `Accedi.jsx`, `authService.js`, `HumanVerification.jsx`

```mermaid
sequenceDiagram
  actor U as Utente
  participant SPA as wenando.com
  participant API as api.wenando.com
  participant Mail as Email

  U->>SPA: Inserisce email
  SPA->>SPA: validateEmailForPortal(consumer)
  alt email partner
    SPA-->>U: Errore cross-portal
  end
  U->>SPA: Completa captcha (4 digit + checkbox)
  SPA->>API: GET /sanctum/csrf-cookie
  SPA->>API: POST /auth/otp/request {email, portal:consumer, captcha}
  alt rate limit (3/15min)
    API-->>SPA: 429 OTP_RATE_LIMITED
    SPA-->>U: Troppi tentativi
  else captcha fail
    API-->>SPA: 422 CAPTCHA_FAILED
  else ok
    API->>Mail: Send 6-digit OTP (TTL 10min)
    API-->>SPA: {expires_in_ms}
    SPA-->>U: Step codice OTP
  end
  U->>SPA: Inserisce 6 cifre
  SPA->>API: POST /auth/otp/verify {email, code}
  alt expired
    API-->>SPA: 422 OTP_EXPIRED
    SPA-->>U: Richiedi nuovo codice
  else wrong code
    API-->>SPA: 422 OTP_INVALID
  else ok
    API-->>SPA: Set-Cookie session + {user, redirect_to:/user}
    SPA->>SPA: AuthContext.setSession
    SPA-->>U: Redirect /user
  end
```

**Resend cooldown [VERIFICATO]:** 60s between sends — UI timer via `getResendCooldown`.

---

## 2. OTP Auth — B2B Partner (`/pro/accedi`)

Same flow as §1 with `portal: partner` and redirect:
- `approved` → `/pro/dashboard` [VERIFICATO] `getB2BRedirectPath`
- `pending_review` / `in_progress` → `/pro/onboarding`

---

## 3. OTP Auth — Admin [ASSUNZIONE]

```mermaid
sequenceDiagram
  participant SPA as /admin (future /admin/accedi)
  participant API as api.wenando.com

  Note over SPA: Today /admin has NO auth [VERIFICATO]
  SPA->>API: POST /auth/otp/request {portal:admin}
  API->>API: Require user_type=superadmin
  API-->>SPA: session + redirect /admin
```

---

## 4. B2C Wizard → Results → Advisor

[VERIFICATO] `Wizard.jsx`, `ResultsPage.jsx`, `BookingSheet.jsx`

```mermaid
sequenceDiagram
  actor U as Famiglia
  participant SPA as wenando.com
  participant API as api.wenando.com
  participant Q as Queue

  U->>SPA: Step 1-4 wizard (autonomy→location→budget→contact)
  SPA->>SPA: AnalyzingState (~mock delay)
  Note over SPA: Today: navigate /results with state only
  SPA->>API: POST /leads {sector_slug, payload}
  API->>Q: ProcessLeadMatching job
  API-->>SPA: {lead.uuid, status:processing}
  SPA->>SPA: AnalyzingState poll [ASSUNZIONE]
  loop poll GET /leads/{uuid}/status
    API-->>SPA: processing | completed
  end
  SPA->>API: GET /leads/{uuid}/results
  API-->>SPA: {diagnosis, matches[], advisor}
  SPA-->>U: ResultsPage (diagnosis + MatchCards)
  U->>SPA: Prenota chiamata consulente
  SPA->>API: POST /advisor-bookings {name, date, time, lead_uuid}
  API-->>SPA: {booking_id}
  SPA-->>U: Conferma prenotazione
```

**Guard [VERIFICATO]:** ResultsPage redirects to `/wizard` if `!answers.autonomy`.

**Post-login link [VERIFICATO]:** Header links to `/user/ricerche` (searches may be empty until API persists).

---

## 5. B2B Register → Onboarding → Vetting → Approved

[VERIFICATO] `Register.jsx`, `Onboarding.jsx`, `ManagePartners.jsx`

```mermaid
sequenceDiagram
  actor P as Partner
  participant SPA as wenando.com/pro
  participant API as api.wenando.com
  actor A as Admin

  P->>SPA: POST register {email, org, legal}
  Note over SPA: Mock: instant session, no OTP
  SPA->>API: POST /b2b/register
  API-->>SPA: {user, company, session}
  SPA->>SPA: /pro/onboarding step 0-3
  loop each step PATCH /b2b/onboarding
    P->>SPA: Legal / Ops / Trust / Review
    SPA->>API: PATCH partial data + POST documents
  end
  P->>SPA: Invia per revisione
  SPA->>API: POST /b2b/onboarding/submit
  API-->>SPA: status pending_review
  SPA-->>P: PendingReview screen
  A->>SPA: ManagePartners Approva
  SPA->>API: POST /admin/partners/{id}/approve
  API->>API: vetting_status=approved, create wallet
  API-->>SPA: ok
  P->>SPA: Accedi / refresh
  SPA->>SPA: B2BProtectedRoute passes
  SPA-->>P: /pro/dashboard
```

**Reject path [VERIFICATO UI]:** Partner card removed from list — maps to `rejected` + notification [ASSUNZIONE].

---

## 6. Marketplace Unlock (15 credits)

[VERIFICATO] `B2BContext.unlockLead`, `LeadMarketplace.jsx`

```mermaid
sequenceDiagram
  actor P as Partner
  participant SPA as B2B Portal
  participant API as api.wenando.com

  P->>SPA: View marketplace (blurred PII)
  P->>SPA: Click Sblocca (-15€)
  alt wallet < 15
    SPA-->>P: Toast credito insufficiente → recharge modal
  else confirm modal
    P->>SPA: Conferma
    SPA->>API: POST /b2b/marketplace/leads/{id}/unlock
    Note over API: Idempotency-Key header
    API->>API: BEGIN TRANSACTION
    API->>API: debit wallet, set unlocked_at
    API->>API: crm_status=nuovo, insert transaction
    API->>API: COMMIT
    API-->>SPA: {lead full PII, wallet, crm_client}
    SPA-->>P: Toast success + CRM entry
  end
```

---

## 7. CRM Pipeline Transitions

[VERIFICATO] `SmartCRM.jsx`, `B2BContext.updateCRMStatus`, `scheduleVisit`

```mermaid
sequenceDiagram
  participant SPA as SmartCRM
  participant API as api.wenando.com

  SPA->>API: GET /b2b/crm/clients
  API-->>SPA: clients[] (unlocked only)
  alt manual status change
    SPA->>API: PATCH /b2b/crm/clients/{id} {stato:Contattato}
  else schedule visit
    SPA->>API: POST /b2b/appointments {client_id, date, time}
    API->>API: crm_status=visita_fissata
    API-->>SPA: {appointment, client}
  end
  API->>API: activity_feed entry [ASSUNZIONE]
```

**CRM statuses [VERIFICATO]:** Nuovo → Contattato → Visita Fissata → Chiuso | Perso

---

## 8. Admin — Approve / Reject / Suspend / Impersonate / Route Lead

```mermaid
sequenceDiagram
  actor A as Super Admin
  participant SPA as /admin
  participant API as api.wenando.com
  participant Audit as audit_logs

  A->>SPA: Approva partner PR-001
  SPA->>API: POST /admin/partners/{id}/approve
  API->>Audit: log action
  API-->>SPA: company approved

  A->>SPA: Impersonate partner
  SPA->>API: POST /admin/partners/{id}/impersonate
  API->>Audit: log impersonation + IP
  API-->>SPA: {impersonation_token, expires_at:15min}
  SPA->>SPA: Switch session [ASSUNZIONE]

  A->>SPA: Lead Router override
  SPA->>API: PATCH /admin/leads/{id}/assign {partner_id}
  API->>API: admin_status=Assegnato
  API-->>SPA: updated lead

  A->>SPA: Reroute AI
  SPA->>API: POST /admin/leads/{id}/reroute
  API-->>SPA: {job_id}
```

---

## 9. Matching algorithm — Decision tree

Logic today is **mock**; target behavior inferred from UI + data shapes.

```mermaid
flowchart TD
  Start([Lead submitted]) --> Sector{sector_id match?}
  Sector -->|no| Exclude[Exclude company]
  Sector -->|yes| Vetting{vetting_status = approved?}
  Vetting -->|no| Exclude
  Vetting -->|yes| Geo{location in service area?}
  Geo -->|no| Exclude
  Geo -->|yes| Budget{budget overlap?}
  Budget -->|no| LowScore[score -= 30]
  Budget -->|yes| Cap{capacity available?}
  Cap -->|no| LowScore
  Cap -->|yes| Autonomy{autonomy vs dynamic_attributes?}
  Autonomy -->|mismatch nonSelfSufficient| LowScore
  Autonomy -->|match| HighScore[score += weights]
  LowScore --> Trust[Apply trust_score factor]
  HighScore --> Trust
  Trust --> Rank[Rank top N]
  Rank --> B2C{score >= B2C threshold?}
  B2C -->|yes| ConsumerVisible[is_visible_to_consumer=true]
  B2C -->|no| SkipB2C
  Rank --> Mkt{score >= 80?}
  Mkt -->|yes| Marketplace[is_in_marketplace=true]
  Mkt -->|no| SkipMkt
  ConsumerVisible --> Done([Persist lead_matches])
  Marketplace --> Done
```

**Weights [ASSUNZIONE] from `sectors.matching_rules` JSON:**

| Factor | Weight | Source hint |
|--------|--------|-------------|
| Budget overlap | 25% | wizard budget vs company pricing [ASSUNZIONE] |
| Geo proximity | 20% | location_label vs company.city |
| Autonomy fit | 25% | non-autosufficient → requires nonSelfSufficient |
| Trust score | 15% | company_trust_scores.score |
| Capacity | 10% | dynamic.capacity |
| Night staff | 5% | non-autosufficient + nightStaff bonus |

**min_match_score_marketplace:** 80 [VERIFICATO] seed in database_master.sql

---

## 10. Error & retry paths

| Flow | Error | User message [VERIFICATO] | Retry |
|------|-------|---------------------------|-------|
| OTP request | Rate limit | Troppi tentativi. Riprova tra N min | Wait window |
| OTP verify | Expired | Codice scaduto | New request |
| OTP verify | Invalid | Codice non valido | Re-enter |
| Captcha | Too fast | Attendi un momento | Wait 2s [VERIFICATO] |
| Unlock | Insufficient credits | Credito insufficiente | Recharge |
| Onboarding step | Missing fields | Button disabled | Fill fields |
| Results | No answers | Redirect wizard | Restart wizard |
| Network | 5xx | Generic [ASSUNZIONE] | Exponential backoff |

---

## 11. Wallet recharge flow [VERIFICATO mock]

```mermaid
sequenceDiagram
  participant P as Partner
  participant SPA as B2B
  participant API as api.wenando.com
  participant Pay as Payment [ASSUNZIONE]

  P->>SPA: Open recharge modal
  P->>SPA: Enter amount
  SPA->>API: POST /b2b/wallet/recharge {amount, payment_method}
  alt mock today
    API-->>SPA: instant completed
  else production
    API->>Pay: Create payment intent
    Pay-->>API: webhook completed
    API-->>SPA: wallet updated
  end
  SPA-->>P: Toast + invoice row
```
