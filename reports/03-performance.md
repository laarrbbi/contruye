# Benchmarks and Optimization — Ebni Academy

**Date:** 2026-08-27
**Scope:** `api/` at commit `4689730` (branch `main`), measured against a purpose-seeded database
**Phase:** 3 of 4 — Performance

> **Status, 2026-10-04:** the tables below were brought up to date by the go-live pass. ✅ = fixed and covered by a test; *decided* = deliberately left as it is, owner's call; *mitigated* = the harm is gone though the mechanism remains. What was done, and how it was verified: [`06-go-live-pass.md`](06-go-live-pass.md).

## Summary

Twelve product-carrying endpoints were benchmarked against a database seeded to ten times the
expected first-year volume (20 005 users, 275 000 progress rows, 156 643 checklist ticks). Eleven
of them answer in under 13 ms. One did not: the admin dashboard at **341 ms p50 / 517 ms p99**,
because it pulled every enrollment and every completion group into PHP and looped over them.

Two fixes were applied, both from the cheapest rung of the ladder — no cache, no new service, no
index. The dashboard is now **142.7 ms p50 (−58 %)** and the catalog **4.8 ms (−53 %)** with its
query count cut from 7 to 2 and made constant in catalog size. Peak request memory fell from
94 MB to 44 MB. Every optimized endpoint was verified to return byte-identical values.

Counts by severity: Critical 0 · High 1 · Medium 2 · Low 2

## Scope and method

- **Read:** every controller under `api/app/Http/Controllers`, `PricingService`, the Eloquent
  models carrying query logic, and all 20 migrations.
- **Ran:**
  - `php artisan db:seed --class=BenchmarkSeeder` (new, committed) against a throwaway `ebni_bench`
    database — never against dev or demo data.
  - `ANALYZE TABLE` on all 12 measured tables before every run, so the planner had fresh statistics.
  - `php artisan bench:endpoints --runs=25` (new, committed) — 25 runs per endpoint, in-process,
    with the query log enabled; reports p50/p95/p99, query count, payload size and peak RSS.
  - `EXPLAIN ANALYZE` on the heavy aggregate, pasted verbatim below.
  - `php artisan test` (231 tests) after every change.
- **Environment:** Apple M5, 10 cores, macOS 25.5; PHP 8.3.32 and MySQL 8.0.46, both in Docker.
- **System state before the run:** load average 3.53 (1 min) on 10 cores, ~4 GB free; fifteen
  containers up, including four unrelated projects. MySQL at 2.35 % CPU / 448 MB before the run.
- **Could not verify:** absolute latency on production hardware — these numbers come from a shared
  laptop and are only meaningful **relative to each other and before/after**. Query counts and
  row counts are hardware-independent and are the numbers to trust. No load/concurrency test was
  run: every measurement is single-request.

## Seeded volume

| Table | Rows | Rationale |
|---|---|---|
| `users` | 20 005 | 10× the expected first-year cohort |
| `orders` | 13 006 | ~65 % of students buy; 1 in 11 left `awaiting_review` |
| `enrollments` | 26 366 | mix of full-access (1 row) and per-module buyers (3 rows) |
| `lesson_progress` | 275 000 | long tail: half drop out in module 1, a quarter finish everything |
| `checklist_item_user` | 156 643 | a third of students tick 10–40 steps |

## Baseline and result

