# Dev seed data

Idempotent demo users and records for **local development only**. Safe to re-run:

```bash
php artisan db:seed
```

Requires migrations and base seeders (`SectorSeeder`, `RolesPermissionsSeeder`, etc.) — run via `DatabaseSeeder`.

## Credentials (local only)

| Email | Password | `user_type` | Role(s) | Purpose |
|-------|----------|-------------|---------|---------|
| `admin@wenando.test` (or `SEED_SUPERADMIN_EMAIL`) | `password` | `superadmin` | `super_admin` | God Mode `/admin`, partner approval, impersonation |
| `consumer@wenando.test` | `password` | `consumer` | `consumer` | B2C home/searches; lead `LD-SEED-CONSUMER` |
| `partner@care.it` | `password` | `b2b` | `partner_owner` | Approved company, profile, wallet **150** credits |
| `partner-pending@wenando.test` | `password` | `b2b` | `partner_owner` | Company `Residenza In Attesa` — `pending_review` for admin approve/reject |

**Never** use the default password `password` in production. Production must not run `DevUsersSeeder` on a public database without changing credentials.

## Environment

| Variable | Default | Notes |
|----------|---------|-------|
| `SEED_SUPERADMIN_EMAIL` | `admin@wenando.test` | Superadmin login for local God Mode tests |

Add to `.env` for local overrides:

```env
SEED_SUPERADMIN_EMAIL=you@wenando.test
```

## Seeded artifacts

| Artifact | Key | Notes |
|----------|-----|-------|
| Consumer lead | `public_ref` = `LD-SEED-CONSUMER` | Status `routed`, senior-care sector |
| Approved partner company | `Care Partner Italia` | `vetting_status` = `approved`, `company_profiles` + `wallets` |
| Pending partner company | `Residenza In Attesa` | `vetting_status` = `pending_review` |
| Demo match companies | — | Still from `DemoMatchCompaniesSeeder` (no login users) |

## OTP vs password

Production auth is OTP-first. For local API tests, feature tests use factories; seeded users include a bcrypt `password` hash so you can use password grant or test login if enabled in your environment.

## Related docs

- Gap tracker: [10_SQL_REVIEW_&_GAPS.md](./10_SQL_REVIEW_&_GAPS.md) §8
- Deploy: [9_DEPLOYMENT_&_OPERATIONS_RUNBOOK.md](./9_DEPLOYMENT_&_OPERATIONS_RUNBOOK.md) §3.3
