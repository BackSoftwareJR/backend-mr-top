# Wenando — Security & Compliance Guidelines

> Linee guida per implementazione Laravel API. Riferimento normativo: GDPR (UE 2016/679), Codice Privacy italiano (D.Lgs. 196/2003 come modificato), ePrivacy per comunicazioni.

---

## 1. GDPR / Privacy italiana

### 1.1 Base giuridica trattamento

| Dato | Finalità | Base giuridica | Retention [ASSUNZIONE] |
|------|----------|----------------|------------------------|
| Email/telefono wizard | Matching servizi Senior Care | Consenso (art. 6.1.a) + esecuzione misure precontrattuali (6.1.b) | 24 mesi post ultimo contatto |
| Lead PII | Condivisione con partner sbloccato | Consenso esplicito in wizard | Fino a revoca / chiuso lead |
| P.IVA / visura partner | Due diligence B2B | Legittimo interesse / obbligo contrattuale | Durata rapporto + 10 anni fiscali |
| Trust test answers | Quality scoring | Contratto B2B | Durata rapporto |
| OTP email | Autenticazione | Contratto / legittimo interesse | 10 min (codice), log 90 giorni |
| Audit impersonation | Accountability | Legittimo interesse | 5 anni |

### 1.2 Consenso wizard [ASSUNZIONE — non in UI mock]

Aggiungere checkbox obbligatoria prima di `POST /leads`:

- Informativa privacy Wenando (link)
- Consenso trattamento dati per matching
- Consenso opzionale marketing

Registrare: `consent_at`, `consent_version`, `ip`, `user_agent` in `leads.metadata` o tabella `consent_records`.

### 1.3 Diritti interessato

| Diritto | Implementazione API [ASSUNZIONE] |
|---------|----------------------------------|
| Accesso | `GET /user/data-export` |
| Rettifica | `PATCH /user/profile` |
| Cancellazione | `DELETE /user/account` → anonymize leads |
| Portabilità | JSON export |
| Opposizione | Flag `marketing_opt_out` |

### 1.4 DPA con partner B2B

Partner che sbloccano lead diventano **contitolari o responsabili** del trattamento post-unlock [ASSUNZIONE legale — da validare con counsel]. API deve loggare `unlocked_at`, `company_id` per accountability.

### 1.5 Minimizzazione dati marketplace

[VERIFICATO] Frontend offusca PII pre-unlock — API **non deve** inviare `contact_phone`, `contact_email`, `contact_name` finché `unlocked_at IS NULL`.

---

## 2. Rate limiting (tiers)

Usare Laravel `RateLimiter` con driver `database` o `file` (no Redis).

| Limiter | Key | Limit | Scope |
|---------|-----|-------|-------|
| `api-default` | user_id or IP | 120/min | Authenticated API |
| `auth-otp-request` | email + IP | **3 / 15 min** | [VERIFICATO] authService |
| `auth-otp-verify` | email | 10/min | Brute force 6-digit |
| `auth-otp-verify-lockout` | email | 5 failures → lock 30 min | [ASSUNZIONE] |
| `wizard-submit` | IP | 5/hour | Anonymous lead spam |
| `b2b-unlock` | company_id | 30/min | Wallet abuse |
| `b2b-recharge` | company_id | 10/hour | Payment spam |
| `admin-api` | user_id | 300/min | God Mode |
| `document-upload` | company_id | 20/hour | Storage abuse |
| `locations-autocomplete` | IP | 60/min | [ASSUNZIONE] future endpoint |

Response **429** con header `Retry-After` e body:

```json
{
  "error": {
    "code": "OTP_RATE_LIMITED",
    "message": "Troppi tentativi. Riprova tra 12 min."
  },
  "meta": { "retry_after_seconds": 720 }
}
```

---

## 3. OTP security

| Parameter | Value | Source |
|-----------|-------|--------|
| Code length | 6 digits | [VERIFICATO] authService |
| TTL | 10 minutes | [VERIFICATO] CODE_TTL_MS |
| Resend cooldown | 60 seconds | [VERIFICATO] RESEND_COOLDOWN_MS |
| Send window | 3 per 15 min per email | [VERIFICATO] MAX_SEND_ATTEMPTS |
| Storage | bcrypt hash in `otp_codes.code_hash` | Never plaintext in DB |
| Single use | Delete on successful verify | [VERIFICATO] mock behavior |
| Dev bypass | `dev_code` in response | **Only** `APP_ENV=local` |
| Session TTL | 7 days | [VERIFICATO] saveSession expiresAt |

### Lockout policy [ASSUNZIONE]

After 5 failed verify attempts within 15 min:
- Invalidate active OTP
- Block new verify for 30 min
- Log `warning` with masked email

