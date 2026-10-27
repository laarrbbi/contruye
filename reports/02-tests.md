# Test Validation — Ebni Academy

**Date:** 2026-08-27
**Scope:** all 29 test files against the 128 behaviors of `reports/behavior-inventory.md`
**Phase:** 2 of 4 — Tests

## Summary

Three reviewers mapped every existing test onto the behavior inventory and asked the sharpest
question available: **would this test still pass if the function body were deleted?** For 77
behaviors the answer was yes or nearly yes. 68 tests were written to close the ones that matter,
each one verified by breaking the production code it covers and confirming it goes red.

The suite went from **245 to 313 passing tests**, and — more usefully — from a suite that looked
thorough to one that actually holds. Six of this session's own fixes had no test pinning them and
could have silently regressed, including the coupon reservation and the webhook amount check.

Gaps found: High 14 · Medium 33 · Low 30
Misleading tests identified: 19
Tests added: 68 in the phase itself, 77 including the follow-up that closed B36, B51 and B122.
Tests with zero assertions before this phase: 0. Final suite: **320 passing**.

## Scope and method

- **Read:** all 29 files in `api/tests/Feature/`, and for each behavior the production code it
  claims to protect.
- **Ran:** `php artisan test` throughout; every proposed test was executed, and every one was
  verified by mutation — the production code was deliberately broken, the test observed failing,
  and the code restored (`git diff` outside `api/tests/` empty afterwards).
- **Could not verify:** genuine concurrency. Race conditions (invoice sequence allocation, coupon
  reservation under simultaneous checkout) are tested sequentially or by forcing the conflicting
  state, never under real parallel load.

## The gaps that mattered most

### Rules that no test actually exercised

| Behavior | What a plausible edit would have done, with the suite still green |
|---|---|
| **B102** attachments gated on ownership | Making the attachment gate "consistent" with progress — one extra argument — hands **every paid attachment in the course** to any registered user through a single free-preview lesson. Now covered; the mutation was re-run for this report and two tests go red. |
| **B101** locked lesson content withheld | Removing the `$playable ?` ternaries leaks the **entire paid written curriculum** to a student who owns one module. Nothing asserted the withholding half — only that the module reads `is_locked`. |
| **B87** active-enrollment predicate | Deleting `->active()` from the single predicate behind four access checks left learn, playback, attachments and checklists ungated, and no test failed. An expired student would keep access forever. |
| **B81** webhook amount matching | Every Stripe payload in the suite omitted `amount_total`, so the check short-circuited before comparing anything. Swapping `amount * 100` for `amount` passed the whole suite while letting a €1.99 payment settle a €199 order. Re-verified for this report: the new test goes red. |
| **B95** invoice seller snapshot | No test issued an invoice, then edited the seller settings, then re-read the invoice. A correction to the RC or NIF could have retroactively rewritten already-delivered fiscal documents. |
| **B65** full-access-only module | The 422 branch was never reached, so deleting it billed a null-priced module at `(int) null = 0`. |

### Fixes from this session that nothing pinned

Six defects fixed in Phases 1, 3 and 4 had no regression test — the bug had already proved it can
happen, and nothing stopped it happening again:

- coupon use reserved at checkout and released on failure (B72/B121)
- the completion-rate SQL rewrite returning the same number as the PHP loop it replaced (B55)
- catalog social proof after the per-course aggregates became subqueries (B5/B126)
- `activeCampaign()` resolving in memory when the relation is loaded (B6)
- the RESTRICT foreign keys protecting paid orders (B85/B89)
- `app:check-secrets` refusing a blank secret (B9/B10)

All six now have tests.

## Misleading tests

Worse than no test, because they buy confidence. 19 were identified; the pattern repeats:

