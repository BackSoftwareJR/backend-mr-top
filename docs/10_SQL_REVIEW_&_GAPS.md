# Wenando — SQL Review & Gaps Analysis

> Review of `backend/database_master.sql` against frontend analysis (Giugno 2026).
> **Last updated:** 2026-06-04 — schema gaps from §4 largely addressed via Laravel migrations.

---

## 1. Executive summary

| Area | Alignment | Score |
|------|-----------|-------|
| Core entities (users, companies, leads, matches, wallet) | Strong | 95% |
| Onboarding JSON shapes | Strong | 90% |
| CRM / marketplace | Strong | 90% |
| Admin portfolio metrics | Partial — mostly computed [ASSUNZIONE] | 60% |
| Auth / OTP | Strong | 95% |
| Missing tables (audit, idempotency, consent) | **Addressed** — see §4 | 95% |

**Verdict:** `database_master.sql` remains the **Phase 0 bootstrap** reference. Laravel migrations (2026-06-03/04) implement the documented deltas. Remaining gaps are deferred features and operational seeds — see §12.

---

## 2. Frontend ↔ SQL alignment matrix

| Frontend concept | SQL table(s) | Status |
|------------------|--------------|--------|
| `authService` session | `users`, `sessions` | ✓ |
| OTP store | `otp_codes` | ✓ (+ add `portal` used) |
| B2B registration | `companies`, `company_user`, `users` | ✓ |
| Onboarding data | `companies.*`, `company_documents`, `trust_tests` | ✓ |
| `vetting_status` flow | `companies.vetting_status` | ✓ maps `in_progress`, `pending_review`, `approved` |
| Wizard payload | `leads.payload` + denormalized cols | ✓ |
| `mockMatches` | `lead_matches` + `company_profiles` | ✓ [DONE `000032`] |
| Marketplace unlock | `lead_matches.unlocked_at`, `wallets`, `transactions` | ✓ |
| CRM pipeline | `lead_matches.crm_status` | ✓ |
| Appointments | `appointments` | ✓ |
| Saved matches | `saved_matches` | ✓ |
| User searches | `leads` by `user_id`, `leads.title` | ✓ [DONE `000035`] |
| Admin transactions | `transactions` | ✓ |
| Admin lead router | `leads.admin_status`, `admin_notes` | ✓ |
| Notifications | `notifications` | ✓ |
| Advisor booking | `appointments.type=advisor`, `advisor_profiles` | ✓ [DONE `000036`] |
| Impersonation | `impersonation_sessions`, `audit_logs` | ✓ [DONE `000027`, `000028`] |
| Audit log | `audit_logs` | ✓ [DONE `000027`] |
| Consent records | `consent_logs` | ✓ [DONE `2026_06_03_000001` + alters] |
| Idempotency keys | `idempotency_keys` | ✓ [DONE `000029`] |
| Company public profile (name, image, pros) | `company_profiles` | ✓ [DONE `000032`] |
| GDPR erasure | `data_erasure_requests` | ✓ [DONE `000030`] |
| Admin settings | `platform_settings` | ✓ [DONE `000031`] |

---

## 3. Recommended index additions

| Index | Status | Migration |
|-------|--------|-----------|
| `lead_matches_lead_b2c_index` | **DONE** | `2026_06_04_000034_add_recommended_performance_indexes` |
| `lead_matches_company_crm_index` | **DONE** | `2026_06_04_000034_add_recommended_performance_indexes` |
| `companies_city_index` | **DONE** | `2026_06_04_000034_add_recommended_performance_indexes` |
| `notifications_unread_index` | **DONE** | `2026_06_04_000034_add_recommended_performance_indexes` |
| `leads_public_ref_index` | **OPEN** — covered by `public_ref` UNIQUE on `leads`; dedicated non-unique index optional for admin prefix search | — |

Existing indexes in master SQL are solid for marketplace and wallet history.

---

## 4. Schema additions — status tracker

### 4.1 `audit_logs` — **DONE** [P1]

Migration: `2026_06_04_000027_create_audit_logs_table.php`

Required for: impersonation, approve/reject, manual lead assign.

