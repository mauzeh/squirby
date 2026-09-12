# Telemetry Admin — Blueprint Tab — Prompt for Antigravity CLI (Logger, FL-28)

## Global Execution Rules
1. Execute sequentially; test ONLY at Milestone checkpoints.
2. SELF-CORRECTION LOOP: run the checkpoint; if it fails, read `.test-output.txt`, fix, re-run within the
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

### 1. Steering
```
docs/antigravity-steering.md             → git (NEVER commit), Pint BAN, DB safety, milestones, AGY_COMPLETE
.kiro/steering/safe-operations.md        → files never to edit, artisan safety, Pint ban
.kiro/steering/laravel-boost.md          → Eloquent/DB::, constructor promotion, explicit return types, PHPUnit-only
```

### 2. Plan (reference — execute from THIS prompt)
```
docs/plans/telemetry-blueprint-tab.md    → architecture, the JSON_TABLE query, phases, rules
```

### 3. Existing code (read before modifying)
```
app/Telemetry/Services/TelemetryReportService.php    → add blueprint(); FIX trail() (skip blueprint_state)
app/Telemetry/Controllers/TelemetryApiController.php  → add blueprint() (mirror summary/trail; reuse parseSince)
routes/web.php                                        → add the admin-gated route
resources/views/telemetry/dashboard.blade.php         → add tab button + x-show section + Alpine loadBlueprint()
```

---

## What You're Building

A read-only, admin-gated **Blueprint tab** on the telemetry dashboard that surfaces FL-27
`blueprint_state` snapshots (the onboarding/personalization choices users landed on). Today the dashboard
drops them — `trail()` maps everything to `{ screen, ts }` so snapshots show as `"Unknown"`. This slice:
1. Adds `TelemetryReportService::blueprint()` — a `JSON_TABLE` unroll of `blueprint_state` events →
   latest-per-device → per-field value distribution.
2. Fixes `trail()` to exclude `blueprint_state` events.
3. Adds a `GET api/telemetry/blueprint` endpoint + a Blueprint tab.

Read-only. No migration, no write-path change, no new table, no contract. Heavy work in SQL (`JSON_TABLE`),
final tally a bounded PHP reduce over one-row-per-device. MySQL 8.0 JSON features only (prod is 8.0; local
may be 9.x — don't rely on newer functions). Single-user admin tool — a prod hiccup is a trivial fix, so
do NOT over-engineer.

## HARD RULES — NEVER VIOLATE
- NEVER commit/add/push. NEVER Pint. NEVER destructive DB (`migrate:fresh`/`reset`/`db:wipe`).
- READ-ONLY: do NOT touch `TelemetryController@store`, the `athlete_events` schema, or the write path.
- Do NOT add a migration or a new table (no denormalization — read-side SQL only).
- Do NOT add a new unauthenticated route — reuse the `['auth','verified','admin']` group.
- Do NOT loop all rows in PHP to unroll — use `JSON_TABLE`.
- Do NOT test the `JSON_TABLE` query on SQLite (contract runner) — MySQL feature test only.

## Milestone 1 — Service: blueprint() + trail() fix
- `TelemetryReportService::blueprint(Carbon $since): array`:
  - Unroll via `JSON_TABLE` (see the plan's query), filtered to `etype = 'blueprint_state'`, from
    `athlete_events` where `created_at >= $since`.
  - Reduce to LATEST snapshot per `device_id` (window fn `ROW_NUMBER() OVER (PARTITION BY device_id ORDER
    BY ts DESC)` = 1, OR a max-ts join — both MySQL 8.0-valid).
  - Compute per-field value distribution in a bounded PHP reduce over the latest-per-device `selections`
    (scalars → count value; arrays like `themes`/`goals` → count each element; objects like `availability`
    → count each selected/true key). Return
    `{ total: <#devices with a snapshot>, distribution: { field: { value: count } }, devices: [{ device_id, selections, ts }] }`.
- **Fix `trail()`:** in its event loop, `continue` when `($evt['type'] ?? null) === 'blueprint_state'` so
  snapshots are excluded from the screen trail.
- Test (`tests/Feature/Telemetry/BlueprintReportTest.php`, `RefreshDatabase`, MySQL): seed `athlete_events`
  rows with mixed screen + `blueprint_state` events; include a device with TWO snapshots (older `intensity=low`,
  newer `intensity=high`) → assert distribution counts `high` (latest wins), not `low`; assert a second
  device's choice is counted; assert `trail()` for a device returns only screen events (no `blueprint_state`).
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 2 — Controller + route
- `TelemetryApiController::blueprint(Request $request): JsonResponse` — `$since = $this->parseSince($request->query('since'))`;
  return `response()->json($this->reportService->blueprint($since))`.
- `routes/web.php`: inside the `['auth','verified','admin']` group, add
  `Route::get('api/telemetry/blueprint', [\App\Telemetry\Controllers\TelemetryApiController::class, 'blueprint'])->name('telemetry.blueprint');`
- Feature test: authenticated admin GET returns the distribution JSON (200); unauthenticated → redirect/403.
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 3 — Blade: Blueprint tab
- Add a third tab button next to Devices/Trail: `@click="activeTab = 'blueprint'; loadBlueprintOnce()"`
  (lazy-load on first open to avoid an extra fetch on page load).
- Add `x-show="activeTab === 'blueprint'"` section: for each field in `blueprintData.distribution`, render
  a small labelled list/bar of `value → count`, reusing the existing slate card styling. Optionally a
  per-device list below.
- Alpine: add `blueprintData: { total: 0, distribution: {}, devices: [] }`, `blueprintLoading: false`,
  `blueprintLoaded: false`; `loadBlueprint()` fetches `api/telemetry/blueprint?since=` + current `since`
  (mirror `loadSummary`'s fetch + CSRF/headers); `loadBlueprintOnce()` guards on `blueprintLoaded`.
  When the date chips change (`setChip`/`onDateInput`), reset `blueprintLoaded=false` so the tab refetches
  for the new floor next time it's opened.
### Checkpoint (manual)
Load `/telemetry`, open the Blueprint tab, confirm the distribution renders and respects the date filter.

## Milestone 4 — Cleanup sweep + final run
- Sweep (search tool, not bash grep): no unused imports; `trail()` still returns real screen events only;
  the SQL uses only MySQL 8.0 JSON features; no write-path/schema/migration touched; delete `.test-output.txt`.
- Final:
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```
All green. Delete `.test-output.txt`. Print:
```
AGY_COMPLETE: All milestones passed.
```

## Success Criteria
- [ ] `blueprint()` returns per-field value distribution over LATEST snapshot per device via `JSON_TABLE`.
- [ ] `trail()` excludes `blueprint_state` (no `"Unknown"` entries).
- [ ] `GET api/telemetry/blueprint`, admin-gated, honors the date floor.
- [ ] Blueprint tab renders the distribution, driven by the date chips.
- [ ] `php artisan test --parallel` green (MySQL feature tests: latest-per-device + trail exclusion + endpoint auth).
- [ ] No migration/write-path/new-table/new-access-surface; no deps; no commits.

## Do Not
- Do NOT denormalize / add a snapshots table / interpret events on write.
- Do NOT PHP-loop all rows for the unroll; do NOT test JSON_TABLE on SQLite.
- Do NOT add an unauthenticated route; do NOT commit/push/Pint/destructive-DB.

## Post-Execution Retro (REVIEWER-authored — leave placeholders untouched)
- **Attempts:** {1 (clean) / N (root cause)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {…}
- **Steering updates needed:** {yes/no + what}
