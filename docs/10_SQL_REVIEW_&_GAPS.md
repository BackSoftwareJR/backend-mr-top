# Wenando — SQL Review & Gaps Analysis

> Review of `backend/database_master.sql` against frontend analysis (Giugno 2026).

---

## 1. Executive summary

| Area | Alignment | Score |
|------|-----------|-------|
| Core entities (users, companies, leads, matches, wallet) | Strong | 95% |
| Onboarding JSON shapes | Strong | 90% |
| CRM / marketplace | Strong | 90% |
| Admin portfolio metrics | Partial — mostly computed [ASSUNZIONE] | 60% |
| Auth / OTP | Strong | 95% |
| Missing tables (audit, idempotency, consent) | Gaps documented below | — |

**Verdict:** `database_master.sql` is suitable as **Phase 0 bootstrap**. Implement Laravel migrations as deltas from this baseline.

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
| `mockMatches` | `lead_matches` + `companies` | ✓ need company profile cols [gap] |
| Marketplace unlock | `lead_matches.unlocked_at`, `wallets`, `transactions` | ✓ |
| CRM pipeline | `lead_matches.crm_status` | ✓ |
| Appointments | `appointments` | ✓ |
| Saved matches | `saved_matches` | ✓ |
| User searches | `leads` by `user_id` | ✓ (title field missing — gap) |
| Admin transactions | `transactions` | ✓ |
| Admin lead router | `leads.admin_status`, `admin_notes` | ✓ |
| Notifications | `notifications` | ✓ |
| Advisor booking | `appointments.type=advisor` | ✓ |
| Impersonation | **Missing** | ✗ |
| Audit log | **Missing** | ✗ |
| Consent records | **Missing** | ✗ |
| Idempotency keys | **Missing** | ✗ |
| Company public profile (name, image, pros) | **Partial** | △ |

---

## 3. Recommended index additions

```sql
-- B2C results fetch
ALTER TABLE lead_matches
  ADD INDEX lead_matches_lead_b2c_index (lead_id, is_visible_to_consumer, match_score);

-- CRM list by company + status
ALTER TABLE lead_matches
  ADD INDEX lead_matches_company_crm_index (company_id, crm_status, unlocked_at);

-- Admin partners by city
ALTER TABLE companies ADD INDEX companies_city_index (city);

-- Unread notifications
ALTER TABLE notifications
  ADD INDEX notifications_unread_index (notifiable_type, notifiable_id, read_at);

-- Lead public ref admin search
ALTER TABLE leads ADD INDEX leads_public_ref_index (public_ref);
```

Existing indexes in master SQL are solid for marketplace and wallet history.

---

## 4. Recommended schema additions (deferred tables)

### 4.1 `audit_logs` [Priority P1]

```sql
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  subject_type VARCHAR(255) NULL,
  subject_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NULL
);
```

Required for: impersonation, approve/reject, manual lead assign.

### 4.2 `impersonation_sessions` [P1]

Short-lived admin → partner context.

### 4.3 `idempotency_keys` [P2]

For unlock/recharge deduplication.

### 4.4 `consent_records` [P2 GDPR]

Wizard privacy acceptance proof.

### 4.5 `company_profiles` or columns on `companies` [P2 B2C results]

[VERIFICATO] mockMatches needs:
- `display_name`, `service_type`, `description`, `pros` JSON, `image_url`, `contact_hint`

Currently not in `companies` table — add JSON `consumer_profile` or separate table.

### 4.6 `lead_search_meta` [P3]

Optional `title` for user searches ("Ricerca per la Mamma") — column on `leads` or user-editable metadata.

### 4.7 `platform_settings` [P3]

Admin settings JSON (thresholds, IP allowlist) — referenced in AdminSettings placeholder UI.

### 4.8 `notification_templates` [Deferred]

Email/SMS templates for OTP, approve partner — not in frontend.

### 4.9 `webhooks` / `payment_intents` [Deferred]

Wallet recharge production — UI mocks instant success.

### 4.10 `advisor_profiles` [P3]

Peer advisor "Marco" — static in mock; may remain config row.

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

| Topic | Recommendation |
|-------|----------------|
| `lead_matches.public_ref` | **Missing** — add `public_ref VARCHAR(32)` for ML-#### [GAP] |
| `crm` client id CRM-102 | Use `lead_matches.id` or `public_ref` — not separate table ✓ |
| Credits vs cents | Wallet uses credits; transactions have `amount_cents` — document 1 credit = 100 cents [ASSUNZIONE] |
| `companies.rejection_reason` | ✓ present for reject flow |
| Soft delete leads | ✓ — align with GDPR erasure job |

---

## 7. Migration ordering notes

When moving from `database_master.sql` to Laravel migrations:

| Order | Migration group |
|-------|-----------------|
| 1 | users, password_reset_tokens, sessions |
| 2 | permissions, roles, pivots |
| 3 | sectors (+ seed senior-care) |
| 4 | companies, company_user, company_documents, company_sectors |
| 5 | trust_tests, company_trust_scores |
| 6 | leads |
| 7 | lead_matches |
| 8 | wallets, transactions |
| 9 | appointments, notifications, saved_matches |
| 10 | otp_codes |
| 11 | jobs, failed_jobs, cache |
| 12 | personal_access_tokens |
| 13 | audit_logs, impersonation_sessions (new) |
| 14 | FK `model_has_roles.company_id` (deferred in master SQL) ✓ |

---

## 8. Seed data gaps

Master SQL seeds:
- `sectors` (senior-care, home-renovation stub) ✓
- `roles`, `permissions` ✓

**Missing seeds for dev:**
- Demo consumer user
- Demo partner `partner@care.it` approved
- Sample companies matching mockMatches names
- Super admin user [ASSUNZIONE]

---

## 9. Frontend features without SQL home

| Feature | Resolution |
|---------|------------|
| Activity feed ACT-* | Ephemeral or `activity_logs` table [ASSUNZIONE] — not in master SQL |
| Dashboard KPIs (leadsSbloccati, conversion) | Aggregate queries on transactions + lead_matches |
| Admin portfolio AUM | Computed from transactions [ASSUNZIONE] — no AUM column |
| B2B demo autodemo flag | Client-only localStorage — no DB |
| Partner tier Enterprise/Growth | ✓ `companies.tier` |

---

## 10. Comments added to database_master.sql

See header block in `../database_master.sql` listing:
- Missing `lead_matches.public_ref`
- Missing audit/idempotency/consent tables
- Missing company consumer profile fields
- Recommended indexes (§3 above)

---

## 11. Open questions for human review

1. **1 credit = €1 vs amount_cents** — confirm ledger semantics before implement.
2. **Multi-unlock** — can N partners unlock same lead? Frontend implies yes.
3. **Consumer email on wizard** — only phone required today; add email field to wizard?
4. **Trust score MVP** — rule-based vs manual admin score?
5. **Import master SQL vs migrations-only** for first Hostinger deploy?
6. **Company images** — CDN URLs vs uploaded media?
7. **Legal review** — GDPR consent copy and DPA with partners before launch.
