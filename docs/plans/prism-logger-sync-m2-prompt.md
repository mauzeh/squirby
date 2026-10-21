# Prism ↔ Logger Sync — Milestone 2 (Push endpoints + actions + Prism throttles) — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this milestone correctly.

### 1. Steering (project rules — always follow)
```
.kiro/steering/git-workflow.md          → commit freely, NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files to never touch, bash safety, artisan safety
.kiro/steering/project-conventions.md   → architectural principles; dispatch events don't inline; domain folders; Eloquent not DB::
.kiro/steering/laravel-boost.md         → Laravel conventions (Form Requests, constructor promotion, return types, PHPUnit, Pint policy)
.kiro/steering/sync-api-context.md      → sync endpoint inventory + design constraints for app/Sync/
```

### 2. Feature Plan (what to build)
```
docs/plans/prism-logger-sync-plan.md    → architecture reference. Implement the "M2 — Push endpoints + actions + Prism throttles" milestone and the "Endpoints", "Wire shapes", and "Rate limits" sections. M1 (schema + models) is DONE. M3 (/changes GET) and M4 (hardening) are OUT OF SCOPE for this prompt.
```

### 3. Existing Code to Understand (read before modifying)
```
app/Sync/Models/PrismProgram.php, PrismProgramCell.php, PrismExerciseOverlayRecord.php, PrismPreference.php, Gym.php
                                        → the M1 models you will write to (fillable/casts already set)
app/Sync/Controllers/PreferencesController.php   → the whole-value upsert controller pattern to mirror (updateOrCreate, device_id from request attributes, { "status": "ok" } response)
app/Sync/Controllers/BlueprintController.php     → second mirror of the same store() pattern
app/Sync/Controllers/ExerciseController.php      → closest precedent for $request->validate() in a sync controller returning shaped JSON + 201/200
app/Sync/Middleware/EnsureDeviceId.php           → device id arrives as header X-Device-Id and is placed in $request->attributes->get('device_id')
routes/sync.php                                  → the auth:sanctum + device-id + log-sync-request middleware group you will extend; existing throttle usage (throttle:sync-per-user etc.)
config/rate_limits.php                           → declarative limiter thresholds; you ADD two keys here
app/Providers/AppServiceProvider.php             → RateLimiter::for(...) registrations reading config via buildLimits(); you ADD two limiters here
bootstrap/app.php                                → the centralized api/sync/* error shaping: ValidationException→422, AuthenticationException→401, HttpException 404→{status:error,message}, 429→shaped. abort(404) is ALREADY shaped here — do not build your own 404 body.
```

### 4. Reference (already implemented — don't rebuild, just understand)
```
app/Sync/Actions/StoreSyncLogAction.php          → the canonical app/Sync Action shape to mirror: constructor-promoted deps, single execute(User, array, ?string) method, returns the persisted model; shows the existing multi-row write pattern (loop creating child rows) and a server-timestamp touch() to advance the pull cursor. Your multi-row actions (cells, exercises) follow this shape but use keyed upsert() + re-query instead of per-row create().
tests/Feature/Sync/TelemetryTest.php             → feature-test pattern: withHeaders(['X-Device-Id'=>...])->postJson('/api/sync/...'), Sanctum::actingAs, assertDatabaseCount, 422 assertion
tests/Feature/Sync/AuthUpgradeTest.php §test_email_check_rate_limited  → throttle test pattern using $this->travel(N)->seconds() to drive a paced cadence and assert 200s then 429
```

---

## What You're Building

Milestone 1 built the gym partition and four granular Prism stores (models + migrations, already run). This milestone builds the **write path**: the four POST endpoints Prism calls to push changes, the actions behind them, and two dedicated rate limiters sized for Prism's ~5-second poll cadence. No `/changes` GET pull yet — that is Milestone 3.

Every endpoint follows one uniform shape: validate the body with a Form Request, confirm the `{gym}` path segment names a gym that exists (404 via the centralized shaping if not), perform a single keyed upsert through an Action class, and return `{ "status": "ok", "updated_at": <iso8601> }` where `updated_at` is the server-stamped max timestamp of the rows written. Writes are small and bounded (a handful of changed cells/records), each a single `upsert()` call — never a read-modify-write of a large document. Auth is `auth:sanctum` on every route; Phase-1 trust-on-login means any authenticated user may write any existing gym (no membership check).

End state: four controllers under `app/Sync/Controllers/`, their upsert actions under `app/Sync/Actions/`, Form Requests under `app/Sync/Requests/`, five routes registered in `routes/sync.php` under a Prism-dedicated group with the new `prism-write` throttle, two new limiters (`prism-write`, `prism-poll`) declared in `config/rate_limits.php` and registered in `AppServiceProvider`, and feature tests proving upsert-creates / re-upsert-updates / tombstone / unknown-gym-404 / unauthenticated-401 / validation-422, plus a throttle test proving a sustained 5s poll cadence does NOT 429 under `prism-poll`.

