# Go-Live Pass — Ebni Academy

**Date:** 2026-10-04
**Scope:** everything in `specs/001-go-live/spec.md` that does not need the owner's credentials, plus
every finding still open in reports 01–05
**Commits:** `b643a7e` … `4342e93` (20 commits on `claude/eloquent-galileo-1bhiul`)

## Summary

The product was audited and feature-complete; what stood between it and a first paying student was
deployment, the deploy gate, and a tail of open findings. This pass closed what can be closed
without the owner.

Tests went from **320 to 404 passing**, and from passing only on the original laptop to passing on a
clean checkout. Every push now runs the constitution's quality gates in GitHub Actions: 13 runs, 12
green and one cancelled because the next push superseded it (that next run, which contained the
same commit, was green). `composer audit` and `npm audit --omit=dev` are clean again: four new advisories
had been published since 26 September.

Five defects found along the way touched money or proof of payment:

| Defect | Consequence | Commit |
|---|---|---|
| Replayed "payment failed" webhooks released the coupon use each time | A 10-use coupon could be redeemed without limit | `87f5454` |
| A buyer who bought every module separately was not "full access" to checkout | They were offered the full course again at 90 000 DA | `040b3d8` |
| A failed receipt upload deleted the previous receipt first | The buyer's only proof of a CCP transfer was lost | `6080afc` |
| `migrate --seed` was the only way to fill a database | Production would get `admin@example.dz` / `password`, invented paid orders, public coupons | `2b12ab4` |
| Card payment was offered whether or not Chargily was configured | On a CCP-only launch, every "Payer" made a 502 and a failed order | `eacb524` |

## Scope and method

- **Ran:** the stack in Docker (MySQL 8, Redis), PHP 8.3 and Node 22 on the host. Every change went
  through the full suite with `--parallel`, Pint, `tsc` + Vite build and ESLint before commit.
- **Mutation checks:** each new guard was removed or broken and its test observed going red, then
  restored, as quality gate 2 requires. Where a characterization test had pinned a defect "so that
  fixing it fails here loudly", it was rewritten to assert the fix, not deleted.
- **Browser:** every visible change was driven in Chromium (Playwright) against the seeded dev
  database. That covered checkout in three configurations (including Arabic at 390 px), the admin
  dashboard and review queue, the cash-payment dialog, paginated reviews, the FAQ icon and the
  player sidebar.
- **Deployment rehearsal:**
  - `deploy/deploy.sh` ran end to end in a scratch copy against MySQL.
  - The nginx vhost was served through real nginx and php-fpm containers with a self-signed
    certificate.
  - The backup and restore drill ran against MySQL, including both failure paths.
- **Could not verify:**
  - Real payment providers (still faked).
  - Certbot against a real domain, and real SMTP delivery.
  - Genuine concurrency: the invoice and enrollment races are reasoned and enforced by locks and
    indexes, and tested sequentially.
  - A human reading of the Arabic.

## What was done

### Infrastructure

- **Clean checkout:** `tests/Unit` was never committed, so paratest refused to start. Three Stripe
  tests depended on a key in one developer's `.env`. With a blank key the Stripe webhook answered
  500, not 403. Fixed; `phpunit.xml` now pins every third-party credential blank.
- **CI** (`.github/workflows/ci.yml`): Pint, composer audit and the suite on MySQL 8; tsc + Vite
  build, ESLint and the npm runtime audit. Making it *block* merges is a branch-protection setting.
- **Advisories:** laravel/framework, league/commonmark and league/flysystem on the PHP side; axios
  and react-router in the shipped bundle. Two build-tool advisories remain with no non-breaking
  fix: braces (via tailwindcss 3) and esbuild (via Vite 5). Neither reaches a browser.
- **Dev setup:** the Vite port moved to 5174 while `.env.example`, CORS and the README said 5173;
  the lint command in `CLAUDE.md` could not find its config. Both fixed.

### Go-live

- **Payment methods** (`PaymentMethods`, `GET /api/checkout/methods`): a method is offered only
  when it can take money.
  - The checkout page offers that list and nothing else.
  - `POST /checkout` refuses an unavailable method before any order, provider call or coupon
    reservation.
  - `CHARGILY_ENABLED` / `STRIPE_ENABLED` switch a gateway off on purpose; blank keys are not "off".