### 4.2 `impersonation_sessions` — **DONE** [P1]

Migration: `2026_06_04_000028_create_impersonation_sessions_table.php`

Short-lived admin → partner context. Scheduled cleanup via `impersonation:close-expired`.

### 4.3 `idempotency_keys` — **DONE** [P2]

Migration: `2026_06_04_000029_create_idempotency_keys_table.php`

For unlock/recharge deduplication.

### 4.4 `consent_logs` — **DONE** [P2 GDPR]

Migrations:
- `2026_06_03_000001_create_consent_logs_table.php`
- `2026_06_03_000025_add_soft_deletes_to_consent_logs_table.php`
- `2026_06_03_000026_add_lead_id_and_metadata_to_consent_logs_table.php`
- `2026_06_04_000001_add_terms_b2b_to_consent_logs_consent_type.php`

Wizard privacy acceptance proof. Retention job: `consent-logs:anonymize`.

### 4.5 `company_profiles` — **DONE** [P2 B2C results]

Migration: `2026_06_04_000032_create_company_profiles_table.php`

Fields: `display_name`, `service_type`, `description`, `pros` JSON, `image_url`, `contact_hint`, `location_label`, `tagline`.

Backfill for legacy rows: `php artisan company-profiles:backfill [--dry-run]`.

### 4.6 `leads.title` — **DONE** [P3]

Migration: `2026_06_04_000035_add_title_to_leads_table.php`

User-editable search title ("Ricerca per la Mamma"). Separate `lead_search_meta` table not needed.

### 4.7 `platform_settings` — **DONE** [P3]

Migration: `2026_06_04_000031_create_platform_settings_table.php`

Admin settings JSON (thresholds, IP allowlist).

### 4.8 `data_erasure_requests` — **DONE** [P2 GDPR]

Migration: `2026_06_04_000030_create_data_erasure_requests_table.php`

Consumer right-to-erasure workflow.

### 4.9 `advisor_profiles` — **DONE** [P3]

Migration: `2026_06_04_000036_create_advisor_profiles_table.php`

Peer advisor config (replaces static mock "Marco").

### 4.10 `lead_matches.public_ref` — **DONE**

Migration: `2026_06_04_000033_add_public_ref_to_lead_matches_table.php`

