# Code and Schema Review — Ebni Academy

**Date:** 2026-08-27
**Scope:** `api/` and `web/src/` at commit `4689730`, reviewed before the fixes in `e28da22`…`16d4ab1`
**Phase:** 1 of 4 — Code & Schema

> **Status, 2026-10-04:** the tables below were brought up to date by the go-live pass. ✅ = fixed and covered by a test; *decided* = deliberately left as it is, owner's call; *mitigated* = the harm is gone though the mechanism remains. What was done, and how it was verified: [`06-go-live-pass.md`](06-go-live-pass.md).

## Summary

Five parallel reviewers covered the behavior inventory, correctness, the API contract as a set,
pagination, the schema, and structure. Every finding then faced a skeptic instructed to refute it:
**84 were raised, 63 survived**, and 21 were rejected as misread semantics or already-fixed.

The headline is that **a catalogue edit could destroy the commercial record**: `orders.course_id`,
`orders.user_id`, `order_modules.module_id` and `enrollments.course_id` all cascaded, so deleting a
course erased every order ever placed for it — paid ones included. Separately, a `max_uses` coupon
capped only its counter and not the discount, which a verifier reproduced with no concurrency at all.
Both are fixed. What remains is a long tail of contract inconsistencies and unpaginated collections.

Counts by severity: Critical 0 · High 3 · Medium 10 · Low 50
(The two cascade findings were raised as Critical and downgraded to High on verification, since they
require an admin action rather than being reachable by a customer.)

**Nine findings are already fixed and shipped** — marked ✅ below.

## Scope and method

- **Read:** `api/routes/api.php` and every controller it reaches; all Eloquent models and
  `PricingService`; all 21 migrations plus the live schema via `db:show`/`db:table`; every Resource
  class; `web/src` for the structural pass.
- **Ran:** `php artisan test` (231 → 238 tests) throughout; targeted Pest probes written and deleted
  by the verifiers to reproduce claims; `DELETE FROM courses` inside a rolled-back transaction
  against the live MySQL to observe the cascade directly.
- **Environment:** PHP 8.3.32, Laravel 12, MySQL 8.0.46, all in Docker; 89 review/verify agents.
- **Could not verify:** anything requiring the real payment providers (Chargily and Stripe were
  stubbed), real mail delivery, and production data volumes. Concurrency claims were reasoned from
  the SQL and, where possible, reproduced sequentially rather than under genuine parallel load.

## Behavior inventory

`reports/behavior-inventory.md` — **126 behaviors** (B1…B126): every HTTP route with its auth
requirement and failure modes, the pricing rules, the order state machine, enrollment and invoice
invariants, the paid-perk locking rules, and the locale fallback chain. Phases 2–4 refer back to
these IDs.

## Findings

Legend: `bug` = defect · `pref` = preference · `→P3` / `→P4` = handed to a later phase · ✅ = fixed.

