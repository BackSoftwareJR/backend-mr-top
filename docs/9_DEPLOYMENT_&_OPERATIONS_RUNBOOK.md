# Wenando — Deployment & Operations Runbook

> Hostinger Cloud shared/VPS hosting · Laravel API · MySQL/MariaDB · **No Redis** · Queue via database + cron worker

---

## 1. Prerequisites

| Item | Requirement |
|------|-------------|
| PHP | 8.2+ with extensions: mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath, fileinfo |
| Composer | 2.x |
| MySQL | 8.0+ or MariaDB 10.6+ |
| Node | 20+ (build SPA separately on CI or local) |
| SSH | Hostinger panel → SSH access enabled |
| Domains | `wenando.com`, `www.wenando.com`, `api.wenando.com` |

---

## 2. Directory layout on server

```
/home/{user}/
├── wenando-api/          # Laravel (document root → public/)
│   ├── public/           # Point api.wenando.com here
│   ├── storage/
│   └── ...
└── wenando-spa/          # Static build from Vite → wenando.com
    └── dist/
```

---

## 3. Initial deployment (SSH step-by-step)

### 3.1 Clone & install

```bash
ssh user@hostinger-server
cd ~
git clone <repo-url> wenando-api
cd wenando-api/backend   # or Laravel root path
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

### 3.2 Configure `.env`

```env
APP_NAME=Wenando
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.wenando.com
FRONTEND_URL=https://wenando.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wenando
DB_USERNAME=...
DB_PASSWORD=...

BROADCAST_CONNECTION=log
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_DOMAIN=.wenando.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=wenando.com,www.wenando.com

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=noreply@wenando.com
MAIL_FROM_NAME=Wenando

FILESYSTEM_DISK=local
```

### 3.3 Database bootstrap

**Option A — Fresh from master SQL (greenfield):**

```bash
mysql -u USER -p < database_master.sql
php artisan migrate --force   # if Laravel migrations added later
```

**Option B — Laravel migrations only (after implement):**

```bash
php artisan migrate --force
php artisan db:seed --force
```

See [10_SQL_REVIEW_&_GAPS.md](./10_SQL_REVIEW_&_GAPS.md) for ordering.

### 3.4 Permissions & link storage

```bash
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3.5 Web server

Point `api.wenando.com` document root to `wenando-api/public`.

Apache `.htaccess` (Laravel default) or Nginx:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Force HTTPS in Hostinger panel.

---

## 4. Cron jobs (no dedicated queue worker)

Hostinger shared hosting often lacks Supervisor — use **cron** for scheduler + queue.

```cron
# Laravel scheduler — every minute
* * * * * cd /home/user/wenando-api && php artisan schedule:run >> /dev/null 2>&1

# Queue worker — process all jobs then exit (database driver)
* * * * * cd /home/user/wenando-api && php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 --max-time=50 >> storage/logs/queue.log 2>&1
```

### 4.1 Scheduled tasks (register in `routes/console.php`)

| Command | Schedule | Purpose |
|---------|----------|---------|
| `otp:cleanup` | hourly | Delete expired otp_codes |
| `sessions:gc` | daily | Prune old sessions |
| `matching:retry-failed` | every 15 min | Retry stuck leads [ASSUNZIONE] |
| `backup:run` | daily 03:00 | DB dump [ASSUNZIONE] |

**Note:** `--max-time=50` keeps worker under 60s cron interval.

If VPS with Supervisor available — prefer persistent worker:

```ini
[program:wenando-worker]
command=php /home/user/wenando-api/artisan queue:work database --sleep=3 --tries=3
autostart=true
autorestart=true
```

---

## 5. SPA deployment

```bash
# Local or CI
npm ci
npm run build
rsync -avz dist/ user@server:~/wenando-spa/dist/
```

Hostinger: `wenando.com` → static `dist/`, SPA fallback `index.html` for client routes.

Env build arg: `VITE_API_BASE_URL=https://api.wenando.com/api/v1`

---

## 6. Environment variables checklist

| Variable | Required | Notes |
|----------|----------|-------|
| `APP_KEY` | ✓ | |
| `APP_URL` | ✓ | api subdomain |
| `DB_*` | ✓ | |
| `SESSION_DOMAIN` | ✓ | `.wenando.com` |
| `SANCTUM_STATEFUL_DOMAINS` | ✓ | |
| `MAIL_*` | ✓ | OTP delivery |
| `QUEUE_CONNECTION` | ✓ | `database` |
| `CACHE_STORE` | ✓ | `database` or `file` |
| `HCAPTCHA_SECRET` | prod | Bot protection |
| `STRIPE_*` / `MOLLIE_*` | [ASSUNZIONE] | Wallet recharge |
| `SENTRY_LARAVEL_DSN` | optional | Error tracking |

---

## 7. Migration strategy from database_master.sql

1. Import `database_master.sql` on empty DB
2. Future Laravel migrations must **ALTER** not recreate tables
3. Keep `database_master.sql` updated as snapshot after each major schema change
4. Never edit applied migrations in prod — forward-only

---

## 8. Backup

| What | How | Retention |
|------|-----|-----------|
| MySQL | `mysqldump wenando \| gzip > backup-$(date +%F).sql.gz` | 14 daily, 4 weekly |
| `storage/app/partners` | rsync to offsite | 30 days |
| `.env` | encrypted secrets vault | — |

Hostinger panel backups as secondary layer.

Test restore quarterly [ASSUNZIONE procedure doc].

---

## 9. Monitoring & logs

| Log | Path | Rotation |
|-----|------|----------|
| Laravel | `storage/logs/laravel.log` | daily, 14 days |
| Queue cron | `storage/logs/queue.log` | 7 days |
| PHP errors | Hostinger error log | panel |

### Health check endpoints

| Method | Path | Expected |
|--------|------|----------|
| GET | `/api/v1/health` | `{ "status": "ok", "db": true, "queue": true }` |
| GET | `/up` | Laravel 11 built-in (if enabled) |

Implement `/health` checking DB connection + `jobs` table readable.

Alerts [ASSUNZIONE]: email on 5xx spike, failed_jobs count > 10, disk > 85%.

---

## 10. Deploy update procedure

```bash
ssh user@server
cd ~/wenando-api
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Queue workers pick up code on next cron cycle
```

Zero-downtime [ASSUNZIONE]: maintenance mode during migration if breaking.

---

## 11. Rollback

1. `php artisan migrate:rollback --step=1` (if migration failed)
2. `git checkout {previous_tag}`
3. `composer install --no-dev`
4. Re-cache config
5. Restore DB from last backup if schema broken

---

## 12. Performance notes (frontend)

See [`../../docs/PERFORMANCE.md`](../../docs/PERFORMANCE.md):
- Route-level code split for `/wizard`, `/user`, `/pro`
- API should respond < 200ms for auth; matching async via queue
- Enable gzip/brotli on static SPA

---

## 13. Security hardening on Hostinger

- Disable directory listing
- Block access to `.env`, `storage/`, `vendor/` via web root = `public/` only
- IP allowlist for `/admin` routes [ASSUNZIONE optional in settings]
- Fail2ban on SSH if VPS