> Note: `prism-poll` is the limiter for the Milestone 3 `/changes` GET. It is declared and registered in THIS milestone (so the config/provider work is done once) and its headroom is proven by a throttle test here, but no route uses it yet. The `/changes` route is added in M3.

---

## Execution Plan

### Phase 1 — Form Requests (validation)
Create `app/Sync/Requests/` (new folder, namespace `App\Sync\Requests`). One FormRequest per endpoint, `authorize(): bool` returns `true` (auth is handled by `auth:sanctum`; Phase-1 trust-on-login), explicit `rules(): array`. Match this app's array-based validation-rule convention (see `ExerciseController`'s inline rules for the vocabulary). Exact rules:

```
StorePrismProgramRequest:
  'name'        => ['required', 'string', 'max:255'],
  'week_count'  => ['required', 'integer', 'min:0', 'max:65535'],
  'metadata'    => ['nullable', 'array'],
  'device_id'   => ['nullable', 'string', 'max:36'],

StorePrismCellsRequest:
  'cells'            => ['required', 'array', 'min:1'],
  'cells.*.cell_id'  => ['required', 'string', 'max:16'],
  'cells.*.text'     => ['present', 'string'],          // '' is valid — an emptied cell
  'device_id'        => ['nullable', 'string', 'max:36'],

StorePrismExercisesRequest:
  'upserts'               => ['present', 'array'],
  'upserts.*.exercise_id' => ['required', 'string', 'max:255'],
  'upserts.*.record'      => ['required', 'array'],
  'removals'              => ['present', 'array'],
  'removals.*.exercise_id'=> ['required', 'string', 'max:255'],
  'device_id'             => ['nullable', 'string', 'max:36'],

StorePrismPreferencesRequest:
  'data'       => ['required', 'array'],
  'device_id'  => ['nullable', 'string', 'max:36'],
```

### Phase 2 — Actions (single keyed upserts)
Create under `app/Sync/Actions/` (namespace `App\Sync\Actions`), each a class with a single `execute(...)` method, constructor promotion where a dependency is needed (none required here), explicit return types. Each returns the server-stamped `updated_at` (a `\Illuminate\Support\Carbon`) that the controller serializes. Use Eloquent `upsert()` / `updateOrCreate()` — never `DB::`.

- `UpsertPrismProgramAction::execute(string $gymId, string $programUuid, array $attributes): Carbon`
  — `PrismProgram::updateOrCreate(['gym_id'=>$gymId,'program_uuid'=>$programUuid], ['name'=>…,'week_count'=>…,'metadata'=>…,'device_id'=>…])`; return the row's `updated_at`.
- `UpsertPrismCellsAction::execute(string $gymId, string $programUuid, array $cells, ?string $deviceId): Carbon`
  — build the upsert rows (each with `gym_id`, `program_uuid`, `cell_id`, `text`, `device_id`), call `PrismProgramCell::upsert($rows, ['gym_id','program_uuid','cell_id'], ['text','device_id'])`, then re-query the affected cells and return the MAX `updated_at` across them. (Eloquent `upsert()` does not stamp `updated_at` into the in-memory models, so re-query to get the server value.)
- `UpsertPrismExercisesAction::execute(string $gymId, array $upserts, array $removals, ?string $deviceId): Carbon`
  — upsert the `upserts` rows (`deleted_at => null` on each, so a re-added exercise clears a prior tombstone) keyed `['gym_id','exercise_id']` updating `['record','device_id','deleted_at']`; for each `removals` entry, upsert a tombstone row setting `deleted_at => now()` and `record => null`. Return the MAX `updated_at`/`deleted_at` across all rows touched. If both arrays are empty the request is still valid — return `now()`.
- `UpsertPrismPreferencesAction::execute(string $gymId, array $data, ?string $deviceId): Carbon`
  — `PrismPreference::updateOrCreate(['gym_id'=>$gymId], ['data'=>$data,'device_id'=>$deviceId])`; return `updated_at`.

### Phase 3 — Controllers
Create under `app/Sync/Controllers/` (namespace `App\Sync\Controllers`), constructor-promoted action dependencies, explicit `JsonResponse` return types. Each action method: resolve + validate the gym, read `device_id` from `$request->attributes->get('device_id')` (set by `EnsureDeviceId` from the header; the body `device_id` field is accepted for validation parity but the header is authoritative — mirror `BlueprintController`), call the action, return `response()->json(['status'=>'ok','updated_at'=>$updatedAt->toIso8601String()])`.

