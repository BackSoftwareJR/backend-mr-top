# 11 — Email & Deliverability (Hostinger SMTP)

> **Mailbox:** `hola@wenando.com` · **SMTP:** `smtp.hostinger.com`  
> **Stack:** Laravel 13 · database queue · no Redis on shared hosting

---

## 1. Hostinger SMTP setup (hPanel)

1. **hPanel → Emails → Create email account** for `hola@wenando.com` (or use existing).
2. Note the mailbox password — set **only** in production `.env` as `MAIL_PASSWORD` (never commit).
3. **Connect domain** (Emails → Manage → Connect Domain) so Hostinger can add or verify DNS records.
4. Configure Laravel `.env` (see `.env.production.example`):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=hola@wenando.com
MAIL_PASSWORD=<from hPanel>
MAIL_TIMEOUT=30
MAIL_EHLO_DOMAIN=wenando.com
MAIL_FROM_ADDRESS=hola@wenando.com
MAIL_FROM_NAME="Wenando"
MAIL_REPLY_TO_ADDRESS=hola@wenando.com
```

### Alternative: port 587 + STARTTLS

If port 465 is blocked by a firewall, use:

```env
MAIL_PORT=587
MAIL_SCHEME=smtp
```

Legacy templates may use `MAIL_ENCRYPTION=tls` — `config/mail.php` maps `tls` → `smtp` and `ssl` → `smtps`.

### Laravel 13 note

Symfony Mailer uses **`MAIL_SCHEME`** (`smtps` = implicit SSL on 465, `smtp` = STARTTLS on 587).  
`MAIL_ENCRYPTION` is supported only as a backward-compatible alias in `config/mail.php`.

---

## 2. DNS authentication checklist

Apply at your DNS provider (Hostinger DNS zone or external). **One SPF TXT per domain.**

| Record | Type | Host / Name | Value / Points to |
|--------|------|-------------|-------------------|
| SPF | TXT | `@` | `v=spf1 include:_spf.mail.hostinger.com ~all` |
| DKIM A | CNAME | `hostingermail-a._domainkey` | `hostingermail-a.dkim.mail.hostinger.com` |
| DKIM B | CNAME | `hostingermail-b._domainkey` | `hostingermail-b.dkim.mail.hostinger.com` |
| DKIM C | CNAME | `hostingermail-c._domainkey` | `hostingermail-c.dkim.mail.hostinger.com` |
| DMARC (monitor) | TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:hola@wenando.com; pct=100; adkim=s; aspf=s` |
| DMARC (enforce later) | TXT | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:hola@wenando.com; pct=100; adkim=s; aspf=s` |

**Propagation:** allow 24–72 hours. Verify in hPanel → Emails → Connect Domain → Refresh.

### Merging SPF with other senders

If you later add marketing ESP (e.g. SendGrid), merge into **one** SPF record:

```txt
v=spf1 include:_spf.mail.hostinger.com include:sendgrid.net ~all
```

### Reverse DNS / dedicated IP

- **Shared Hostinger hosting:** outbound mail uses shared IPs; you cannot set custom rDNS. Rely on SPF, DKIM, DMARC, and consistent `From` alignment.
- **VPS / dedicated IP:** request rDNS (PTR) matching `MAIL_EHLO_DOMAIN` / sending hostname from the provider.

---

## 3. Anti-spam & content best practices

| Practice | Wenando guidance |
|----------|------------------|
| **From alignment** | `MAIL_FROM_ADDRESS` = same mailbox as `MAIL_USERNAME` (`hola@wenando.com`) |
| **Reply-To** | Set `MAIL_REPLY_TO_ADDRESS=hola@wenando.com` for support replies |
| **Subject / body** | Avoid ALL CAPS, excessive links, spam trigger words; Italian plain text + HTML |
| **OTP / transactional** | Short, single purpose; no marketing banners in OTP mails |
| **Marketing** | Separate subdomain or ESP; include **List-Unsubscribe** header and visible opt-out |
| **Bounce handling** | Monitor `hola@wenando.com`; remove invalid addresses from lists |
| **Rate limits** | On shared hosting, cap bursts (~100–200/hour is a safe starting point); use queue + cron worker |
| **Double opt-in** | Required for newsletter; not for OTP/login |

---

## 4. Queue async sending (database driver)

Production uses `QUEUE_CONNECTION=database` (no Redis).

1. Mailables `App\Mail\OtpMail` and `App\Mail\WelcomeMail` implement `ShouldQueue` and `QueuesOnDatabase`.
2. Cron runs the worker (see `DEPLOY.md`):

```cron
* * * * * cd /home/USER/wenando-api && php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 --max-time=50 >> storage/logs/queue.log 2>&1
```

3. Dispatch example:

```php
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;

Mail::to($user->email)->queue(new OtpMail($code, expiresInMinutes: 10));
```

Failed jobs: `php artisan queue:failed` / `queue:retry`.

---

## 5. Deploy verification

After `.env` is filled and `config:cache`:

```bash
php artisan wenando:mail-test your-inbox@example.com
```

Expected: command exits 0; message arrives (check spam). Headers should show SPF/DKIM pass in Gmail “Show original”.

### Tinker (alternative)

```php
php artisan tinker
>>> Mail::raw('Wenando tinker test', fn ($m) => $m->to('you@example.com')->subject('Test'));
```

---

## 6. Application mail classes

| Class | Purpose | Queued |
|-------|---------|--------|
| `App\Mail\OtpMail` | OTP code (stub) | Yes (`database`) |
| `App\Mail\WelcomeMail` | Welcome after signup (stub) | Yes (`database`) |

Views: `resources/views/mail/otp*.blade.php`, `welcome*.blade.php`.

---

## 7. Troubleshooting

| Symptom | Check |
|---------|--------|
| Authentication failed | `MAIL_USERNAME` / `MAIL_PASSWORD`; mailbox active in hPanel |
| Connection timeout | `MAIL_TIMEOUT`, firewall on 465 vs try 587 |
| Mail goes to spam | SPF/DKIM/DMARC; From ≠ mailbox; new domain reputation |
| Jobs not sent | `jobs` table exists; cron `queue:work`; `failed_jobs` table |
| Config stale | `php artisan config:clear` then `config:cache` after `.env` change |

---

## Related docs

- [`DEPLOY.md`](../DEPLOY.md) — post-deploy mail test step  
- [`9_DEPLOYMENT_&_OPERATIONS_RUNBOOK.md`](./9_DEPLOYMENT_&_OPERATIONS_RUNBOOK.md) — cron & env overview
