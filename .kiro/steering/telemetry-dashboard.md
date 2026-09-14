---
inclusion: auto
---

# Telemetry Dashboard — Decisions & Constraints

Durable decisions for the Logger telemetry dashboard and its reporting layer. These were settled
deliberately (and pushed back on hard) — treat them as the default posture for any future telemetry
dashboard rework or feature, so they don't have to be re-argued. Root principle #35 (`principles.md`:
"Logger's request path is scarce") is the WHY; this file is the HOW for telemetry specifically.

## Performance model (the core constraint)

Logger runs on **Forge Hobby, shared with the production backend/DB**. The telemetry dashboard must be
cheap on the request path. The model:

- **Overview aggregates** (device counts, blueprint distribution, median dwell) are computed by an
  **hourly `telemetry:rollup` Artisan command** (scheduled `->hourly()`), written to a **cache with a
  `generated_at` timestamp**, and READ by the dashboard. The dashboard NEVER unrolls all-device
  `event_data` JSON on a page view. Data is up to ~1h stale; the UI shows its age. Cold-start:
  compute-once-and-cache if the cache is empty.
- **Device deep-dive is LIVE**, but scoped to ONE device and **paginated by SESSION** (≤10 sessions/page,
  newest first, never splitting a session across pages). The sessionless "Before session tracking" bucket
  is a SEPARATELY capped list. One device's data is tiny, so live is cheap.
- **One additive migration only:** a `created_at` INDEX on `athlete_events` — keeps both the hourly scan
  and the live per-device query bounded. NOT a data rewrite. No other schema change.

Do NOT build live all-device queries. Do NOT compute aggregates on the request path. If a new panel needs
a new cross-entity aggregate, it goes in the rollup, not a live query.

## Data & metric decisions

- **`event_data` is an opaque, verbatim blob.** Ingest stores it byte-for-byte; nothing interprets it at
  rest. Derive at read/rollup time. NO data-rewrite/backfill migrations of stored payloads.
- **Dwell metric is MEDIAN** — per screen + one overall median. NOT average, NOT total/sum. **No "unknown"
  count is surfaced** — unmeasurable visits are silently excluded from the median.
- **Dwell resolution per screen-open:** `leave.duration_ms` → else MAX heartbeat `duration_ms` → else
  EXCLUDED. **Never infer dwell from arrival gaps** — that is the unreliable signal this whole effort
  replaced; do not reintroduce it, and never blend inferred with measured values.
- **Measured dwell starts at the instrumentation cutover.** `session_id` present = measured/new; absent =
  historic. Sessionless historic events stay fully browsable in the deep-dive via the labeled "Before
  session tracking" bucket — NO inference, NO backfill.
- **Screen normalization:** the auto-update cycle appends a cache-bust param **`_v`**. Per-screen dwell
  groups by a NORMALIZED key that strips `_v` but KEEPS other params (so `/express?step=1` and
  `?step=summary` stay distinct). Query/rollup-time only; storage and the device trail keep the RAW screen.

## UI structure

- Two screens: **Overview** (date filter + Devices / Blueprint / Dwell cards + device list) → **device
  deep-dive** (tap a device). Design contract: `designs/telemetry-dashboard.html` — implement faithfully,
  read it at build time, don't code from memory.
- **File structure:** do NOT reproduce one ~600-line Blade file. Extract the Alpine component to its own
  JS file; split markup into Blade partials (thin shell + `partials/_*`). One authoritative component owns
  state/fetches; partials are dumb renderers.
- **JS location (NO build step in this repo):** there is no Vite here — plain JS is served from
  `public/js/` via `asset('js/...')` (see existing `public/js/*.js`). The extracted component MUST live at
  `public/js/telemetry/dashboard.js` and load via `<script src="{{ asset('js/telemetry/dashboard.js') }}">`.
  Putting it in `resources/js/` will 404 (that dir is not web-served without a build).
- Collapse animations use `grid-template-rows`, not max-height (iOS-safe).
- Aggregate cards show a **data-age label** from the rollup's `generated_at`. Per-screen dwell list is
  capped to top-N (~15) with "show all".
- Stay on Blade + Alpine + Tailwind + Chart.js. Do NOT introduce React or a build step for this dashboard.

## References

- Cross-repo spine (FROZEN shared shape + performance contract): root `docs/plans/telemetry-dwell-cross-repo.md`
- Reporting slice: `docs/plans/telemetry-dashboard-dwell.md` + `-dwell-prompt.md`
- UI slice: `docs/plans/telemetry-dashboard-ui-prompt.md`
- Design mock: `designs/telemetry-dashboard.html`
- Ingest/opaque-blob context: `.kiro/steering/sync-api-context.md`, `project-conventions.md`
