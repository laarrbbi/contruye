# Deploying Construye Academy

One Ubuntu server, one domain. Nginx serves the built SPA and passes `/api`, `/sanctum` and `/up`
to Laravel on PHP-FPM; MySQL holds the data; Supervisor keeps the queue worker running. The SPA
and the API share one origin, so there is no CORS and Sanctum's cookies are first-party.

Everything below was rehearsed end to end, with two exceptions marked **not rehearsed**. The
deploy, the secrets gate, the backup and the restore drill were run against a real MySQL 8. The
nginx vhost was served through real nginx and PHP-FPM containers. The rehearsal used a
self-signed certificate and a placeholder domain, so certbot and DNS were not part of it.

| File | What it is |
|---|---|
| `deploy/deploy.sh` | Ships the current checkout. Stops before touching the live site if anything is wrong. |
| `api/.env.production.example` | The production `.env`, with every value the deploy gate checks. |
| `deploy/nginx/construye.conf` | The vhost: TLS, SPA routing, Laravel routes, cache and security headers, including a Content-Security-Policy on HTML pages. |
| `deploy/supervisor/construye-queue.conf` | The queue worker (payment webhooks and every e-mail). |
| `deploy/php/99-construye.ini` | PHP upload limits: Ubuntu's 2 MB default refuses most receipt photos. |
| `deploy/backup.sh` | Nightly dump of the database and the private files (CCP receipts, attachments). |
| `deploy/restore-drill.sh` | Restores the latest backup into a scratch database and compares it with live. |

## 1. First install (once)

On Ubuntu 24.04, as a sudo user:

```bash
sudo apt install nginx mysql-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath \
  composer supervisor certbot python3-certbot-nginx rsync git unzip
# Node 22 for the front-end build (nodesource or nvm), then:
sudo mkdir -p /var/www/construye && sudo chown "$USER":www-data /var/www/construye
git clone https://github.com/laarrbbi/construye.git /var/www/construye
```

PHP upload limits. Ubuntu's PHP refuses any upload over 2 MB before Laravel sees it, and buyers
send CCP and BaridiMob receipts of up to 5 MB (a phone photo is often 3–4 MB). Without this, the
buyer gets "Le fichier reçu n'a pas pu être téléversé" and cannot finish paying:

```bash
sudo cp /var/www/construye/deploy/php/99-construye.ini /etc/php/8.3/fpm/conf.d/
sudo cp /var/www/construye/deploy/php/99-construye.ini /etc/php/8.3/cli/conf.d/
sudo systemctl reload php8.3-fpm
php-fpm8.3 -i | grep -E 'upload_max_filesize|post_max_size'   # 10M, 12M
```

Database:

```sql
CREATE DATABASE construye CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'construye'@'localhost' IDENTIFIED BY '<a long random password>';
GRANT ALL PRIVILEGES ON construye.* TO 'construye'@'localhost';
```

Environment: copy the template and fill every blank. The comments in the file say what each
value is for and where to find it.

```bash
cd /var/www/construye/api
cp .env.production.example .env
php artisan key:generate
```

Permissions: PHP-FPM runs as `www-data` and writes to `storage/` and `bootstrap/cache/`.

```bash
sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R g+rwX storage bootstrap/cache
```

TLS first, then Nginx. The vhost names the certificate files, so `nginx -t` fails until they
exist. Certbot answers its challenge through the stock default site:

```bash
sudo certbot certonly --nginx -d example.es -d www.example.es
sudo cp /var/www/construye/deploy/nginx/construye.conf /etc/nginx/sites-available/construye   # replace example.es
sudo ln -s /etc/nginx/sites-available/construye /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

The queue worker:

```bash
sudo cp /var/www/construye/deploy/supervisor/construye-queue.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
```

Outbound connections. If the host or its firewall filters outgoing traffic, allow:

| To | Port | For | If blocked |
|---|---|---|---|
| `MAIL_HOST` | `MAIL_PORT` (587) | Every e-mail: receipts, access granted, password reset | Mail stays in the queue and fails |
| `pay.chargily.net` | 443 | CIB / Edahabia checkout | Buyer sees "payment unavailable" and is offered CCP |
| `api.stripe.com` | 443 | International card checkout (if enabled) | Same |
| `api.pwnedpasswords.com` | 443 | Refusing leaked passwords at sign-up and reset | Sign-up still works without the check; a silently dropped connection makes each sign-up wait 5 s (capped in `AppServiceProvider`) |
| `BACKUP_RSYNC_TARGET` | 22 | The off-server copy of the nightly backup | Backups stay on the server only |

Inbound, the payment webhooks arrive on 443 like any visitor. Lesson videos stream from Bunny
straight to the browser; the server never contacts Bunny.

Then deploy (section 2). After the first deploy has created the tables, install the content and
the admin account:

```bash
cd /var/www/construye/api && php artisan app:install
```

`app:install` seeds the 58 provinces, the instructor's curriculum and checklists, and one admin. It
asks for the owner's real e-mail and a password of 12 characters or more. **Never run
`db:seed` or `migrate --seed` here.** The default seeder creates `admin@example.es` with the
password `password`, invented students with paid orders, and coupons whose codes are public. It
now refuses to run in production, and so do the showcase and benchmark seeders.

## 2. Every deploy

```bash
cd /var/www/construye && git pull && PHP_FPM_SERVICE=php8.3-fpm deploy/deploy.sh
```

In this order:

1. `composer install --no-dev`.
2. **`php artisan app:check-secrets --strict`.** The deploy stops here if:
   - a secret is blank;
   - Chargily points at its test endpoint or uses a test key;
   - Stripe uses a test key;
   - `APP_DEBUG` is on, or cookies aren't secure;
   - a URL is not https;
   - mail goes to the log;
   - no payment method is configured at all.

   It names every problem at once.
3. The front-end build, into `web/dist-next`. The live `dist` is untouched.
4. Maintenance mode, then `migrate --force`. This is the first step that touches the live site.
5. `artisan optimize`, the swap to the new `dist`, `queue:restart` (otherwise the workers keep
   running the old code), an FPM reload, then out of maintenance.
6. A request to `/up`.

If step 2 or 3 fails, the running release is exactly as it was. If a migration fails, the site
stays in maintenance and the script says so.

Run `php artisan app:check-secrets` without `--strict` at any time to preview the gate.

## 3. Payment methods: what the buyer is offered

The checkout offers only the methods that can take money (`GET /api/checkout/methods`):

| Method | Offered when |
|---|---|
| CIB / Edahabia (Chargily) | `CHARGILY_ENABLED=true` and both Chargily keys are set |
| International card (Stripe) | `STRIPE_ENABLED=true` and both Stripe keys are set, and the course has a euro price |
| CCP | `PAYMENT_CCP_NUMBER` and `PAYMENT_CCP_NAME` are set |
| BaridiMob | `PAYMENT_BARIDIMOB_RIP` is set |

**Launching before the gateways are live** (CCP and BaridiMob only): set `CHARGILY_ENABLED=false`
and `STRIPE_ENABLED=false`. Blank keys with the switch left on fail the deploy on purpose, because
blank is not the same as off.

Webhook URLs to register with the providers:
- Chargily: `https://example.es/api/webhooks/chargily`
- Stripe: `https://example.es/api/webhooks/stripe`

