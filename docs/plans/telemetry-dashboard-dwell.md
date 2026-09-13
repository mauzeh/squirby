# Telemetry Dashboard Dwell — Logger Slice (Plan)

Reference architecture for the Logger reporting/dashboard changes that consume the richer telemetry
payload. WHY and WHAT. The shared cross-app shape is duplicated **inline** below; this repo does not reach
up to the root at author time.

## Execution: TWO prompts (data, then UI)

This slice is delivered as two sequential prompts:

1. **`telemetry-dashboard-dwell-prompt.md`** — REPORTING only: `TelemetryReportService` dwell aggregation
   + API + PHPUnit tests. No Blade.
2. **`telemetry-dashboard-ui-prompt.md`** — the Blade/Alpine dashboard rebuild against the FROZEN design
   mock `designs/telemetry-dashboard.html`. Runs AFTER the reporting prompt (it consumes the new API).

The Athlete client slice (`../../athlete/docs/plans/telemetry-dwell.md`, executed from the Athlete repo)
can run in parallel with the reporting prompt.

## Finalized design decisions (locked with product)

- Dwell metric is **MEDIAN** — per screen AND one overall median across all measured visits. NOT average,
  NOT a total/sum.
- **No "unknown" count is surfaced anywhere.** Visits with no measurable dwell are silently excluded from
  the median.
- Dashboard is a **two-screen mobile UI**: Overview (date filter + Devices / Blueprint / Dwell cards +
  device list) → Device deep-dive (opened by tapping a device).
- Deep-dive trail is **session-grouped, newest session first, most-recent expanded**.
- **No inference, no backfill migration.** Sessionless historic events (no `session_id`) render in a
  labeled **"Before session tracking"** bucket at the bottom of the deep-dive — full navigation history
  preserved without inventing sessions or durations.
- New/Cumulative/Active toggle KEPT; ALL blueprint fields shown on Overview.

## What we're building

The Athlete client now emits richer telemetry (see inline shape below): every event carries a
`session_id`, plus new `leave` and `heartbeat` events with a wall-clock `duration_ms`. This slice updates
Logger's **reporting layer only** so the telemetry dashboard can answer "how long do people spend on each
screen" — grouping events into sessions and computing per-screen dwell.

**Storage is untouched.** Ingest (`app/Sync/Controllers/TelemetryController.php`) already stores
`{ events }` verbatim into the opaque `athlete_events.event_data` JSON column, and its validation has no
inner-key rules (`events.*` → `array`), so the new fields already land unchanged. No migration, no new
column, no ingest change.

## Current architecture (what exists today)

- **Ingest:** `app/Sync/Controllers/TelemetryController.php` — stores `event_data = { events: [...] }`
  verbatim. `events` capped at `max:200` per request. DO NOT change.
- **Reporting service:** `app/Telemetry/Services/TelemetryReportService.php` — the logic:
  - `blueprint(since)` — latest `blueprint_state` per device → field distribution. **Dual-path**: MySQL
    `JSON_TABLE` unroll + a SQLite PHP-loop fallback (`DB::getDriverName()`).
  - `trail(deviceId, since)` — unrolls `event_data.events[*]` into `{ screen, ts }`, skips
    `blueprint_state`, sorts by `ts` desc.
  - `summary(since, page)` / `buildChartSeries(since)` — device counts and New/Cumulative/Active series
    at the ROW (`created_at`) level, not event-level.
- **API:** `app/Telemetry/Controllers/TelemetryApiController.php` — `summary`/`trail`/`blueprint`
  endpoints; `parseSince` defaults to `2026-09-09` ("since launch").
- **Dashboard UI:** `resources/views/telemetry/dashboard.blade.php` — Alpine + Chart.js; calls the three
  endpoints; renders chart + device list + per-device trail + blueprint distribution.

## FROZEN shared shape (duplicated inline — do not reach up to root `contracts/`)

```
// arrival / view
{ "ts": "<ISO8601>", "screen": "<url>", "event": "view", "session_id": "<uuid>" }
// leave (NEW)
{ "ts": "<ISO8601>", "screen": "<url>", "event": "leave", "session_id": "<uuid>", "duration_ms": <int> }
// heartbeat (NEW)
{ "ts": "<ISO8601>", "screen": "<url>", "event": "heartbeat", "session_id": "<uuid>", "duration_ms": <int> }
// blueprint_state (existing; now also carries session_id)
{ "type": "blueprint_state", "selections": {...}, "ts": "<ISO8601>", "session_id": "<uuid>" }
```

