# Construye Academy Constitution

The rules below are not preferences. Each one exists because breaking it either misleads a buyer,
destroys a record someone is legally required to keep, or gives away something that was paid for.
They were learned from real defects in this codebase — several of which shipped before being caught.

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
- `landing.trackRecord.attribution` states that the track record is the instructor's own and not
  Construye Academy's. **That sentence does not change.**
- Project imagery is the instructor's own work. If provenance cannot be established, the image does
  not ship — and neither does a number describing a project we cannot attribute.

A claim that cannot be verified is removed, not softened.

**Amendment (2026-10-09, owner's decision).** The sales page presents the complete payment offer
planned for launch — CIB/Edahabia, CCP, BaridiMob, and the international card with its euro price —
before every gateway is configured, because the gateways are added just before launch. The
checkout still offers only the methods that can take money, and the Course structured data offers
a euro price only when card payment in euros is on.

### II. The course content is the instructor's, not ours

Lesson text, key points, legal citations and figures are authored material.

- **Never reword a legal figure.** The échéancier (20/15/35/25/5), the 10 % and 20 % ceilings, the
  60 000 DA FGCMPI fee, the fines, and every law and decree number are quoted, not paraphrased.
- Passages the instructor marked "à vérifier / à confirmer / à verrouiller" keep an admin-only
  A VALIDER badge and appear in `content-review.md`. **They are never deleted to tidy the text.**
- Marketing copy (`landing.*`) is ours to sharpen. Course content is not. When asked to make the
  writing punchier, that request applies to the sales page only.

### III. What was paid for stays behind the paywall

A module the student does not own must not leak through any surface.

- Lesson text, key points, content blocks, attachments, partner contacts and checklist items are
  withheld from non-owners. A locked module exposes titles, durations and **counts** only.
- A free preview makes one lesson playable. It does not unlock that module's attachments,
  partner contacts or written content.
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
- Numbering is gap-free per fiscal year, and the year is the **Algiers** calendar year.

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

French is the authored locale. Arabic and English are translations, and the interface ships in all
three.

- Course **material** exists in French and Arabic. A locale with no content columns falls back to
  French — a reader sees the authored text, never a blank.
- `config/locales.php` is the single source of truth for which locales exist. Middleware and
  validation read it; nothing hardcodes a locale list.
- Arabic is RTL. Directional motion and layout use logical properties, never physical left/right.
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

This constitution supersedes habit and convenience. It does not supersede the owner: when he
decides differently, the decision is his, and the amendment is recorded here with its reason.

Amending it means changing this file in the same commit as the behaviour it permits, so the rule
and the code never disagree. A principle that is inconvenient is still in force; a principle that
turns out to be wrong is amended, not quietly ignored.

The four pipeline reports and the behavior inventory in `reports/` are the evidence behind these
rules. Read them before arguing with one.

**Version**: 1.1.0 | **Ratified**: 2026-09-26 | **Last Amended**: 2026-10-09
