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

# Telemetry Dwell — Logger REPORTING Slice Prompt (data/API only)

> **App-scoped execution — stay inside `logger/`.** Runs ENTIRELY within the Logger repo.
> **Hard boundary rule:** never use `../`, `../../`, or any path that escapes `logger/`. Do NOT read,
> reference, or write the root workspace, root `contracts/`, root `docs/`, or any sibling app
> (`athlete/`, `relay/`, etc.). The cross-app shape is duplicated INLINE in the plan
> (`logger/docs/plans/telemetry-dashboard-dwell.md`) — that is your source of truth. Run from within
> `logger/` so the app's own steering auto-loads.

> **Scope: DATA + API + ROLLUP JOB — no Blade, no UI.** The dashboard rebuild is a SEPARATE prompt
> (`telemetry-dashboard-ui-prompt.md`) that consumes the API this slice produces. Do NOT touch
> `resources/views/telemetry/dashboard.blade.php` here.

## Performance model (WHY this slice is shaped the way it is)

Logger runs on Laravel Forge **Hobby**, on the SAME shared server as the production backend. Unrolling
`event_data.events[*]` for ALL devices on every dashboard load would be too expensive. So:

- **Overview aggregates** (device counts, blueprint distribution, per-screen + overall median dwell) are
  computed by an **hourly Artisan command** (`telemetry:rollup`) that does the expensive MySQL unroll
  ONCE per hour, off the request path, and writes the result (with a `generated_at` timestamp) to the
  cache. The dashboard READS the cache — it never triggers the unroll. Data is therefore up to ~1h stale;
  the UI shows its age.
- **Device deep-dive** is computed **LIVE**, but scoped to ONE device and **paginated by session** (up to
  10 sessions per page). One device's data is tiny, so this is cheap.
- A **`created_at` index** on `athlete_events` keeps both the hourly rollup scan and the live per-device
  query bounded. This is the ONE migration in this slice — additive, reversible (an index, NOT a data
  rewrite).

## What You're Building

1. A `created_at` index migration on `athlete_events`.
2. `TelemetryReportService` dwell aggregation (median per screen + overall median), grouped by
   `(device_id, session_id, screen-open)` on a NORMALIZED screen key, plus a live, session-paginated
   per-device method.
3. An hourly `telemetry:rollup` Artisan command that computes the aggregates and caches them with
   `generated_at`; scheduled `->hourly()`.
4. API: aggregates served from the cached rollup (with `generated_at`); a live device endpoint paginated
   by session.

Storage/ingest is untouched — `event_data` stays opaque; the rollup and the device query only READ it.

## FROZEN decisions (locked with product — do not re-decide)

- Dwell metric is **MEDIAN** (per screen, and one overall median). NOT average, NOT a total/sum.
- **No "unknown" count is surfaced.** Visits with no measurable dwell are silently EXCLUDED from the median.
- `duration_ms` is WALL-CLOCK. Dwell resolution per screen-open: `leave.duration_ms` preferred → else the
  MAX `duration_ms` among that open's `heartbeat`s → else EXCLUDED.
- Old/new discriminator: presence of `session_id`. Dwell considers only `session_id`-bearing events.
  Sessionless historic events are NOT part of dwell but MUST still be returned by the device query.
- Per-screen grouping uses a NORMALIZED screen key: strip the `_v` cache-bust param, KEEP all other params
  (so `/express?step=1` and `/express?step=summary` stay distinct). Query-time only; raw screen kept in
  storage and in the device trail.
- **Aggregates are hourly-cached (with `generated_at`); the deep-dive is live + session-paginated (≤10
  sessions/page).**

## Read These Files (in order, before writing any code)

