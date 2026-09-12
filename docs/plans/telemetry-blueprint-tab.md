# Telemetry Admin — Blueprint Tab (Plan, Logger-only, FL-28)

> **Reference architecture, NOT execution steps.** Execute from
> `docs/plans/telemetry-blueprint-tab-prompt.md`. Logger-only, read-only, admin-gated. No writes, no
> migration, no cross-repo work, no contract (reporting doesn't touch the wire).
>
> **Builds on:** the shipped telemetry admin view (`docs/plans/telemetry-admin-view.md`) and the FL-27
> blueprint-state snapshots (`../../docs/plans/onboarding-snapshot-telemetry-cross-repo.md`).

---

## What You're Building

A third **Blueprint** tab on the existing telemetry admin dashboard that surfaces the FL-27
`blueprint_state` snapshots — the onboarding/personalization choices users landed on. Today those
snapshots are captured and stored, but the dashboard is blind to them: `TelemetryReportService::trail`
maps every event to `{ screen, ts }`, so a snapshot (which has no `screen`) renders as a useless
`"Unknown"` timeline entry, and its `selections` payload is dropped. This slice reads them properly.

Two things the tab shows (both from ONE unrolled query):
1. **Distribution** — across the latest snapshot per device, count of each value per field (how many chose
   `intensity=high`, etc.). This is the "what are people picking" aggregate.
2. **Per-device current choices** — the latest `blueprint_state` selections for each device (optional
   drill-down; the distribution is the primary view).

Plus a **fix to the existing Trail tab:** filter `blueprint_state` events OUT of the screen trail so it
stops showing spurious `"Unknown"` entries interleaved with real screen views.

## Performance approach (Option 2 — SQL unroll, read-time)

The snapshots live inside `athlete_events.event_data->'$.events[*]'` as opaque JSON, one row per batch,
mixed with screen events. Do the unroll + aggregation **in SQL via `JSON_TABLE`**, not by looping rows in
PHP — the DB does the work, which scales far better than the existing PHP-loop pattern.

- **No untangling / no write change.** Events stay opaque blobs in `athlete_events`. This is a READ-side
  query only — no new table, no migration, no controller change, no opaque-blob violation. It also reads
  the ~600 existing rows for free (no backfill needed).
- **MySQL 8.0 `JSON_TABLE`.** Available since 8.0.4. Production is 8.0; local dev may be newer (9.x) —
  target 8.0-era JSON functions (`JSON_TABLE`, `JSON_EXTRACT`, `->`, `->>`, `JSON_UNQUOTE`). Single-user
  admin tool, so a prod hiccup is a trivial fix with no user impact — do NOT over-engineer version guards.
- **Latest snapshot per device.** Unroll events, keep only `type='blueprint_state'`, then for each
  `device_id` take the row with the max snapshot `ts`. Use a window function (`ROW_NUMBER() OVER (PARTITION
  BY device_id ORDER BY ts DESC)`) or a max-ts group-by join — both 8.0-valid.

## The query shape (illustrative — MySQL 8.0)
```sql
-- Unroll blueprint_state events, one row per (device, snapshot), newest first per device
SELECT ae.device_id, ev.ts, ev.selections
FROM athlete_events ae
JOIN JSON_TABLE(
  ae.event_data, '$.events[*]'
  COLUMNS (
    etype       VARCHAR(32)  PATH '$.type',
    ts          VARCHAR(40)  PATH '$.ts',
    selections  JSON         PATH '$.selections'
  )
) ev
WHERE ae.created_at >= ?           -- the dashboard date floor (reuse parseSince)
  AND ev.etype = 'blueprint_state'
```
- From this, "latest per device" = window/group-by on `device_id` by `ts DESC`.
- **Distribution** = over the latest-per-device set, group by each field's value. Because `selections` is
  an object with arbitrary keys (intensity, themes[], goals[], availability{}), the cleanest approach is
  to pull the latest `selections` JSON per device in SQL, then compute the per-field value counts in a
  small PHP reduce over the (already deduped, ≤ #devices) result set. That keeps the heavy unroll in SQL
  while the light final tally is a bounded PHP loop over one-row-per-device.
- Array/object fields (`themes`, `goals`, `availability`) — count each element/selected key. Scalars
  (`intensity`) — count the value. Decide a small per-field rendering (see Blade).

## Existing Code to Understand (read before modifying)
```
app/Telemetry/Services/TelemetryReportService.php    → add blueprint() method; also FIX trail() to skip type==='blueprint_state'
app/Telemetry/Controllers/TelemetryApiController.php  → add blueprint() endpoint (mirror summary/trail; reuse parseSince)
routes/web.php                                        → add GET api/telemetry/blueprint inside the ['auth','verified','admin'] group
resources/views/telemetry/dashboard.blade.php         → add a third tab button + x-show section + Alpine loadBlueprint()
app/Sync/Models/AthleteEvent.php                      → the event_data JSON column (read-only here)
```

## Execution Plan
Checkpoints use `php artisan test --parallel`.

### Phase 1 — Service: blueprint() + trail() fix + tests
- `TelemetryReportService::blueprint(Carbon $since)`:
  - Run the `JSON_TABLE` unroll above (raw query via `DB::select` or the query builder with a `JSON_TABLE`
    join expression), filtered to `blueprint_state`.
  - Reduce to latest-per-device (window fn in SQL, or max-ts).
  - Compute the per-field value distribution in a bounded PHP reduce over the latest-per-device rows.
  - Return `{ total_devices_with_blueprint, distribution: { field: { value: count } }, devices: [{ device_id, selections, ts }] }`.
- **Fix `trail()`:** skip events where `type === 'blueprint_state'` (only keep real screen events) so the
  Trail tab no longer shows `"Unknown"` snapshot entries. One `if` in the unroll loop.
- Tests (`tests/Feature/Telemetry/...` — MySQL, RefreshDatabase): seed `athlete_events` with a mix of
  screen events and blueprint_state snapshots (including a device with TWO snapshots to prove latest-wins);
  assert `blueprint()` distribution counts the LATEST selection per device; assert `trail()` excludes
  blueprint_state events. NOTE: these run on MySQL (the contract-runner SQLite has no `JSON_TABLE` — do
  NOT test this via the contract harness).
- **Checkpoint.**

### Phase 2 — Controller + route
- `TelemetryApiController::blueprint(Request $request)`: parse `since` (reuse `parseSince`), call the
  service, return JSON. Mirror `summary`/`trail`.
- `routes/web.php`: `Route::get('api/telemetry/blueprint', [TelemetryApiController::class, 'blueprint'])`
  inside the existing `['auth','verified','admin']` group.
- Feature test: the endpoint returns the distribution JSON, admin-gated (401/403 unauthenticated).
- **Checkpoint.**

### Phase 3 — Blade: Blueprint tab
- Add a third tab button `@click="activeTab = 'blueprint'"` next to Devices/Trail.
- Add an `x-show="activeTab === 'blueprint'"` section rendering the distribution: per field, a small
  bar/list of value→count (reuse the existing Tailwind card styling). Optionally a per-device list.
- Add Alpine state (`blueprintData`, `blueprintLoading`) + `loadBlueprint()` that fetches
  `api/telemetry/blueprint?since=...` (respect the existing date-filter chips — call it alongside/like
  `loadSummary`, or lazily when the tab is first opened to avoid an extra fetch on load).
- The date floor / chips should drive it the same way they drive summary.
- **Checkpoint** (manual: load the page, open the Blueprint tab, confirm the distribution renders).

### Phase 4 — Verify + cleanup
- `php artisan test --parallel` green. Cleanup: no unused imports, no dead code; the `JSON_TABLE` SQL uses
  only 8.0-era functions; delete `.test-output.txt`.

## Simplicity Criteria
- ONE service method + ONE trail() fix + ONE endpoint + ONE tab. Read-only. No migration, no write change,
  no new table, no contract. Heavy work in SQL; final tally a bounded PHP reduce over one-row-per-device.

## Hard Rules
- NEVER commit/push. NEVER Pint. NEVER destructive DB.
- Read-only + admin-gated — reuse the existing `['auth','verified','admin']` group; add NO new access surface.
- Do NOT change the write path / `TelemetryController@store` / `athlete_events` schema (no interpretation
  on write — this is read-side only).
- Do NOT test the `JSON_TABLE` query via the SQLite contract runner — MySQL feature test only.
- Keep the SQL to MySQL 8.0 JSON features.

## Success Criteria
- [ ] `TelemetryReportService::blueprint()` returns the per-field value distribution over the LATEST
      snapshot per device, computed via a `JSON_TABLE` unroll (not a full PHP row loop).
- [ ] `trail()` excludes `blueprint_state` events (no more `"Unknown"` entries in the screen trail).
- [ ] `GET api/telemetry/blueprint` endpoint, admin-gated, honoring the date floor.
- [ ] A Blueprint tab renders the distribution, driven by the existing date chips.
- [ ] `php artisan test --parallel` green (MySQL feature tests, incl. latest-per-device + trail exclusion).
- [ ] No migration, no write-path change, no new access surface, no deps, no commits.

## Do Not
- Do NOT denormalize / add a snapshots table / interpret events on write (that's the expensive untangle we
  rejected — read-side SQL only).
- Do NOT loop all rows in PHP for the unroll — use `JSON_TABLE`.
- Do NOT add a new unauthenticated route. Do NOT commit/push, Pint, or run destructive DB.

## Post-Execution Retro (REVIEWER-authored — leave placeholders)
- **Attempts:** {…}
- **Tests added:** {count}
- **Prompt gap:** {…}
- **Steering updates needed:** {yes/no + what}
