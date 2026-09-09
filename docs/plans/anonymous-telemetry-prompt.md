# Anonymous Screen-View Telemetry — Prompt for Antigravity CLI (Logger slice)

## Global Execution Rules
1. Execute sequentially; test ONLY at the Milestone checkpoints.
2. SELF-CORRECTION LOOP: run the checkpoint; if tests fail, read `.test-output.txt`, fix, re-run within the
   turn. Do NOT yield with a failing milestone; do NOT ask for help — fix it.
3. When ALL milestones pass AND the cleanup sweep is clean, print exactly:
   ```
   AGY_COMPLETE: All milestones passed.
   ```
   Do NOT write the Post-Execution Retro (reviewer-authored).
4. Test output: `php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt`. Read the
   file; never re-run just to re-see output. Never `/tmp/`. Delete `.test-output.txt` at the end.

---

## Before You Start (read in order)

### 1. Steering (project rules — always follow)
```
docs/antigravity-steering.md             → git (NEVER commit), Pint BAN, §6 DB rules (nullable + $fillable + cast), §13 Consumer Impact Trace, §15 decomposition, milestones, AGY_COMPLETE
.kiro/steering/safe-operations.md        → files never to edit, bash safety, artisan safety, Pint ban
.kiro/steering/project-conventions.md    → forward-only migrations, no column repurposing, soft deletes
.kiro/steering/sync-api-context.md       → sync API architecture, app/Sync/ layout, data model
.kiro/steering/laravel-boost.md          → Eloquent not DB::, constructor promotion, explicit return types, PHPUnit-only, config() not env()
```

### 2. Plan (reference — execute from THIS prompt)
```
docs/plans/anonymous-telemetry.md        → this slice (WHAT/WHY, Entity Default, phases, §13 trace, frozen shapes duplicated inline)
```
(The root cross-repo spine `../../docs/plans/anonymous-telemetry-cross-repo.md` is the authoring source of
truth, but per directional isolation you do NOT need to read up into it — the frozen shapes are duplicated
into the plan above.)

### 3. Existing code to understand (read before modifying)
```
routes/sync.php                                      → add PUBLIC /telemetry OUTSIDE the auth:sanctum group; /auth/check is the public-route precedent
bootstrap/app.php                                    → 'device-id' + 'log-sync-request' aliases; api/sync/* JSON error contract
app/Sync/Middleware/EnsureDeviceId.php               → reads X-Device-Id → attribute device_id (reuse as-is)
app/Sync/Controllers/BlueprintController.php          → opaque-blob controller to mirror (but create(), and anonymous)
app/Sync/Models/AthleteBlueprint.php                 → model to mirror ($fillable + 'x' => 'array' cast)
database/migrations/2026_06_15_000001_create_athlete_blueprints_table.php → migration to mirror (drop ->unique(), add device_id index)
app/Providers/AppServiceProvider.php                 → RateLimiter::for + buildLimits(); add 'telemetry' keyed on X-Device-Id
config/rate_limits.php                               → add 'telemetry' thresholds
```

---

## What You're Building

A NEW, **PUBLIC (unauthenticated)** telemetry ingest endpoint storing anonymous screen-view events as an
OPAQUE JSON blob. The Athlete app POSTs batches of `{ screen, ts }` events keyed on its anonymous device
id. Add: a `create_athlete_events_table` migration (nullable non-unique `user_id` FK, indexed nullable
`device_id`, `json event_data`, timestamps); an `AthleteEvent` model; a `telemetry` rate limiter keyed on
the DEVICE id; a `TelemetryController@store` that appends ONE row per request via `create()` (opportunistic
nullable `user_id`, cap + size guard); and a PUBLIC `POST /telemetry` route OUTSIDE `auth:sanctum`.

`event_data` is OPAQUE — stored verbatim, NEVER interpreted (same as `blueprint`/`preferences`). The
endpoint is anonymous — NEVER gated on auth; `user_id` is opportunistic. This is additive: no existing
route, table, or endpoint is touched.

---

## HARD RULES — NEVER VIOLATE
- **NEVER commit / add / push.** No git commands.
- **NEVER run Pint** (`vendor/bin/pint` in any form).
- **NEVER run destructive DB commands** (`migrate:fresh`, `migrate:reset`, `db:wipe`). Forward-only
  `migrate`. Never modify a run migration.
- **NEVER put `auth:sanctum` on `/telemetry`** or add any auth guard — telemetry is anonymous.
- **NEVER interpret `event_data`** (no reading `events.*.screen`, no inner branching) or use it for
  anything but verbatim storage.
- **NEVER `updateOrCreate`** — append-only `create()`. **NEVER** make `user_id` unique/required.

---

## Milestone 1 — Migration + model, then RUN it
- `php artisan make:migration create_athlete_events_table --no-interaction`.
  - `up()`:
    ```php
    Schema::create('athlete_events', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        $table->string('device_id', 36)->nullable()->index();
        $table->json('event_data');
        $table->timestamps();
    });
    ```
  - `down()`: `Schema::dropIfExists('athlete_events');`
- `app/Sync/Models/AthleteEvent.php`: `protected $table = 'athlete_events';`
  `$fillable = ['user_id','device_id','event_data']`; `casts()` returns `['event_data' => 'array']`;
  `user()` BelongsTo `User` (mirror `AthleteBlueprint`).