Reporting rules (frozen):
- **Old/new discriminator:** presence of `session_id`. Historic events (pre-instrumentation) have NO
  `session_id`. Duration analysis considers only events WITH a `session_id`.
- **`duration_ms` is WALL-CLOCK** (includes idle/backgrounded time). Trim idle time using `session_id`
  boundaries, not a separate field.
- **Per-screen dwell (MEDIAN)**, per `(device_id, session_id, screen-open)`:
  - Preferred: `duration_ms` from the matching `leave`.
  - Fallback (no leave): the MAX `duration_ms` among that screen-open's `heartbeat`s (undercounts by ≤15s).
  - No leave and no heartbeat: **EXCLUDED** from dwell — do NOT infer from the gap to the next arrival, do
    NOT count it as unknown. It is silently dropped from the median.
  - Report the **median** per screen and one **overall median** across all resolved opens. No average, no
    total, no unknown count.
- Existing arrival-count / screen-popularity / device reports keep working unchanged across full history.

## Key behaviors / decisions

- **New aggregation, mirror the dual-path convention.** Dwell aggregation reads `event_data.events[*]`,
  filters to events with `session_id`, groups by `(device_id, session_id, screen)` in visit order, and
  resolves dwell per the frozen rule. Follow the existing MySQL `JSON_TABLE` + SQLite PHP-loop pattern used
  by `blueprint()`/`trail()`. (A pure PHP-loop reduce over rows is acceptable if the dual SQL path is not
  worth the complexity for dwell — match whatever the existing service leans on; do not invent a third
  pattern.)
- **`trail()` enrichment.** Extend the per-event trail objects to include `event`, `session_id`, and
  `duration_ms` (when present) so the deep-dive can group by session and mark durations. Keep `screen`/`ts`.
  CRITICAL: keep returning sessionless (historic, no `session_id`) events — the UI needs them for the
  "Before session tracking" bucket. Additive to the returned shape.
- **Dashboard surface (see `designs/telemetry-dashboard.html`).** Two-screen UI: Overview (Devices /
  Blueprint / Dwell cards + device list) → Device deep-dive. Dwell card shows overall MEDIAN + per-screen
  MEDIAN list (no total, no unknown). Deep-dive groups the trail by `session_id` (newest first,
  most-recent open) and puts sessionless events in a labeled "Before session tracking" bucket at the
  bottom. Built in the UI prompt, not the reporting prompt.
- **Cutover honesty.** Dwell is computed only over `session_id`-bearing events; the UI labels it "since
  instrumentation." Historic navigation history stays visible via the bucket — NO inference, NO backfill.

## Risks

- **SQLite vs MySQL parity.** The dashboard tests likely run on SQLite; production is MySQL. Any new SQL
  unroll MUST have the SQLite fallback like the existing methods, or use a PHP-loop reduce that works on
  both. Verify both paths.
- **Sessionless historic rows.** Must not crash or miscount when events lack `session_id`/`duration_ms` —
  missing `session_id` = historic (excluded from dwell, but STILL returned by `trail()` for the bucket).
- **Heartbeat volume.** Many heartbeats per screen-open; the fallback must pick the MAX `duration_ms` for
  that open, not sum them.
- **Ordering within a session.** Screen-opens are ordered by `ts`; a repeated screen in the same session is
  a distinct open (new `view` after an intervening leave). Group by open, not just by screen.
- **Median math on both drivers.** Median must be computed identically on SQLite and MySQL (PHP-side
  median over collected durations is the safe choice).

## Test strategy

Reporting prompt — Logger telemetry unit/feature tests (`php artisan test --parallel`, PHPUnit/Paratest):
- Dwell from `leave.duration_ms` preferred; fallback to last-heartbeat MAX; EXCLUDED when neither.
- Median math (odd/even counts); overall median across opens.
- Historic sessionless events excluded from dwell but STILL returned by `trail()`.
- Trail returns `event`/`session_id`/`duration_ms` additively; `blueprint_state` still skipped.
- Both DB drivers (or the PHP-loop path) produce identical dwell results.
- Existing summary/blueprint/trail tests still pass unchanged.

UI prompt — verified by matching the frozen mock + manual viewport checks at 375px; existing feature tests
stay green.

## Scope boundary

Everything stays inside `logger/`. No `../`, no root workspace, no sibling app. Do not read or author root
`contracts/` — the cross-app fixture is reconciled later from the root, after this slice lands. Do NOT
touch ingest, the migration, or the `event_data` column shape. The reporting prompt does NOT touch Blade;
the UI prompt does NOT touch the service/API.
