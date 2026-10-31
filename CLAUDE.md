# Construye Academy

Online course platform for CONSTRUYE, a Spanish real-estate development (promoción inmobiliaria)
course: 12 modules, one per phase of a housing development from the site to after-sales, plus
Financials, quizzes, a final exam and a directory of the 52 provinces. Laravel 12 API + React 18
SPA + a small Node.js service for the financial template. Spanish (authored) and English.

It is the Ebni Academy platform (the Algerian edition, in the sibling `ebni` repository) adapted
for Spain: content, language, euros, Stripe / bank transfer / Bizum, the 52 provinces and Spanish
invoices. The functionality is the same; keep it that way unless asked.

## Read this first

**`.specify/memory/constitution.md`** — the rules that are not negotiable: nothing invented on a
public surface, course content is the instructor's, paid content stays behind the paywall,
financial records are immutable, secrets fail closed. Each one exists because of a real defect.
Read it before changing anything that a buyer, a student or a tax inspector would see.

`reports/` holds the evidence behind those rules: a behavior inventory of 128 IDs and four review
reports (code & schema, tests, performance, security) plus a motion audit. `06-go-live-pass.md`
says what has been closed since, and what is left for the owner. They were written about Ebni,
before the Spanish adaptation: the paths and behaviours still match, the Algerian specifics do not.

**`curso/`** is the instructor's package, version 4.0, exactly as delivered (start with
`curso/LEEME.md`). Its texts are integrated as written and its financial template is used as is:
no formula, value or label changes, ever. Never edit anything under `curso/`; a correction comes
as a new package from the instructor.

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
`php artisan app:install` (the 52 provinces, the curriculum, one real admin).

| Task | Command |
|---|---|
| Tests | `docker compose exec -T php php artisan test --parallel --processes=4` |
| Format PHP | `docker compose exec -T php ./vendor/bin/pint` |
| Dependency audit | `docker compose exec -T php composer audit` |
| Secrets gate (before deploy) | `docker compose exec -T php php artisan app:check-secrets --strict` |
| Deploy / backups | `deploy/README.md` — `deploy/deploy.sh`, `deploy/backup.sh`, `deploy/restore-drill.sh` |
| Benchmark | `docker compose exec -T php php artisan bench:endpoints` |
| Front-end build / lint | `npm --prefix web run build` · `npm --prefix web run lint` |
| Financial template (engine = Excel; service) | `npm --prefix curso/2_plantilla_financiera/motor test` · `npm --prefix financials test` |

Tests run against `mysql-test`. `--parallel` gives each worker its own database; the grant that
makes that work lives in `docker/mysql-test/`.

## Shape of the code

- `api/app/Services/` holds the rules worth isolating — `PricingService` (tiers, coupons,
  campaigns, "best price wins"), `InvoiceBuilder`, `SpanishNumberSpeller` (the amount in words on
  invoices), `PaymentMethods` and `StripeService`.
- `api/app/Http/Controllers/Api/Concerns/AuthorizesCourseAccess.php` is the access gate. Every
  route that serves course material goes through it.
- `web/src/i18n/` — `es.json` is authored; `en.json` is the translation. Course *material* lives
  in the database with `_es`/`_en` columns and falls back to Spanish (the English material is
  empty at launch). The UI calls the unit "módulo", as the course text does; "fase" is the phase
  of a real promotion.
- `api/app/Support/Course/CourseSource.php` reads `curso/` and cuts each module file at its
  headings (overview sections, lessons, checklist, closing sections) without changing a word;
  `CurriculumV2Seeder`, `ChecklistSeeder` and `ProvinceGuideSeeder` store the pieces, and
  `tests/Feature/CourseContentTest.php` rebuilds every file from the database to prove it.
- Course text is Markdown, rendered on the server by `App\Support\Markdown` (raw HTML stripped).
- Modules have a `kind`: `phase` (numbered "Módulo N") or `financials`. Course-level texts are
  `CourseDocument`s (introduction, resources, annexes); Anexo A is one `ProvinceGuide` per province.
- Quizzes and the final exam are graded in `QuizController`; answers never go out before a
  submission, the exam's key never does.
- `financials/` wraps the package's engine (`curso/2_plantilla_financiera/motor`) in an HTTP
  service; `FinancialsController` is the only caller and checks access first.
- `.claude/skills/` — Laravel and design skills used by review passes, vendored from
  [ECC](https://github.com/affaan-m/ecc) and [emilkowalski/skills](https://github.com/emilkowalski/skills).

## Conventions worth knowing

- Money is whole euros on orders and courses, **cents** on invoices. The suffix says which:
  `total_gross_c` is cents. Invoices hold net (base imponible), `iva_c` and gross amounts.
- `config/locales.php` is the only list of supported locales. Nothing hardcodes one.
- Demo data is prefixed `[DEMO]`.
- The business calendar is `Europe/Madrid` (`app.business_timezone`); instants are stored in UTC.
- Payment credentials are placeholders. Wiring the real ones is the last step of the project.
