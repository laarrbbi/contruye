# Security — Ebni Academy

**Date:** 2026-08-27
**Scope:** `api/` and `web/` at commit `3ec1af7`, plus both lockfiles
**Phase:** 4 of 4 — Security

> **Status, 2026-10-04:** the tables below were brought up to date by the go-live pass. ✅ = fixed and covered by a test; *decided* = deliberately left as it is, owner's call; *mitigated* = the harm is gone though the mechanism remains. What was done, and how it was verified: [`06-go-live-pass.md`](06-go-live-pass.md).

## Summary

Three reviewers covered dependencies, authentication/authorization, and input handling, secrets and
uploads. Every finding then had to be **proven by an executed test** or refuted: 27 were raised,
**12 survived — all 12 proven by a test that was written, run, and deleted**, not by reading alone.
15 were rejected, most of them dependency advisories whose vulnerable code path is not reachable here.

The headline is one defect with two faces: **a blank payment secret made webhook signature
verification accept anything**. HMAC with an empty key is a value any attacker can compute, and
neither our code nor Stripe's SDK rejected a blank secret — so an *unconfigured* provider verified
everything instead of nothing. A verifier registered an account, started a checkout to learn their
own order id, posted a forged webhook, and watched a 90 000 DZD order flip to `paid` with a full
enrollment created. Free access to the whole course, unauthenticated.

This is not hypothetical for this project: `.env.example` ships `STRIPE_WEBHOOK_SECRET` and
`STRIPE_SECRET` blank, and the plan has always been to add real keys last. **Fixed**, along with the
three other findings that touch money or personal data.

Counts by severity: Critical 0 · High 2 · Medium 1 · Low 9

## Scope and method

- **Read:** every controller, service, job, middleware and request class in `api/app`; `config/`;
  `routes/api.php`; `docker-compose.yml`; the Stripe SDK's own `WebhookSignature`.
- **Ran:** `npm audit` (web), `composer audit` (api), and roughly thirty temporary Pest tests that
  each attempted one concrete attack against the running stack, then were deleted.
- **Environment:** PHP 8.3.32 / Laravel 12 / MySQL 8.0.46 in Docker; advisory data from the live
  npm and Packagist databases, never from memory.
- **Could not verify:** production configuration. Every finding about a secret, a cookie flag or a
  debug setting is a statement about what happens *if* the deployed environment has that value —
  the deployed environment does not exist yet. `php artisan app:check-secrets --strict` was added
  so that question gets answered at deploy time rather than by an attacker.

## Findings

| ID | Sev | Eff | Evidence | Finding | Location |
|----|-----|-----|----------|---------|----------|
| S1 | High | S | proven | Payment webhook signature verification degenerates to a publicly computable HMAC when the provid ✅ | `api/app/Services/ChargilyService.php:20-26 and :84-93` |
| S2 | High | S | proven | Stripe webhook signature is forgeable when STRIPE_WEBHOOK_SECRET is unset ✅ | `api/app/Services/StripeService.php:66-77 (constructEvent) a…` |
| S3 | Medium | M | proven | Payment webhook grants enrollment without verifying amount, currency, or that the event belongs  ✅ | `api/app/Jobs/ProcessPaymentEvent.php:63-80 (markPaid) and :…` |
| S4 | Low | S | proven | 403-vs-404 on another user's order is an existence oracle that lets any authenticated user enume ✅ | `api/app/Http/Controllers/Api/OrderController.php:29-36 (sho…` |
| S5 | Low | M | proven | AuditLog covers only part of the admin surface — coupon, course, module and lesson mutations lea ✅ | `Unlogged: api/app/Http/Controllers/Api/Admin/CouponControll…` |
| S6 | Low | S | proven | Reflex-sheet PDF bypasses the module content gate: it serves locked-module legal content blocks  — *decided* | `api/app/Http/Controllers/Api/ReflexSheetController.php:24-5…` |
| S7 | Low | M | proven | Chargily webhook has no replay protection — no signed timestamp and no event-id de-duplication — *mitigated* | `api/app/Services/ChargilyService.php:88-97` |
| S8 | Low | S | proven | Full webhook payloads (buyer PII, card metadata, emails) are written to the application log at i ✅ | `api/app/Http/Controllers/Api/WebhookController.php:33 and :54` |
| S9 | Low | S | proven | 403-vs-404 on another user's order discloses whether that order exists ✅ | `api/app/Http/Controllers/Api/OrderController.php:29-36 (sho…` |
| S10 | Low | M | proven | AuditLog::record covers some admin mutations and not others — courses, modules, lessons and coup ✅ | `api/app/Models/AuditLog.php:33-42` |
| S11 | Low | S | proven | Unbounded per_page on the admin students listing ✅ | `api/app/Http/Controllers/Api/Admin/StudentController.php:25` |
| S12 | Low | S | proven | guzzlehttp/guzzle 7.14.0 is behind the fixed 7.15.2 across six advisories ✅ | `api/composer.lock (guzzlehttp/guzzle 7.14.0)` |
## Details — what was fixed

