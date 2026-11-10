# Construye Academy

Online course platform for CONSTRUYE, a Spanish real-estate development (promoción inmobiliaria)
course: 12 modules, one per phase of a housing development from the site to after-sales, plus
Financials, quizzes, a final exam and a directory of the 52 provinces. Laravel 12 API + React 18
SPA + a small Node.js service for the financial templates. Spanish (authored), English, French,
Arabic (right to left), Russian and Ukrainian.

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

**`curso/`** is the instructor's package, version 4.1, exactly as delivered (start with
`curso/LEEME.md`). Its texts are integrated as written and its financial templates are used as
delivered: no formula, value or label changes, ever; only the «Parámetros legales» sheet is set,
by the admin. Never edit anything under `curso/`; a correction comes as a new package from the
instructor (copied whole into `curso/`, then the curriculum, checklist, province guide and
translation seeders, in that order, which bring an installed course up to it in place).

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
| Course translations: check / load | `docker compose exec -T php php artisan app:check-translations` · `… db:seed --class=CourseTranslationSeeder` |
| Deploy / backups | `deploy/README.md` — `deploy/deploy.sh`, `deploy/backup.sh`, `deploy/restore-drill.sh` |
| Benchmark | `docker compose exec -T php php artisan bench:endpoints` |
| Company directory: import | `docker compose exec -T php php artisan directory:import <file.xlsx>…` (Orbis export; see README «Directorio de empresas» on data rights) |
| Front-end build / lint | `npm --prefix web run build` · `npm --prefix web run lint` |
| Financial templates (engine = Excel; service) | `npm --prefix curso/2_plantilla_financiera/motor test` (the package's 39 checks) · `npm --prefix financials test` |

Tests run against `mysql-test`. `--parallel` gives each worker its own database; the grant that
makes that work lives in `docker/mysql-test/`.

## Shape of the code

- `api/app/Services/` holds the rules worth isolating — `PricingService` (tiers, coupons,
  campaigns, "best price wins"), `InvoiceBuilder`, `SpanishNumberSpeller` (the amount in words on
  invoices), `PaymentMethods` and `StripeService`.
- `api/app/Http/Controllers/Api/Concerns/AuthorizesCourseAccess.php` is the access gate. Every
  route that serves course material goes through it.
- `web/src/i18n/` — `es.json` is authored; `en`, `fr`, `ar`, `ru` and `uk.json` are translations,
  each with its own plural forms (Russian/Ukrainian one/few/many, Arabic zero…many). Course
  *material* lives in the database with a column per locale (`_es`, `_ru`…) and falls back to
  Spanish, which the player says. The UI calls the unit "módulo", as the course text does; "fase"
  is the phase of a real promotion.
- `traducciones/` — our translations of the course, one folder per locale, files mirroring
  `curso/` section for section (`<!-- es -->` = not translated yet). `CourseTranslation` checks each
  piece against the Spanish (every figure, law number and acronym kept) and
  `CourseTranslationSeeder` loads them. Drafts until a human reviews them: see its README.
- `api/app/Support/Course/CourseSource.php` reads `curso/` and cuts each module file at its
  headings (overview sections, lessons, checklist, closing sections) without changing a word;
  `CurriculumV2Seeder`, `ChecklistSeeder` and `ProvinceGuideSeeder` store the pieces, and
  `tests/Feature/CourseContentTest.php` rebuilds every file from the database to prove it.
- The sales page (`web/src/pages/SalesPage.tsx`, sections in `web/src/components/landing/s1`…`s9`)
  and the sign-in screens (`web/src/pages/auth/`) are Hani Oulhadj's design for Ebni, kept as is
  and filled with CONSTRUYE's copy (`landing.*` keys). Every picture on it is a licensed
  illustration, none an instructor's project: sources, licences and scripts in
  `docs/credits-photos.md`.
- Course text is Markdown, rendered on the server by `App\Support\Markdown` (raw HTML stripped).
- Modules have a `kind`: `phase` (numbered "Módulo N") or `financials`. Course-level texts are
  `CourseDocument`s (introduction, resources, annexes); Anexo A is one `ProvinceGuide` per province.
- The company directory (`Company`, `config/directory.php`) maps each company's CNAE code to a kind
  and each kind to the modules it serves. `CompanyDirectoryController` serves it behind the module
  gate; `/admin/companies` decides which companies students see. The data is never committed.
- The three offers (`Offer`, `OfferService`, `/admin/offers`): after the purchase (24–48 h), at the
  first milestone, at the end. A student's moment is stored once with its real time
  (`user_offers`), so a deadline never moves; `offers:send` (scheduler, every 10 min) e-mails them.
  The slots ship empty and off: nothing is offered until the owner writes it.
- Every law the course names links to its official text (`config/legal.php`, `Support/Legal`):
  added when the Markdown is rendered, never written into the text. A new law in a new package
  needs its entry (`LegalLinksTest` fails until it has one); `docs/fuentes-oficiales.md` says how
  each link was checked. Only official sites, https. The app's own copy that names a law (the FAQ)
  is linked in the browser from `web/src/content/legal-links.json`: `php artisan legal:export-web`.
- Quizzes and the final exam are graded in `QuizController`; answers never go out before a
  submission, the exam's key never does.
- `financials/` wraps the package's engine (`curso/2_plantilla_financiera/motor`) in an HTTP
  service: the professional template and the five small ones. `App\Services\TemplateEngine` is the
  only caller; `FinancialsController` (the professional template, the extra) and
  `SmallTemplateController` (Plantilla 1–5, each in its module: 1, 2, 5, 7, 8) check access first.
  `TemplateCatalog` holds inputs to each template's dictionary; `XlsxFiller` writes a user's inputs
  and the parameters into a copy of the package's `.xlsx` for download.
- Legal parameters (`LegalParameters`, tables `legal_parameters`, `legal_region_rates`,
  `legal_parameter_changes`): seeded from the package, edited in `/admin/templates` with a history,
  and sent with every calculation. A project never stores them.
- Financials is the extra (`FinancialsExtra`): outside every plan and full access
  (`Course::includedModules()`), bought on its own (checkout scope `extra`), on sale only once the
  admin switches it on with a price. Offered on the final exam once passed, on the dashboard of a
  student who finished, and in Financials itself.
- `.claude/skills/` — Laravel and design skills used by review passes, vendored from
  [ECC](https://github.com/affaan-m/ecc) and [emilkowalski/skills](https://github.com/emilkowalski/skills).

## Conventions worth knowing

- Money is whole euros on orders and courses, **cents** on invoices. The suffix says which:
  `total_gross_c` is cents. Invoices hold net (base imponible), `iva_c` and gross amounts.
- `config/locales.php` is the only list of supported locales. Nothing hardcodes one.
- Demo data is prefixed `[DEMO]`.
- The business calendar is `Europe/Madrid` (`app.business_timezone`); instants are stored in UTC.
- Payment credentials are placeholders. Wiring the real ones is the last step of the project.
