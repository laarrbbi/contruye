# Construye Academy Constitution

The rules below are not preferences. Each one exists because breaking it either misleads a buyer,
destroys a record someone is legally required to keep, or gives away something that was paid for.
They were learned from real defects in the codebase this platform was adapted from (Ebni Academy,
the Algerian edition) — several of which shipped before being caught. Construye Academy is the same
platform, adapted for Spain.

Anything not covered here is an ordinary engineering decision. Make it and move on.

## Core Principles

### I. Nothing on a public surface may be invented (NON-NEGOTIABLE)

Every figure, photograph, contact, review and counter shown to a visitor must trace to something
the instructor actually supplied or the database actually holds.

- Partner slots render the partners attached to that module, or a placeholder. **Never a plausible
  name, phone or address.** A fabricated notary is a person a student may actually call.
- Review counts, student counts and ratings come from approved rows. `rating` stays `null` until a
  real review lands — never a placeholder score.
- No countdown that resets, no "12 people are viewing this", no testimonial that was not written.
- No track record, biography, project photo or figure about the instructor appears until the
  instructor supplies it. The Spanish edition launched without one, on purpose: the sales page
  describes the course, not a career we cannot attribute.
- Project imagery is the instructor's own work. If provenance cannot be established, the image does
  not ship — and neither does a number describing a project we cannot attribute. The drawings on
  the page (`BlueprintBackdrop`) are illustrations, not projects.

A claim that cannot be verified is removed, not softened.

