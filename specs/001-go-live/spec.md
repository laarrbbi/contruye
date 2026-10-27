# Feature Specification: Go Live

**Feature Branch**: `001-go-live`
**Created**: 2026-09-26
**Status**: Everything that does not need the owner is done (see `reports/06-go-live-pass.md`, 2026-10-04). What remains below is the owner's: credentials, a server, decisions marked NEEDS DECISION, and the Arabic.
**Input**: The platform is built and audited. What remains is everything between "it works on a
laptop" and "an Algerian promoter can pay for it and start learning."

## Context

Four review phases and a motion audit are complete; their reports are in `reports/`. 320 tests
pass. `composer audit` is clean. *(2026-10-04: a go-live pass took this to 404 tests in CI on every
push, deploy tooling, and every open finding closed or decided — `reports/06-go-live-pass.md`.)* What is NOT done is the part that cannot be done without the
owner: real credentials, a server, a domain, and the human translation of the Arabic.

This spec exists so that work is a checklist with acceptance criteria rather than a scramble on
launch day. It deliberately does not specify new product features.

## User Scenarios & Testing

### User Story 1 — A buyer in Algeria pays and gets access (Priority: P1)

A promoter finds the sales page, chooses full access or a few modules, pays with a CIB or Edahabia
card, and is watching the first lesson within a minute. No human touches the order.

**Why this priority**: This is the business. Everything else on this page is worthless until money
can move. It is also the only path that depends on a third party we have never actually called —
every Chargily interaction to date has been faked in tests.

**Independent Test**: One real card payment in Chargily production mode, end to end, from the
public page to a playing lesson.

**Acceptance Scenarios**:

1. **Given** real Chargily credentials are set, **When** a buyer completes a card payment,
   **Then** the webhook arrives signed, the order becomes `paid`, an enrollment row exists, and the
   confirmation e-mail is delivered to a real inbox.
2. **Given** the payment fails or is abandoned, **When** the buyer returns to the site,
   **Then** the order reads `failed` or `pending`, any reserved coupon use has been released, and
   they can retry without a duplicate order.
3. **Given** a webhook is replayed, **When** it arrives a second time, **Then** the order stays
   `paid`, no second enrollment is created, and no second e-mail is sent.
4. **Given** `CHARGILY_SECRET` is blank, **When** the deploy runs,
   **Then** `app:check-secrets --strict` fails and the deploy stops.

### User Story 2 — A buyer abroad pays in euros (Priority: P2)

A member of the diaspora pays €300 by international card.

**Why this priority**: A real second revenue stream, and the Stripe path has the same "never
actually called" risk as Chargily — with the added hazard that its shipped `.env.example` values
are blank, the exact condition that made webhook forgery possible.

**Independent Test**: One real Stripe payment in live mode.

**Acceptance Scenarios**:

1. **Given** real Stripe credentials, **When** a €300 payment completes, **Then** the order settles
   with `currency = EUR` and the amount matches in minor units.
2. **Given** a Stripe event for an order placed by CCP, **When** it arrives signed,
   **Then** it is ignored — a gateway settles only its own orders.

### User Story 3 — A buyer pays by CCP or BaridiMob (Priority: P1)

The buyer transfers the money at the post office, uploads a photo of the receipt, and an admin
approves it.

**Why this priority**: In Algeria this is not a fallback, it is how a large share of buyers will
actually pay. It is also the only path that works with zero third-party credentials, which makes it
the honest answer to "can we launch before the gateways are live?"

**Independent Test**: Already covered end to end by the suite; what is untested is the human loop —
the admin notices the pending order, and the buyer is told what happened.

**Acceptance Scenarios**:

1. **Given** a buyer uploads a receipt, **When** the admin approves it, **Then** access is granted
   and the buyer receives the confirmation e-mail.
2. **Given** the admin rejects it with a reason, **Then** the buyer receives that reason and the
   coupon use is released.
