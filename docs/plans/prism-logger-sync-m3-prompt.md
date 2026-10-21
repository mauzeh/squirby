# Prism ↔ Logger Sync — Milestone 3 (The `/changes` delta pull) — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this milestone correctly.

### 1. Steering (project rules — always follow)
```
.kiro/steering/git-workflow.md          → commit freely, NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files to never touch, bash safety, artisan safety
.kiro/steering/project-conventions.md   → architectural principles; "a migration is not done until it RUN" (n/a here — no migration); Eloquent not DB::
.kiro/steering/laravel-boost.md         → Laravel conventions (constructor promotion, return types, PHPUnit, Pint policy)
.kiro/steering/sync-api-context.md      → sync endpoint inventory + design constraints for app/Sync/
```

### 2. Feature Plan (what to build)
```
docs/plans/prism-logger-sync-plan.md    → architecture reference. Implement the "M3 — The /changes delta pull" milestone, the GET /changes row in "Endpoints", the "Cursor" bullet, and the GET /changes block of "Wire shapes". M1 (schema) and M2 (push endpoints + throttles) are DONE. M4 (hardening) is OUT OF SCOPE.
```

### 3. Existing Code to Understand (read before modifying)
```
app/Sync/Controllers/ChangesController.php        → THE reference to mirror. Copy its cursor high-water-mark algorithm exactly: compute max updated_at and max deleted_at across the gym's rows, take the later of the two, and if `since` is later than both (or there are no rows) the cursor stays at `since` (never rewind). Note it validates `since` as a date and parses with Carbon. Your version is gym-keyed, not user-keyed, and returns Prism's row shapes instead of lift logs.
app/Sync/Models/PrismProgram.php, PrismProgramCell.php, PrismExerciseOverlayRecord.php, PrismPreference.php, Gym.php   → the M1 models you read from
app/Sync/Controllers/Concerns/ValidatesGym.php    → the M2 trait: findGymOrFail(string $gymId): Gym → abort(404,'Gym not found.') via centralized shaping. Reuse it; do NOT reimplement gym validation.
routes/sync.php                                   → the M2 Prism write group (throttle:prism-write). You ADD the /changes GET route under a NEW group using throttle:prism-poll (declared in M2, currently unused).
config/rate_limits.php / app/Providers/AppServiceProvider.php   → prism_poll (60/min) + the prism-poll limiter ALREADY EXIST from M2. Do NOT add them again; just reference the limiter in the route.
bootstrap/app.php                                 → centralized api/sync/* shaping: ValidationException(since not a date)→422, AuthenticationException→401, abort(404)→shaped. Do not hand-roll error bodies.
```

### 4. Reference (already implemented — don't rebuild, just understand)
```
tests/Feature/Sync/ChangesDeltaSyncTest.php       → THE test-pattern reference. Study these specifically:
  · test_since_omitted_returns_full_dump_and_cursor  → cold-start full-dump shape + cursor present
  · test_since_filters_live_logs_and_tombstones      → `since` filters; technique: create row, then LiftLog::where('id',…)->update(['updated_at' => $t]) to STAMP an explicit timestamp (do NOT rely on real wall-clock writes)
  · test_since_inclusive_boundary                    → a row stamped EXACTLY at `since` is returned (>= boundary). THIS is the technique for the microsecond regression test below.
  · test_cursor_computation                          → cursor = max(updated_at, deleted_at); a `since` later than all rows yields cursor == since (no rewind)
  · test_old_tombstone_forever_fix                   → old tombstones before `since` are NOT re-sent
tests/Feature/Sync/Prism/PrismExercisesStoreTest.php  → M2 test setup for Prism (Gym::create, Sanctum::actingAs, withHeaders X-Device-Id); reuse this scaffolding.
```

---

## What You're Building

Milestone 2 built the write path. This milestone builds the **read path**: the single `GET /api/sync/prism/{gym}/changes?since=<cursor>` endpoint that Prism polls (~every 5s) to pull everything that changed in its gym since the last cursor. This is the counterpart to Logger's existing Athlete `ChangesController`, re-keyed to the gym partition.