- **Deploy gate** (`app:check-secrets --strict`). It now also refuses:
  - a blank `CHARGILY_API_KEY`;
  - the Chargily **test endpoint** (the spec's named trap: it is the default);
  - test-mode keys;
  - `APP_DEBUG` on, insecure cookies, or http URLs;
  - `MAIL_MAILER=log`;
  - a deploy where no payment method at all is configured.
- **Deploy tooling** (`deploy/`):
  - `deploy.sh` runs the gate and the build before touching the live site, then migrates, caches,
    swaps the front end and restarts the queue.
  - The nginx vhost, the supervisor config and a production `.env` template.
  - `backup.sh` and `restore-drill.sh`.
  - The rehearsal caught an nginx `add_header` inheritance trap that would have shipped the pages
    without HSTS.
- **`php artisan app:install`:** wilayas, curriculum and one real admin with a typed password. The
  demo, showcase and benchmark seeders now refuse to run in production.
- **Receipts waiting > 48 h** (spec US3): `orders.receipt_uploaded_at`, a red count on the
  dashboard, and an oldest-first review queue with each receipt's age.
- **Cash at the office** (C5): `POST /admin/students/{id}/cash-orders`, priced by PricingService,
  with a dialog in the admin.

### Findings closed

| ID | Fix |
|---|---|
| C13/C16 | One definition of full access (`User::hasFullAccessTo`): every module watchable however bought. |
| coupon leak | `markFailed` acts once, from pending/awaiting_review only. Every way out of `failed` re-claims the use. |
| C18 | Paid transition, audit entry and queued mail commit together; a failed queue write is retried with the mail. |
| C24 | The new receipt is stored before the old is deleted; a failed write answers 500 and keeps everything. |
| C15 | An invoice can no longer carry a date earlier than the previous number of its run. |
| C20 | A second invoice for the same order is a 422 under a row lock, not three collisions and a 500. |
| C21/C40 | Functional unique index: one full-access enrollment per student per course. Enrollment uses `createOrFirst`. |
| C10 | Public reviews paginated (10, max 50); the summary still counts every review. |
| C31 | Bare `abort()` and missing models answer a translated message, never `""` or a class name. |
| S4/S9/C37 | Another account's order answers 404, byte-identical to a missing one. |
| C22 | `position_seconds` capped at a day; was a 500 past the column's ceiling. |
| C51 | Admin searches escape LIKE wildcards (`App\Support\Like`). |
| C32 | `orders_by_method` is always a JSON object. |
| C56 | The reflex-sheet PDF exists only for lessons written as one. |
| C35/C45/C46/C52/C53/C54/C57 | Dead code removed. `api/types.ts` takes `Locale` from i18n (it lacked English). |
| student detail | A per-module buyer showed one row per module, each "0 / 34 leçons". Now one row per course. |
| motion | 31 of the 32 open motion findings, or verified as already fixed (see `05`). |

Re-checked and already fixed by earlier commits: C3, C9, C12, C25/C28/C50, C39, C63 (S5/S10), S12,
P1–P3, and the motion items listed in the motion commit.

## Second pass: driving the product (2026-10-07)

**Commits:** `95b67f6` … `f81692f` (10 commits). **Tests:** 408 passing. `composer audit` and
`npm audit --omit=dev` clean. Every CI run of this pass that finished was green; one was cancelled
by the next push.

**Method.** The product was used the way its three kinds of user use it, in Chromium (Playwright)
against the dev stack, then again against the **production build**:

- **Sweep:** 93 views (guest, three students in different states, admin) in FR/AR/EN, at 390 px and
  1280 px. Each view was checked for sideways scroll, console errors, failed requests, and axe
  accessibility findings (moderate and above).
- **Flows,** by clicking and typing:
  - a first-time buyer on a phone: sales page, sign-up (bad phone, then a good one), checkout,
    CCP, receipt upload;
  - the admin: business settings, review queue, approve, invoice, PDF from both sides, moderation,
    coupon, promotion, reject with a reason;
  - the new student: lesson, complete, next, roadmap, own task, review;
  - password reset from the queued e-mail's link;
  - card payment with the gateway down;
  - the player on a phone in Arabic;
  - the 404 page.

Fourteen scripted flows in all, plus a page chunk made to 404 mid-session to mimic a deploy.

**Defects found and fixed**, worst first:

| Defect | Consequence | Commit |
|---|---|---|
| Ubuntu's PHP refuses uploads over 2 MB; the runbook never raised it | A 4.2 MB phone photo of a CCP receipt (the app allows 5 MB) was refused, so the buyer could not finish paying. Reproduced on stock PHP 8.3 | `47b1f83` |
| An HTTP-layer exception from Chargily escaped as a 500 | The order stayed pending and the buyer saw a raw exception. Now 502, a clean failure, and a one-tap switch to CCP | `1e03c25` |
| An expired session turned invoice links into a 500 | Laravel redirected to a `login` route this API does not have | `905f6c7` |
| Every facture was 880 KB | DomPDF embedded two whole fonts. Subsetting brings it to 27.6 KB, same text and layout | `905f6c7` |
| Sign-up said 8 characters, the API requires 10 | A 9-character password passed the form and came back refused | `e43b664` |
| Every page scrolled sideways on a 390 px phone | The header was 472 px wide | `95b67f6` |
| During a promotion, checkout's plan card said 90 000 DA and the button 72 000 DA | Two prices on one screen | `f81692f` |
| A promotion required an Arabic name | A French-speaking owner could not start one | `1e03c25` |
| French lines on Arabic pages were reordered | "2.1 Choisir la forme" read "Choisir la forme 2.1" | `1e03c25` |
| Unknown URLs rendered a blank page; a render crash, a white page | No way back | `905f6c7` |
| Admin orders clipped its action buttons at 1280 px | Approve/reject for a waiting receipt sat off-screen | `905f6c7` |
| Arabic admin showed blank module and lesson titles | "Supprimer « »" in confirmations | `0e1650e` |
| The mobile buy bar's 250 ms rise generated no CSS | An ambiguous Tailwind class, silently dropped | `0e1650e` |

**Front-end performance and deployment** (`b696e41`, `5448bd3`, `47b1f83`):

- The app shipped as one 1,059 KB script (306 KB gzip) with all three languages. Now only the sales
  page is in the entry chunk: 252 KB, plus a 327 KB vendor chunk that stays cached across deploys
  (79 + 107 KB gzip).
  - The buying path is prefetched when the browser is idle. In the production build the sign-up
    form appears about 160 ms after the main CTA is clicked.
  - Arabic and English load on demand. Arabic still paints Arabic and RTL first.
  - A tab left open across a deploy reloads once instead of crashing. Tested with a 404'd chunk;
    a chunk that stays missing shows the error screen after one retry, with no loop.
- Favicon, touch icon and an Open Graph card, so a link shared on WhatsApp or Facebook gets a
  preview, not a bare URL. The card uses only the sales page's own words and the instructor's
  photo. `og:image` is absolute: `deploy.sh` passes `APP_URL`.
- Runbook additions: `deploy/php/99-ebni.ini` with its install step, and every outbound connection
  the server makes, with what breaks when a firewall blocks it.

**Accessibility and UX** (`95b67f6`, `0e1650e`, `7ee2892`, `e43b664`): after this pass the sweep's
only axe finding is the 9 px "ACADEMY" under the logo, which WCAG exempts as a logotype.

- Every form error is tied to its field and focused.
- One `<main>` per page.
- Headings in order, and an h1 on every state screen.
- The language button's spoken name includes the "FR" it shows.
- Error red at 5.4:1 on its own panels.
- Locked lessons are readable: they are the teaser.
- Forgot/reset password now look like login, offer a new link when theirs has expired, and let a
  password manager save the new password against the right account.

## Third pass: what a buyer downloads, reads and receives (2026-10-08)

**Commits:** `337ed5b` … `ff6be50` (6 commits). **Tests:** 421 passing. `composer audit` and
`npm audit --omit=dev` clean.

**Method.** The checks the first two passes had not covered:

- bytes downloaded per page, measured in the browser;
- every e-mail rendered in FR/AR/EN and read at phone width;
- the HTTP security headers, rehearsed through the real nginx vhost;
- then the 93-view sweep and the scripted flows again on the result.

| Defect | Consequence | Commit |
|---|---|---|
| Every page with the background video downloaded all 3.1 MB of it, phones included, and login/sign-up on phones fetched it for a panel phones never display | First visit on a phone: 3.6 MB for the sales page, 3.3 MB for sign-up. Now 906 KB and 281 KB; checkout on a phone 301 KB | `337ed5b` |
| Arabic e-mails printed amounts backwards ("DZD 000 72") and broke the invoice number apart | An Arabic buyer misreads what they paid | `9f490d4` |
| Gmail drops `dir` on `<html>`, the only place the e-mails set it | Arabic e-mails laid out left to right in Gmail | `9f490d4` |
| 16 server messages had no French or Arabic, among them the invoice e-mail's subject, sentence and button | French and Arabic buyers received them in English | `9f490d4` |
| "Commencer la formation" in the payment e-mail opened the sales page | One more step for a buyer who has just paid | `9f490d4` |
| The leaked-password check waited up to 30 s | Behind a firewall that drops the connection, every sign-up would hang 30 s | `fd4bd49` |
| No Content-Security-Policy | Nothing limited what an injected script could load or send | `ab49b78` |
| Every page had the same title; `/robots.txt` returned the app's HTML | Tabs, history and screen readers could not tell pages apart | `3107163` |

- **Video.** Two encodes of the same footage: 720p (982 KB) from 768 px, 360p (366 KB) below.
  Both are H.264 at level 3.1 like the original, with no audio track and the index first. The
  video is not mounted where its panel is hidden, and not in the checkout band on phones.
  Compared frame by frame under the hero's scrim: no visible difference on desktop.
- **E-mails.**
  - One shared layout in the site's blue; two e-mails had used a teal from outside the palette.
  - Amounts come from `App\Support\Money`: ICU, the same patterns as the site's
    `Intl.NumberFormat`.
  - Mixed-direction text is isolated server-side by `App\Support\Bidi`, because e-mail clients
    do not honour `dir="auto"`.
  - `TranslationCoverageTest` fails on any `__()` string without a French and an Arabic
    translation, or with a translation that drops a placeholder.
- **CSP.** Sent on HTML responses only, through an nginx `map` on the Content-Type. The first
  rehearsal run found one violation on every page: Zod's `eval` probe. Zod's `jitless` setting
  removes it. The second run found none, apart from a foreign frame added on purpose to prove
  reporting worked.
- **Regression.** The 93-view sweep found one new item, the "à valider" badge in the content
  editor at 3.95:1, fixed in `ff6be50`; the only remaining finding is the exempt logotype. The
  scripted flows: 32 steps pass. The one failure was a stale button name in the test script; the
  product's invoice flow passed.

## Fourth pass: search engines (2026-10-08)

**Commits:** `687d98f`, `152d7bf`. **Tests:** 435 passing.

**Method.** Each public URL was crawled twice: as a crawler that runs no JavaScript (Bing,
Facebook, WhatsApp, LinkedIn) and as Googlebot renders it (Chromium, `en-US`). The crawl checked
status, title, description, canonical, hreflang, robots, structured data, words and the target
terms (promotion immobilière, promoteur, Algérie, construction, appartement, logement, permis de
construire, vente sur plan, chantier, terrain, formation, France). After the fix, the same crawl ran
through the real vhost in nginx with Laravel behind it.

| Before | After |
|---|---|
| Google rendered the French site **in English** (its browser is `en-US` and the app followed it) | `/` is French whatever the browser; `/ar` Arabic and right to left; `/en` English |
| Arabic and English had no URL of their own, so they could not be indexed | One URL per language, with hreflang fr/ar/en/x-default and a sitemap listing them all |
| 0 words without JavaScript; one title and description for every URL | 875 words on the French home page; title, description, canonical and Open Graph per page and language |
| No structured data | Organization, WebSite, Course (syllabus, price today incl. promotion, euro offer only when euro payment is on, rating from approved reviews only), FAQPage, BreadcrumbList, ItemList |
| `/` and `/courses/promocion-inmobiliaria-espana` were duplicates | The course URL's canonical is `/` |
| Unknown URLs answered 200; the static robots.txt blocked `/api`, which Google needs to render the course | Real 404s; robots.txt from Laravel, `/api` open, absolute `Sitemap:` line |
| Five Arabic FAQ answers showed in French | Translated; a test fails on any app string missing in Arabic or English |

Lighthouse scored 100/100 before and after: its SEO audit checks none of the defects above. The
hreflang and canonical audits now pass instead of not applying.

**Truthful by construction.** Titles, descriptions and the FAQ come from the app's translation
files, written from what the course covers. Prices, promotion, rating and contact details come from
the database and the payment configuration as they are at request time. Module descriptions, which
are buyers' material, appear nowhere. One FAQ entry is new ("from France or abroad"); the owner should
read it.

## Local run with the business details filled in (2026-10-08)

The whole stack behind the production vhost (nginx 1.27, the same routing, a local upstream), fresh
database with the demo data, then driven in a browser: a new buyer on a phone signs up, pays by CCP
and sends a receipt; the admin approves it, fills *Entreprise & contact* and issues the invoice; the
buyer opens the course, in French and in Arabic. No console error, no failed request. Earlier sweeps
ran with the business details empty, so the footer's legal block had never been looked at in Arabic:

| Found | Fixed |
|---|---|
| The Arabic footer reordered what the owner typed: a capital of "1 000 000 DA" showed as "DA 000 000 1", the RC "16/00-1234567 B 26" as "1234567-16/00 B 26". The whole line, Arabic label included, was forced left to right, so the browser read the digits after the label as Arabic numbers | Only the value is isolated (`IsolatedValue`, a `<bdi>`): identifiers and the capital left to right and on one line; name, address and opening hours in the direction of their first letter. Translations unchanged |
| On a phone the sales page's buy bar covered the footer's last lines (the NIF, the copyright), and the space meant to clear it sat above the footer as an empty band | The layout reserves the bar's height after the footer, on pages that have one (`data-bottom-bar`). The bar is at most 99 px tall; every language at 320 and 390 px now ends clear of it |
| Same cause, elsewhere: a French review on the Arabic page showed its final period first and its "[DEMO]" prefix last (an Arabic review on the French page reorders the same way). An audit of every place the interface shows text from the database found eleven more without isolation: the module headings ("الوحدة 3 — …"), module introductions, the modules on the payment page, the admin's rejection note, attachment and partner names, the free-preview title, and course titles and descriptions where they can fall back to French | Each takes its direction from its own first letter (`bidi-auto`, or `IsolatedValue` inside a sentence). Checked before and after in the browser: the reviews now read as written; the real titles, which happen to end in a letter or a closed bracket, render as before. 31 lesson titles start with a number ("2.1 Choisir la forme"); every list that shows them was already isolated |
| The documented local setup had never run from a fresh clone (an agent sandbox cannot reach Debian's mirrors to build the php image). On GitHub's Linux runner the image built, composer installed and the demo data loaded, then the home page answered 500: php-fpm ran as www-data and could not write the compiled views of a checkout owned by another user, nor the log file the queue worker (root) had created. The same on any Linux host, and on Windows with the code inside WSL | php and queue run as the checkout's owner (UID/GID, 1000 by default; another id goes in a root `.env`). `make setup` does the first run. `.github/workflows/setup.yml` runs the README's steps from a fresh checkout whenever the setup files change and every Monday, and checks the API, the home page, the demo admin's sign-in from the Vite dev server's origin and the queue worker: green |

## Main page design pass (2026-10-09)

The sales page, reviewed at 1440 and 390 px in French and Arabic. The words are the same except new
section labels and two price phrases. Course content, prices and figures did not change.

| Found | Changed |
|---|---|
| Sections were either 4xl or 5xl wide, so the left edge moved from one band to the next | One width and one left edge for every band. Bands alternate their background instead of using borders between them |
| On a wide screen, long sections stacked header, list and closing note in one narrow column | The header sits beside its content: the obstacles, the toolkit, the curriculum (header and buttons stay in view beside the module list), the modular offer, the FAQ |
| Nothing told a skimming reader what each section was | A short label above each title ("Le programme", "Les tarifs"…) with the brand's amber rule. No letter-spacing in Arabic, where it breaks the joined letters |
| Curriculum rows wrapped differently from one to the next: the lesson count and price sometimes on the title's line, sometimes under it | The title on its own line; under it, always the lesson count, then the price at the end |
| "ou à partir de 12 000 DA par module" used as a statement on its own; a "-20%" badge beside the unit price read as a discount on that price | "À partir de 12 000 DA par module". The badge reads "jusqu'à -20%": the best volume discount |
| On a phone, each of the final section's figures wrapped onto three lines ("ans de / métier / 16") | The figure first, its caption under it |
| The project gallery left a half-empty last row, on phones and on wide screens | Every row is full at every width |
| French punctuation could wrap onto a line of its own (a lone "?" or ":") | No-break spaces before ? ! ; : and inside « », as French typesetting requires (`fr.json`). Headings are balanced so none ends on a single word |

The phone page is no longer than before (17,276 px, was 17,188).

A second pass, the same day:

| Found | Changed |
|---|---|
| While the course loaded, the page showed a white "Chargement…" line with the footer right under it. In production that came between the server-drawn hero and the app's: navy, white, navy. Then the page pushed the footer away: a layout shift of 0.93 on a desktop screen (Google counts above 0.1 as poor) | The loading state is the hero's navy band, a screen tall, with the shape of its title and text. Layout shift: 0.0008 on desktop, 0.03 on a phone |
| On a phone the floor plan, alone on its gallery row since the first pass, had no height until its image arrived: the page grew 262 px under the reader, and a link to the programme stopped short | Every gallery tile reserves its 4:3 space before its image loads |
| On a phone the price and the buy button came after the four figures, which the next band shows again as photographs | The price card follows the pitch. The four figures stay on wider screens |
| The phone's buy bar appeared after 320 px of scrolling, over the hero's own price card: two prices, two buttons | The bar appears once the price card has left the screen, and leaves when it comes back |
| Nothing in the hero led to the programme or its free previews | A link to the programme ("Voir le programme — 13 modules, aperçus gratuits", already translated and unused), landing below the sticky header |
| The sections were 48 px narrower than the header, so the logo and the page did not share an edge | Same width as the header |
| The footer was a centred copyright line, plus the contact block once filled | What the site is, links (home, courses, sign in), contact when the owner has filled it in, then the legal lines. No contact heading over nothing |
| The reviews sat alone in a column, the right of the band empty | Header beside the reviews, like the other sections. The closing band gets a faint drafting grid |

## Content pass on the sales page (2026-10-09)

The page's words checked against the data and the checkout. The course material itself was not
touched: no lesson, module, title or seeder changed.

| Found | Changed |
|---|---|
| "Aperçus gratuits", "des leçons en aperçu gratuit": the real curriculum opens one free preview, the introduction | The copy counts the previews ("une leçon en aperçu gratuit"). Module and lesson counts come from the catalogue wherever they appear, the search description included |
| Every way to pay was named (CIB/Edahabia, CCP, BaridiMob, international card) and "240 € par carte internationale" shown, whatever the checkout offered. On this environment, with no Stripe key, the page and the FAQ that Google reads promised Visa and Mastercard | First made to follow `GET /checkout/methods`. The owner then decided otherwise (constitution I, amended 2026-10-09): the page, its FAQ and the server-rendered page present the complete offer planned for launch, the euro price included, while the gateways are still being configured. The checkout takes only what is configured, and the Course structured data offers euros only once a card can pay them. Wording shared by `PaymentWording` (server) and `lib/payments.ts` (app) |
| The FAQ offered cash without saying how | "Vous pouvez aussi régler en espèces : contactez-nous", only when a contact channel exists: the owner takes cash in person |
| "Remises automatiques dès 2 modules" and "les remises s'appliquent toutes seules" were written whatever the discount configuration | Said from the first real tier, and only while tiers exist |
| At launch the sales page would have shown "Aucun avis pour le moment" | The reviews band shows from the first approved review. A buyer still sees it, with the invitation to write the first |
| A band that does not render (modular offer, reviews) left two greys or two whites side by side | The light bands alternate on their own |
| Nothing said who the course is for, or what happens after paying | "Pour qui ?" (four starting points) and "Comment ça se passe ?" (choose, pay — by card or by receipt —, learn) |

| Sign-up required an Algerian mobile (05, 06, 07), while the page and the FAQ invite buyers living abroad: without an Algerian number they could not create an account | Also accepted: an international number (`+`, or `00`, then 8 to 15 digits), cleaned the same way by the form and the API (spaces, dots, dashes, brackets, the `(0)` of `+33 (0)6…`, the direction marks of a number copied in Arabic). `+213` stays held to the mobile rule, and `+33 0…` is refused: no French number has a 0 there |

Tests: `SeoPagesTest` pins the payment wording (all four named, with nothing or only CCP
configured), cash only with a contact, and the counted modules; `AuthTest` pins the phone rule
(28 forms, accepted or refused). Each was seen failing with its guard removed.

Drafts for the owner, **not published**: `docs/arabic-titles-draft.md` (Arabic titles for the
13 modules and 34 lessons, machine-translated, to be checked by an Arabic-speaking reader who
knows the trade), `docs/legal/conditions-generales-de-vente-brouillon.md` and
`docs/legal/politique-de-confidentialite-brouillon.md` (from what the code does, for a lawyer).

Found, and only the owner can settle:

- **The instructor is never named or shown.** There is no field for it either.
- **The teaching language** (French, Arabic, darija) is stated nowhere.
- **Nothing is filmed yet**, and the one free preview is the introduction lesson.
- **No refund policy, terms of sale or privacy page** are published (drafts above).

## Decided, not fixed — the owner's call

- **S6/C14:** the reflex-sheet PDF opens for a free-preview lesson and includes the module's legal
  blocks. An earlier pass decided this on purpose, because the sheet is meant to be printed and
  posted on site. Constitution III says a preview unlocks no written content. One of the two has
  to give way, and that choice is the owner's. If the constitution wins, the fix is one argument
  (`allowFreePreview: false`) plus updating the test that pins it.
- **C19:** an upgrade purchase gives no credit for modules already owned. This is pricing policy.
- **C26:** a 100 % coupon cannot be paid by card. Pricing policy again: should it bypass the
  gateway?
- **M31:** no hover on the bento tiles. They are not interactive, and a hover would promise a click.

## Still open, deliberately

| ID | Why it waits |
|---|---|
| C29/C30/C38/P4 | Admin collections and `GET /orders` unpaginated, offset pagination at depth: small tables at launch, and each needs UI work. |
| C33/C34/C36/C59 | Envelope and naming consistency across endpoints: one contract change, best made with a real API consumer in mind. |
| C17/C44/C48/C49/C55/C58/C60–C62 | Duplication and naming preferences. No behaviour is wrong. |
| C23/C27 | Registration if mail queueing fails; display-order race between two admins. Both rare and harmless. |
| reports/02 medium gaps | Not revisited in this pass beyond the guards added above. |
| plain-text e-mails | The e-mails are HTML only. A text part helps a few spam filters; worth adding if the first real sends land in spam. |
| CSP with a real video | The policy allows the Bunny player's host and was rehearsed with a Bunny frame, but no real lesson video exists yet. Check one plays once the first lesson is filmed. |
| search: once live | Search Console and Bing verification, sitemap submission, URL inspection of `/`, `/ar`, `/en` (runbook section 5). Rankings then depend on time, links and content: articles written by the instructor (not generated) are what would rank for long-tail questions. |
| Course rich result | Google's course result wants a workload or a schedule. `courseWorkload` is emitted from the lesson durations, which are 0 until lessons are filmed. |
| Arabic strings | The new Arabic search titles, descriptions and FAQ answers need the same native read as the rest of the Arabic, and so do the sales page's section labels (`landing.eyebrow`), its two new price phrases (`landing.modular.from`, `landing.pricing.upTo`), the footer's headings (`footer.explore`, `footer.courses`, `footer.contact`), the ways to pay (`payments.*`), the counted phrases (`landing.counts`, `landing.hero.ctaSecondary*`, `landing.curriculumIntro.leadPreview`, `landing.faq.expertHelpPreview`, `landing.pricing.unitTiers`) and the two new sections (`landing.forWhom`, `landing.howItWorks`). |

## What only the owner can do

From `specs/001-go-live/spec.md`, with what now enforces each:

| Item | Status |
|---|---|
| Server, domain, DNS, TLS | Runbook ready (`deploy/README.md`), including the PHP upload limits and the outbound connections to allow. Certbot not rehearsed. |
| Chargily live keys, or `CHARGILY_ENABLED=false` | The gate refuses blank or test keys and the test endpoint. |
| Stripe: launch or not | The gate refuses blank keys unless `STRIPE_ENABLED=false`. |
| CCP / BaridiMob account details | Not offered at checkout until filled. |
| SMTP, one test message received | The gate refuses `MAIL_MAILER=log`. |
| Seller RC/NIF/NIS/AI in the admin | Invoicing refuses to issue until filled. |
| TVA rate | Decision with the accountant. |
| Bunny keys and one filmed lesson | The gate refuses blank Bunny keys. |
| Restore drill on the server | `deploy/restore-drill.sh`, rehearsed locally. |
| Branch protection on `main` | CI exists. Requiring `api` and `web` is one setting. |
| Arabic reviewed by a native reader | Not something code can do. |
| Front-end tests, error tracking, a reminder for receipts awaiting review | Decisions. |
