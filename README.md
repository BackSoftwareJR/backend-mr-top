# Wenando API (Laravel)

Backend for the Wenando Trust Engine (B2C, B2B, Admin).

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

## Dev seed users

After `php artisan db:seed`, use the accounts documented in **[docs/DEV_SEED.md](docs/DEV_SEED.md)**.

All local seed users share the documented default password `password` (never commit real passwords; never use this default in production).

Optional: set `SEED_SUPERADMIN_EMAIL` in `.env` to override the superadmin address (default `admin@wenando.test`).

## Tests

```bash
php artisan test
```

## Documentation

See [docs/0_INDEX.md](docs/0_INDEX.md) for architecture, schema, API roadmap, and operations runbooks.