## 4. Backups

```bash
# as the deploy user, in crontab -e
30 2 * * * BACKUP_RSYNC_TARGET=backup@other-host:/backups/construye /var/www/construye/deploy/backup.sh >> /var/log/construye-backup.log 2>&1
```

Each night this writes `db-<time>.sql.gz` (a consistent InnoDB snapshot taken without locking the
site) and `files-<time>.tar.gz` (`storage/app/private`: CCP receipts and lesson attachments). It
checks that the dump finished, keeps 30 days, and copies everything to `BACKUP_RSYNC_TARGET`. Set
that target: a backup that lives only on the server it backs up does not survive the failure it is
for.

**Before go-live, and after any change to the backup, prove it restores:**

```bash
MYSQL_ADMIN_USER=root MYSQL_ADMIN_PASSWORD=... deploy/restore-drill.sh
```

The drill loads the latest dump into a scratch database and compares users, orders, order lines,
enrollments, invoices and coupons with live. It also checks that the files archive opens and
matches what is on disk, then drops the scratch database. A live table with *fewer* rows than the
backup fails the drill. An untested backup is not a backup.

## 5. Search engines

The public pages (`/`, `/formations`, `/courses/…`, and the same under `/ar` and `/en`) are
rendered by Laravel with their own title, description, canonical URL, links to their other
languages, structured data (course, price, FAQ, organisation) and content, so Google, Bing and
link previews read them without running JavaScript. The vhost sends exactly those paths to
Laravel; the deploy fails its health check if the home page comes back without its canonical link.
French is at the root, Arabic under `/ar`, English under `/en`. Every other path is a real 404, and
the app's own screens (login, dashboard, checkout…) tell search engines not to index them.

After the first deploy, once DNS points at the server:

1. **Google Search Console**: add a *Domain* property and verify it with the DNS TXT record it gives
   (or a *URL prefix* property with `GOOGLE_SITE_VERIFICATION` in `.env`). Submit
   `https://example.es/sitemap.xml`. Use *URL inspection* on `/`, `/ar` and `/en`: the rendered page
   must be in French, Arabic and English respectively.
2. **Bing Webmaster Tools**: import the site from Search Console (or verify with
   `BING_SITE_VERIFICATION`) and submit the same sitemap.
3. **Link previews**: paste `https://example.es/` into the Facebook Sharing Debugger and the LinkedIn
   Post Inspector once, so they cache the current card.
4. **Google Business Profile**, if the business receives people at an address: same name, address and
   phone as in *Entreprise & contact*.

## 6. Before taking money: what only the owner can do

These cannot be done from the repository. The deploy gate catches each one that is a setting.

- [ ] A server and a domain, and DNS pointing at it. (**not rehearsed**: certbot needs the real domain)
- [ ] Chargily account in **live** mode: keys plus the webhook URL. Or `CHARGILY_ENABLED=false`.
- [ ] Stripe: launch or not. Keys plus webhook URL, or `STRIPE_ENABLED=false`.
- [ ] CCP and BaridiMob account details in `.env`.
- [ ] SMTP credentials, and one test message actually received. (**not rehearsed**)
- [ ] Seller identity in the admin (*Entreprise & contact*): RC, NIF, NIS, AI. Invoicing refuses to
      issue until they are filled.
- [ ] The TVA rate for online training, confirmed with the accountant.
- [ ] Bunny Stream library and token key, and one real lesson video playing with the watermark.
- [ ] The restore drill run once on the server.
- [ ] Branch protection on `main`, with the `api` and `web` CI checks required.
