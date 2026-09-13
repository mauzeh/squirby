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

> **Scope: DATA + API ONLY — no Blade, no UI.** The dashboard rebuild is a SEPARATE prompt
> (`telemetry-dashboard-ui-prompt.md`) that consumes the API this slice produces. Do NOT touch
> `resources/views/telemetry/dashboard.blade.php` here.

## What You're Building

Update Logger's telemetry **reporting service + API** to consume the richer Athlete payload (events now
carry `session_id`, plus new `leave`/`heartbeat` events with wall-clock `duration_ms`) and expose
per-screen dwell. Storage/ingest is untouched — `event_data` is opaque JSON and the new fields already land
verbatim.

End state: `TelemetryReportService` computes **median** dwell per screen and an **overall median** across
all measured visits, grouped by `(device_id, session_id, screen-open)` using the frozen rule (leave
preferred, last-heartbeat fallback, else excluded); `trail()` returns `event`/`session_id`/`duration_ms`
additively AND still returns sessionless historic events (so the UI can show them); the dwell data is
exposed through the API. Historic sessionless data still works for existing panels.

## FROZEN decisions (locked with product — do not re-decide)

- Dwell metric is **MEDIAN** (per screen, and one overall median across all measured visits). NOT average,
  NOT a total/sum.
- **No "unknown" count is surfaced.** Visits with no measurable dwell (no leave, no heartbeat) are simply
  **excluded** from the median math. Do not compute or return an unknown count.
- `duration_ms` is WALL-CLOCK. Dwell resolution per screen-open: `leave.duration_ms` preferred → else the
  MAX `duration_ms` among that open's `heartbeat`s → else EXCLUDED from dwell.
- Old/new discriminator: presence of `session_id`. Dwell considers only `session_id`-bearing events.
  Sessionless historic events are NOT part of dwell but MUST still be returned by `trail()`.

## Read These Files (in order, before writing any code)

```
docs/plans/telemetry-dashboard-dwell.md                        → this slice's architecture + INLINE FROZEN shape (source of truth)
.kiro/steering/conventions.md                                  → Laravel conventions for this repo
app/Telemetry/Services/TelemetryReportService.php              → the service you extend (blueprint/trail/summary + dual-path pattern)
app/Telemetry/Controllers/TelemetryApiController.php           → summary/trail/blueprint endpoints + parseSince
routes/web.php (telemetry.* routes only)                       → route registration (line-range read)
app/Sync/Controllers/TelemetryController.php                   → READ ONLY for context; DO NOT change ingest
```
Also search `tests/` for "Telemetry" and read the existing telemetry tests to mirror their setup.
Do NOT read anything outside `logger/`. Do NOT read the Blade view — it's the other prompt's concern.

---

## Milestone 1: dwell aggregation in the service

### Step 1: Add a dwell aggregation method
Add a method to `TelemetryReportService` (e.g. `dwell(Carbon $since)`) that unrolls
`event_data.events[*]`, considers ONLY events with a `session_id`, groups by `(device_id, session_id,
screen-open)` in `ts` order, and resolves each open's dwell per the FROZEN rule (leave → last-heartbeat MAX
→ excluded). A repeated screen in the same session after an intervening `leave` is a DISTINCT open — group
by open, not just by screen.
Return: per-screen MEDIAN dwell (`ms`), sorted descending, AND one overall MEDIAN across all resolved
opens. Do NOT return counts of excluded/unknown visits.

### Step 1a: Normalize the screen key for per-screen grouping (avoid an infinite list)
The Athlete auto-update cycle appends a cache-bust query param **`_v`** (from `cacheBustReload`), so raw
screens look like `/plan?_v=k3f9x2`. Without normalization the per-screen list grows unbounded — a new row
every deploy cycle for the same logical page. Add a single normalization helper (one authoritative place,
reused by every screen aggregate) that:
- strips the `_v` query param from the screen string,
- KEEPS all other params (so `/express?step=1` and `/express?step=summary` stay DISTINCT — onboarding
  steps must remain individually visible),
- returns the path unchanged when there is no query string.
Group per-screen dwell by this NORMALIZED key. Implementation note: this is a denylist-of-one (`_v`),
easily extended if future volatile params appear. Do NOT strip the whole query string (that would merge the
onboarding steps). Store nothing normalized — normalization is a query-time concern only; `trail()` and
storage keep the RAW screen.