| ID | Sev | Eff | Kind | Finding | Location |
|----|-----|-----|------|---------|----------|
| C1 | High | M | bug | A max_uses-capped coupon keeps discounting orders after the cap; only the counter is capped ✅ | `api/app/Models/Order.php:118-129` |
| C2 | High | M | bug | Deleting a course cascades away every paid order, enrollment and order line ✅ | `api/database/migrations/2026_07_11_100005_create_orders_table.php:14 (order…` |
| C3 | High | M | bug | Deleting a module deletes the order_modules lines of a PAID order and silently corrupts the invoice later issued for it ✅ | `api/database/migrations/2026_07_12_200001_add_per_module_pricing.php:53 (or…` |
| C4 | Medium | S | →P4 | Global `api` rate limiter is declared but never attached — every unthrottled route is unlimited ✅ | `api/app/Providers/AppServiceProvider.php:25 and api/bootstrap/app.php:18` |
| C5 | Medium | M | bug | The documented cash-at-the-office flow cannot be executed — no code path can create a `cash` order ✅ | `api/app/Http/Controllers/Api/Admin/OrderController.php:159-162 and api/app/…` |
| C6 | Medium | S | bug | Chargily connection failures escape the catch, leaving the order stuck pending ✅ | `api/app/Http/Controllers/Api/CheckoutController.php:134-153` |
| C7 | Medium | M | bug | Coupon max_uses is not enforced — an unlimited number of orders can redeem a capped coupon ✅ | `api/app/Http/Controllers/Api/CheckoutController.php:109-128 and api/app/Mod…` |
| C8 | Medium | S | bug | Invoice date and fiscal-year sequence are computed in UTC, not Algeria time ✅ | `api/app/Http/Controllers/Api/Admin/InvoiceController.php:82,86,104 (config/…` |
| C9 | Medium | S | →P4 | Chargily signature verification degenerates to a known key when the secret is unset ✅ | `api/app/Services/ChargilyService.php:19-25,71-80` |
| C10 | Medium | S | bug | Public course-reviews endpoint returns every approved review, unpaginated, to unauthenticated callers ✅ | `api/app/Http/Controllers/Api/ReviewController.php:27` |
| C11 | Medium | S | →P4 | The api rate limiter is defined but never applied to any route ✅ | `api/app/Providers/AppServiceProvider.php:25` |
| C12 | Medium | M | bug | A paid order keeps no auditable record of the discount that produced its price ✅ | `api/database/migrations/2026_07_11_100005_create_orders_table.php:15 (coupo…` |
| C13 | Medium | M | bug | "Full access" is computed three different ways and the three endpoints already disagree for an a-la-carte completer ✅ | `api/app/Http/Resources/LearnCourseResource.php:65` |
| C14 | Low | S | bug | Reflex-sheet PDF hands a locked module's legal content to non-owners, contradicting its own docblock and test — *decided* | `api/app/Http/Controllers/Api/ReflexSheetController.php:26,39-40` |
| C15 | Low | M | bug | Invoice backdating breaks the chronological ordering the gap-free numbering is meant to guarantee ✅ | `api/app/Http/Controllers/Api/Admin/InvoiceController.php:86 and api/app/Htt…` |
| C16 | Low | S | bug | `full_access` means two different things across three endpoints ✅ | `api/app/Http/Resources/LearnCourseResource.php:65` |
| C17 | Low | S | pref | `savings_vs_itemized` carries two different meanings depending on the quote scope | `api/app/Services/PricingService.php:212 and :249` |
| C18 | Low | M | bug | Payment confirmation email is permanently lost if the webhook job retries ✅ | `api/app/Jobs/ProcessPaymentEvent.php:64-80 (same shape at api/app/Http/Cont…` |
| C19 | Low | M | bug | Upgrade purchases charge the full course price with no credit for modules already owned | `api/app/Services/PricingService.php:109-121 and 219-254` |
| C20 | Low | S | bug | Concurrent invoice issuance on one order returns a 500 after three pointless retries ✅ | `api/app/Http/Controllers/Api/Admin/InvoiceController.php:54-58 and 126-139` |
| C21 | Low | M | bug | Duplicate full-access enrollments are possible despite the migration's stated guard ✅ | `api/app/Models/Order.php:107-116 and api/database/migrations/2026_07_12_200…` |
| C22 | Low | S | bug | Progress heartbeat with a large position value returns a 500 ✅ | `api/app/Http/Requests/ProgressHeartbeatRequest.php:20` |
| C23 | Low | S | bug | Registration leaves an account created when the welcome mail cannot be queued | `api/app/Http/Controllers/Api/AuthController.php:20-34` |
| C24 | Low | S | bug | Receipt upload trusts putFile()'s return value ✅ | `api/app/Http/Controllers/Api/OrderController.php:77-83` |
| C25 | Low | S | →P3 | per_page is unclamped on the admin students endpoint alone ✅ | `api/app/Http/Controllers/Api/Admin/StudentController.php:25` |
| C26 | Low | S | pref | A 100% coupon is accepted but cannot be redeemed by card | `api/app/Http/Requests/Admin/StoreCouponRequest.php:21` |
| C27 | Low | S | bug | Display-order assignment is a read-modify-write with no lock | `api/app/Http/Controllers/Api/Admin/ModuleController.php:22, api/app/Http/Co…` |
| C28 | Low | S | bug | GET /api/admin/students accepts an unvalidated per_page: -1 crashes with a 500, large values dump the whole table ✅ | `api/app/Http/Controllers/Api/Admin/StudentController.php:25` |
| C29 | Low | M | bug | Four admin collections are unpaginated over tables that only grow | `api/app/Http/Controllers/Api/Admin/PartnerController.php:26` |
| C30 | Low | S | bug | GET /api/orders is unpaginated and its table grows with every abandoned checkout | `api/app/Http/Controllers/Api/OrderController.php:20` |
| C31 | Low | S | bug | 404/403 responses come in three incompatible shapes; 21 of them send an empty message in production ✅ | `api/app/Http/Controllers/Api/Concerns/AuthorizesCourseAccess.php:45` |
| C32 | Low | S | bug | admin/stats orders_by_method flips from JSON object to JSON array when there are no orders ✅ | `api/app/Http/Controllers/Api/Admin/StatsController.php:27` |
| C33 | Low | S | bug | Two different pagination envelopes across the four paginated endpoints | `api/app/Http/Controllers/Api/Admin/ReviewController.php:31` |
| C34 | Low | S | bug | GET/PUT /api/admin/invoicing/settings is the only route in the API with no data envelope | `api/app/Http/Controllers/Api/Admin/InvoicingSettingsController.php:21` |
| C35 | Low | S | bug | EnrollmentResource is dead code that documents a different contract than the live endpoint ✅ | `api/app/Http/Resources/EnrollmentResource.php:14` |
| C36 | Low | S | pref | Date-only and date-time timestamps are mixed inside the same object without a naming distinction | `api/app/Http/Resources/AdminInvoiceResource.php:22` |
| C37 | Low | S | →P4 | 403 on another user's order confirms the order exists ✅ | `api/app/Http/Controllers/Api/OrderController.php:33` |
| C38 | Low | M | →P3 | All pagination is offset-based with a COUNT(*) per request, over queries with leading-wildcard LIKE filters | `api/app/Http/Controllers/Api/Admin/OrderController.php:25` |
| C39 | Low | S | bug | Deleting a course or coupon returns a 500 instead of a 422 once any invoice exists ✅ | `api/app/Http/Controllers/Api/Admin/CourseController.php:65-70` |
| C40 | Low | M | bug | The enrollments unique index does not prevent duplicate full-course enrollments ✅ | `api/database/migrations/2026_07_12_200001_add_per_module_pricing.php:34-43` |
| C41 | Low | S | →P3 | Admin order list sorts by created_at with no index that can serve the sort | `api/app/Http/Controllers/Api/Admin/OrderController.php:79-88` |
| C42 | Low | S | →P3 | Monthly revenue query filters on the unindexed orders.paid_at | `api/app/Http/Controllers/Api/Admin/StatsController.php:20-26` |
| C43 | Low | S | →P3 | Admin review list filters by status against an index that leads with course_id | `api/app/Http/Controllers/Api/Admin/ReviewController.php:23-26` |
| C44 | Low | M | bug | QuoteRequest and CheckoutRequest are duplicate rule sets on the money path, and currency-from-method is decided in two places | `api/app/Http/Requests/QuoteRequest.php:20-46 and api/app/Http/Requests/Chec…` |
| C45 | Low | S | bug | Stale duplicate type module api/types.ts has drifted from the per-feature API types (Locale missing 'en', OrderMethod missing 'stripe') ✅ | `web/src/api/types.ts:1-2,60-73` |
| C46 | Low | S | bug | ModulePartners.tsx is still imported by nothing, and the module-level partners payload it consumed is now dead too ✅ | `web/src/components/player/ModulePartners.tsx:13` |
| C47 | Low | S | bug | Locale -> BCP-47 mapping bypassed in two components, which still hardcode fr-FR and ignore English ✅ | `web/src/lib/utils.ts:15-19` |
| C48 | Low | S | bug | Volume-tier selection implemented twice with different tie-breaking; the PHP side explicitly guards a case the TS side does not | `api/app/Services/PricingService.php:142-154` |
| C49 | Low | M | bug | "Which modules does this user own" implemented three times across model, service and controller | `api/app/Models/User.php:132-141` |
| C50 | Low | S | bug | Admin per_page clamp applied in three controllers and forgotten in the fourth ✅ | `api/app/Http/Controllers/Api/Admin/StudentController.php:25` |
| C51 | Low | S | bug | LIKE search escaping done in one admin controller and skipped in the other three ✅ | `api/app/Http/Controllers/Api/Admin/PartnerController.php:29-33` |
| C52 | Low | S | bug | useDebouncedValue defined twice with different signatures ✅ | `web/src/pages/CheckoutPage.tsx:41-50` |
| C53 | Low | S | bug | Dead resource class: EnrollmentResource is referenced by nothing and documents a convention the controller abandoned ✅ | `api/app/Http/Resources/EnrollmentResource.php:8-33` |
| C54 | Low | S | bug | Lesson::course() is dead and misleadingly named - it looks like an Eloquent relation but is not ✅ | `api/app/Models/Lesson.php:49-52` |
| C55 | Low | S | bug | InvoiceBuilder::build and ::retotal duplicate the HT/TVA breakdown arithmetic | `api/app/Services/InvoiceBuilder.php:74-81 and 121-134` |
| C56 | Low | S | bug | ReflexSheetController generates a "fiche reflexe" PDF for any lesson, ignoring the is_reflex_sheet flag the payload gates on ✅ | `api/app/Http/Controllers/Api/ReflexSheetController.php:24-58` |
| C57 | Low | S | bug | InvoiceBuilder::build returns a discount_percent no caller reads ✅ | `api/app/Services/InvoiceBuilder.php:27,91` |
| C58 | Low | S | pref | Magic numbers with no named constant on two business rules | `api/app/Http/Controllers/Api/ChecklistController.php:106-110` |
| C59 | Low | L | pref | Admin controllers split between JsonResource classes and hand-rolled private payload() methods | `api/app/Http/Controllers/Api/Admin/CampaignController.php:80-97` |
| C60 | Low | S | pref | Shared UI helpers live under pages/admin/lib.ts but are imported by non-admin components | `web/src/pages/admin/lib.ts:34-56` |
| C61 | Low | S | pref | Misleading local name: notEnrolled is assigned !full_access, and partial owners are enrolled | `web/src/pages/CourseLandingPage.tsx:146-148` |
| C62 | Low | S | pref | Pass-through type aliases and an unused exported brand constant | `web/src/api/orders.ts:25-26` |
| C63 | Low | M | →P4 | AuditLog::record applied to some admin mutations and not others ✅ | `api/app/Models/AuditLog.php:33-42` |
## Details — what was fixed