**Amendment (2026-10-09, owner's decision, inherited from Ebni).** The sales page presents the
complete payment offer planned for launch — bank card (Stripe), bank transfer and Bizum — before
every gateway is configured, because the gateways are added just before launch. The checkout still
offers only the methods that can take money, and the Course structured data offers a price only
while at least one of them can.

### II. The course content is the instructor's, not ours

The course is the instructor's package, version 4.0, kept unchanged in `curso/`: the module texts,
the quizzes and the exam, the province directory, the tax tables and the financial template. Its
sequence of phases follows Nicolás Cabañas Yuste's «Fases para el desarrollo de una promoción de
viviendas».

- **Texts are integrated as written.** The seeders cut the files at their headings and store every
  piece as is, Markdown included; `CourseContentTest` rebuilds each module file from the database
  and compares it line by line. The only change of form: module titles in sentence case, as the
  instructor's own summary page writes them.
- **Never reword a legal figure.** Rates, deadlines, thresholds and every law and decree number
  are quoted, not paraphrased. A text that needs correcting is corrected in the package, by the
  instructor, and re-imported.
- **The financial template is used as is: no formula, value or label is changed**, not in
  `PLANTILLA_LIBRE.xls`, not in its export, not in its engine. The platform only writes the input
  cells the template's own dictionary lists, and reads results. Its 2002 parameters and the two
  incidences documented in its specification stay until the professional decides otherwise.
  `npm test` in `curso/2_plantilla_financiera/motor` must keep passing.
- Passages marked "pendiente de validar / verificar / confirmar" (or "por validar…") keep an
  admin-only A VALIDAR badge and appear in `content-review.md`. **They are never deleted to tidy
  the text.** The delivered text has none.
- Marketing copy (`landing.*`) is ours to sharpen. Course content is not. When asked to make the
  writing punchier, that request applies to the sales page only.

### III. What was paid for stays behind the paywall

A module the student does not own must not leak through any surface.

- Lesson text, key points, content blocks, attachments, partner contacts and checklist items are
  withheld from non-owners. A locked module exposes titles, durations and **counts** only.
- A free preview makes one lesson playable. It does not unlock that module's attachments,
  partner contacts or written content.
- A module's quiz is its owners'; the final exam, the whole Anexo A and the course PDF are for
  full access; the financial template and its files are for the owners of Financials. **Answers
  never leave the server before an attempt is submitted**, and the exam's answer key never does.
- Every new endpoint that reads course material states which of the two gates it uses —
  ownership, or ownership-or-preview — and has a test asserting the withholding half, not just
  the 403.

### IV. A financial record is immutable and self-explanatory

Orders, enrollments and invoices are the record of money that changed hands.

- **A catalogue edit must never destroy a payment record.** Courses, users and sold modules cannot
  be deleted while orders reference them; the database enforces it and the API explains it.
- An issued invoice is a snapshot. Corrections recompute from the **stored lines**, never from the
  live catalogue. Its number and date never change, and every correction keeps the previous values.
- An order carries the full breakdown that produced its price. Provenance must not depend on a
  coupon or campaign row that may later be edited or deleted.
- Numbering is gap-free per fiscal year, and the year is the **Madrid** calendar year
  (`app.business_timezone`).
- An invoice carries what the Reglamento de facturación (RD 1619/2012) requires: number and series,
  date, the issuer's name, NIF and address, the buyer's name and address (and NIF, which the
  admin fills in when the buyer is a business or asks for a full invoice), the taxable base, the
  VAT rate and amount. The issuer block refuses to issue until those are filled. Electronic invoicing
  requirements (VeriFactu) are the owner's to settle with an adviser before they apply.

### V. Secrets fail closed

A missing secret disables a capability. It never weakens a check.

- A blank webhook secret makes HMAC verification accept anything, because an empty key is one
  anyone can compute. Every signature check refuses a blank secret outright.
- `php artisan app:check-secrets --strict` gates a deploy. A blank value is a failure, not a default.
- Webhooks verify the signature, the amount, the currency, and that the gateway signing the event
  is the one the order was placed with.
- Logs record what traces a payment. They never record the buyer's name, e-mail or card metadata.

### VI. Demo data announces itself

Every seeded entity a human could mistake for real is prefixed `[DEMO]`. The showcase seeder is
opt-in and never wired into the default seed. Credentials stay placeholders until the explicit
final step of the project.

## Language

Spanish is the authored locale, served at the root URLs. English is a translation, under `/en`, and
the interface ships in both.

- Course **material** has `_es` and `_en` columns. An empty English column falls back to Spanish —
  a reader sees the authored text, never a blank. The English material is empty at launch.
- `config/locales.php` is the single source of truth for which locales exist. Middleware and
  validation read it; nothing hardcodes a locale list.
- Both locales are left to right, but layout keeps logical properties (start/end), never physical
  left/right, so a right-to-left locale stays a configuration change.
- Machine translation is a draft. Any locale not yet reviewed by a human is stated as such to the
  owner, not presented as finished.

## Quality Gates

A change is done when all of these hold — not when the code works.

1. `docker compose exec -T php php artisan test --parallel --processes=4` is green.
2. A new guard has a test that **fails when the guard is removed.** Write it, break the production
   code, watch it go red, restore. A test that passes with the body deleted is worse than none.
3. `docker compose exec -T php ./vendor/bin/pint` leaves no diff.
4. `npm run build` compiles and `npx eslint src` reports no errors.
5. `composer audit` reports no advisories.
6. Shared test fixtures live in `tests/Pest.php`. A helper declared inside a test file is invisible
   to the other parallel workers.
7. Anything observable in the browser was actually looked at. If it could not be verified, the
   report says so plainly instead of implying it was.

## Governance

This constitution supersedes habit and convenience. It does not supersede the owner: when they
decide differently, the decision is theirs, and the amendment is recorded here with its reason.

Amending it means changing this file in the same commit as the behaviour it permits, so the rule
and the code never disagree. A principle that is inconvenient is still in force; a principle that
turns out to be wrong is amended, not quietly ignored.

The four pipeline reports and the behavior inventory in `reports/` are the evidence behind these
rules. They were written about Ebni Academy, before the Spanish adaptation: their file paths and
behaviours still match, their Algerian specifics (dinars, Chargily, CCP, wilayas, French and
Arabic) do not. Read them before arguing with one.

**Version**: 2.1.0 (Spanish edition, course package 4.0) | **Ratified**: 2026-09-26 | **Last Amended**: 2026-10-31