Gym validation helper (shared): resolve `Gym::find($gym)`; if null, `abort(404, 'Gym not found.')` — the centralized shaping in `bootstrap/app.php` turns that into `{ "status":"error","message":"Gym not found." }` with a 404. Put this in a small private method or a thin `Concerns` trait under `app/Sync/Controllers/Concerns/` — pick one and apply it uniformly across the three controllers.

- `PrismProgramController@storeProgram(StorePrismProgramRequest $request, string $gym, string $program_uuid): JsonResponse`
- `PrismProgramController@storeCells(StorePrismCellsRequest $request, string $gym, string $program_uuid): JsonResponse`
- `PrismExerciseController@store(StorePrismExercisesRequest $request, string $gym): JsonResponse`
- `PrismPreferencesController@store(StorePrismPreferencesRequest $request, string $gym): JsonResponse`

### Phase 4 — Throttles (config + provider)
Add two keys to `config/rate_limits.php` with explanatory comments matching the file's existing comment style:
```
'prism_write' => [ 'per_minute' => 120, 'per_hour' => null ],   // generous write ceiling; cheap keyed upserts
'prism_poll'  => [ 'per_minute' => 60,  'per_hour' => null ],   // ~5s poll = ~12/min per tab; 60 gives headroom for multiple tabs. Used by M3 /changes.
```
Register two limiters in `AppServiceProvider::boot()` next to the other sync limiters, keyed per authenticated user, using the existing `buildLimits()` helper:
```
RateLimiter::for('prism-write', fn (Request $request) => $this->buildLimits('prism_write', $request->user()?->id ?: $request->ip()));
RateLimiter::for('prism-poll',  fn (Request $request) => $this->buildLimits('prism_poll',  $request->user()?->id ?: $request->ip()));
```

### Phase 5 — Routes
Add a new middleware group to `routes/sync.php` (append; do NOT alter the existing groups). Import the three controllers at the top. The Prism write endpoints must NOT reuse the Athlete `sync-per-user` group (10/min would 429 a normal session):
```
Route::middleware(['auth:sanctum', 'throttle:prism-write', 'device-id', 'log-sync-request'])->group(function () {
    Route::post('/prism/{gym}/programs/{program_uuid}', [PrismProgramController::class, 'storeProgram']);
    Route::post('/prism/{gym}/programs/{program_uuid}/cells', [PrismProgramController::class, 'storeCells']);
    Route::post('/prism/{gym}/exercises', [PrismExerciseController::class, 'store']);
    Route::post('/prism/{gym}/preferences', [PrismPreferencesController::class, 'store']);
});
```
(The `/prism/{gym}/changes` GET under `throttle:prism-poll` is added in M3 — do NOT add it now.)

### Phase 6 — Checkpoint
Run `php artisan test --parallel tests/Feature/Sync/Prism/` once the tests below exist and are green, then continue to Phase 7 only if green.

### Phase 7 — Feature tests
Create under `tests/Feature/Sync/Prism/` (namespace `Tests\Feature\Sync\Prism`), using `RefreshDatabase`, `Sanctum::actingAs($user)`, and `withHeaders(['X-Device-Id' => ...])`. Seed a gym per test (`Gym::create(['id'=>'the-cove','name'=>'The Cove'])`). One file per endpoint plus one throttle file:

- `PrismProgramStoreTest.php` — program upsert creates a row (200, `status:ok`, `updated_at` present & ISO8601); re-posting the same `program_uuid` updates in place (still 1 row, fields changed); unknown gym → 404 `{status:error}`; unauthenticated (no Sanctum) → 401; invalid body (missing `name`) → 422.
- `PrismCellsStoreTest.php` — posting N cells creates N rows; re-posting one cell with new `text` updates in place (count unchanged); `text:''` is accepted (emptied cell); unknown gym → 404; unauthenticated → 401; empty `cells` array → 422.
- `PrismExercisesStoreTest.php` — `upserts` creates overlay rows with `deleted_at` null; a `removals` entry writes a tombstone (`deleted_at` non-null, `record` null); re-upserting a tombstoned exercise clears `deleted_at`; unknown gym → 404; unauthenticated → 401.
- `PrismPreferencesStoreTest.php` — first post creates the single gym preferences row; second post overwrites `data` in place (still 1 row for the gym); unknown gym → 404; unauthenticated → 401; missing `data` → 422.
- `PrismWriteThrottleTest.php` — assert the write endpoints are NOT on the 10/min `sync-per-user` bucket: drive ~20 writes across paced `$this->travel()` steps simulating a realistic cadence and assert none return 429 under `prism-write`. Also add a focused `prism-poll` headroom assertion: register a temporary route is NOT needed — instead assert the limiter config value (`config('rate_limits.prism_poll.per_minute')` >= 60) so M3's poll inherits the proven ceiling. (Keep the throttle test deterministic; prefer asserting the config + a bounded burst over flaky time-based 429 hunting.)