### C1 — A capped coupon kept discounting after its cap ✅ High

Redeemability was checked when the order was created, but the counter only advanced when the order
was *paid*. Manual CCP and BaridiMob orders sit pending for days, so an unbounded number of buyers
could each be quoted and committed at the same capped discount while `uses` still read 1. A verifier
reproduced it with three sequential checkouts — no race required — all three paid at half price with
the counter reporting a single use. The comment in `Order::markPaidAndEnroll` asserted an invariant
that held for the counter and not for the money, and `CheckoutTest` encoded that same wrong invariant
as a passing test.

**Fixed** by making the cap a reservation: `Coupon::reserveUse()` claims it atomically when the
discounted price is committed, and `Order::markFailed()` releases it when the order fails or an
admin rejects the receipt. Two tests now assert the real guarantee — the second buyer is refused at
checkout, and a released use becomes available again.

### C2, C3 — Catalogue deletes destroyed payment records ✅ High

Observed directly against the live database inside a rolled-back transaction: `DELETE FROM courses
WHERE id = 1` took `orders` from 10 to 0, `enrollments` from 5 to 0 and `order_modules` from 3 to 0.
`CourseController::destroy` was a bare `$course->delete()` with no guard. The only thing that ever
blocked it was an incidental `RESTRICT` from `invoices.order_id` — so a course with no invoice yet
issued lost its whole payment history silently.

