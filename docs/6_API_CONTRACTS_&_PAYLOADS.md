# Wenando — API Contracts & Payloads

> **Base:** `https://api.wenando.com/api/v1`  
> **Envelope success:** `{ "success": true, "data": {}, "meta": { "request_id" } }`  
> **Envelope error:** see [`schemas/api_error_response.json`](./schemas/api_error_response.json)

---

## 1. Convenzioni globali

### 1.1 Headers

| Header | Required | Usage |
|--------|----------|-------|
| `Accept` | Recommended | `application/json` |
| `Content-Type` | POST/PATCH | `application/json` or `multipart/form-data` |
| `X-XSRF-TOKEN` | Stateful mutating | From `/sanctum/csrf-cookie` |
| `Cookie` | Stateful | `laravel_session`, `XSRF-TOKEN` |
| `Idempotency-Key` | Financial ops | UUID v4 |
| `Authorization` | PAT only | `Bearer {token}` |

### 1.2 Pagination

```json
{
  "success": true,
  "data": { "items": [] },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 142,
    "last_page": 8
  }
}
```

| Param | Default | Max |
|-------|---------|-----|
| `page` | 1 | — |
| `per_page` | 20 | 100 |
| `sort` | `-created_at` | field name; prefix `-` desc |

### 1.3 Public ID rules

| Prefix | Regex | Example |
|--------|-------|---------|
| `LD-` | `^LD-[0-9]{4,}$` | LD-2048 |
| `ML-` | `^ML-[0-9]{4,}$` | ML-2048 |
| `TX-` | `^TX-[0-9]{4,}$` | TX-8842 |
| `CRM-` | `^CRM-[0-9]+$` | CRM-102 |
| `INV-` | `^INV-[0-9]{4}-[0-9]{3,}$` | INV-2026-042 |

Internal APIs prefer `uuid` (RFC 4122) for leads and companies.

---

## 2. Auth endpoints

### POST `/auth/otp/request`

**Request:**
```json
{
  "email": "user@example.com",
  "portal": "consumer",
  "captcha": {
    "honeypot": "",
    "challenge_answer": "1234",
    "expected_challenge": "1234",
    "form_started_at": 1717400000000,
    "human_confirmed": true
  }
}
```

| Field | Rules |
|-------|-------|
| `email` | required, email, max:255, lowercase |
| `portal` | required, enum: `consumer`, `partner`, `admin` |
| `captcha.honeypot` | must be empty string |
| `captcha.human_confirmed` | required boolean true |
| `captcha.form_started_at` | ms timestamp; now - started >= 2000ms |
| `captcha.challenge_answer` | required if no hCaptcha/reCAPTCHA token [ASSUNZIONE] |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "email": "user@example.com",
    "expires_in_ms": 600000
  }
}
```

**Errors:**

| HTTP | code | message (IT) |
|------|------|--------------|
| 422 | VALIDATION_FAILED | Inserisci un indirizzo email valido. |
| 422 | CAPTCHA_FAILED | Verifica non superata. |
| 429 | OTP_RATE_LIMITED | Troppi tentativi. Riprova tra {n} min. |
| 403 | WRONG_PORTAL | [mirror validateEmailForPortal messages] |

---

### POST `/auth/otp/verify`

**Request:**
```json
{
  "email": "user@example.com",
  "code": "123456"
}
```

| Field | Rules |
|-------|-------|
| `code` | required, digits, size:6 |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "01J...",
      "email": "user@example.com",
      "name": "Mario Rossi",
      "user_type": "consumer",
      "onboarding_status": null
    },
    "redirect_to": "/user"
  }
}
```

| HTTP | code | message |
|------|------|---------|
| 422 | OTP_EXPIRED | Codice scaduto. Richiedine uno nuovo. |
| 422 | OTP_INVALID | Codice non valido. Controlla e riprova. |
| 422 | OTP_NOT_FOUND | Nessun codice attivo. Richiedine uno nuovo. |
| 429 | OTP_VERIFY_RATE_LIMITED | Troppi tentativi di verifica. |

---

### GET `/auth/me`

**Response 200 (consumer):**
```json
{
  "success": true,
  "data": {
    "user": { "id": "...", "email": "...", "name": "...", "user_type": "consumer" }
  }
}
```

**Response 200 (b2b approved):**
```json
{
  "success": true,
  "data": {
    "user": { "user_type": "b2b", "onboarding_status": "approved" },
    "company": { "id": "...", "organization_name": "Studio Care Milano", "vetting_status": "approved" },
    "wallet_summary": { "balance_credits": 150, "currency": "EUR" }
  }
}
```

---

## 3. B2C Wizard