### S1 / S2 — Forgeable webhooks when the secret is blank ✅ High

**Proven.** With `CHARGILY_SECRET` (or `STRIPE_WEBHOOK_SECRET`) empty, this succeeds:

```
POST /api/webhooks/chargily
signature: <hash_hmac('sha256', body, '')>      ← anyone can compute this
{"type":"checkout.paid","data":{"metadata":{"order_id":<attacker's order>}}}
```

The order becomes `paid`, an `enrollments` row appears, and the endpoint answers `200` so nothing
looks wrong. The control case — a non-empty secret — correctly returns 403, so the hole is strictly
conditional on the blank value and an attacker cannot induce it. But nothing in the app made the
blank state visible, and it is the shipped default for Stripe.

**Fixed.** Both services now refuse to verify when their secret is blank, and log why. Four
regression tests reproduce the two forgeries, the amount mismatch and the cross-provider settlement.

### S3 — Enrollment granted without checking amount, currency or provider ✅ Medium

**Proven.** `findOrder()` resolved `metadata.order_id` — a value the caller controls — with no check
that the order belonged to the signing provider, and `markPaid()` never compared the amount. So a
correctly signed Stripe event could settle a **cash or CCP** order, and a signed 1 DZD payment could
settle a 90 000 DZD one.

**Fixed.** The order must have been placed with the gateway that signed the event, and the amount
and currency must match — with Stripe's minor units converted. When the provider states no amount,
the signature remains the authority and the payment is honoured.

### S8 — Buyer PII written to the application log ✅ Low

Both handlers logged the entire payload: buyer name, e-mail, and card metadata, into a file with a
different retention and access model than the database. Now only what traces a payment is logged —
provider, event type, event id, object id, order id.

## Dependency audit

`composer audit` and `npm audit` were run against the committed lockfiles. **One finding survived
reachability analysis**: `guzzlehttp/guzzle 7.14.0` is behind 7.15.2 and sits in the live Chargily
request path. The other flagged packages — vite, esbuild, postcss, nanoid, js-yaml, brace-expansion,
react-router, league/commonmark — were rejected because their vulnerable paths are build-time or
dev-server only, or the flagged API is never called. Each rejection is recorded with the reason, so
the next audit does not re-litigate it.

**Not upgraded in this pass.** A dependency bump is exactly the change that Phase 2's test coverage
is supposed to make safe, and Phase 2 has not been run. Recommended order: run Phase 2, then upgrade
guzzle, then re-run the suite.

## Recommended order

1. **Done.** S1, S2, S3 and S8 are fixed and covered by regression tests.
2. **Before any deploy:** `php artisan app:check-secrets --strict` in the deploy pipeline. It fails
   while any of the six security-critical secrets is blank.
3. **Set `SESSION_SECURE_COOKIE=true`** in the production environment. The finding that this is
   missing was *rejected* — the framework default derives it correctly — but it costs nothing to be
   explicit, and the reviewer's concern is reasonable for a stateful-cookie SPA.
4. Add replay protection to the Chargily webhook (S7): it carries no signed timestamp and no event-id
   de-duplication, so a captured valid webhook can be resent. Impact today is limited because
   `markPaidAndEnroll()` is idempotent, which is why this is Low rather than High.
5. Clamp `per_page` on `/admin/students` (S11) — the last unclamped one, admin-only.
6. Widen `AuditLog` coverage (S5/S10): coupon, course, module and lesson mutations are unlogged, so a
   rogue admin can change a price and leave no trace.
7. The 403-vs-404 order oracle (S4/S9) is real but discloses only existence, not content. Return 404
   for non-owners when convenient; it is not worth its own deploy.

## Not verified

- **Production configuration.** Cookie flags, `APP_DEBUG`, CORS origins and the real secrets are all
  environment-dependent and this environment is local. The findings say what happens for a given
  value; they cannot say what value production will have.
- **The real payment providers.** Every provider call was stubbed. The signature logic is exercised
  by tests using real HMACs, but no live Chargily or Stripe event was ever received.
- **The reflex-sheet gate (S6).** Proven that the PDF serves a locked module's legal content, but
  the business decision — whether that sheet is deliberately free, since it concerns a fatal
  workplace accident — is the owner's to make, not a defect to silently patch.
