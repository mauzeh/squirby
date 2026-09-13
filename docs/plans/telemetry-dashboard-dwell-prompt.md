# Global Execution Rules

1. Execute this plan sequentially, pausing to test ONLY at the designated Milestone checkpoints.
2. CRITICAL SELF-CORRECTION LOOP: at a testing step, run the test command; if it fails, read the errors, fix, and re-run within this turn until green. Do not yield with a failing milestone; do not ask for help with a test failure — fix it.
3. Do not finish your turn until the current milestone's tests pass completely.
4. When ALL milestones pass and all success criteria are met, print exactly (do NOT write the retro — the reviewer authors it):
   ```
   AGY_COMPLETE: All milestones passed.
   ```
5. CONTEXT BUDGET: never read a 300-line file to change 1 line — use grep/line-range reads.

---

# Telemetry Dashboard Dwell — Logger Slice Prompt

> **App-scoped execution — stay inside `logger/`.** This prompt runs ENTIRELY within the Logger repo.
> **Hard boundary rule:** never use `../`, `../../`, or any path that escapes `logger/`. Do NOT read,
> reference, or write the root workspace, root `contracts/`, root `docs/`, or any sibling app
> (`athlete/`, `relay/`, etc.). The cross-app shape you need is duplicated INLINE in the plan
> (`logger/docs/plans/telemetry-dashboard-dwell.md`) — treat that as your source of truth. Run this from
> within `logger/` so the app's own steering auto-loads.

## What You're Building

Update Logger's telemetry **reporting layer only** to consume the richer Athlete payload (events now carry
`session_id`, plus new `leave`/`heartbeat` events with wall-clock `duration_ms`) and surface per-screen
dwell on the dashboard. Storage/ingest is untouched — `event_data` is opaque JSON and the new fields
already land verbatim.

End state: `TelemetryReportService` computes per-screen dwell grouped by `(device_id, session_id,
screen-open)` using the frozen rule (leave preferred, last heartbeat fallback, else unknown); the trail
returns `event`/`session_id` additively; the dashboard shows a per-screen dwell panel and groups the device
trail by session. Historic sessionless data still works for the existing panels.

## Read These Files (in order, before writing any code)

```
docs/plans/telemetry-dashboard-dwell.md                        → this slice's architecture + INLINE FROZEN shape (source of truth)
.kiro/steering/conventions.md                                  → Laravel conventions for this repo
app/Telemetry/Services/TelemetryReportService.php              → the service you extend (blueprint/trail/summary + dual-path pattern)
app/Telemetry/Controllers/TelemetryApiController.php           → summary/trail/blueprint endpoints + parseSince
resources/views/telemetry/dashboard.blade.php                  → Alpine + Chart.js UI (use line-range reads)
app/Sync/Controllers/TelemetryController.php                   → READ ONLY for context; DO NOT change ingest
```
Also read any existing telemetry tests (search `tests/` for "Telemetry") to mirror their setup.

Do NOT read anything outside `logger/`.

---

## Milestone 1: dwell aggregation in the service

### Step 1: Add a dwell aggregation method
Add a method to `TelemetryReportService` (e.g. `dwell(Carbon $since)`) that unrolls `event_data.events[*]`,
considers ONLY events with a `session_id`, groups by `(device_id, session_id, screen-open)` in `ts` order,
and resolves dwell per the FROZEN rule:
- Preferred: `duration_ms` from the matching `leave`.
- Fallback: the LAST `heartbeat` for that open — pick the MAX `duration_ms` (do not sum heartbeats).
- Neither: dwell UNKNOWN (count the view, exclude from dwell averages).
A repeated screen in the same session after an intervening `leave` is a DISTINCT open — group by open, not
just by screen.

### Step 2: Match the existing dual-path pattern
Mirror how `blueprint()`/`trail()` handle drivers: either a MySQL `JSON_TABLE` unroll with a SQLite
PHP-loop fallback, OR a pure PHP-loop reduce over rows if that's simpler and works on both drivers. Do NOT
invent a third pattern. Whatever you choose MUST produce identical results on SQLite and MySQL.