CRM public ref (ML-####).

### 4.11 `notification_templates` — **OPEN** [Deferred]

Email/SMS templates for OTP, approve partner — not in frontend.

### 4.12 `webhooks` / `payment_intents` — **OPEN** [Deferred]

Wallet recharge production — UI mocks instant success.

---

## 5. Enum & label mapping gaps

| Frontend (Italian UI) | DB enum | Action |
|-----------------------|---------|--------|
| CRM `Nuovo` | `nuovo` | API transformer |
| Admin partner `Pending` | `pending_review` | Map in admin API |
| Admin partner `Active` | `approved` | Map |
| Admin partner `Suspended` | `suspended` | ✓ |
| Transaction `Completata` | `completed` | Transformer |
| Lead admin `In routing` | `admin_status` VARCHAR | ✓ flexible |

Do not duplicate Italian strings in MySQL ENUMs — keep canonical English/snake in DB.

---

## 6. Data type notes

| Topic | Status | Notes |
|-------|--------|-------|
| `lead_matches.public_ref` | **DONE** | `2026_06_04_000033` |
| `crm` client id CRM-102 | ✓ | Use `lead_matches.id` or `public_ref` — not separate table |
| Credits vs cents | [ASSUNZIONE] | Wallet uses credits; transactions have `amount_cents` — document 1 credit = 100 cents |
| `companies.rejection_reason` | ✓ | Present for reject flow |
| Soft delete leads | ✓ | Align with GDPR erasure job |
| `leads.title` | **DONE** | `2026_06_04_000035` |

---

## 7. Migration ordering notes

When moving from `database_master.sql` to Laravel migrations:

| Order | Migration group | Status |
|-------|-----------------|--------|
| 1 | users, password_reset_tokens, sessions | ✓ |
| 2 | permissions, roles, pivots | ✓ |
| 3 | sectors (+ seed senior-care) | ✓ |
| 4 | companies, company_user, company_documents, company_sectors | ✓ |
| 5 | trust_tests, company_trust_scores | ✓ |
| 6 | leads (+ title) | ✓ |
| 7 | lead_matches (+ public_ref) | ✓ |
| 8 | wallets, transactions | ✓ |
| 9 | appointments, notifications, saved_matches | ✓ |
| 10 | otp_codes | ✓ |
| 11 | jobs, failed_jobs, cache | ✓ |
| 12 | personal_access_tokens | ✓ |
| 13 | audit_logs, impersonation_sessions, idempotency_keys, consent_logs, data_erasure_requests, platform_settings, company_profiles, advisor_profiles | ✓ |
| 14 | FK `model_has_roles.company_id` (deferred in master SQL) | ✓ |
| 15 | Performance indexes | ✓ `000034` |

---

## 8. Seed data gaps

Master SQL seeds:
- `sectors` (senior-care, home-renovation stub) ✓
- `roles`, `permissions` ✓

**Dev seeds [DONE]** — see [DEV_SEED.md](./DEV_SEED.md) (`DevUsersSeeder` + `DemoMatchCompaniesSeeder`):
- Demo consumer `consumer@wenando.test` + lead `LD-SEED-CONSUMER`
- Approved partner `partner@care.it` (company profile + wallet credits)
- Pending partner `partner-pending@wenando.test`
- Super admin `admin@wenando.test` (override via `SEED_SUPERADMIN_EMAIL`)

---

## 9. Frontend features without SQL home

| Feature | Resolution |
|---------|------------|
| Activity feed ACT-* | ✓ `activity_feed` table (`000038`); written on recharge/unlock/CRM status |
| Dashboard KPIs (leadsSbloccati, conversion) | Aggregate queries on transactions + lead_matches ✓ |
| Admin portfolio AUM | Computed from transactions [ASSUNZIONE] — no AUM column [OPEN] |
| B2B demo autodemo flag | Client-only localStorage — no DB ✓ |
| Partner tier Enterprise/Growth | ✓ `companies.tier` |

---

## 10. Comments added to database_master.sql

See header block in `../database_master.sql`. **Note:** master SQL header may still list pre-migration gaps; treat this doc and `database/migrations/` as source of truth for implemented schema.

Previously listed gaps now addressed:
- ~~Missing `lead_matches.public_ref`~~ → `000033`
- ~~Missing audit/idempotency/consent tables~~ → `000027`, `000028`, `000029`, consent_logs chain
- ~~Missing company consumer profile fields~~ → `000032`
- ~~Recommended indexes (§3)~~ → `000034` (except optional `leads_public_ref_index`)
- ~~Activity feed persistence (P3)~~ → `000038` `activity_feed`

---

## 11. Open questions for human review

1. **1 credit = €1 vs amount_cents** — confirm ledger semantics before implement.
2. **Multi-unlock** — can N partners unlock same lead? Frontend implies yes.
3. ~~**Consumer email on wizard**~~ — **DONE** (Giugno 2026): optional `email` on wizard contact step → `payload.contact.email` → `leads.contact_email`; see `LeadSubmissionTest`, `wizardConfig.js`.
4. **Trust score MVP** — rule-based vs manual admin score?
5. **Import master SQL vs migrations-only** for first Hostinger deploy?
6. **Company images** — CDN URLs vs uploaded media?
7. **Legal review** — GDPR consent copy and DPA with partners before launch.

---

## 12. Remaining OPEN gaps (summary)

| Item | Priority | Notes |
|------|----------|-------|
| `notification_templates` | Deferred | No frontend requirement yet |
| `webhooks` / `payment_intents` | Deferred | Wallet recharge production |
| `leads_public_ref_index` | P3 optional | UNIQUE on `public_ref` may suffice |
| ~~Dev seed data (demo users, mock companies)~~ | Done | §8, [DEV_SEED.md](./DEV_SEED.md) |
| ~~Activity feed persistence~~ | Done | `activity_feed` migration `000038` + `ActivityFeedService` |
| Admin portfolio AUM column | P3 | Computed acceptable [ASSUNZIONE] |
| Open questions §11 | — | Product/legal decisions |