The endpoint returns, for one gym, all programs / cells / exercise-overlays / preferences whose `updated_at` is at or after the `since` cursor, plus a single `cursor` the client sends next time. Two rules are load-bearing and are the whole reason M1 used `timestamps(6)`:

1. **Inclusive boundary.** The filter is `updated_at >= since` (NOT strict `>`). A row stamped at the exact cursor instant is re-sent; that is harmless (the client de-dupes by value) and it is what prevents a second write landing in the same microsecond as the cursor from being silently skipped.
2. **Non-rewinding high-water-mark cursor.** The returned `cursor` is the max of (max `updated_at`, max `deleted_at`) across the gym's rows; if `since` is already later than everything (or the gym is empty), the cursor stays at `since`. Mirror `ChangesController`'s computation exactly.

Tombstones (removed exercise overlays, `deleted_at` non-null) MUST appear in the delta — the overlay model deliberately does NOT use the `SoftDeletes` trait (M1 decision) so the query is explicit and tombstones are not scope-hidden. Cold start (`since` absent) returns the full current gym state. No new tables, no new throttle, no write-path changes.

End state: a `PrismChangesController@index` under a new `throttle:prism-poll` route group, returning the exact wire shape below, with feature tests covering cold start, delta filtering, the inclusive-boundary microsecond regression, tombstone delivery, and the non-rewinding cursor.

---

## Execution Plan

### Phase 1 — Controller
Create `app/Sync/Controllers/PrismChangesController.php` (namespace `App\Sync\Controllers`), `use ValidatesGym`, explicit `JsonResponse` return type.

```php
public function index(Request $request, string $gym): JsonResponse
```

Logic, in order:
1. `$this->findGymOrFail($gym);` (reuse the M2 trait — 404 via centralized shaping).
2. Parse `since`: if `$request->filled('since')`, `$request->validate(['since' => 'date'])` then `$since = Carbon::parse($request->query('since'))`; else `$since = null` (cold start).
3. Query each store for the gym, applying `where('updated_at', '>=', $since)` ONLY when `$since !== null`:
   - `programs`: `PrismProgram::where('gym_id',$gym)` (+ since filter) → map to `{ program_uuid, name, week_count, metadata, updated_at }`.
   - `cells`: `PrismProgramCell::where('gym_id',$gym)` (+ since filter) → map to `{ program_uuid, cell_id, text, updated_at }`.
   - `exercises`: `PrismExerciseOverlayRecord::where('gym_id',$gym)` (+ since filter) → map to `{ exercise_id, record, deleted_at, updated_at }`. `record` is `null` for a tombstone; `deleted_at` is ISO8601 or null. **Do NOT filter out rows with a non-null `deleted_at`** — tombstones must be delivered. The since filter is on `updated_at` only (a removal bumped `updated_at` when the tombstone was written in M2).
   - `preferences`: `PrismPreference::where('gym_id',$gym)` (+ since filter) `->first()` → `{ data, updated_at }` or `null` if none (or none changed since `since`).
4. Compute the cursor via the high-water-mark algorithm (Phase 2).
5. Return `response()->json([...])` in the exact wire shape below.

Serialize every timestamp with `->toIso8601String()`. `week_count` is an int, `metadata`/`record`/`data` are arrays (model casts already hydrate them).

### Phase 2 — Cursor high-water-mark (mirror ChangesController)
Compute the max instant across ALL the gym's rows (NOT just the filtered delta — the cursor is the gym's true high-water mark, exactly as `ChangesController` uses `withTrashed()...max`):
- `maxUpdated` = max `updated_at` across the four tables for this gym.
- `maxDeleted` = max `deleted_at` across `prism_exercise_overlay` for this gym (the only table with `deleted_at`).
- `maxInstant` = the later of the two non-null values (or whichever is non-null, or null if the gym is empty).
- If `$since !== null` and (`maxInstant` is null OR `since` > `maxInstant`): `cursorInstant = since` (never rewind). Else `cursorInstant = maxInstant ?? now()`.
- `cursor = $cursorInstant->toIso8601String()`.