| Test | Problem |
| `grants a pending cash order: paid, enrolled, audited` | Fabricates method => 'cash' via the factory. CheckoutRequest's method allow-list is {chargily, stripe, manual_ccp, manual_baridimob} and POST /checkout is the only order-creation path, so a cash order can never |
| `paidModulesOrder() helper — used by 10 of the 14 tests in th` | Hand-writes amount => 32400 and discount_percent => 10 instead of routing through /api/checkout or PricingService. Every centime assertion in the file is therefore anchored to numbers a human typed, not to what |
| `serves the PDF to the buyer and the admin, and to nobody els` | Asserts only 200 + content-type: application/pdf on the student route and the admin route. It is the sole test for B99 ('byte-identical across all three paths') yet never compares the two response bodies. Two e |
| `marks the order paid… on checkout.paid / on checkout.session` | Every happy-path payload omits amount / amount_total / currency, so ProcessPaymentEvent::amountMatches() returns true at the `$paid === null` short-circuit before comparing anything. The tests read as end-to-en |
| `approves an awaiting_review order / stays idempotent on doub` | All three assert enrollment with assertDatabaseHas('enrollments', ['user_id','course_id','order_id']) and never pin module_id. OrderFactory sets no scope, so every one of them runs only the scope='full' branch  |
| `deletes a coupon` | Deletes a coupon that no order references, then asserts the row is gone. It reads as coverage for coupon deletion but avoids the only case with consequences: orders.coupon_id is nullOnDelete — the single financ |
| `exports filtered orders as csv` | Asserts content-type and three substrings. B41 specifies a UTF-8 BOM (the reason the export exists in that form — Excel rendering Arabic buyer names and accented course titles) and a commandes-YYYY-MM-DD.csv fi |
| `rejects an awaiting_review order with a reason: failed, audi` | The name promises the full consequence of a rejection but the assertions stop at status, admin_note, the audit row and the mail. reject() calls $order->markFailed(), whose whole purpose is to hand the reserved  |
| `it('logs out the authenticated user')` | The name claims the user is logged out; the body proves only that the in-memory `web` guard no longer holds a user. It asserts nothing about `$request->session()->invalidate()` or `regenerateToken()` — both lin |
| `it('offers the module 13.2 reflex sheet as a downloadable PD` | 'to owners only' is an assertion about a gate the controller does not implement. ReflexSheetController calls authorizeLessonAccess(..., allowFreePreview: true), so a user with no enrollment at all downloads a 2 |
| `it('registers a new student and logs them in')` | It asserts data.role === 'student' and assertDatabaseHas(['role' => 'student']) but never submits a `role` field, so the assertion passes identically whether the controller forces the role or trusts the client. |
| `it('rejects a negative position with 422') + it('rejects a m` | The pair reads as 'position_seconds is validated'. Only the lower bound and presence are. There is no upper bound: probe returns a 500 for position_seconds = 999999999999 and PHP_INT_MAX (outside the column's i |
| `it('throttles every API route, not only the ones with their ` | The name promises coverage of the whole throttling contract (B123). It sends 121 requests to one public GET and exercises only the 120/min 'api' limiter. The security-relevant limiter — throttle:auth, 10/min on |
| `it('computes monthly revenue by currency and orders by metho` | It reads data.orders_by_method.chargily.paid with four orders seeded, so it can only ever see the map-shaped serialisation. With an empty orders table the same field comes back as a JSON array `[]` rather than  |
| `it('throttles every API route, not only the ones with their ` | The only regression test for the api-wide rate limiter (C4/C11/B124) lives in the locale test file and has nothing to do with locale. It is also the slowest test in that file (121 requests). Anyone triaging a f |
| `it('computes the average completion rate across enrollments'` | Reads as a semantic pin for B55 and is not one. It survived the Phase 3 rewrite unchanged while the rewrite silently changed the denominator — enrollments on a course with no lessons used to count as 0% samples |
| `it('refuses to delete a course that has been sold, and never` | The name claims two guarantees and proves one. The controller returns 422 before any DELETE reaches the database, so the assertion that the order survives is satisfied by the guard alone — the RESTRICT foreign  |
| `the catalog social-proof suite` | ReviewTest's three social-proof tests ('reports no rating at all until a real review is approved', 'publishes only approved reviews and averages exactly those', 'counts only real active enrolments as students') |
| `ZzProbeTest.php / ZzProbe2Test.php (assertion-free probe fil` | Currently untracked and owned by a concurrent session, so not a defect to fix now — but they contribute 20 PHPUnit 'risky, did not perform any assertions' warnings to every suite run, which is noise that trains |
None were deleted. A misleading test is usually a real test with a weak assertion, and the fix is
to strengthen it rather than remove coverage — which is what the 68 new tests do around them.

## Full gap table

| Behavior | Risk | Status | What it protects | Why the existing tests did not |
|---|---|---|---|---|
| B95 | High | uncovered | Seller (émetteur) block is snapshotted into invoices.seller at issuance, so later settin | InvoiceTest only asserts the settings round-trip (complete/missing/seller.business_name) BEFORE any invoice exists. No test issues an invoice, then edits invoicing.seller, then re-reads the … |
| B81 | High | partial | Webhook settles an order only when the collected amount matches, with Stripe's minor uni | Every Stripe payload in the suite (stripeEventPayload()) omits amount_total and currency, so ProcessPaymentEvent::amountMatches() hits the `$paid === null` short-circuit and returns true bef… |
| B102 | High | uncovered | Attachments are gated on module ownership alone — a free preview inside an unowned modul | AttachmentTest never builds the one case that distinguishes this rule from B101: a free-preview lesson inside a module the user does not own. Every test either enrolls the user (full access)… |
| B87 | High | partial | Active enrollment = expires_at IS NULL OR expires_at > now(); every access check applies | Only /api/enrollments is tested (EnrollmentsApiTest 'excludes expired enrollments'), and EnrollmentController::index applies ->active() itself on its own query. Deleting ->active() from User… |
| B65 | High | partial | A full-access-only module (null price in the chosen currency) in a partial, non-completi | PricingTest covers only the completing selection that upgrades to full ('upgrades an all-modules EUR selection…'). The throw at PricingService.php:98-103 is never reached — no test asks for … |
| B101 | High | partial | Playable = module owned OR lesson is_free_preview; playability gates content, key_points | PricingTest 'locks non-owned modules in the player' asserts only is_locked plus the playback/progress 403s. PartnerTest covers the free-preview progress round-trip. Nothing asserts the withh… |
| B26 | High | partial | GET /lessons/{lesson}/reflex-sheet — access gate: the PDF embeds the LESSON's expert_tip | CurriculumV2Test:139 ('offers the module 13.2 reflex sheet as a downloadable PDF to owners only') only checks a NON-preview lesson for a non-enrolled user (403) and an owner (200). Probe pro… |
| B1 | High | partial | POST /register forces role='student' regardless of what the client submits | AuthTest:5 asserts data.role === 'student' and the DB row's role, but never SUBMITS a role. The assertion therefore passes identically whether the controller forces the role or honours the c… |
| B123 | High | uncovered | throttle:auth — 10 requests/min per IP on /register, /login, /forgot-password, /reset-pa | LocaleTest:62 covers only the global 'api' limiter (120/min) via 121 GET /api/courses. No test touches the 'auth' limiter, which is the brute-force / credential-stuffing control. Probe: 12 s… |
| B13 | High | partial | POST /logout — logs out the web guard, invalidates the session, rotates the CSRF token | AuthTest:73 asserts only assertGuest('web'), which catches deletion of `Auth::guard('web')->logout()` but not deletion of `$request->session()->invalidate()` or `regenerateToken()` — both li… |
| B72 / B121 | High | partial | Coupon max_uses is claimed as a reservation at checkout and released when the order fail | CheckoutTest pins the two halves in isolation ('caps a limited coupon at the discount, not just at the counter' and 'gives a reserved coupon use back when the order never completes'), and bo… |
| B5 / B126 | High | uncovered | Catalog social proof: distinct active-enrollment students, approved-review count and ave | Proven by mutation: I stripped ->active() off the students_count subquery and ->approved() off the reviews_count subquery in Api/CourseController::index and the entire suite still passed 247… |
| B55 | High | partial | Admin dashboard average completion rate (StatsController::completionRate SQL rewrite) | AdminStatsTest 'computes the average completion rate across enrollments' passes on both the old and the new implementation, so it does not pin the rewrite. It also hides a semantic change th… |
| B81 / B119 | High | partial | Payment webhook must match amount, currency and provider before enrolling | WebhookForgeryTest pins the provider check ('will not let one provider settle an order placed with another') and the Chargily amount mismatch, and both pass. Two thirds of amountMatches() ar… |
| B76 | Medium | partial | pending → failed when the provider throws during checkout creation; API answers 502 | Covered twice for Chargily (HTTP 500 response, and ConnectionException — the C6 regression test). The Stripe equivalent, CheckoutController::stripeCheckout's catch (Throwable) → markFailed()… |
| B93 | Medium | partial | issued_at may be backdated up to 60 days and the fiscal-year sequence follows it, so a J | InvoiceTest 'rejects out-of-range issue dates' covers only the two rejections (+1 day, -90 days). Nothing asserts the accepted case: an invoice issued today with issued_at 45 days back must … |
| B122 | Medium | uncovered | Every admin mutation with money implications writes an AuditLog — invoice.created/update | InvoiceTest and CampaignTest contain zero audit_logs assertions across ~1,000 lines. Only order.approved/rejected/granted are proven (AdminOrdersTest). The concurrent AuditTrailTest.php in t… |
| B51 | Medium | uncovered | POST /admin/invoices/{invoice}/send queues InvoiceIssuedMail to invoice.order.user, audi | Zero tests touch this route. No Mail::assertQueued(InvoiceIssuedMail::class), no recipient assertion, no audit assertion, no throttle assertion, no 403 assertion. The whole method body could… |
| B85 | Medium | partial | Uniqueness is (user_id, course_id, module_id); MySQL allows repeated NULLs so full-acces | The firstOrCreate guard is proven only against repeat delivery of the SAME order (ChargilyWebhookTest idempotency, PricingTest double markPaidAndEnroll). The documented hole — two DIFFERENT … |
| B36 | Medium | uncovered | Coupon CRUD; a coupon that priced a paid order must not erase that order's discount prov | AdminCouponTest 'deletes a coupon' deletes a coupon nothing references. orders.coupon_id is the one financial foreign key the 2026_08_18 hardening migration left as nullOnDelete (it hardened… |
| B89 | Medium | partial | Deleting a module must not take away access or order lines behind an order's back | FinancialRecordProtectionTest proves a module carrying order_modules rows cannot be deleted, and that an unsold one deletes normally. But enrollments.module_id is still cascadeOnDelete and M… |
| B104 | Medium | partial | Module-level content blocks (legal, expert_tip, partner_slot, text) are served only for  | Only the partner_slot block type is asserted under lock (PartnerTest:54, WilayaCoverageTest:211). CurriculumV2Test asserts legal blocks are PRESENT for an enrolled student but never that the… |
| B2 | Medium | uncovered | POST /login answers 422 auth.failed with no user-enumeration difference between a wrong  | AuthTest:48 only exercises an EXISTING user with a wrong password. Nothing compares that response to one for an address that has no account. Probe confirms both are byte-identical 422 {messa… |
| B23 | Medium | partial | POST /lessons/{lesson}/progress — position_seconds validation (finding C22, raised and d | ProgressTest covers the missing (422) and negative (422) cases, which reads as 'the field is validated'. It is not validated upward: probe returns 500 for position_seconds = 999999999999 and… |
| B127 | Medium | uncovered | `full_access` computed two different ways: hasFullAccessTo (a module_id NULL row) on /en | Findings C13/C16, deferred. PricingTest:353 and :392 assert full_access === false for a PARTIAL owner, where the two definitions happen to agree; nothing tests the à-la-carte completer where… |
| B7 | Medium | uncovered | GET /courses/{slug}/reviews meta.can_review and meta.my_review — the viewer's own review | ReviewTest asserts data, meta.count, meta.average and meta.distribution, but never can_review or my_review. Both lines in ReviewController::index (:50-51) can be deleted with all ten review … |
| B110 | Medium | partial | SetLocale — primary subtag of the FIRST tag, lowercased, fallback to fr; q-values are ig | LocaleTest:7 covers 'ar', 'en' and 'de-DE,de;q=0.9'. Untested: a region subtag that must map to a supported locale ('ar-DZ' → ar), a multi-tag header ('fr,ar;q=0.8' → fr), an absent/empty he… |
| B106 | Medium | uncovered | Player progress arithmetic ignores locked modules: progress_percent and next_lesson_id a | Every progress_percent assertion on /learn (LearnTest:59, :90, :102) uses a full-access enrollment where accessible == all. PricingTest:335 covers a partial owner but asserts is_locked, owne… |
| B6 | Medium | uncovered | GET /courses/{slug} attaches per-module `is_owned` and course-level `full_access` for an | CourseCatalogTest asserts is_enrolled true/false/expired only. No test reads data.modules.*.is_owned or data.full_access from the public course endpoint — the three setAttribute lines in Cou… |
| B19 | Medium | uncovered | Admin bypass asymmetry: an admin may toggle a checklist item and create a task in a modu | ChecklistTest proves student-vs-student isolation ('A stranger cannot edit or delete someone else's task') but never puts an admin anywhere in the checklist surface. Probe confirms the docum… |
| B102 | Medium | uncovered | Attachments are gated on module OWNERSHIP alone — a free-preview lesson inside an unowne | AttachmentTest:90 covers a user with no enrollment at all on an ordinary lesson. The discriminating case — enrolled in module A, requesting an attachment of a FREE-PREVIEW lesson in unowned … |
| B15 | Medium | partial | PATCH /me updates only wilaya_id and locale; email, role and password are deliberately n | WilayaCoverageTest:156 covers role and LocaleTest:50 covers locale, so two of the four are pinned. Submitting `email` or `password` is untested — probe confirms both are ignored today (addre… |
| B4 | Medium | partial | POST /reset-password — Password::reset consumes the token; a reset token cannot be repla | PasswordResetTest covers the happy path, an invalid token (422 on `email`) and a weak password, which is unusually good. Not covered: replaying a token that has ALREADY been used successfull… |
| B26 | Medium | uncovered | The reflex sheet ignores lessons.is_reflex_sheet — any lesson id renders a 'fiche réflex | CurriculumV2Test:139 fetches the one lesson with is_reflex_sheet = true, so it never asks what an ordinary lesson does. Probe: an enrolled student requesting an ordinary lesson gets a 200 PD… |
| B117 | Medium | uncovered | GET /wilayas sends Cache-Control: private, max-age=86400 and appends Vary: Accept-Langua | WilayaCoverageTest:16 asserts the 58 rows and their order but reads no header. Both lines in WilayaController (:33, :35) can be deleted, or `private` changed to `public`, with the suite gree… |
| B20 | Medium | uncovered | POST /modules/{module}/tasks — 100 tasks per (user, module) then 422, and order = max(or | ChecklistTest:145 covers the empty body and the 501-character body but neither the cap nor the ordering. Probe: the 101st task returns 422. The cap is the only thing standing between a perso… |
| B113 | Medium | partial | The user's STORED locale drives outbound mail (ResetPasswordNotification), while the req | AdminStatsTest:102 pins the rule for WelcomeMail only (asserts $mail->locale === 'ar'). The password-reset notification — the one mail a user receives while logged out, when no Accept-Langua… |
| B18 | Medium | uncovered | GET /courses/{slug}/checklists — 403 when the caller holds no enrollment in the course,  | ChecklistTest covers per-MODULE locking richly but every actor in it is already enrolled somewhere in the course; the guest/403 case is tested only for the toggle route (:88). Probe: not-enr… |
| B89 / B85 | Medium | partial | Catalogue deletes must not destroy payment records (RESTRICT foreign keys plus 422 guard | FinancialRecordProtectionTest pins the controller guards (course and module delete both 422, unsold ones still 204) and pins the database-level RESTRICT for users.user_id via a direct $stude… |
| B55 | Medium | uncovered | revenue_this_month month boundary in the business timezone | The same C8 fix also changed StatsController:19 to now()->setTimezone(config('app.business_timezone'))->startOfMonth(), but AdminStatsTest 'computes monthly revenue by currency and orders by… |
| B9 / B10 | Medium | uncovered | php artisan app:check-secrets --strict fails while a security-critical secret is blank | The command shipped in 7137999 as the deploy-time answer to S1/S2 (the security report makes it recommendation #2, 'before any deploy'), and no test invokes it. Nothing pins the six secrets … |
| B6 / B16 / B17 | Medium | uncovered | full_access means the same thing on the course page, the dashboard and the player | Deferred as C13/C16 — the report states the three endpoints already disagree for an a-la-carte buyer who owns every module. Nothing pins the current disagreement either, so it can drift furt… |
| B85 | Medium | uncovered | A user can never hold two full-access enrollments for one course | Deferred as C21/C40. The inventory itself records that MySQL allows repeated NULLs in the (user_id, course_id, module_id) unique index, so the guarantee is app-side firstOrCreate only, and t… |
| B26 | Medium | uncovered | Reflex-sheet PDF gate: it serves a locked module's legal blocks through a free-preview l | Deferred three times (C14, C56, S6) and explicitly left to the owner as a business decision — the sheet concerns a fatal workplace accident and may be deliberately free. CurriculumV2Test 'of… |
| B56 | Medium | uncovered | per_page clamped on GET /admin/students | Deferred as C25/C28/C50/S11 and recommended by both reports. Note the working tree is being changed under me right now by a concurrent session: StudentController already calls $this->perPage… |
| B91 / B92 | Medium | uncovered | retryOnSequenceRace absorbs a duplicate invoice-number collision instead of returning a  | Deferred as C20. B92 documents a three-attempt retry on SQLSTATE 23000/40001 with a rethrow on the fourth, and nothing exercises it — InvoiceTest 'numbers invoices sequentially within a year… |
| B5 / B6 | Medium | partial | Course::activeCampaign() resolves in memory when campaigns is eager-loaded, matching the | P3 introduced a second implementation of the running-campaign rule (Campaign::isRunning() mirroring scopeActive() term for term) and the Phase 3 report itself flagged the duplication as drif… |
| B80 | Low | partial | {pending, awaiting_review, failed, refunded} → paid via admin grant; the only guard is ' | Only pending → paid and the already-paid 422 are tested. The awaiting_review, failed and refunded source statuses — the ones that make grant a genuine override rather than a duplicate of app… |
| B47 | Low | uncovered | GET /admin/invoices — paginated, ordered by year DESC then sequence DESC, search over nu | No test calls the route. The ordering, the four-way search (including the orWhereHas on order.user), the per_page clamp and the 403 for non-admins are all unasserted; the method body could r… |
| B48 | Low | partial | tva_rate must be one of {0, 9, 19} | Only 19 (default payload) and 0 (the correction test) are ever posted. Neither the legal 9 % rate nor an out-of-set value (e.g. 20) is tested, so the Rule::in could be widened to any numeric… |
| B99 | Low | partial | One rendering path (InvoicePdf::make), so admin download, student download and emailed a | InvoiceTest 'serves the PDF to the buyer and the admin, and to nobody else' asserts 200 + content-type on both routes and never compares the bodies. Two divergent documents pass. (The emaile… |
| B72 | Low | partial | An unknown/expired coupon is a 422 at /checkout but is silently ignored at /checkout/quo | The 422 half is covered three ways in CheckoutTest (expired, capped, unknown). The quote half — resolveCoupon(..., failWhenInvalid: false) returning null so the quote renders at full price r… |
| B70 | Low | partial | Upgrade hint shown only when modules remain unselected AND the selection total ≥ 70 % of | CampaignTest asserts the positive case (9/10 modules during a campaign, price_delta 2880). Nothing asserts the negative cases: hint must be null when the selection is far below 70 % (e.g. 2 … |
| B123 | Low | uncovered | throttle:checkout — 10/min per user-or-IP on POST /checkout | LocaleTest:62 proves the global 120/min api limiter is attached (the C4 regression test), but nothing proves the named checkout limiter is. Removing ->middleware('throttle:checkout') from ro… |
| B84 | Low | partial | markPaidAndEnroll re-reads lockForUpdate, returns false when the row is gone or already  | The already-paid branch is well covered (idempotency tests, coupon non-double-count). Two clauses are not: the `$fresh === null` return-false path, and `'paid_at' => $fresh->paid_at ?? now()… |
| B92 | Low | uncovered | retryOnSequenceRace retries the transaction up to 3 times on SQLSTATE 23000/40001 so the | No test drives a duplicate-key collision. The retry loop is dead code as far as the suite is concerned — the in_array codes could be changed to anything and nothing fails. Genuinely hard to … |
| B41 | Low | partial | GET /admin/orders/export — filtered CSV with a UTF-8 BOM, filename commandes-YYYY-MM-DD. | AdminStatsTest asserts content-type and three substrings, and the method filter genuinely bites (the raw method string is written to the CSV). But the BOM — the entire reason it is there, Ex… |
| B83 | Low | uncovered | refunded is a write-only dead state: it exists in the enum and is filtered/exported on,  | No test pins this. A future refund endpoint that sets refunded without reversing enrollments would be caught by nothing, and no test asserts a refunded order's access consequences (it curren… |
| B121 | Low | partial | Coupon max_uses can never be exceeded — claimed atomically by a conditional UPDATE | The sequential cap is now strongly covered (CheckoutTest 'caps a limited coupon at the discount' and the release-on-failure test — the C1 regression tests). Two things remain: the concurrent… |
| B64 | Low | partial | Module selection = requested ids ∩ course modules, de-duplicated, minus modules already  | The intersection (foreign module 422) and the owned-subtraction (owner of 1-2 quoted for 3 only; fully-owned → 422) are well covered. The de-duplication is not: array_unique in PricingServic… |
| B19 | Low | uncovered | PUT /checklist-items/{item} — 422 on a missing or non-boolean `checked`, 404 on an item  | ChecklistTest only sends well-formed true/false payloads. Probe: missing → 422, 'oui' → 422, unknown id → 404. Low individually, but this is the only mutation endpoint in the slice with a ha… |
| B32 | Low | uncovered | POST /courses/{slug}/reviews — 404 on an unpublished course, 422 when body exceeds 2000  | ReviewTest covers the rating range, the enrolment gate and the moderation round-trip, but never posts to a draft course nor an oversized body. Probe: draft → 404 (both index and store), 2001… |
| B112 | Low | uncovered | Array-valued content (key_points_*) uses the same fallback chain, treating [] as empty | LocaleTest:7 and CurriculumV2Test:163 cover the STRING chain (localizedTitle / localizedDescription). localizedArray in HasLocalizedAttributes:46 — a separate code path with its own `is_arra… |
| B116 | Low | uncovered | Partner pivot notes pick note_ar only when the locale is exactly 'ar', with NO fallback  | PartnerTest:51 asserts the note in the default locale only. Probe: fr → NOTE-FR, ar → NOTE-AR, en → NOTE-FR. This is the one localized field in the codebase that deliberately does not use Ha… |
| B57 | Low | uncovered | GET /admin/students/{user} 404s when the target's role is not 'student' | AdminStatsTest:62 exercises the endpoint on a real student only. Probe: an admin requesting another ADMIN's id gets 404. This is the only place the admin surface refuses to profile a non-stu… |
| B56 | Low | uncovered | GET /admin/students does not clamp per_page (findings C25/C28/S11, all deferred) | No test passes per_page to any admin collection. Probe: per_page=-1 → 200 (the 500 reported in C28 does not reproduce on the current code) and per_page=100000 → 200, dumping the whole table.… |
| B55 | Low | uncovered | GET /admin/stats orders_by_method serialisation (finding C32) | AdminStatsTest:21 asserts data.orders_by_method.chargily.paid with orders present. Probe with an empty orders table: orders_by_method serialises as `[]` (a JSON array) rather than `{}` (an o… |
| B33 | Low | partial | Admin course slug is auto-generated from title_fr AND de-duplicated when two courses sha | AdminCourseTest:60 covers generation from a unique title and :140 covers an explicit duplicate slug (422). The de-duplication branch — omitting the slug for a title whose slug is already tak… |
| B123 | Low | uncovered | throttle:60,1 on GET /wilayas | Neither WilayaCoverageTest nor LocaleTest's limiter test touches it. Now that the global 'api' limiter (120/min) is attached, the inline 60/min limiter is the only thing making /wilayas stri… |
| B33 | Low | partial | Admin routes return 401 for GUESTS (as opposed to 403 for authenticated non-admins) | AdminCourseTest:9 walks 17 admin routes as a STUDENT (403); InvoiceTest:70 is the only file that also checks a guest. The campaigns, partners, orders, reviews, stats and students groups are … |
| B27 / B76 | Low | uncovered | Stripe checkout-session creation failure also 502s and fails the order | CheckoutController::stripeCheckout has the mirror-image catch (Throwable at line 186, markFailed, 502), and no test drives it — CheckoutTest's Stripe cases only cover the missing-EUR-price 4… |
| B55 | Low | uncovered | orders_by_method keeps a stable JSON shape when there are no orders | Deferred as C32. StatsController:29-34 builds it via ->get()->keyBy()->map(), which serialises to {} normally and to [] on an empty collection, so the client's type flips between object and … |
| B23 | Low | uncovered | Progress heartbeat rejects an out-of-range position instead of 500ing | Deferred as C22. ProgressHeartbeatRequest rules are ['required','integer','min:0'] with no max, so a value beyond the column's range is a database error, not a 422. ProgressTest pins the neg… |
| B29 | Low | uncovered | 403-vs-404 on another user's order does not disclose existence | Deferred as C37/S4/S9, judged not worth its own deploy. No test pins the current behavior either, so the enumeration oracle can neither be shown to have been fixed nor shown to still exist. … |
| B9 | Low | partial | Chargily webhook replay protection | Deferred as S7 — no signed timestamp, no event-id de-duplication. What keeps it Low is idempotence, and that half IS pinned: ChargilyWebhookTest 'stays idempotent on double delivery: one enr… |
| B120 | Low | uncovered | The payment confirmation mail is not lost when the webhook job retries | Deferred as C18. markPaidAndEnroll() returns true exactly once, so a job that dies after the transition and before Mail::queue never re-sends — the buyer pays and hears nothing. ChargilyWebh… |
| B28 / B32 | Low | uncovered | Unbounded collections: GET /api/orders and the public course-reviews endpoint | Deferred as C10/C30 and both recommended as cheap wins. Neither has a test asserting a pagination envelope, so adding pagination later is a silent contract change for the SPA rather than a t… |
## Test infrastructure

- The test database was stale and the suite could not run: `SQLSTATE[42S02] Table 'ebni_test.partners' doesn't exist`. Fixed with `cd /Users/mlh/Desktop/ebni && docker compose exec -T php php artisan migrate:fresh --env=testing --force`. Any Phase 2 work should start with that command — the 242-passing baseline in the brief is not reproducible without it.
- THE WORKING TREE IS NOT CLEAN AND ANOTHER AGENT IS WRITING TO IT CONCURRENTLY. `git status` shows 9 modified admin controllers (CouponController, CourseController, InvoiceController, LessonController, ModuleController, OrderController, ReviewController, StudentController, Controller.php) plus untracked tests/Feature/AuditTrailTest.php, ZzProbeTest.php and ZzTempProbeTest.php. My post-migration baseline was 227 passed / 32 failed / 8 risky, and the failures originate in those in-flight probe files, not in the committed suite.
- Because of that concurrent write activity I did NOT run mutation experiments — deleting a function body while another agent edits the same tree would corrupt both results. Every 'would this still pass if the body were deleted' verdict above is derived from reading the code and the tests, not from an executed mutant. The four High-risk ones (B95, B81 Stripe conversion, B102 attachment gate, B87 expired enrollments) are worth confirming with a real mutant once the tree is quiet.
- tests/Feature/ZzProbeTest.php (254 lines) and tests/Feature/ZzTempProbeTest.php are scratch probe files from another phase and must be deleted before anything here is committed — they currently account for the entire red suite.
- OrderFactory sets no `scope`, so every factory-built order exercises only the `full` branch of Order::markPaidAndEnroll. Adding a `modules()` state to OrderFactory (scope='modules' plus a couple of order_modules rows) is the single cheapest change that would unlock the enrollment-shape gaps in B85/B86 and let the webhook and admin-approval tests assert module_id.
- OrderFactory picks `method` at random from ['chargily','manual_ccp','manual_baridimob']. Today every test that cares overrides it, but ProcessPaymentEvent::belongsToProvider now makes `method` semantically load-bearing on the settlement path, so any future provider-sensitive assertion written against a bare Order::factory() will be intermittently wrong. Consider pinning the default.
- StripeWebhookTest's stripeEventPayload() helper is the natural place to close the largest High-risk gap: adding 'amount_total' => $order->amount * 100 and 'currency' => 'eur' to the data.object, plus one negative test with the wrong figure, covers the minor-unit conversion in one edit.
- There is no test helper for 'a free-preview lesson inside an unowned module'. Three separate gaps (B101 content withholding, B102 attachment gate, B104 module blocks) all need that fixture; PartnerTest:223 builds it inline and is the only place in the suite that does. Extracting it would make all three cheap.
- Auth::attempt() 500s after any authenticated request in the same test. `auth:sanctum` calls $this->auth->shouldUse('sanctum') on success, so the DEFAULT guard becomes Sanctum's RequestGuard for the rest of the process; AuthController::login then calls Auth::attempt() on it and throws BadMethodCallException: 'Method Illuminate\Auth\RequestGuard::attempt does not exist' → 500. Any test of the form 'do something authenticated, then POST /api/login to prove credentials still work' (e.g. the B15 gap: PATCH /me must not change the password) hits this. Fix in the test: call Auth::shouldUse('web') or Auth::forgetGuards() before the login request. Harmless in production (fresh process per request) but a live hazard under Octane.
- Sanctum's RequestGuard caches its resolved user for the whole test process, so a request issued after POST /api/logout is STILL authenticated (probe: GET /me → 200 with the user's e-mail; Auth::forgetGuards() then → 401). Any logout regression test must call Auth::forgetGuards() between the two requests, or it will assert the opposite of the truth.
- SESSION_DRIVER=array in phpunit.xml means there is no server-side session store at all: nothing between requests persists, and authentication across requests in a test is carried entirely by cached guard instances. The suite therefore cannot, as configured, prove that `$request->session()->invalidate()` did anything. Proving B13 properly needs a file/database session driver for that one test (e.g. a `config(['session.driver' => 'file'])` group or a dedicated test-case trait).
- actingAs() persists for the remainder of a Pest test — ChecklistTest:87 documents this in a comment ('Guest first — actingAs() persists for the rest of the test') and several files depend on the ordering implicitly. Any new test that wants both a guest and an authenticated assertion must put the guest call FIRST. My first probe of the guest review payload was wrong for exactly this reason until re-run in its own test.
- Pest helper functions are declared at FILE scope and are therefore global across the entire suite: publishedCourse() and enrolledStudent() (ReviewTest), checklistCourse() (ChecklistTest), partnerCourse() (PartnerTest), wilaya() (WilayaCoverageTest), fakeReceiptFile() (ManualPaymentTest). A new test file that redeclares any of those names is a fatal error, and there is no tests/Feature/helpers.php to hold shared builders. Worth extracting them into one file before adding more.
- The test database can be left mid-migration: every test in the suite failed with SQLSTATE[42S02] 'Table ebni_test.migrations doesn't exist' / SQLSTATE[42S01] 'Table jobs already exists' until `docker compose exec -T php php artisan migrate:fresh --env=testing --force` was run by hand. An interrupted run poisons the next one with errors that look like application failures. Worth a documented reset step (or a `RefreshDatabase` guard) so this is not diagnosed from scratch each time.
- No coverage tooling is wired up: phpunit.xml declares <source><include><directory>app</directory></include></source> but no <coverage> report and there is no Xdebug/PCOV in the php container, so 'which lines does the suite actually reach' cannot be answered mechanically — every gap in this report had to be established by reading and by throwaway probes. Enabling PCOV in the docker php image would make the next pass of this phase much cheaper.
- Admin users are built two ways across the suite — User::factory()->admin()->create() (AdminContentTest, AdminCourseTest, AdminStatsTest) and User::factory()->create(['role' => 'admin']) (PartnerTest, WilayaCoverageTest, ReviewTest). Harmless today, but it means a change to the admin factory state only covers half the suite. Standardise on the factory state.
- CACHE_STORE=array, so RateLimiter counters reset between tests — throttle tests are safe to add without cross-test bleed, and an auth-limiter test needs only 11 requests. Conversely no test can observe limiter state surviving a request boundary.
- The test database was left half-migrated: every filtered run failed with SQLSTATE[42S01] Table 'sessions' already exists until I ran `docker compose exec -T php php artisan migrate:fresh --env=testing --force`. RefreshDatabase assumes a clean ebni_test and there is nothing that guarantees it. A make target or a TestCase bootstrap that recreates the schema when it drifts would have saved the failure being misread as thirteen broken tests.
- No mutation or coverage tooling. The single largest gap in this phase — the catalog social-proof aggregates — was only findable by hand-editing Api/CourseController.php, running the full suite, and observing 247 passes. Infection, or at minimum `--coverage --min` gated on the money paths (Checkout, Order, Coupon, ProcessPaymentEvent, InvoiceBuilder, StatsController), would have surfaced it mechanically. Both Phase 1 and Phase 4 relied on write-run-delete probes for the same reason.
- No harness for genuine concurrency. Every race in the reports (coupon reserveUse, invoice allocateNumber/retryOnSequenceRace B91-B92, module display-order read-modify-write C27, duplicate full-access enrollment B85) is reasoned from the SQL or reproduced sequentially. Two real connections driving the same row would let B92 and reserveUse be tested rather than argued, and both reports flag this as 'not verified'.
- No real provider payloads. Both webhook suites hand-build minimal JSON, which is exactly why the Stripe minor-unit conversion (ProcessPaymentEvent:122) is unreachable by any current test — the payload builder omits amount, so amountMatches() takes the null escape every time. Committing one captured (redacted) Chargily body and one Stripe event body as fixtures would close the biggest remaining money-path blind spot.
- The guzzle 7.14.0 -> 7.15.2 upgrade (S12) is explicitly gated on this phase, and the Chargily HTTP contract it sits under has no test: the 5 s connect / 12 s total timeouts and the deliberate no-retry decision from C7 are asserted nowhere. The bump would land with the failure mode covered (ConnectionException -> 502) but the timeout and retry policy uncovered.
- Six of the reports' shipped fixes are pinned by tests that live in files named after something else (rate limiter in LocaleTest) or by tests that predate the fix (completion rate in AdminStatsTest). There is no convention linking a regression test back to the finding it pins. A `// pins: C4` style marker, or one file per report finding, would make 'was this fix tested?' answerable by grep instead of by the audit I just ran.
- Note for the orchestrator: the working tree changed under me during this phase. api/app/Http/Controllers/Controller.php, six Admin controllers and Admin/StudentController.php are modified, and api/tests/Feature/AuditTrailTest.php plus two probe files are untracked — a concurrent session is landing the per_page clamp (C25/C28/C50/S11) and AuditLog coverage (C5/C10/S5/S10). My own probes and the one-off mutation to Api/CourseController.php were reverted; `git diff` on that file is empty.
Two infrastructure problems were found and **fixed during this phase**:

1. **The suite could not be run twice at once.** Two concurrent runs share `ebni_test` and
   `RefreshDatabase` drops the schema from under the other, so failure counts swung between 16 and
   80 across consecutive runs of unchanged code. This was observed live while a background review
   ran its own tests.
2. **`--parallel` had never worked.** Laravel gives each worker its own database, but the
   container's grant covered `ebni_test` alone. The grant is now part of the container's init, so
   it survives a recreate. The suite runs in about 5 seconds on four processes.
3. **Shared fixtures lived inside a test file.** Three invoice helpers were declared in
   `InvoiceTest.php` and used by three files. That works serially, where every file loads, and
   fails under `--parallel`, where each worker loads only its own — which is exactly how it was
   found. Shared fixtures now live in `tests/Pest.php`.

## Recommended order

1. **Done.** The 14 high-risk gaps are closed and verified by mutation.
2. **The three highest-value medium gaps are now closed too** (commit `cf96995`): B51 — the
   invoice-send endpoint had no test at all and now has three, verified by mutation; B122 — invoice
   and campaign audit entries are asserted, including that a correction keeps the old buyer name;
   B36 — an order now carries a `pricing_snapshot` of every term that produced its price, so
   deleting a coupon no longer erases why a paid order cost what it cost. That also closes the
   Phase 1 finding behind B36.
3. The remaining 30 medium gaps are listed above with the exact edit that would slip through each.
4. Put the suite in CI and make it block merges. It runs in five seconds; there is no reason not to.
5. Re-run this phase after any change to the access gates — those are where the leaks were.

## Not verified

- **Real concurrency.** Every race is reproduced by forcing the state, not by running two requests
  at once. The invoice sequence and coupon reservation tests prove the guard's logic, not that it
  holds under simultaneous load.
- **The front-end.** There is no JavaScript test runner in this project, so nothing in `web/src` is
  covered by an automated test. That is a deliberate omission to report, not an oversight: adding
  one is a decision about tooling the owner should make.
- **The 30 low-risk gaps** are recorded above and left open on purpose. Closing them would add tests
  faster than it would add protection.