### Milestone 1 Checkpoint
```bash
php artisan test --parallel tests/Feature/Telemetry 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
(Adjust the path to wherever telemetry tests live.) Add unit tests for the dwell rule: leave preferred;
last-heartbeat fallback (max, not sum); unknown when neither; sessionless historic events excluded but
counted as views. Read `.test-output.txt`; delete when green.

---

## Milestone 2: expose dwell + enrich trail via the API

### Step 3: Enrich `trail()`
Extend the per-event trail objects returned by `trail()` to include `event` and `session_id` (and
`duration_ms` when present), additively — keep `screen`/`ts`. Continue skipping `blueprint_state` from the
trail. Do not break the existing sort.

### Step 4: Surface dwell through the API
Expose the dwell aggregation via the API (either fold a `dwell` block into `summary`'s response or add a
dedicated `dwell` endpoint + route in `routes/web.php` mirroring the existing `telemetry.*` routes). Keep
`parseSince` semantics. Do not change existing response keys — additive only.

### Milestone 2 Checkpoint
```bash
php artisan test --parallel tests/Feature/Telemetry 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Add tests: trail carries `event`/`session_id`; dwell endpoint/block returns per-screen dwell with an
unknown-count. Green before moving on.

---

## Milestone 3: dashboard UI

### Step 5: Per-screen dwell panel + session grouping
In `dashboard.blade.php`, add a per-screen dwell panel (avg/median dwell per screen + a count of
unknown-dwell views) fed by the new API data, and group the per-device trail visually by `session_id`.
Keep all existing panels intact. Make clear dwell is available "since instrumentation," not for the full
historic range.

### Milestone 3 Checkpoint
```bash
php artisan test --parallel 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Full Logger suite green, zero regressions. (Blade view is exercised via the feature tests / manual check;
ensure no server-side errors.) Delete `.test-output.txt`.

---

## Milestone 4: cleanup

### Step 6: Cleanup sweep
No dead code, no unused imports/uses, no commented-out blocks, no leftover `.test-output.txt`. Confirm
ingest, the migration, and the `event_data` shape are UNCHANGED. Confirm SQLite and MySQL dwell paths agree.

### Milestone 4 Checkpoint
After the full suite is green and cleanup is done, print:
```
AGY_COMPLETE: All milestones passed.
```

---

## Success Criteria

- [ ] `TelemetryReportService` computes per-screen dwell grouped by `(device_id, session_id, screen-open)`.
- [ ] Dwell resolution: leave preferred → last heartbeat (max) fallback → unknown; unknowns excluded from averages but counted as views.
- [ ] Sessionless historic events excluded from dwell yet still counted in existing panels.
- [ ] `trail()` returns `event`/`session_id` additively; `blueprint_state` still skipped.
- [ ] Dwell surfaced through the API and shown on the dashboard; device trail grouped by session.
- [ ] SQLite and MySQL dwell paths produce identical results.
- [ ] Ingest, migration, and `event_data` column UNCHANGED.
- [ ] `php artisan test --parallel` green, zero regressions.
- [ ] All changes confined to `logger/` — no `../`, no root, no sibling app.

## Do Not

- Do NOT change ingest (`TelemetryController`), the `athlete_events` migration, or the `event_data` shape.
- Do NOT change or remove existing API response keys — additive only.
- Do NOT infer dwell from the gap between arrivals (that is the unreliable signal being replaced).
- Do NOT read/reference/write the root workspace, root `contracts/`, or any sibling app.
- Do NOT add composer dependencies. Do NOT commit or push.
- Do NOT run `php artisan test` without `--parallel`. Do NOT re-run tests just to see missed output; redirect to a workspace file. Never use `/tmp/`.
- Do NOT write the Post-Execution Retro (reviewer-authored).

## Post-Execution Retro (authored by the REVIEWER, not the executor)
- **Attempts:** {1 (clean) / N — root cause}
- **Follow-up fixes needed:** {0 / count + subjects}
- **Classification accuracy:** {was the reporting-only framing correct?}
- **Tests added:** {number}
- **Prompt gap:** {what info was missing?}
- **Steering updates needed:** {yes/no + what}