```
docs/plans/telemetry-dashboard-dwell.md                        → this slice's architecture + INLINE FROZEN shape (source of truth)
.kiro/steering/conventions.md                                  → Laravel conventions for this repo
app/Telemetry/Services/TelemetryReportService.php              → the service you extend (blueprint/trail/summary + dual-path JSON_TABLE pattern)
app/Telemetry/Controllers/TelemetryApiController.php           → summary/trail/blueprint endpoints + parseSince
routes/web.php (telemetry.* routes only)                       → route registration (line-range read)
database/migrations/2026_09_09_143358_create_athlete_events_table.php → the table you index (READ; add a NEW index migration, don't edit this one)
app/Console/ (Kernel or bootstrap/app.php schedule)            → where scheduled commands are registered (Laravel 11)
app/Sync/Controllers/TelemetryController.php                   → READ ONLY for context; DO NOT change ingest
```
Also search `tests/` for "Telemetry" and read existing telemetry tests to mirror setup.
Do NOT read anything outside `logger/`. Do NOT read the Blade view — it's the other prompt's concern.

---

## Milestone 1: index migration + dwell aggregation logic

### Step 1: Add a `created_at` index migration
New migration adding an index on `athlete_events.created_at` (additive; `down()` drops the index). Do NOT
edit the original create-table migration. Run migrations in your test env so the suite exercises it.

### Step 2: Screen-key normalization helper
Add ONE authoritative helper that normalizes a screen string: strip the `_v` query param, KEEP all other
params, return the path unchanged when there's no query string. Denylist-of-one (`_v`), extensible later.
Do NOT strip the whole query string (that would merge onboarding steps). Do NOT persist normalized values.

### Step 3: Dwell aggregation in the service
Add `dwell(Carbon $since)` to `TelemetryReportService`: unroll `event_data.events[*]`, consider ONLY events
with a `session_id`, group by `(device_id, session_id, screen-open)` in `ts` order, resolve each open per
the FROZEN rule (leave → last-heartbeat MAX → excluded). A repeated screen in the same session after an
intervening `leave` is a DISTINCT open. Group per-screen by the NORMALIZED key. Return per-screen MEDIAN
(sorted desc) + one overall MEDIAN across all resolved opens. Mirror the existing dual-path convention
(MySQL `JSON_TABLE` + SQLite PHP-loop, or a pure PHP-loop reduce) — identical results on both drivers.
Median in PHP (sort; middle / mean of two middles).

### Milestone 1 Checkpoint
```bash
php artisan test --parallel tests/Feature/Telemetry 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Add unit tests: leave preferred; last-heartbeat MAX fallback (not sum); excluded when neither; median math
(odd/even); sessionless excluded from dwell; normalization (`/plan?_v=a` + `/plan?_v=b` → one `/plan`;
`/express?step=1` vs `?step=summary` stay separate). Read `.test-output.txt`; delete when green.

---

## Milestone 2: hourly rollup command + cached aggregates

### Step 4: `telemetry:rollup` Artisan command
Create a command `telemetry:rollup` that computes the Overview aggregates (device counts + chart series,
blueprint distribution, and the dwell aggregation from Step 3) for the standard range(s) the dashboard
shows, and writes each result to the cache under a stable key with a `generated_at` ISO timestamp embedded
in the payload. Keep it idempotent and safe to re-run. It must be resilient: a bad/edge row must not abort
the whole rollup.

### Step 5: Schedule it hourly
Register the command to run `->hourly()` via the Laravel scheduler (Laravel 11: in `bootstrap/app.php` /
`routes/console.php` or the console schedule, matching this repo's convention). Note for the reviewer/ops:
this requires the standard `schedule:run` cron on Forge (one entry) — that is an ops step, not code here.

### Step 6: Serve aggregates from cache
Update the aggregate API path (the `summary`/dwell endpoints) to READ the cached rollup rather than
computing live. Include `generated_at` in the response so the UI can show data age. If the cache is empty
(first deploy, before the first hourly run), compute-once-and-cache as a cold-start fallback so the
dashboard is never blank — but the steady state is read-from-cache.

### Milestone 2 Checkpoint
```bash
php artisan test --parallel tests/Feature/Telemetry 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Add tests: `telemetry:rollup` populates the cache with a `generated_at`; the aggregate endpoint returns the
cached payload + `generated_at`; cold-start fallback computes when cache is empty. Green before moving on.