### Phase 8 — Final checkpoint
`php artisan test --parallel tests/Feature/Sync/Prism/` (all green), then the full suite `php artisan test --parallel` to confirm existing web UI + Athlete sync are unaffected.

---

## HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`.

---

## Implementation Rules

- **All new code in `app/Sync/`** — `Controllers/`, `Controllers/Concerns/`, `Actions/`, `Requests/`. Routes only in `routes/sync.php`. Nothing in `app/Http/`, `routes/api.php`, or `routes/web.php`.
- **No schema changes.** M1 built the tables; this milestone writes to them only. Do not add migrations.
- **device_id is authoritative from the header** (`$request->attributes->get('device_id')`), mirroring `BlueprintController`; the body `device_id` exists for validation parity only.
- **Every write is a single keyed upsert** — `updateOrCreate` / `upsert`. Never read-modify-write a large document. Never `DB::`.
- **404 / 401 / 422 come from the centralized shaping in `bootstrap/app.php`** — use `abort(404, '…')`, `auth:sanctum`, and FormRequest validation. Do NOT hand-roll error bodies.
- **Use PHP 8 constructor promotion** and **explicit return types** on every new method.
- **PHPUnit only** (no Pest). Always `php artisan test --parallel`.
- **No new composer dependencies.**
- **Do not modify the existing `routes/sync.php` groups** — append a new group. Do not change existing limiters in `config/rate_limits.php`/`AppServiceProvider` — append new ones.

---

## Success Criteria

- [ ] Four Form Requests in `app/Sync/Requests/` with the exact rules above; `authorize()` returns true.
- [ ] Four upsert actions in `app/Sync/Actions/`, each a single keyed upsert returning the server-stamped `updated_at`.
- [ ] Three controllers (`PrismProgramController` with storeProgram+storeCells, `PrismExerciseController`, `PrismPreferencesController`), constructor-promoted actions, `JsonResponse` returns, shared gym-404 helper.
- [ ] Five POST routes registered under a new `throttle:prism-write` + `auth:sanctum` + `device-id` + `log-sync-request` group in `routes/sync.php` (the four write routes; no `/changes` yet).
- [ ] `prism_write` (120/min) and `prism_poll` (≥60/min) keys in `config/rate_limits.php`; `prism-write` and `prism-poll` limiters registered in `AppServiceProvider` via `buildLimits`, keyed per user.
- [ ] Each endpoint: upsert creates; re-upsert updates in place (no dupes); exercise removal writes a `deleted_at` tombstone; re-upsert clears it.
- [ ] Response body is `{ "status":"ok", "updated_at": <iso8601> }` on success.
- [ ] Unknown gym → 404 `{status:error}`; unauthenticated → 401; validation failure → 422 — all via centralized shaping.
- [ ] A sustained write cadence does not 429 under `prism-write`; `config('rate_limits.prism_poll.per_minute') >= 60` asserted.
- [ ] `php artisan test --parallel tests/Feature/Sync/Prism/` green; full suite green; Athlete sync + web UI unaffected.
- [ ] No git commits; no Pint; no new composer dependencies; no schema migrations.

---

## Do Not

- Do NOT commit or push.
- Do NOT run Pint.
- Do NOT run destructive database commands.
- Do NOT add the `/prism/{gym}/changes` GET route, the `PrismChangesController`, or any delta-pull logic — that is Milestone 3.
- Do NOT reuse the Athlete `sync-per-user` throttle for the Prism write routes (its 10/min cap would 429 a normal session).
- Do NOT hand-roll 404/401/422 JSON bodies — rely on `abort()` / `auth:sanctum` / FormRequest + the centralized `api/sync/*` shaping.
- Do NOT read-modify-write a whole document; every write is a single keyed upsert.
- Do NOT use the `DB::` facade.
- Do NOT add migrations or touch M1 schema/models beyond writing rows.
- Do NOT read the Prism repo or the root `squirby/contracts/` — the wire shapes in the plan are this slice's local source of truth.

---

## Post-Execution Retro (authored by the REVIEWER, not the executor)

> The executor must NOT fill this in — leave the `{placeholder}` values untouched. After the run, the human reviewer reconstructs this section from the commit trail at archive time and moves both plan + prompt to `completed/`. See `.kiro/steering/architect-workflow.md` → Post-Execution Review Protocol.

- **Attempts:** {1 (clean) / N (root cause of failures)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {what to add/change}
- **Steering updates needed:** {yes/no, what}
