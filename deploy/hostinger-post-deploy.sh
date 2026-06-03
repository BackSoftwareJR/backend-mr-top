#!/usr/bin/env bash
# Wenando API — Hostinger post-deploy (safe defaults)
# Usage: ./deploy/hostinger-post-deploy.sh [--fresh-seed]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

FRESH_SEED=false
for arg in "$@"; do
  case "$arg" in
    --fresh-seed)
      FRESH_SEED=true
      ;;
    -h|--help)
      echo "Usage: $0 [--fresh-seed]"
      echo "  --fresh-seed  Run migrate:fresh --seed (DESTRUCTIVE — empty DB only)"
      exit 0
      ;;
    *)
      echo "Unknown option: $arg" >&2
      exit 1
      ;;
  esac
done

if [[ ! -f .env ]]; then
  echo "ERROR: .env missing. Copy .env.production.example to .env and configure DB_*." >&2
  exit 1
fi

if grep -qE '^APP_KEY=$' .env || ! grep -qE '^APP_KEY=.+' .env; then
  echo "WARN: APP_KEY empty — run: php artisan key:generate"
fi

if grep -q '^APP_DEBUG=true' .env; then
  echo "WARN: APP_DEBUG=true in production is not recommended."
fi

echo "==> Composer (production)"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ "$FRESH_SEED" == true ]]; then
  if [[ "${ALLOW_DESTRUCTIVE:-}" != "yes" ]]; then
    echo "ERROR: migrate:fresh is destructive. Set ALLOW_DESTRUCTIVE=yes to confirm." >&2
    exit 1
  fi
  echo "==> migrate:fresh --seed (DESTRUCTIVE)"
  php artisan migrate:fresh --seed --force
else
  echo "==> migrate"
  php artisan migrate --force
  echo "==> db:seed"
  php artisan db:seed --force
fi

echo "==> storage:link"
php artisan storage:link 2>/dev/null || true

echo "==> cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> permissions"
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "==> health"
php artisan route:list --path=health --columns=method,uri 2>/dev/null || true

echo "Done. Verify: curl -sS \"\${APP_URL:-https://api.wenando.com}/up\""
