# Prism ↔ Logger Sync — Milestone 4 (Cost + hardening pass) — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this milestone correctly.

### 1. Steering (project rules — always follow)
```
.kiro/steering/git-workflow.md          → commit freely, NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files to never touch, bash safety, artisan safety
.kiro/steering/project-conventions.md   → "Storage is cheap; computation is expensive" (Forge Hobby shared box); Eloquent not DB:: in app code
.kiro/steering/laravel-boost.md         → Laravel conventions (constructor promotion, return types, PHPUnit, Pint policy)
.kiro/steering/sync-api-context.md      → sync endpoint inventory + design constraints for app/Sync/
```

### 2. Feature Plan (what to build)
```
docs/plans/prism-logger-sync-plan.md    → architecture reference. Implement the "M4 — Cost + hardening pass" milestone and the "Architecture → Guiding constraint" + "Risks → Cost / request-path" sections. M1 (schema), M2 (push), M3 (/changes) are DONE.
```

### 3. Existing Code to Understand (read before modifying)
```
app/Sync/Controllers/PrismChangesController.php   → the read path to assert is flat: 4 get() + 5 max() = 9 queries, independent of row count. This is the primary hardening target.
app/Sync/Actions/UpsertPrism*.php                 → the four write actions; confirm each is a single keyed upsert / updateOrCreate (plus one bounded re-query for the server timestamp), never a read-modify-write of a large document.
database/migrations/2026_10_20_2037*_*.php        → the M1 migrations; confirm the (gym_id, updated_at) composite index exists on prism_programs, prism_program_cells, prism_exercise_overlay. prism_preferences is a single row per gym (unique gym_id) — a point lookup, so it intentionally has NO (gym_id, updated_at) index.
app/Providers/AppServiceProvider.php              → NOTE: DB::enableQueryLog() is already enabled globally in boot(). A query-count test must DB::flushQueryLog() right before the measured request (or use a scoped DB::listen counter) so setup queries are not counted.
```

### 4. Reference (already implemented — don't rebuild, just understand)
```
tests/Feature/Sync/Prism/PrismChangesTest.php     → the M3 test scaffolding (Gym::create in setUp, Sanctum::actingAs, explicit timestamp stamping) to reuse for the new cost test.
```

---

## What You're Building

This is a **verification-and-hardening milestone**, not a feature milestone. M1–M3 built the gym schema, the write endpoints, and the `/changes` delta pull. This pass confirms the two load-bearing cost properties the design-of-record demands (every write is a single-row keyed upsert; every read is an indexed, flat-query range scan) are actually true and locks them in with a durable test so a future change cannot silently regress them. It also does a dead-code sweep across the four milestones and confirms the migrations have run.

Most of this milestone is **verification with a small amount of new test code** — the plan's indexes landed in M1 and the single-row-upsert writes landed in M2, so do NOT re-add them. The one genuinely new artifact is a query-count regression test on `/changes`: because the read path fans out across four tables, the risk is a future edit introducing an N+1 (e.g. a lazy-loaded relationship inside a map closure). The test asserts the endpoint issues a small, constant number of queries regardless of how many programs/cells/exercises the gym holds. If — and ONLY if — your investigation finds an actual N+1 or an unindexed scan in the current code, fix it; otherwise the code is already correct and you add only the guarding test.