Use `Model::query()->where('gym_id',$gym)->max('updated_at')` per table and take the overall max in PHP (parse each non-null via `Carbon::parse`). Eloquent only — no `DB::`.

### Phase 3 — Route
Add a NEW group to `routes/sync.php` (append; do not touch M2's write group). Import `PrismChangesController`. The poll uses the dedicated `prism-poll` limiter (declared in M2):
```php
Route::middleware(['auth:sanctum', 'throttle:prism-poll', 'device-id', 'log-sync-request'])->group(function () {
    Route::get('/prism/{gym}/changes', [PrismChangesController::class, 'index']);
});
```

### Phase 4 — Checkpoint
`php artisan test --parallel tests/Feature/Sync/Prism/` once the tests below exist — all green before Phase 5.

### Phase 5 — Feature tests
Create `tests/Feature/Sync/Prism/PrismChangesTest.php` (namespace `Tests\Feature\Sync\Prism`, `RefreshDatabase`, seed a gym in `setUp`, `Sanctum::actingAs`). **Use the explicit-timestamp-stamping technique** from `ChangesDeltaSyncTest` — create the row, then `PrismProgramCell::where(...)->update(['updated_at' => $t])` to set a precise instant. Do NOT rely on real wall-clock ordering (tests run on in-memory SQLite, which does not reproduce MySQL microsecond behavior — stamping explicitly is what makes the assertion meaningful and deterministic).

Required tests:
1. `test_cold_start_returns_full_gym_state` — no `since`: seed a program, 2 cells, 1 overlay, preferences; assert all appear with the right shape and `cursor` is present. Assert `assertJsonStructure(['status','cursor','programs','cells','exercises','preferences'])`.
2. `test_since_filters_to_rows_at_or_after_cursor` — stamp some rows before `since` and some at/after; assert only at/after rows are returned across all four collections.
3. `test_inclusive_boundary_delivers_two_writes_at_same_instant` — **the microsecond regression test.** Create two cells, stamp BOTH with the identical `updated_at` instant `$t` (e.g. `Carbon::parse('2026-09-01T12:00:00.000001Z')`); request `?since=` that same `$t`; assert BOTH cells are returned (proves `>=`, not `>`). This is the test that would fail under a strict `>` boundary or second-resolution timestamps.
4. `test_tombstone_is_delivered_in_delta` — write an overlay, then a tombstone (set `deleted_at` + `record` null, bump `updated_at` to after `since`); request with `since` before the tombstone; assert the exercise appears with non-null `deleted_at` and null `record` (NOT scope-hidden).
5. `test_cursor_is_non_rewinding_high_water_mark` — mirror `test_cursor_computation`: cursor equals max(updated_at, deleted_at); a `since` later than all rows yields `cursor == since`.
6. `test_empty_gym_returns_empty_collections` — a seeded gym with no Prism rows: `programs/cells/exercises` empty arrays, `preferences` null, `cursor` present (== since if given, else now-ish).
7. `test_unknown_gym_returns_404` — `GET /prism/nonexistent/changes` → 404 `{status:error,message:'Gym not found.'}`.
8. `test_unauthenticated_returns_401` — no Sanctum → 401 `{status:error}`.
9. `test_invalid_since_returns_422` — `?since=not-a-date` → 422 (centralized validation shaping).

### Phase 6 — Final checkpoint
`php artisan test --parallel tests/Feature/Sync/Prism/` (green), then the full suite `php artisan test --parallel` to confirm existing web UI + Athlete sync unaffected.

---

## Wire shape (the exact response body — local source of truth)

```
GET /api/sync/prism/{gym}/changes?since=<iso8601|absent>  — 200 response:
{
  "status": "ok",
  "cursor": "<iso8601>",
  "programs":  [ { "program_uuid": string, "name": string, "week_count": int, "metadata": object|null, "updated_at": "<iso8601>" } ],
  "cells":     [ { "program_uuid": string, "cell_id": string, "text": string, "updated_at": "<iso8601>" } ],
  "exercises": [ { "exercise_id": string, "record": object|null, "deleted_at": "<iso8601>"|null, "updated_at": "<iso8601>" } ],
  "preferences": { "data": object, "updated_at": "<iso8601>" } | null
}
```

---

## HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`.

---

## Implementation Rules

- **All new code in `app/Sync/Controllers/`.** Route only in `routes/sync.php`. Nothing in `app/Http/`, `routes/api.php`, `routes/web.php`.
- **No new migration, model, throttle, config, or provider change.** M1/M2 built all of that. `prism-poll` already exists — reference it, don't redeclare it.
- **Reuse `ValidatesGym::findGymOrFail`** for the 404; reuse the M2 Prism test scaffolding.
- **Inclusive boundary: `updated_at >= since`**, never strict `>`.
- **Tombstones are delivered**, not filtered — the overlay query must return rows with non-null `deleted_at`. Never add the `SoftDeletes` trait.
- **Cursor never rewinds** — mirror `ChangesController` exactly.
- **Eloquent only — never `DB::`.** Constructor promotion + explicit return types on every method. PHPUnit only; `php artisan test --parallel`.
- **404/401/422 come from centralized shaping** — `abort()` / `auth:sanctum` / `$request->validate`. No hand-rolled error bodies.
- **No new composer dependencies.**

---

## Success Criteria

- [ ] `PrismChangesController@index(Request, string $gym): JsonResponse` exists, uses `ValidatesGym`, returns the exact wire shape above.
- [ ] `GET /prism/{gym}/changes` registered under a new `throttle:prism-poll` + `auth:sanctum` + `device-id` + `log-sync-request` group; M2's write group untouched.
- [ ] Cold start (`since` absent) returns the full gym state; `since` present filters to `updated_at >= since` across all four collections.
- [ ] **The inclusive-boundary regression test passes: two rows stamped at the identical instant as `since` are BOTH returned.**
- [ ] Tombstoned overlay rows appear in the delta with non-null `deleted_at` and null `record` (not scope-hidden).
- [ ] Cursor equals the non-rewinding max(updated_at, deleted_at) high-water mark; a `since` past all rows yields `cursor == since`.
- [ ] Unknown gym → 404; unauthenticated → 401; `since=not-a-date` → 422 — all via centralized shaping.
- [ ] No new migration/model/throttle; `prism-poll` referenced, not redeclared.
- [ ] `php artisan test --parallel tests/Feature/Sync/Prism/` green; full suite green; Athlete sync + web UI unaffected.
- [ ] No git commits; no Pint; no new composer dependencies.

---

## Do Not

- Do NOT commit or push.
- Do NOT run Pint.
- Do NOT run destructive database commands.
- Do NOT add a migration, model, throttle, config key, or provider registration — M1/M2 built them. Do NOT redeclare `prism-poll`.
- Do NOT use a strict `>` boundary — the filter is `>= since`.
- Do NOT filter out tombstones, and do NOT add the `SoftDeletes` trait to the overlay model.
- Do NOT rely on real wall-clock timestamps in tests — stamp `updated_at`/`deleted_at` explicitly (SQLite in tests will not reproduce MySQL microsecond behavior, so a real-time test can pass for the wrong reason).
- Do NOT hand-roll 404/401/422 bodies — use `abort()` / `auth:sanctum` / `$request->validate` + centralized shaping.
- Do NOT use the `DB::` facade.
- Do NOT read the Prism repo or the root `squirby/contracts/` — the wire shape above is this slice's local source of truth.
- Do NOT touch the write endpoints, actions, or requests from M2.

---

## Post-Execution Retro (authored by the REVIEWER, not the executor)

> The executor must NOT fill this in — leave the `{placeholder}` values untouched. After the run, the human reviewer reconstructs this section from the commit trail at archive time and moves both plan + prompt to `completed/`. See `.kiro/steering/architect-workflow.md` → Post-Execution Review Protocol.

- **Attempts:** {1 (clean) / N (root cause of failures)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {what to add/change}
- **Steering updates needed:** {yes/no, what}