- Test (model): `AthleteEvent::create(['device_id'=>'d','event_data'=>['events'=>[['screen'=>'welcome','ts'=>'2026-09-09T09:00:00Z']]]])`
  reads `event_data` back as that array via the cast; `user_id` may be null.
- **RUN the migration:** `php artisan migrate` then `php artisan migrate:status` — confirm the new
  migration is **Ran** (§8).
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 2 — Rate limiter + config
- `config/rate_limits.php`: add (with a comment: keyed on device id — shared gym WiFi would collapse ~800
  members into one IP bucket):
  ```php
  'telemetry' => [ 'per_minute' => 30, 'per_hour' => null ],
  ```
- `app/Providers/AppServiceProvider.php` `boot()`: add
  ```php
  RateLimiter::for('telemetry', function (Request $request) {
      return $this->buildLimits('telemetry', $request->header('X-Device-Id') ?: $request->ip());
  });
  ```
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```
(No dedicated limiter test; the Phase-3 feature test exercises the route through the middleware.)

## Milestone 3 — Controller + PUBLIC route + feature tests
- `app/Sync/Controllers/TelemetryController.php`:
  ```php
  public function store(Request $request): JsonResponse
  {
      $validated = $request->validate([
          'events'   => 'required|array|max:200',   // per-request event-count cap
          'events.*' => 'array',                     // opaque — NO inner-key rules
      ]);

      AthleteEvent::create([
          'user_id'    => $request->user()?->id,               // opportunistic; null for anonymous
          'device_id'  => $request->attributes->get('device_id'),
          'event_data' => ['events' => $validated['events']],  // verbatim
      ]);

      return response()->json(['status' => 'ok']);
  }
  ```
  - The `max:200` count cap doubles as the oversized-payload guard (validation failure → the api/sync/*
    handler returns 422 JSON). Do NOT read inside `events.*`.
- `routes/sync.php`: OUTSIDE the `auth:sanctum` group (near `/auth/check`), add:
  ```php
  Route::post('/telemetry', [\App\Sync\Controllers\TelemetryController::class, 'store'])
      ->middleware(['device-id', 'throttle:telemetry', 'log-sync-request']);
  ```
- Feature tests (`tests/Feature/Sync/TelemetryTest.php`):
  1. **Anonymous** POST (no token) with a small `events` array and header `X-Device-Id: <uuid>` → 200
     `{status:ok}`; one `athlete_events` row with `user_id === null`, `device_id === <uuid>`, and
     `event_data['events']` deep-equal to the payload.
  2. **Authenticated** POST (Sanctum token via `Sanctum::actingAs`) → row has `user_id` set; still stored
     verbatim.
  3. **Append-only** — two POSTs from the same device id → `athlete_events` count is 2 (NOT an upsert).
  4. **Over-cap** — an `events` array longer than the cap → 422, `athlete_events` count unchanged (0).
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 4 — Cleanup sweep + final verification
- End-of-Run Cleanup Sweep (grep/search tool, not bash grep): no unused `use`; no `event_data`
  interpretation anywhere; confirm the route is NOT inside the `auth:sanctum` group and has NO auth
  middleware; no `updateOrCreate`; no dead branch; delete `.test-output.txt`.
- Final run:
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```
All green. Delete `.test-output.txt`. Then print:
```
AGY_COMPLETE: All milestones passed.
```

---

## Implementation Rules
- All code in `app/Sync/` (Controller + Model), `database/migrations/`, `routes/sync.php`,
  `config/rate_limits.php`, `app/Providers/AppServiceProvider.php`. Eloquent not `DB::`; constructor
  promotion; explicit return types; PHPUnit only; factories; `--no-interaction`. New columns
  nullable-where-noted + `$fillable` + `array` cast. Mirror `BlueprintController` / `AthleteBlueprint` /
  `create_athlete_blueprints_table` (drop `->unique()`, add the `device_id` index, use `create()`).

## Success Criteria
- [ ] `athlete_events` (nullable non-unique `user_id` FK cascadeOnDelete, indexed nullable `device_id`
      string(36), `json event_data`, timestamps); migration RUN (`migrate:status` = Ran); no backfill.
- [ ] `AthleteEvent` fills the three columns + casts `event_data` → `array`; `user_id` nullable.
- [ ] `POST /api/sync/telemetry` PUBLIC (no `auth:sanctum`); appends via `create()`; `device_id` from
      `X-Device-Id`; `user_id` only when a token resolves.
- [ ] Event-count cap (`max`) rejects oversized `events` with 422; no row written on rejection.
- [ ] `telemetry` limiter registered, keyed on device id (IP fallback), thresholds in
      `config/rate_limits.php`.
- [ ] `event_data` never interpreted; endpoint never gated on auth.
- [ ] `php artisan test --parallel` green; sweep clean; no existing endpoint touched; no deps; no commits.

## Do Not
- Do NOT gate on auth / add `auth:sanctum` / require a token.
- Do NOT interpret/validate-inner/derive-from `event_data`.
- Do NOT key the limiter primarily on IP. Do NOT `updateOrCreate`. Do NOT make `user_id` unique/required.
- Do NOT backfill or add a data migration. Do NOT edit historical migrations or any `contracts/` file.
- Do NOT commit/push, run Pint, or run destructive DB commands.

## Post-Execution Retro (authored by the REVIEWER, not the executor — leave placeholders untouched)
- **Attempts:** {1 (clean) / N (root cause)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {…}
- **Steering updates needed:** {yes/no + what}