End state: a passing query-count regression test proving `/changes` is flat under growing data; a written confirmation (in this prompt's retro, filled by the reviewer — you just make the findings true) that all writes are single upserts and all delta reads hit the `(gym_id, updated_at)` index or a point lookup; no dead code from M1–M3; migrations confirmed Ran; full suite green.

---

## Execution Plan

### Phase 1 — Audit the read path (investigate, change only if broken)
Read `PrismChangesController@index` and confirm:
- Exactly four `get()`/`first()` collection queries (programs, cells, exercises, preferences) and the cursor `max()` aggregates — no query issued inside a `->map()` closure, no lazy-loaded relationship, no per-row query.
- Each collection query filters `where('gym_id', $gym)` and (when `since` present) `where('updated_at', '>=', $since)` — i.e. it uses the `(gym_id, updated_at)` composite index on the three multi-row tables, and a `gym_id` point lookup on `prism_preferences`.

If the current code already satisfies both (it is expected to), make NO code change here — proceed to Phase 2. If you find an actual N+1 or an unindexed filter, fix it minimally (eager-load or restructure the query) and note what you changed; do NOT refactor beyond the defect.

### Phase 2 — Audit the write path (investigate, change only if broken)
Read the four `UpsertPrism*Action` classes and confirm each performs a single keyed `upsert()` / `updateOrCreate()` (cells and exercises may do one bounded follow-up `max('updated_at')` re-query — that is correct, it reads the server timestamp, not a document). Confirm none reads a large record, mutates it in PHP, and writes it back. Expected: already correct — make NO change unless you find a read-modify-write.

### Phase 3 — Query-count regression test (the one new artifact)
Add `test_changes_query_count_is_flat_under_growing_data` to `tests/Feature/Sync/Prism/PrismChangesTest.php`. It proves the `/changes` query count does not grow with row count:

1. Seed the gym with a SMALL dataset: 1 program, 2 cells, 2 exercise overlays, 1 preferences row. Hit `GET /prism/{gym}/changes` and capture the query count.
2. Seed a LARGE dataset in the same gym: e.g. 10 programs, 50 cells, 30 overlays. Hit `/changes` again and capture the query count.
3. Assert the two counts are EQUAL (the query count is constant, not proportional to rows) and small (assert `<= 12` as a generous ceiling — the controller issues ~9).

Measure queries deterministically despite the global query log:
```php
\Illuminate\Support\Facades\DB::flushQueryLog();   // clear setup/auth queries
$response = $this->withHeaders(['X-Device-Id' => 'dev-1'])->getJson('/api/sync/prism/the-cove/changes');
$count = count(\Illuminate\Support\Facades\DB::getQueryLog());
```
(Flush immediately before each measured request so auth/setup queries are excluded. Using `DB::` in a TEST for instrumentation is fine — the "no `DB::`" rule governs application code, not test assertions.) Assert both responses are 200 and the counts match.

### Phase 4 — Dead-code sweep
Grep the four milestones' surface for leftovers and remove any you find (expected: none):
- Unused imports, private methods, or variables in `app/Sync/Controllers/Prism*.php`, `app/Sync/Actions/UpsertPrism*.php`, `app/Sync/Requests/StorePrism*.php`, `app/Sync/Controllers/Concerns/ValidatesGym.php`.
- Commented-out code, `TODO`/`FIXME`, or backward-compat shims introduced during M1–M3.
- Any `prism_*` route, limiter, or config key declared but never consumed (note: `prism-poll` IS now consumed by M3's `/changes` route — that is correct, not dead).
Report what you found (even if "nothing"). Do NOT delete tests or touch non-Prism code.

### Phase 5 — Migration confirmation
Confirm the M1 migrations have run against the dev DB (per `project-conventions.md`, a migration is not done until it has RUN): run `php artisan migrate --no-interaction` (should report nothing to migrate) and `php artisan migrate:status` — the five `prism`/`gyms` migrations must all show **Ran**. Do NOT create or modify any migration.

### Phase 6 — Final checkpoint
`php artisan test --parallel tests/Feature/Sync/Prism/` (green), then the full suite `php artisan test --parallel` to confirm existing web UI + Athlete sync unaffected.

---

## HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`.

---

## Implementation Rules

- **This is a verification pass — prefer adding the guarding test over changing code.** Only change application code if Phase 1 or 2 finds a real defect (N+1, unindexed scan, read-modify-write). If you change code, keep it minimal and say what and why.
- **Do NOT add migrations, indexes, models, throttles, config keys, or endpoints.** M1–M3 built them. The `(gym_id, updated_at)` indexes already exist; `prism_preferences` intentionally has none (point lookup).
- **`DB::` is allowed in the test ONLY** for query-count instrumentation (`flushQueryLog`/`getQueryLog`). Application code stays Eloquent-only.
- **Constructor promotion + explicit return types** on anything you touch. PHPUnit only; `php artisan test --parallel`.
- **Do NOT delete or significantly alter existing tests.** Add the one new test method.
- **No new composer dependencies.**

---

## Success Criteria

- [ ] Read path confirmed flat: `/changes` issues a small constant number of queries (no query inside a map closure, no lazy relationship).
- [ ] Write path confirmed: each `UpsertPrism*Action` is a single keyed upsert/updateOrCreate (+ at most one bounded timestamp re-query), no read-modify-write.
- [ ] `test_changes_query_count_is_flat_under_growing_data` added and passing: query count is EQUAL between a small and a large dataset and `<= 12`.
- [ ] `(gym_id, updated_at)` composite index confirmed present on `prism_programs`, `prism_program_cells`, `prism_exercise_overlay`; `prism_preferences` confirmed as a point lookup (unique `gym_id`, no extra index needed).
- [ ] Dead-code sweep done across M1–M3 Prism surface; findings reported; nothing left unused (`prism-poll` is consumed by M3 — not dead).
- [ ] `php artisan migrate:status` shows the five gyms/prism migrations **Ran**.
- [ ] `php artisan test --parallel tests/Feature/Sync/Prism/` green; full suite green; Athlete sync + web UI unaffected.
- [ ] No git commits; no Pint; no new migrations/models/throttles; no new composer dependencies.

---

## Do Not

- Do NOT commit or push.
- Do NOT run Pint.
- Do NOT run destructive database commands.
- Do NOT add migrations, indexes, models, throttles, config keys, or endpoints — M1–M3 built them.
- Do NOT add a `(gym_id, updated_at)` index to `prism_preferences` — it is a single-row-per-gym point lookup by design.
- Do NOT refactor working code in the name of "hardening." Change application code ONLY to fix a real N+1 / unindexed scan / read-modify-write that you actually find.
- Do NOT use `DB::` in application code (test instrumentation only).
- Do NOT delete or significantly alter existing tests.
- Do NOT touch non-Prism code or the Athlete sync.
- Do NOT read the Prism repo or the root `squirby/contracts/`.

---

## Post-Execution Retro (authored by the REVIEWER, not the executor)

> The executor must NOT fill this in — leave the `{placeholder}` values untouched. After the run, the human reviewer reconstructs this section from the commit trail at archive time and moves both plan + prompt to `completed/`. See `.kiro/steering/architect-workflow.md` → Post-Execution Review Protocol. NOTE: M4 is the final milestone — once its retro is filled, archive ALL of M1–M4's plan + prompt files to `docs/plans/completed/`.

- **Attempts:** 1 (clean) — executor correctly treated this as a verification pass, found no defect, and added only the guarding test. No commits, no Pint.
- **Tests added:** 1 (`test_changes_query_count_is_flat_under_growing_data`): seeds a small then a large dataset into the same gym, flushes the query log before each measured `/changes` request, and asserts the counts are equal and <= 12. Verified passing — proving the read path issues a constant query count (no N+1) as data grows. Full suite 2226 → 2227.
- **Code changes beyond the test:** None. Phase 1/2 audit confirmed the read path is already flat (4 collection queries + 5 cursor aggregates, nothing in map closures, no lazy relationships) and every write action is a single keyed upsert/updateOrCreate (+ one bounded timestamp re-query for cells/exercises). The `(gym_id, updated_at)` indexes were already present from M1; `prism_preferences` is correctly a single-row point lookup. Dead-code sweep found nothing; `prism-poll` is consumed by M3's route. Migrations confirmed Ran.
- **Prompt improvements for next time:** None material. The key framing that made this milestone substantive rather than a no-op was stating up front that the indexes/upserts already landed in M1/M2 and directing the executor to ADD the guarding query-count test rather than re-add infrastructure — plus the explicit `DB::flushQueryLog()` technique (there was no existing query-count test precedent in the suite to copy). Both worked. For future hardening milestones, pre-identifying "what's already done vs. what's genuinely new" prevents a hollow pass.
- **Steering updates needed:** No. The query-count-regression pattern is now in the suite as a reusable precedent; no steering change required.