3. **Given** an order has been awaiting review for more than 48 hours, **Then** the admin can see
   that at a glance. *(Done: a red count on the dashboard, and an oldest-first review queue showing
   each receipt's age. NEEDS DECISION: is an e-mail nudge wanted as well?)*

### User Story 4 — An Arabic-speaking student reads a page written for them (Priority: P2)

**Why this priority**: The interface is fully translated, but the Arabic is machine-produced. The
sales page in particular argues for a 90 000 DA purchase; stilted Arabic undermines exactly the
credibility the page is trying to build.

**Independent Test**: A native Algerian reader reviews the 127 `landing.*` strings and the course
titles, and reports whether anything reads as translated.

**Acceptance Scenarios**:

1. **Given** the reviewed Arabic is merged, **When** an Arabic visitor loads the sales page,
   **Then** every string is reviewed text and none falls back to French.
2. **Given** RTL layout, **Then** no motion or layout mirrors the wrong way.

### Edge Cases

- **A deploy omits one secret.** The gate fails the deploy. This is the scenario that produced the
  webhook-forgery defect; it is now the first thing the pipeline checks.
- **The queue worker is not restarted after deploy.** Invoices and e-mails silently stop.
  Restarting workers is part of the deploy, not a step someone remembers. *(`deploy/deploy.sh`
  runs `queue:restart`; supervisor brings the worker back on the new code.)*
- **Chargily is unreachable during checkout.** The buyer sees a 502 and the order is marked failed —
  covered by a test, never seen against the real endpoint.
- **A student's enrollment expires** while they are mid-course. Nothing in the product sets an
  expiry today; the column exists and every access check honours it.
- **Two admins issue the first invoice of the year simultaneously.** Handled by a retry; never
  observed under real concurrency. *(Two admins invoicing the same order now serialize on its row
  and the second gets a 422.)*

## Requirements

### Must have before taking money

- [ ] Real Chargily credentials, **live** mode, and `CHARGILY_BASE_URL` pointing at production —
      it currently defaults to the test endpoint, so a missing variable would silently take test
      payments. *(The deploy gate now refuses the test endpoint and test keys. Launching CCP-only
      first: `CHARGILY_ENABLED=false`.)*
- [ ] Real Stripe secret and webhook secret, or Stripe disabled outright. Blank is not "disabled".
      *(`STRIPE_ENABLED=false` is the switch; blank keys with it on fail the deploy.)*
- [x] `app:check-secrets --strict` wired into the deploy, failing it. *(`deploy/deploy.sh`, before
      anything touches the live site.)*
- [x] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, a generated `APP_KEY`.
      *(Enforced: the deploy gate refuses each of them, and https URLs, otherwise.)*
- [ ] Real SMTP, with one test message actually received — confirmation, receipt rejection, welcome,
      password reset and invoice mails all go through it. *(The gate refuses `MAIL_MAILER=log`;
      the test command is in `api/.env.production.example`.)*
- [ ] Seller block filled with the real RC, NIF, NIS and AI. Invoicing refuses to issue until then,
      by design.
- [ ] TVA rate confirmed with the accountant. *(NEEDS DECISION: 19 %, 9 %, or exempt for online
      training. The admin picks per invoice; the default should be the right one.)*

### Must have before inviting students

- [ ] Bunny credentials and at least one real lesson video playing with the watermark.
- [x] The suite in CI, blocking merges. *(`.github/workflows/ci.yml` runs on every push. Making it
      block merges is branch protection on `main` with `api` and `web` required — the owner's click.)*
- [ ] A database backup that has been restored once. An untested backup is not a backup.
      *(`deploy/backup.sh` and `deploy/restore-drill.sh`, rehearsed against MySQL. Run the drill
      once on the server.)*
- [ ] The Arabic reviewed by a human.

### Should have

- [ ] Front-end test tooling. *(NEEDS DECISION: nothing in `web/src` is covered today.)*
- [ ] Error tracking in production.
- [ ] The 30 medium test gaps in `reports/02-tests.md`, each with the exact edit that would slip
      through it.

## Success Criteria

1. A buyer who has never seen the site can pay and start learning without anyone intervening.
2. Every fiscal document the business issues carries the mentions the law requires, numbered
   gap-free in the Algiers calendar year.
3. No secret is blank, and the deploy cannot proceed if one is.
4. An Arabic-speaking buyer reads Arabic written by a person.
5. A regression in any access gate turns a test red before it reaches a student.

## Out of Scope

New product features. The platform is feature-complete for launch: 13 modules, per-module and full
purchase, checklists, partners filtered by wilaya, invoicing, reviews, campaigns and coupons. Adding
to that before the first paying student is a distraction from finding out what they actually want.
