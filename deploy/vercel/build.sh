#!/bin/sh
# Vercel preview, build of the API function (vercel.json → vercel-php → `composer run vercel`).
#
# Runs on Vercel's build machine with the runtime's own PHP and Composer on the PATH. It
# leaves in the checkout what the function needs: api/vendor (no dev packages), the demo
# database, the key the preview signs its cookies with, and the template engine.
set -eu
cd "$(dirname "$0")/../.."

echo "▸ API dependencies, with dev ones: the demo seeders use the factories"
composer install --working-dir=api --no-interaction --no-progress --prefer-dist --no-scripts --ignore-platform-reqs

echo "▸ Demo database (SQLite): the curriculum and its translations, the 52 provinces,"
echo "  demo students, orders and companies, all marked [DEMO]. No real data."
DB="$PWD/api/database/preview.sqlite"
rm -f "$DB" && touch "$DB"
export APP_ENV=local APP_DEBUG=false DB_CONNECTION=sqlite DB_DATABASE="$DB" \
  CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=log LOG_CHANNEL=stderr \
  APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
(
  cd api
  php artisan package:discover
  php artisan migrate --force --seed
  php artisan db:seed --class=DemoShowcaseSeeder --force
  php artisan db:seed --class=PreviewVideoSeeder --force
)

echo "▸ The key this deployment signs its cookies with (new on every deploy)"
printf 'base64:%s' "$(head -c 32 /dev/urandom | base64)" > api/storage/app/vercel-app-key

echo "▸ API dependencies for the function: no dev packages"
composer install --working-dir=api --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --ignore-platform-reqs --optimize-autoloader
# The package list still names the dev packages: drop it before Laravel reads it again.
rm -f api/bootstrap/cache/packages.php api/bootstrap/cache/services.php
(cd api && php artisan package:discover)

echo "▸ The template engine (HyperFormula), run by financials/cli.js"
npm ci --prefix curso/2_plantilla_financiera/motor --no-audit --no-fund --ignore-scripts