### GET `/sectors/senior-care/wizard`

**Response 200:** mirrors [VERIFICATO] `wizardConfig.js` exactly.

### POST `/leads`

**Request:**
```json
{
  "sector_slug": "senior-care",
  "payload": {
    "autonomy": "parziale",
    "location": { "label": "Milano (MI)", "value": "milano-mi" },
    "budget": { "min": 1500, "max": 2500 },
    "contact": { "nome": "Mario", "telefono": "+39 333 123 4567" }
  },
  "consent": {
    "privacy_accepted": true,
    "marketing_accepted": false
  }
}
```

| Field | Validation |
|-------|------------|
| `sector_slug` | required, exists:sectors,slug |
| `payload` | JSON Schema [`wizard_senior_care_payload.json`](./schemas/wizard_senior_care_payload.json) |
| `payload.budget.max` | gte: payload.budget.min + 100 |
| `consent.privacy_accepted` | required true [ASSUNZIONE GDPR] |

**Response 201:**
```json
{
  "success": true,
  "data": {
    "lead": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "public_ref": "LD-2049",
      "status": "processing"
    },
    "job_id": "01J..."
  }
}
```

---

### GET `/leads/{uuid}/results`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "diagnosis": {
      "recommendation": "Assistenza Domiciliare",
      "primary": "Assistenza Domiciliare",
      "secondary": "RSA",
      "summary": "Per un'autonomia parziale..."
    },
    "matches": [
      {
        "id": "match-uuid",
        "company_id": "company-uuid",
        "name": "Casa Serenità",
        "type": "Assistenza Domiciliare",
        "location": "Milano, Zona Navigli",
        "compatibility": 95,
        "image_url": "https://...",
        "description": "...",
        "pros": ["..."],
        "contact_hint": "..."
      }
    ],
    "advisor": {
      "name": "Marco",
      "role": "Consulente pari",
      "story": "...",
      "cta_label": "Prenota una chiamata gratuita (15 min)"
    }
  }
}
```

---

### GET `/user/searches`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "searches": [
      {
        "id": "search-uuid",
        "title": "Ricerca per la Mamma",
        "location": "Milano",
        "date": "12 Ottobre",
        "status": "completed",
        "match_count": 3,
        "answers": { }
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 2 }
}
```

| `status` | enum: `processing`, `completed` [VERIFICATO] |

---

### POST `/advisor-bookings`

**Request:**
```json
{
  "lead_uuid": "550e8400-...",
  "name": "Mario",
  "scheduled_date": "2026-06-10",
  "scheduled_time": "10:30"
}
```

| Field | Rules |
|-------|-------|
| `name` | required, max:128 |
| `scheduled_date` | required, date, after:today |
| `scheduled_time` | required, regex `^([01][0-9]|2[0-3]):[0-5][0-9]$` |
| `lead_uuid` | optional uuid |

---

## 4. B2B endpoints

### POST `/b2b/register`

**Request:**
```json
{
  "email": "referente@struttura.it",
  "organization_name": "Casa Serena",
  "legal_name": "Casa Serena S.r.l."
}
```

| Field | Rules |
|-------|-------|
| `email` | required, email, unique:users |
| `organization_name` | required, max:255 |
| `legal_name` | required, max:255 |

**Response 201:** session cookie + company `vetting_status: in_progress`

---

### PATCH `/b2b/onboarding`

Partial update — schema [`company_onboarding_payload.json`](./schemas/company_onboarding_payload.json).

**Response 200:**
```json
{
  "success": true,
  "data": {
    "status": "in_progress",
    "data": { "vat": "IT12345678901", "dynamic": {}, "schedule": {}, "trust_answers": {} }
  }
}
```

---

### POST `/b2b/onboarding/documents`

**Multipart:**

| Field | Rules |
|-------|-------|
| `type` | required, enum: `visura`, `identity` |
| `file` | required, file, mimes:pdf,jpg,jpeg,png, max:10240 (KB) |

**Response 201:**
```json
{
  "success": true,
  "data": {
    "type": "visura",
    "file_name": "visura-camerale.pdf",
    "document_id": "01J..."
  }
}
```

---

### GET `/b2b/marketplace/leads`

**Query:** `?unlocked_only=false&page=1`

**Response 200 (locked lead):**
```json
{
  "success": true,
  "data": {
    "leads": [
      {
        "id": "ML-2048",
        "match_score": 98,
        "budget": "2.400€/mese",
        "location": "Milano (MI)",
        "need": "Assistenza domiciliare h24",
        "unlock_cost": 15,
        "unlocked": false,
        "name": null,
        "phone": null,
        "email": null
      }
    ]
  }
}
```

