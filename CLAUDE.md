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

**`curso/`** is the instructor's package, version 5, exactly as delivered (start with
`curso/LEEME.md`). Its texts are integrated as written and its financial templates are used as
delivered: no formula, value or label changes, ever; only the «Parámetros legales» sheet is set,
by the admin. Never edit anything under `curso/`; a correction comes as a new package from the
instructor (copied whole into `curso/`, then the curriculum, checklist, province guide and
translation seeders, in that order, which bring an installed course up to it in place).
Templates the instructor sends outside a package go, unchanged, in **`materiales/`** (its
`LEEME.md` lists each file, where it is offered and its checksum); `CourseFiles` serves them to the
owners of their module (that module's «Plantilla» page) or, for the extra's material, to its buyers.
It is empty now: the three that came loose on 11/11/2026 are in package v5.

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
| Financial templates (engine = Excel; service) | `npm --prefix curso/2_plantilla_financiera/motor test` (the package's 44 checks) · `npm --prefix financials test` |

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
- The course text never leaves in one response: `/learn` is the outline, each lesson's and
  module's text comes from `LearnContentController`, metered per student (`MeterCourseContent`,
  alias `content`, on every route that serves material) and marked with the reader's invisible
  fingerprint (`ContentFingerprint`, `php artisan content:trace`). The player column is a
  `ProtectedArea` (no select/copy/print, the reader's e-mail as a faint pattern). Two devices per
  account (`DeviceSessions`). All of it: `docs/proteccion-contenido.md`.
- The solar calculator (`SolarTeaser`, `SolarCalculatorController`, public): a sample of Plantilla 1
  (6 of 7 inputs, its results but the VAT line and the money on the day, which stay with Module 1;
  constitution III, amendment 2026-11-12) on its own page `/calculadora-precio-solar`, «Valorador de
  solares» in the menu, not on the sales page (the owner's call)
  (`SolarCalculatorPage`; server-rendered for search engines by `SeoPageController::solarCalculator`,
  with the ITP/AJD of every community, an FAQ and its structured data, in the sitemap).
- The menu (`components/layout/Header.tsx`): a signed-in user's places (Mis cursos, Partners; Mi
  cuenta and Administración in the account menu) and, for everyone, the sections Valorador de
  solares, Noticias, Empleo, Obras and Data centers (tagged «Pronto»). Below 1280px they move into a
  menu (the account menu once signed in).
- Data centers (`/data-centers`, `DataCentersPage`, `SeoPageController::dataCenters`): the next
  course, announced and nothing more (no programme, price or date until there is one); a sign-up to
  the newsletter with the source `datacenters`, so the owner counts who is waiting for it.
- The news (`NewsPost`, `NewsController`, `/admin/news`): pieces the owner writes, Spanish required,
  the other languages optional (Spanish shows where one is missing), a date (shown from that day)
  and a picture on the local disk. `/noticias` and `/noticias/<slug>` are public pages, rendered
  for search engines with `NewsArticle` data and listed in the sitemap once a piece is out. The
  table ships empty: nothing is published that nobody wrote.
- Empleo and Obras are **Construye Fichaje**, a separate site with its own accounts and payments
  (github.com/laarrbbi/contruye_fichaje, Vercel project `construye-fichaje`): the menu links to its
  `/empleo` and `/obras` in a new tab (`lib/fichaje.ts`, `VITE_FICHAJE_URL`). Nothing of it runs here.
- Lesson videos: Bunny Stream on the real server (set per lesson in the admin; its player has its
  own speed and captions). Where Bunny is not configured (the Vercel preview, `VIDEO_FILES_BASE`) a
  video is a file in `web/public/video`: `<id>.mp4` (H.264, AAC 48 kHz for iPhones), `<id>.jpg`
  (cover) and `<id>.vtt` (subtitles), played by `VideoPlayer` with a speed selector (0.75–2×) and a
  subtitles switch, both remembered on the device. The lesson videos of Modules 1 and 2 (package
  v5, `4_videos/`: 1.1–1.4 and 2.1–2.5, copied as delivered, ids ending in `-v5` so a new cut is a
  new address): `PreviewVideoSeeder`, which places each by its lesson's heading.
- The newsletter (`NewsletterSubscriber`, `NewsletterController`): double opt-in (the e-mail's link
  confirms on `/newsletter`, a click unsubscribes), the same answer whoever signs up, a honeypot;
  sections on the sales page (`landing/s8/Boletin`) and the calculator's page. `/admin/newsletter`
  lists them and exports the confirmed ones with each one's unsubscribe link, for the owner's
  mailing tool (nothing is sent from here but the confirmation).
- The account page (`/account/:section`, `AccountController`): details, invoice details (they
  pre-fill the owner's invoice form; `FiscalId` checks a NIF/NIE/CIF), invoices, orders and payment
  methods, password and devices, a JSON copy of the student's own data.
- `financials/` wraps the package's engine (`curso/2_plantilla_financiera/motor`) in an HTTP
  service: the professional template, the five small ones and the professional's «números gordos
  del solar» (no legal sheet: it gets no parameters). `App\Services\TemplateEngine` is the
  only caller; `FinancialsController` (the professional template, the extra) and
  `SmallTemplateController` (Plantilla 1–5, each in its module: 1, 2, 5, 7, 8, by number; the
  «números gordos», Module 1, by its id `numeros_gordos_solar`) check access first.
  `TemplateCatalog` holds inputs to each template's dictionary; `XlsxFiller` writes a user's inputs
  and the parameters into a copy of the package's `.xlsx` for download.
- The 7 templates included (package v5, `plantillas_pequenas/indice.json`): the 5 of CONSTRUYE and
  the professional's 2 in Module 1, on its «Plantilla» page under Plantilla 1: the «números gordos»
  (calculator and Excel) and the land management database (`base_datos_suelo.xlsx`, a download,
  `CourseFiles`). The extra adds the professional's feasibility guide
  (`Guia_Analisis_de_Viabilidad_corregida.xlsx`, served with the legal parameters in force written
  in) and, in its calculator, «Cargar el ejemplo de la guía del profesional»
  (`pruebas/escenario_guia_profesional.json` without D133 and D154). Every calculation of the
  professional template returns Datos de Partida E79, the calendar warning shown beside month zero
  (D79): «AVISO…» when a payment falls outside the 48 months of the cash flow.
- Under each lesson's text (`LessonResources` in the player, `config/lesson_resources.php`): the
  websites the lesson names, each at its checked official address (`docs/enlaces-de-las-lecciones.md`;
  `mention` must be the lesson's own words, `LessonResourcesTest`), then the partners it calls on:
  the academy's own (`Partner` categories, `inmobiliaria` included) and the directory's companies of
  those kinds (`/lessons/{lesson}/companies`, the module's gate). Then the module's introduction
  (objective, laws, where to go), below the lesson: the owner wants the lesson read first.
- Legal parameters (`LegalParameters`, tables `legal_parameters`, `legal_region_rates`,
  `legal_parameter_changes`): seeded from the package, edited in `/admin/templates` with a history,
  and sent with every calculation. A project never stores them.
- Reference values (`ReferenceValues`, `financials/valores_de_referencia.json`, tables
  `template_reference_values` and `_changes`): the 2002 tariffs of the professional template (OCT,
  ten-year insurance, guarantees, fee tables; 193 plain-value cells, the wrapper's whitelist) and
  the values 30 inputs are presented with, kept up to date in `/admin/templates` beside the
  package's values (constitution II, amendment 2026-11-12). What sits inside formulas comes with a
  new package. The analysis: `docs/valores-de-referencia.md`.
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
- Phones: a list built with `components/ui/table.tsx` becomes cards under 640px (each value under
  its column's name; `stack={false}` opts out); fields are 16px on a touch screen (iOS zooms
  under that); bars pinned to the bottom pad themselves with `env(safe-area-inset-bottom)`;
  heights use `dvh`/`svh`, never `vh`; a control is at least 40px to a finger. The rules close
  `web/src/index.css`. Emulation does not show zoom, tap delay or safe areas: check on a phone.
