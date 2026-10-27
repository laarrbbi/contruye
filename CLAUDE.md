# Ebni Academy

Online course platform for an Algerian real-estate development (promotion immobilière) course,
taught by a promoter with 16 years in the trade. Laravel 12 API + React 18 SPA, trilingual FR/AR/EN.

## Read this first

**`.specify/memory/constitution.md`** — the rules that are not negotiable: nothing invented on a
public surface, course content is the instructor's, paid content stays behind the paywall,
financial records are immutable, secrets fail closed. Each one exists because of a real defect.
Read it before changing anything that a buyer, a student or a tax inspector would see.

`reports/` holds the evidence behind those rules: a behavior inventory of 128 IDs and four review
reports (code & schema, tests, performance, security) plus a motion audit. `06-go-live-pass.md`
says what has been closed since, and what is left for the owner.

## Running it

Everything PHP goes through Docker. There is no local PHP. From a fresh clone, `make setup` does the
first run (settings, containers, `composer install`, the app key, the demo database, `npm install`);
`.github/workflows/setup.yml` runs those steps every week. Then:

```bash
docker compose up -d
docker compose exec -T php php artisan migrate --seed
docker compose exec -T php php artisan db:seed --class=DemoShowcaseSeeder   # opt-in demo data
npm --prefix web run dev
```

The default seed is dev-only: demo logins with the password `password`, invented paid orders,
public coupon codes. It refuses to run in production; a live database is filled with
`php artisan app:install` (wilayas, curriculum, one real admin).

| Task | Command |
|---|---|
| Tests | `docker compose exec -T php php artisan test --parallel --processes=4` |
| Format PHP | `docker compose exec -T php ./vendor/bin/pint` |
| Dependency audit | `docker compose exec -T php composer audit` |
| Secrets gate (before deploy) | `docker compose exec -T php php artisan app:check-secrets --strict` |
| Deploy / backups | `deploy/README.md` — `deploy/deploy.sh`, `deploy/backup.sh`, `deploy/restore-drill.sh` |
| Benchmark | `docker compose exec -T php php artisan bench:endpoints` |
| Front-end build / lint | `npm --prefix web run build` · `npm --prefix web run lint` |

Tests run against `mysql-test`. `--parallel` gives each worker its own database; the grant that
makes that work lives in `docker/mysql-test/`.

## Shape of the code

- `api/app/Services/` holds the rules worth isolating — `PricingService` (tiers, coupons,
  campaigns, "best price wins"), `InvoiceBuilder`, `FrenchNumberSpeller`, the payment gateways.
- `api/app/Http/Controllers/Api/Concerns/AuthorizesCourseAccess.php` is the access gate. Every
  route that serves course material goes through it.
- `web/src/i18n/` — `fr.json` is authored; `ar.json` and `en.json` are translations. Course
  *material* lives in the database with `_fr`/`_ar` columns and falls back to French.
- `.claude/skills/` — Laravel and design skills used by review passes, vendored from
  [ECC](https://github.com/affaan-m/ecc) and [emilkowalski/skills](https://github.com/emilkowalski/skills).

## Conventions worth knowing

- Money is whole currency units on orders and courses, **centimes** on invoices. The suffix says
  which: `total_ttc_c` is centimes.
- `config/locales.php` is the only list of supported locales. Nothing hardcodes one.
- Demo data is prefixed `[DÉMO]`.
- Payment credentials are placeholders. Wiring the real ones is the last step of the project.
