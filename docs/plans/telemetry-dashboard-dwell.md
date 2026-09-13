# Telemetry Dashboard Dwell — Logger Slice (Plan)

Reference architecture for the Logger reporting/dashboard changes that consume the richer telemetry
payload. WHY and WHAT — execution steps are in `telemetry-dashboard-dwell-prompt.md`. The shared cross-app
shape is duplicated **inline** below; this repo does not reach up to the root at author time.

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
- **Per-screen dwell**, per `(device_id, session_id, screen-open)`:
  - Preferred: `duration_ms` from the matching `leave`.
  - Fallback (no leave): `duration_ms` from the LAST `heartbeat` for that screen-open (undercounts by ≤15s).
  - No leave and no heartbeat: dwell UNKNOWN — do NOT infer from the gap to the next arrival. Count the
    view; report dwell as unknown/excluded from dwell averages.
- Existing arrival-count / screen-popularity / device reports keep working unchanged across full history.

## Key behaviors / decisions

- **New aggregation, mirror the dual-path convention.** Dwell aggregation reads `event_data.events[*]`,
  filters to events with `session_id`, groups by `(device_id, session_id, screen)` in visit order, and
  resolves dwell per the frozen rule. Follow the existing MySQL `JSON_TABLE` + SQLite PHP-loop pattern used
  by `blueprint()`/`trail()`. (A pure PHP-loop reduce over rows is acceptable if the dual SQL path is not
  worth the complexity for dwell — match whatever the existing service leans on; do not invent a third
  pattern.)
- **`trail()` enrichment.** Extend the per-event trail objects to include `event` and `session_id` (and
  `duration_ms` when present) so the per-device trail view can show session grouping and leave/heartbeat
  markers. Keep `screen`/`ts` as-is (additive to the returned shape).
- **Dashboard surface.** Add a per-screen dwell view (avg/median dwell per screen, plus a count of
  views with unknown dwell) and, in the device trail, visually group by `session_id`. Keep existing
  panels intact.
- **Cutover honesty.** Dwell metrics are computed only over `session_id`-bearing events; the UI should make
  clear dwell is available "since instrumentation," not for the full historic range.

## Risks

- **SQLite vs MySQL parity.** The dashboard tests likely run on SQLite; production is MySQL. Any new SQL
  unroll MUST have the SQLite fallback like the existing methods, or use a PHP-loop reduce that works on
  both. Verify both paths.
- **Sessionless historic rows.** Must not crash or miscount when events lack `session_id`/`duration_ms` —
  treat missing `session_id` as historic (exclude from dwell), missing `duration_ms` as unknown dwell.
- **Heartbeat volume.** Many heartbeats per screen-open; the "last heartbeat" fallback must pick the max
  `duration_ms` (or latest `ts`) for that open, not sum them.
- **Ordering within a session.** Screen-opens are ordered by `ts`; a repeated screen in the same session is
  a distinct open (new `view` after an intervening leave). Group by open, not just by screen.

## Test strategy

Extend the Logger telemetry feature/unit tests (`php artisan test --parallel`, PHPUnit/Paratest):
- Dwell from `leave.duration_ms` preferred; fallback to last `heartbeat`; unknown when neither.
- Historic sessionless events excluded from dwell but still counted as views.
- Trail returns `event`/`session_id` additively; `blueprint_state` still skipped from the trail.
- Both DB drivers (or the PHP-loop path) produce identical dwell results.
- Existing summary/blueprint/trail tests still pass unchanged.

## Scope boundary

Everything stays inside `logger/`. No `../`, no root workspace, no sibling app. Do not read or author root
`contracts/` — the cross-app fixture is reconciled later from the root, after this slice lands. Do NOT
touch ingest, the migration, or the `event_data` column shape.