| Endpoint | Queries before → after | p50 ms before → after | p99 ms before → after |
|---|---|---|---|
| `GET /admin/stats` | 12 → **10** | 341.3 → **142.7** | 517.4 → **224.9** |
| `GET /courses` | 7 → **2** | 10.2 → **4.8** | 54.7 → **20.3** |
| `GET /courses/{slug}` | 9 | 11.6 → 10.1 | 15.1 → 23.6 |
| `GET /learn` (full access) | 10 | 5.8 → 5.0 | 9.3 → 9.8 |
| `GET /learn` (partial owner) | 10 | 4.8 → 4.4 | 5.8 → 6.0 |
| `GET /checklists` | 8 | 3.9 → 3.7 | 6.3 → 7.4 |
| `GET /orders` | 4 | 1.5 → 1.0 | 7.0 → 5.9 |
| `GET /admin/orders` (page 1) | 4 | 7.6 → 7.2 | 10.2 → 12.7 |
| `GET /admin/orders` (page 50) | 4 | 8.7 → 7.1 | 10.5 → 7.6 |
| `GET /admin/students` | 2 | 9.0 → 7.9 | 11.4 → 11.9 |
| `GET /wilayas` | 7 | 3.1 | 11.7 → 16.7 |
| **Peak request memory** | — | **94 MB → 44 MB** | — |

Sub-millisecond differences on the unchanged rows are run-to-run noise on a loaded laptop, not signal.

## Findings

| ID | Severity | Effort | Finding | Location |
|----|----------|--------|---------|----------|
| P1 | High | M | Dashboard completion rate aggregated in PHP over 26k + 13k rows ✅ | `Api/Admin/StatsController.php:57` |
| P2 | Medium | S | Catalog ran 3 aggregate queries **per course** plus 2 redundant campaign lookups ✅ | `Api/CourseController.php:14` |
| P3 | Medium | S | `Course::activeCampaign()` re-queried despite the relation being eager-loaded ✅ | `Models/Course.php:72` |
| P4 | Low | S | Offset pagination on admin collections will degrade at depth | `Api/Admin/OrderController.php` |
| P5 | Low | — | Candidate index on `lesson_progress` measured: **no gain**, not added | — |

## Details

### P1 — Dashboard completion rate computed in PHP  ✅ fixed

**Severity:** High · **Effort:** M · **Location:** `app/Http/Controllers/Api/Admin/StatsController.php:57`

**Evidence.** `completionRate()` issued a `GROUP BY user_id, course_id` over `lesson_progress`
joined to lessons and modules, hydrated all 13 334 result rows into Eloquent models, hydrated all
26 366 enrollments into models, and then looped in PHP. `EXPLAIN ANALYZE` on the aggregate alone:

```
-> Table scan on <temporary>  (actual time=363..364 rows=13334 loops=1)
    -> Aggregate using temporary table  (actual time=363..363 rows=13334 loops=1)
        -> Nested loop inner join  (cost=56893 rows=254236) (actual time=3.78..306 rows=255000 loops=1)
```

255 000 rows scanned into a temporary table, 364 ms, before PHP had done anything at all.

**Impact.** The admin dashboard was the slowest surface in the product by a factor of thirty, and
its cost grew linearly with every lesson any student ever watched.

**Fix applied.** The whole computation moved into one SQL statement (two derived tables joined to
`enrollments`, `SUM(LEAST(1, done / total))`), preserving the previous semantics exactly — including
that a student who bought three modules separately still weighs three times. Verified: old and new
both return **37 %** on the seeded data.

Result: 341.3 → 142.7 ms p50, 517.4 → 224.9 ms p99, 12 → 10 queries, peak memory 94 → 44 MB.

**Remaining headroom, not taken.** The residual ~140 ms is the aggregate itself, which genuinely
must touch every completed progress row. The next rung would be a short-TTL cache (Redis is already
in the stack, so no new infrastructure). It was **not** applied: 143 ms on an admin-only page viewed
a handful of times a day does not justify the staleness. Revisit when `lesson_progress` passes
roughly one million rows — at that point expect ~500 ms and cache with a 5-minute TTL.

### P2 — Catalog aggregates ran per course  ✅ fixed

**Severity:** Medium · **Effort:** S · **Location:** `app/Http/Controllers/Api/CourseController.php:14`

**Evidence.** The query log for `GET /courses` with a single published course showed seven queries:
one for the courses, one eager-loading campaigns, then a distinct-enrollment count, a review count,
a review average, and the campaign lookup **twice more** — because `attachSocialProof()` set the
campaign and `index()` then set it again.