---

## 4. Captcha & bot protection

[VERIFICATO] `HumanVerification.jsx`:

| Layer | Dev (no env keys) | Production |
|-------|-------------------|------------|
| Honeypot | `company_website` field hidden | Same |
| Timing | MIN 2000ms form duration | Same |
| Challenge | 4-digit visual | hCaptcha or reCAPTCHA v3 |
| Checkbox | humanConfirmed required | Same |

Server must re-validate all captcha fields — never trust client-only checks.

Env: `HCAPTCHA_SECRET`, `RECAPTCHA_SECRET` on API; `VITE_*_SITE_KEY` on SPA.

---

## 5. File upload security (vetting)

| Control | Value |
|---------|-------|
| Types | PDF, JPEG, PNG only |
| Max size | 10 MB [VERIFICATO] StepLegal hint |
| Storage | `storage/app/partners/{company_uuid}/` — not public |
| Filename | UUID + extension — ignore client name for path |
| MIME verify | `finfo` + allowlist |
| Download | Signed temporary URL, 15 min TTL |
| Virus scan | ClamAV hook [ASSUNZIONE] async job |

Reject zip, executables, SVG (XSS).

---

## 6. Audit logging

### 6.1 Events requiring audit row [ASSUNZIONE table `audit_logs`]

| Event | Fields logged |
|-------|---------------|
| `partner.approved` | admin_id, company_id, ip |
| `partner.rejected` | admin_id, company_id, reason |
| `partner.suspended` | admin_id, company_id, reason |
| `admin.impersonate.start` | admin_id, target_company_id, ip, user_agent |
| `admin.impersonate.end` | admin_id, duration |
| `lead.manual_assign` | admin_id, lead_id, company_id |
| `lead.reroute` | admin_id, lead_id |
| `wallet.unlock` | company_id, lead_match_id, credits |
| `auth.login.success` | user_id, portal, ip |
| `auth.login.failed` | email masked, ip |

Never log: OTP plaintext, full credit card, document contents.

### 6.2 Impersonation audit trail

[VERIFICATO] UI toast only today — production requirements:

1. Admin clicks Impersonate → API creates `impersonation_sessions` row
2. Token scoped to target company, 15 min max
3. All actions tagged `acting_as_company_id` in logs
4. Banner in SPA: "Stai operando come {partner}" [ASSUNZIONE]
5. Auto-expire; admin cannot approve own partner while impersonating [ASSUNZIONE]

---

## 7. CORS + CSRF + Sanctum (wenando.com ↔ api.wenando.com)

### 7.1 Production config

```env
APP_URL=https://api.wenando.com
FRONTEND_URL=https://wenando.com
SESSION_DOMAIN=.wenando.com
SANCTUM_STATEFUL_DOMAINS=wenando.com,www.wenando.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

### 7.2 SPA bootstrap sequence

```javascript
// 1. CSRF cookie
await fetch('https://api.wenando.com/sanctum/csrf-cookie', { credentials: 'include' })
// 2. API calls
await fetch('https://api.wenando.com/api/v1/auth/otp/request', {
  method: 'POST',
  credentials: 'include',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
  },
  body: JSON.stringify({ ... }),
})
```

### 7.3 CORS (`config/cors.php`)

| Setting | Value |
|---------|-------|
| `paths` | `api/*`, `sanctum/csrf-cookie` |
| `allowed_origins` | `https://wenando.com`, `https://www.wenando.com` |
| `supports_credentials` | `true` |
| `allowed_headers` | `Content-Type`, `X-XSRF-TOKEN`, `Accept`, `Authorization`, `Idempotency-Key` |

**Do not** use `*` origin with credentials.

### 7.4 CSRF exceptions

Only stateless PAT routes (`Authorization: Bearer`) skip CSRF — document each exception.

---

## 8. Transport & headers

| Header | Value |
|--------|-------|
| HSTS | `max-age=31536000; includeSubDomains` at reverse proxy |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` on API |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |

Force HTTPS redirect on Hostinger.

---

## 9. PII in logs

Mask pattern: `m***@domain.com`, `+39 ***4567`

Never log: `otp_codes`, `payload.contact` full phone, trust answer full text (log length only).

---

## 10. Compliance checklist pre-go-live

- [ ] Privacy policy + cookie banner on wenando.com
- [ ] Registro trattamenti [ASSUNZIONE] documentato
- [ ] DPA template per partner B2B
- [ ] Data retention cron (purge expired OTP, old sessions)
- [ ] `/admin` protetto lato API + SPA
- [ ] Impersonation audit attivo
- [ ] Backup cifrati (vedi runbook §9)
- [ ] Incident response contact