### Step 2: Match the existing dual-path pattern
Mirror how `blueprint()`/`trail()` handle drivers: either a MySQL `JSON_TABLE` unroll with a SQLite
PHP-loop fallback, OR a pure PHP-loop reduce over rows if simpler and correct on both drivers. Do NOT
invent a third pattern. The chosen path MUST produce identical results on SQLite and MySQL. (Median in
PHP: sort the durations, pick middle / average the two middles.)

### Milestone 1 Checkpoint
```bash
php artisan test --parallel tests/Feature/Telemetry 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
(Adjust path to where telemetry tests live.) Add unit tests: leave preferred; last-heartbeat MAX fallback
(not sum); excluded when neither; median math (odd/even counts); sessionless events excluded from dwell;
**screen normalization** — `/plan?_v=a` and `/plan?_v=b` roll up under one `/plan` screen, while
`/express?step=1` and `/express?step=summary` stay separate. Read `.test-output.txt`; delete when green.

---

## Milestone 2: expose dwell + enrich trail via the API

### Step 3: Enrich `trail()`
Extend the per-event trail objects returned by `trail()` to include `event`, `session_id`, and
`duration_ms` (when present), additively — keep `screen`/`ts`. CRITICAL: keep returning events that have NO
`session_id` (historic) — the UI groups sessionful events into sessions and puts sessionless ones in a
"Before session tracking" bucket, so both must flow through. Continue skipping `blueprint_state` from the
trail. Do not break the existing sort.

### Step 4: Surface dwell through the API
Expose the dwell aggregation via the API — either fold a `dwell` block into `summary`'s response or add a
dedicated `dwell` endpoint + route in `routes/web.php` mirroring the existing `telemetry.*` routes. Keep
`parseSince` semantics. Do NOT change or remove existing response keys — additive only.

### Milestone 2 Checkpoint
```bash
php artisan test --parallel tests/Feature/Telemetry 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Add tests: trail carries `event`/`session_id`/`duration_ms` and STILL returns sessionless events; dwell
endpoint/block returns per-screen medians + overall median. Green before moving on.

---

## Milestone 3: full suite + cleanup

### Step 5: Full Logger suite
```bash
php artisan test --parallel 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
Zero regressions (existing summary/blueprint/trail tests still pass). Delete `.test-output.txt`.

### Step 6: Cleanup sweep
No dead code, no unused `use`/imports, no commented-out blocks, no leftover `.test-output.txt`. Confirm
ingest, the migration, and the `event_data` shape are UNCHANGED. Confirm SQLite and MySQL dwell paths agree.

### Milestone 3 Checkpoint
After the full suite is green and cleanup is done, print:
```
AGY_COMPLETE: All milestones passed.
```

---

## Success Criteria

- [ ] `TelemetryReportService` computes per-screen MEDIAN dwell + one overall MEDIAN, grouped by `(device_id, session_id, screen-open)`.
- [ ] Per-screen grouping uses a NORMALIZED screen key that strips `_v` but keeps other params (e.g. `step`), via one shared helper; raw screen kept in storage and `trail()`.
- [ ] Dwell resolution: leave preferred → last-heartbeat MAX fallback → excluded. No unknown count computed or returned.
- [ ] `trail()` returns `event`/`session_id`/`duration_ms` additively AND still returns sessionless historic events; `blueprint_state` still skipped.
- [ ] Dwell surfaced through the API (block in `summary` or a dedicated `dwell` endpoint), additive only.
- [ ] SQLite and MySQL dwell paths produce identical results.
- [ ] Ingest, migration, and `event_data` column UNCHANGED. No Blade/UI changes.
- [ ] `php artisan test --parallel` green, zero regressions.
- [ ] All changes confined to `logger/` — no `../`, no root, no sibling app.

## Do Not

- Do NOT touch `resources/views/telemetry/dashboard.blade.php` — that's the UI prompt's job.
- Do NOT change ingest (`TelemetryController`), the `athlete_events` migration, or the `event_data` shape.
- Do NOT compute average/total dwell or an unknown count — median only, excluded visits are silent.
- Do NOT strip the whole query string when normalizing — strip only `_v`; keep `step` and other params so onboarding steps stay distinct.
- Do NOT persist the normalized screen — normalization is query-time only; storage and `trail()` keep the raw screen.
- Do NOT filter out sessionless events from `trail()`.
- Do NOT change or remove existing API response keys — additive only.
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