**Response 200 (unlocked):** includes `name`, `phone`, `email` [VERIFICATO] mockB2B.

---

### POST `/b2b/marketplace/leads/{id}/unlock`

**Headers:** `Idempotency-Key: {uuid}`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "lead": { "id": "ML-2048", "unlocked": true, "name": "Maria Rossi", "phone": "+39...", "email": "..." },
    "wallet": { "balance_credits": 135 },
    "crm_client": {
      "id": "CRM-102",
      "cliente": "Maria Rossi",
      "stato": "Nuovo",
      "esigenza": "...",
      "budget": "2.400€",
      "marketplace_id": "ML-2048"
    }
  }
}
```

| HTTP | code | message |
|------|------|---------|
| 402 | INSUFFICIENT_CREDITS | Credito insufficiente. Ricarica il wallet per sbloccare il lead. |
| 409 | ALREADY_UNLOCKED | Lead già sbloccato. |
| 403 | PARTNER_NOT_APPROVED | Completa onboarding e attendi approvazione. |

---

### PATCH `/b2b/crm/clients/{id}`

**Request:**
```json
{ "stato": "Contattato" }
```

| `stato` | enum UI: Nuovo, Contattato, Visita Fissata, Perso, Chiuso |

---

### POST `/b2b/wallet/recharge`

**Request:**
```json
{
  "amount": 100,
  "payment_method": "card"
}
```

| Field | Rules |
|-------|-------|
| `amount` | required, numeric, min:1, max:10000 |
| `payment_method` | enum: card, transfer [VERIFICATO partial] |

---

## 5. Admin endpoints

### GET `/admin/metrics`

**Response 200:** mirrors [VERIFICATO] `adminMetrics` keys (snake_case in API).

### POST `/admin/partners/{id}/approve`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "company": {
      "id": "...",
      "vetting_status": "approved",
      "approved_at": "2026-06-03T12:00:00Z"
    }
  }
}
```

### POST `/admin/partners/{id}/impersonate`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "impersonation_token": "...",
    "expires_at": "2026-06-03T12:15:00Z",
    "partner": { "organization_name": "Residenza Aurora" }
  }
}
```

### PATCH `/admin/leads/{id}/assign`

**Request:**
```json
{ "partner_id": "company-uuid" }
```

**Response 200:** lead with `admin_status: "Assegnato"` [VERIFICATO] LeadRouter behavior.

---

## 6. Error catalog (complete)

| code | HTTP | When |
|------|------|------|
| VALIDATION_FAILED | 422 | FormRequest fail |
| UNAUTHENTICATED | 401 | No session |
| FORBIDDEN | 403 | Wrong role/portal |
| NOT_FOUND | 404 | Missing resource |
| OTP_RATE_LIMITED | 429 | 3 sends / 15 min |
| OTP_VERIFY_RATE_LIMITED | 429 | Verify brute force |
| OTP_EXPIRED | 422 | TTL 10 min |
| OTP_INVALID | 422 | Wrong code |
| OTP_NOT_FOUND | 422 | No active OTP |
| CAPTCHA_FAILED | 422 | Honeypot/timing/challenge |
| WRONG_PORTAL | 403 | Consumer vs B2B email |
| INSUFFICIENT_CREDITS | 402 | Wallet unlock |
| ALREADY_UNLOCKED | 409 | Duplicate unlock |
| IDEMPOTENCY_KEY_MISMATCH | 422 | Same key, different body |
| PARTNER_NOT_APPROVED | 403 | B2B routes gated |
| LEAD_PROCESSING | 409 | Results not ready |
| FILE_TOO_LARGE | 422 | Upload > 10MB |
| FILE_INVALID_TYPE | 422 | Wrong MIME |
| SERVER_ERROR | 500 | Unexpected |

**Example error:**
```json
{
  "success": false,
  "error": {
    "code": "INSUFFICIENT_CREDITS",
    "message": "Credito insufficiente. Ricarica il wallet per sbloccare il lead.",
    "details": {
      "required_credits": 15,
      "balance_credits": 10
    }
  },
  "trace_id": "01JABCDEFGHJKMNPQRSTVWXYZ"
}
```

---

## 7. Filtering conventions

| Endpoint | Filters |
|----------|---------|
| `/admin/partners` | `?stato=Pending` → maps vetting_status |
| `/admin/leads` | `?stato=In routing` |
| `/admin/transactions` | `?status=Completata&page=1` |
| `/b2b/crm/clients` | `?stato=Nuovo` |
| `/b2b/marketplace/leads` | `?unlocked_only=true` |

Sort tokens: `match_score`, `-created_at`, `importo` [ASSUNZIONE].
