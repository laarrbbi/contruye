#!/usr/bin/env bash
#
# Ship the current checkout. Run on the server, from anywhere, after pulling
# the release:
#
#   cd /var/www/ebni && git pull && deploy/deploy.sh
#
# Order matters. Everything that can fail without touching the live site runs
# first (dependencies, the secrets gate, the front-end build); only then does
# the site go into maintenance for the migration. A deploy that stops early
# leaves the running release exactly as it was.
#
# Environment overrides:
#   PHP=php8.3                       PHP binary (default: php)
#   PHP_FPM_SERVICE=php8.3-fpm       reload it at the end so OPcache drops old code
#   SKIP_WEB=1                       back-end only (e.g. a PHP hotfix)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API="$ROOT/api"
WEB="$ROOT/web"
PHP="${PHP:-php}"

step() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[31m%s\033[0m\n' "$*" >&2; exit 1; }

[ -f "$API/.env" ] || fail "api/.env is missing. Start from api/.env.production.example."

# Read one value from api/.env without sourcing it (values may hold spaces,
# quotes or characters the shell would interpret).
env_get() {
  grep -E "^$1=" "$API/.env" | tail -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/'
}

step "Release $(git -C "$ROOT" rev-parse --short HEAD) — $(git -C "$ROOT" log -1 --format=%s)"

step "Back-end dependencies"
cd "$API"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

step "Secrets and production settings — the deploy stops here if anything is wrong"
# A cached config from the previous release would hide a change to .env.
"$PHP" artisan config:clear
"$PHP" artisan app:check-secrets --strict

if [ "${SKIP_WEB:-}" != "1" ]; then
  step "Front-end build (into dist-next; the live dist is untouched until the swap)"
  APP_URL="$(env_get APP_URL)"
  [ -n "$APP_URL" ] || fail "APP_URL is empty in api/.env."
  cd "$WEB"
  npm ci --no-audit --no-fund
  npx tsc -b
  VITE_API_URL="$APP_URL" VITE_SITE_URL="${APP_URL%/}" npx vite build --outDir dist-next --emptyOutDir
fi

step "Maintenance mode and migrations"
cd "$API"
"$PHP" artisan down --retry=15 || true
trap 'printf "\n\033[31mDeploy failed after maintenance mode began: the site is still DOWN.\nFix the error, then run deploy/deploy.sh again or php artisan up.\033[0m\n" >&2' ERR
"$PHP" artisan migrate --force

step "Caches"
"$PHP" artisan optimize

if [ "${SKIP_WEB:-}" != "1" ]; then
  step "Swap in the new front end"
  cd "$WEB"
  rm -rf dist-prev
  [ -d dist ] && mv dist dist-prev
  mv dist-next dist
  cd "$API"
fi

step "Restart the queue workers"
# Without this, workers keep running the previous release's code: invoices
# and e-mails go out from code that no longer exists on disk.
"$PHP" artisan queue:restart

if [ -n "${PHP_FPM_SERVICE:-}" ]; then
  step "Reload $PHP_FPM_SERVICE"
  sudo systemctl reload "$PHP_FPM_SERVICE"
fi

"$PHP" artisan up
trap - ERR

step "Health check"
APP_URL="$(env_get APP_URL)"
if curl -fsS --max-time 10 "$APP_URL/up" > /dev/null; then
  echo "$APP_URL/up answers."
else
  fail "$APP_URL/up did not answer. The release is live but something is wrong: check storage/logs."
fi
# The home page must come from Laravel with its own head. Served as the bare
# app shell instead (an old vhost), it would tell search engines not to index it.
if curl -fsS --max-time 10 "$APP_URL/" | grep -q 'rel="canonical"'; then
  echo "The home page is rendered for search engines. Deployed."
else
  fail "$APP_URL/ is not rendered by Laravel (no canonical link): install the current deploy/nginx/ebni.conf. Until then search engines are told not to index the site."
fi