---

## Milestone 3: live device deep-dive endpoint (session-paginated)

### Step 7: Per-device session-paginated method + endpoint
Add a live per-device method/endpoint that returns ONE device's data, **paginated by SESSION** (up to 10
sessions per page, most-recent first), NOT by raw event row (raw-row pagination would split a session
across pages). For each returned session: its events with `event`/`session_id`/`duration_ms`/`screen`/`ts`
(RAW screen — keep `_v`, this is the literal trail). ALSO return the device's sessionless historic events
(no `session_id`) as a SEPARATELY capped list (most-recent N, with a "load more" cursor) for the "Before
session tracking" bucket. Continue skipping `blueprint_state` from the trail. This endpoint is LIVE (no
rollup) — it's cheap because it's one indexed `WHERE device_id = ?` query.

### Milestone 3 Checkpoint
```bash
php artisan test --parallel tests/Feature/Telemetry 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Add tests: device endpoint returns ≤10 sessions/page newest-first; a session is never split across pages;
sessionless events returned separately and capped; raw screen preserved (`_v` intact). Green.

---

## Milestone 4: full suite + cleanup

### Step 8: Full Logger suite
```bash
php artisan test --parallel 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Zero regressions. Delete `.test-output.txt`.

### Step 9: Cleanup sweep
No dead code, no unused `use`/imports, no commented-out blocks, no leftover `.test-output.txt`. Confirm
ingest and the `event_data` shape are UNCHANGED (the only migration is the additive `created_at` index).
Confirm SQLite and MySQL dwell paths agree.

### Milestone 4 Checkpoint
After the full suite is green and cleanup is done, print:
```
AGY_COMPLETE: All milestones passed.
```

---

## Success Criteria

- [ ] Additive `created_at` index migration on `athlete_events` (no data rewrite; `down()` drops it).
- [ ] `TelemetryReportService` computes per-screen MEDIAN + overall MEDIAN dwell, grouped by `(device_id, session_id, screen-open)` on a NORMALIZED screen key (strip `_v`, keep other params) via one shared helper.
- [ ] Dwell resolution: leave preferred → last-heartbeat MAX fallback → excluded. No unknown count.
- [ ] `telemetry:rollup` command computes aggregates + caches them with `generated_at`; scheduled `->hourly()`; resilient to bad rows.
- [ ] Aggregate API reads the cached rollup + returns `generated_at`; cold-start fallback when cache empty.
- [ ] Live per-device endpoint returns ONE device's data paginated by SESSION (≤10/page, newest first, no session split across pages), RAW screen preserved, plus a separately-capped sessionless bucket list.
- [ ] SQLite and MySQL dwell paths produce identical results.
- [ ] Ingest and `event_data` column UNCHANGED. No Blade/UI changes.
- [ ] `php artisan test --parallel` green, zero regressions.
- [ ] All changes confined to `logger/` — no `../`, no root, no sibling app.

## Do Not

- Do NOT touch `resources/views/telemetry/dashboard.blade.php` — that's the UI prompt's job.
- Do NOT change ingest (`TelemetryController`) or the `event_data` shape. The ONLY migration is the additive `created_at` index — no data-rewrite/backfill migration.
- Do NOT compute aggregates live on the request path — they come from the hourly rollup cache.
- Do NOT paginate the deep-dive by raw event row — paginate by SESSION so sessions aren't split.
- Do NOT compute average/total dwell or an unknown count — median only, excluded visits are silent.
- Do NOT strip the whole query string when normalizing — strip only `_v`; keep `step` etc.
- Do NOT persist the normalized screen; storage and the device trail keep the raw screen.
- Do NOT filter out sessionless events from the device endpoint.
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
