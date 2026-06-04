# Wenando API — Hostinger SSH Deploy Checklist

Laravel backend for `api.wenando.com`. Document root **must** expose only Laravel `public/` (never project root).

**Hostinger Cloud:** docroot is fixed at `public_html` — put the app in sibling `api/` and a bridge `index.php` in `public_html`. Full layout: [`../docs/DEPLOY_HOSTINGER.md`](../docs/DEPLOY_HOSTINGER.md) §2.

See also: `docs/9_DEPLOYMENT_&_OPERATIONS_RUNBOOK.md`, `.env.production.example`.

---

## Prerequisites

| Item | Requirement |
|------|-------------|
| PHP | 8.3+ (`mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`) |
| Composer | 2.x |
| MySQL | 8.0+ or MariaDB 10.6+ (Hostinger hPanel) |
| SSH | Enabled in Hostinger panel |
| Domains | VPS: `~/wenando-api/public` — Cloud: `~/domains/api.wenando.com/public_html` + app in `../api` |

**No Redis** — use `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=database`.

---

## 1. Upload code

```bash
ssh USER@HOSTINGER_HOST
cd ~
git clone <REPO_URL> wenando-api
cd wenando-api/backend
```

Or upload the `backend/` folder via SFTP to `~/wenando-api/`.

---

## 2. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
```

---

## 3. Environment

```bash
cp .env.production.example .env
# Edit .env: DB_*, MAIL_*, APP_URL, SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS
php artisan key:generate
```

`APP_KEY` is required before `config:cache`. Never commit `.env`.

---

## 4. Database

```bash
php artisan migrate --force
php artisan db:seed --force
```

Greenfield only (destructive):

```bash
# ONLY on empty dev/staging — never on production with data
php artisan migrate:fresh --seed --force
```

---

## 5. Optimize & storage

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

---

## 6. Web server

- **VPS:** document root = `/home/USER/wenando-api/public` (not project root).
- **Hostinger Cloud:** only `public_html` is web-facing; Laravel lives in `../api` — see `docs/DEPLOY_HOSTINGER.md` §2.
- Apache: default `public/.htaccess` copied to `public_html` on Cloud.
- Nginx snippet:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Force HTTPS in Hostinger panel.

---

## 7. Cron (scheduler + queue)

```cron
* * * * * cd /home/USER/wenando-api && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/USER/wenando-api && php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 --max-time=50 >> storage/logs/queue.log 2>&1
```

---

## 8. Post-deploy script (optional)

```bash
chmod +x deploy/hostinger-post-deploy.sh
./deploy/hostinger-post-deploy.sh
```

Use `--fresh-seed` only on empty databases (see script help).

---

## 9. Email verification (SMTP)

After `MAIL_*` is set in `.env` (password from hPanel only — never commit):

```bash
php artisan config:clear
php artisan config:cache
php artisan wenando:mail-test your-inbox@example.com
```

Confirm delivery (inbox or spam). Align DNS: SPF, DKIM, DMARC — see `docs/11_EMAIL_&_DELIVERABILITY.md`.

Queued transactional mail (`OtpMail`, `WelcomeMail`) requires the cron `queue:work` job (section 7).

---

## 10. Health checks

```bash
curl -sS https://api.wenando.com/up
curl -sS https://api.wenando.com/api/v1/health
```

Expected `/api/v1/health`:

```json
{"success":true,"data":{"status":"ok","db":true,"queue":true},"meta":{"request_id":"..."}}
```

---

## 11. Update deploy (no fresh)

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Frontend integration

| Setting | Production value |
|---------|------------------|
| API base URL | `https://api.wenando.com/api/v1` |
| Vite build arg | `VITE_API_BASE_URL=https://api.wenando.com/api/v1` |
| CORS origins | `https://wenando.com`, `https://www.wenando.com` |
| Sanctum stateful | `wenando.com,www.wenando.com` |
| Session cookie domain | `.wenando.com` |
| CSRF cookie | `GET https://api.wenando.com/sanctum/csrf-cookie` (credentials) |

### Required environment variables

| Variable | Required | Notes |
|----------|----------|-------|
| `APP_KEY` | ✓ | `php artisan key:generate` |
| `APP_ENV` | ✓ | `production` |
| `APP_DEBUG` | ✓ | `false` |
| `APP_URL` | ✓ | `https://api.wenando.com` |
| `FRONTEND_URL` | ✓ | `https://wenando.com` |
| `DB_*` | ✓ | Hostinger MySQL |
| `SESSION_DRIVER` | ✓ | `database` |
| `SESSION_DOMAIN` | ✓ | `.wenando.com` |
| `SESSION_SECURE_COOKIE` | ✓ | `true` |
| `QUEUE_CONNECTION` | ✓ | `database` |
| `CACHE_STORE` | ✓ | `file` |
| `SANCTUM_STATEFUL_DOMAINS` | ✓ | `wenando.com,www.wenando.com` |
| `MAIL_*` | ✓ | Hostinger SMTP (`hola@wenando.com`); see `docs/11_EMAIL_&_DELIVERABILITY.md` |
| `WENANDO_PRIVACY_POLICY_VERSION` | ✓ | `1.0.0` |
| `HCAPTCHA_SECRET` | prod | Bot protection |
| `SENTRY_LARAVEL_DSN` | optional | Error tracking |

### B2C lead flow (after sector reference data)

1. `POST /api/v1/consents` — register privacy + terms + lead_sharing hashes
2. `POST /api/v1/b2c/leads` — requires `sector_slug=senior-care` in `sectors` table

**Production (no full `db:seed`):** after `migrate --force`, run once:

```bash
php artisan wenando:seed-sectors
```

---

## Security checklist

- [ ] `.env` not in git (see `.gitignore`)
- [ ] `APP_DEBUG=false`
- [ ] Web root = `public/` only (Cloud: `public_html` = contenuto di `public/` + `index.php` bridge, app in `api/`)
- [ ] `.env` / `vendor/` / `storage/logs` mai sotto docroot
- [ ] TrustProxies enabled (`bootstrap/app.php`)
- [ ] CORS strict (`config/cors.php`)

---

## Local quality gate (before SSH)

```bash
composer install
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan migrate:fresh --seed --force
php artisan test
./vendor/bin/pint --test
php artisan route:list --path=api
php artisan config:show app.url
```