**Impact.** Invisible today with one course, but the cost is 3 queries + 2 campaign lookups *per
course*: a ten-course catalog would have issued 50+ queries for one page.

**Fix applied.** The three aggregates became correlated subqueries on the single courses query, and
the duplicate campaign assignment was removed. Verified identical: `students_count` = 11 820 from
both the subquery and the original expression.

Result: 7 → 2 queries, and now **constant** in catalog size; 10.2 → 4.8 ms p50.

### P3 — `activeCampaign()` ignored the eager-loaded relation  ✅ fixed

**Severity:** Medium · **Effort:** S · **Location:** `app/Models/Course.php:72`

**Evidence.** `with('campaigns')` loaded the relation, then `activeCampaign()` called
`$this->campaigns()->active()->first()` — a fresh query, making the eager load pure waste.

**Fix applied.** When the relation is already loaded, the same rule is resolved in memory via
`Campaign::isRunning()`, which mirrors `scopeActive()` term for term. The un-loaded path is
unchanged, so `show()` and every other caller behave as before.

> Noted for Phase 1: `Campaign::scopeActive()` and `Campaign::isRunning()` express the same rule
> twice. They are adjacent and both covered by tests, but they can drift.

### P4 — Offset pagination will degrade at depth

**Severity:** Low · **Effort:** M · **Location:** `Api/Admin/OrderController.php`, `StudentController`, `ReviewController`, `InvoiceController`

**Evidence.** Measured at 13 006 orders: page 1 and page 50 are indistinguishable (7.2 vs 7.1 ms
p50). Offset cost grows with page depth, so this is not yet a problem — but it is the strategy that
stops working first as the table grows.

**Fix (not applied).** Keyset pagination on `(created_at, id)` for the admin order list. Deferred
deliberately: at current and 10× volume the measurement says there is nothing to win, and keyset
pagination costs the "jump to page N" affordance the admin UI currently offers.

**Verify with:** `php artisan bench:endpoints` after seeding `orders` to ~500 000 rows; act when
page-50 p50 exceeds page-1 p50 by more than 2×.

### P5 — Candidate index measured and rejected

**Severity:** Low · **Location:** `lesson_progress`

A covering index `(completed_at, user_id, lesson_id)` was created and `ANALYZE`d to try to speed up
the completion aggregate. The plan did **not** change: the optimizer still drove the join from
`modules → lessons → lesson_progress` and still touched all 255 000 rows, because every completed
row genuinely participates in the result. Measured gain: none. The index was **dropped** — an
unused index is a permanent write-throughput cost for no read benefit.

This is recorded because "we tried an index and it did not help" is exactly the finding that
otherwise gets re-tried every six months.

## Recommended order

1. **Nothing urgent remains.** P1–P3 are applied and verified; the product's hot paths are 1–10 ms
   at 10× expected volume.
2. When `lesson_progress` passes ~1 M rows, cache the dashboard completion rate (5-minute TTL).
3. When `orders` passes ~500 k rows, revisit keyset pagination for the admin list (P4).
4. Re-run `php artisan bench:endpoints` after any change to the player or catalog serializers —
   the query-count column is the regression alarm, and it is hardware-independent.

## Not verified

- **Production hardware and concurrency.** All numbers are single-request on a shared laptop with
  fourteen other containers running. Treat the *relative* improvements and the *query counts* as
  the findings; treat the absolute milliseconds as indicative only.
- **The front-end bundle.** This phase measured the API only. The SPA's bundle size and the 3 MB
  hero video were raised in an earlier UI/UX audit and are untouched here.
- **Chargily, Stripe, Bunny and SMTP latency.** All external calls were stubbed; their timeout and
  retry behaviour is a Phase 1 correctness concern and is reported there.
- **`GET /admin/partners`** measured 1 query / 0 KB because the benchmark database has no partners
  seeded (partners come from the opt-in demo seeder). Not a defect; simply unmeasured.