**Fixed** at both levels: the four foreign keys are now `RESTRICT`, so the database refuses; and the
admin endpoints check first and answer 422 with a sentence that says why. Unsold courses and modules
still delete normally.

### C4 — The `api` rate limiter was attached to nothing ✅

`RateLimiter::for('api', …)` was defined in `AppServiceProvider` and never referenced by a route or
middleware group. Only `auth`, `checkout` and the two routes with inline throttles were limited;
everything else was unlimited. **Fixed** by appending `throttle:api` to the api group, with a test
that sends 121 requests and expects the 121st to be refused.

### C6 — Invoice fiscal year computed in UTC ✅

`config('app.timezone')` is UTC and Algeria is UTC+1 year-round, so a facture issued between 23:00
and 24:00 UTC carried the *previous* day — and on 31 December, the previous **fiscal year**, taking
a number from the wrong gap-free sequence. **Fixed** with `app.business_timezone`: instants stay
stored in UTC, and only the questions with a calendar answer (invoice date, fiscal year, "revenue
this month") convert. A test issues an invoice at 23:30 UTC on 31 December 2026 and asserts
`FAC-2027-00001`.

### C7 — Chargily connection failures escaped the catch ✅

Verified against the framework source: `Illuminate\Http\Client\ConnectionException` extends
`Exception`, not `RuntimeException`, so a refused connection bypassed `CheckoutController`'s handler,
returned a 500, and left the buyer with an order stuck `pending` and no checkout link. **Fixed** in
the service, which now presents every failure mode as `RuntimeException`; timeouts tightened to
5 s connect / 12 s total and deliberately **not** retried, since creating a checkout is not idempotent.

## Recommended order

1. **Done.** The three High findings and the money/security-adjacent Mediums are fixed and shipped.
2. **Next, cheap and worth it:** clamp `per_page` on `/admin/students` (C: unvalidated `-1` returns a
   500, a large value dumps the table); paginate `GET /api/courses/{slug}/reviews`, which today
   returns every approved review to unauthenticated callers; paginate the four unpaginated admin
   collections and `GET /api/orders`.
3. **Then the contract pass, as one change rather than piecemeal:** one error shape for 403/404
   (21 of them currently send an empty `message` in production), one pagination envelope, one
   `full_access` definition — it presently means two different things across three endpoints and the
   three already disagree for an à-la-carte buyer who owns every module.
4. **Hand-offs:** five findings go to Phase 3 (indexes for the admin sorts and `orders.paid_at`),
   five to Phase 4 (Chargily signature verification degenerating to a known key when the secret is
   unset, order-existence disclosure via 403-vs-404, partial audit-log coverage).
5. **Preferences** (8 items) are listed but deliberately not acted on; they dilute the real work.

## Not verified

- **Real payment providers.** Chargily and Stripe were faked throughout. The webhook amount/currency
  check (raised, then rejected on verification) should be re-examined against real payloads.
- **Genuine concurrency.** The coupon and invoice-sequence races were reproduced sequentially or
  reasoned from the SQL; none was driven under real parallel load.
- **Production data volumes.** Pagination findings state which collections are unbounded, not what
  they cost — that is Phase 3, which measured them at 10× volume.
- **Phases 2 and 4 have not been run.** Test-coverage gaps and the dependency/security audit remain
  open; the pipeline's own rule is one phase per run.
