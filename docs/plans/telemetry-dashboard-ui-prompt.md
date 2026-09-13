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

# Telemetry Dwell — Logger DASHBOARD UI Slice Prompt (Blade/Alpine)

> **App-scoped execution — stay inside `logger/`.** Runs ENTIRELY within the Logger repo.
> **Hard boundary rule:** never use `../`, `../../`, or any path that escapes `logger/`. Do NOT read,
> reference, or write the root workspace, root `contracts/`, root `docs/`, or any sibling app. Run from
> within `logger/` so the app's own steering auto-loads.

> **Scope: UI ONLY.** This rebuilds `resources/views/telemetry/dashboard.blade.php` against a FROZEN design
> mock. It DEPENDS on the reporting slice (`telemetry-dashboard-dwell-prompt.md`) having landed — the
> dwell data and enriched `trail()` (with `event`/`session_id`/`duration_ms`, including sessionless
> events) must already be exposed by the API. Do NOT change `TelemetryReportService` or the API here; if
> the API is missing a field the UI needs, STOP and flag it — that belongs in the reporting slice.

## Sequencing (this runs AFTER the reporting slice)

- [ ] Reporting slice landed — `TelemetryReportService` exposes per-screen MEDIAN dwell + overall MEDIAN,
      and `trail()` returns `event`/`session_id`/`duration_ms` AND sessionless historic events.
If not, STOP.

## What You're Building

Rebuild the telemetry dashboard as a **two-screen mobile UI** matching the frozen design mock exactly:
- **Screen 1 — Overview:** trimmed date filter + three total cards (Devices, Blueprint, Dwell) + the device
  list.
- **Screen 2 — Device deep-dive:** opened by tapping a device row; a back arrow returns to Overview.

The mock is the CONTRACT. Read it at implementation time and extract exact structure/classes — do not code
from memory (per `feature-workflow.md` Phase 4).

## The design mock (FROZEN — implement faithfully)

```
designs/telemetry-dashboard.html   → the frozen two-screen design. Structure, classes, and interactions
                                      are the contract. Port it to Blade + Alpine (NOT the mock's vanilla JS).
```

### FROZEN design decisions (locked with product)

**Overview:**
- Date filter trimmed to 5 presets — `Since launch`, `7d`, `24h`, `1h`, `All` — plus a `Custom…` toggle
  that reveals the date input (not always shown).
- **Devices card:** distinct-device count headline + "new today" secondary + the New/Cumulative/Active
  toggle (KEEP all three) + the Chart.js chart (reuse existing chart wiring).
- **Blueprint card:** ALL fields as share-of-devices bars (aggregate only; per-device blueprint moves to
  the deep-dive).
- **Dwell card:** an **overall MEDIAN dwell** headline ("across all measured screen visits") + a per-screen
  MEDIAN ranked list with relative bars. Label "median per screen · since instrumentation". **NO total/sum
  headline. NO "unknown" count anywhere.**
- Device list at the bottom — reuse the existing paginated list; each row is the entry point to the
  deep-dive.

**Device deep-dive:**
- Sticky "← Overview" back header.
- Device header: id (full/less expand) + stat tiles (Sessions / Events / Engaged) + first/last seen.
- This device's blueprint choices (chips).
- Per-device dwell by screen (median).
- **Session-grouped trail:** collapsible session blocks, **newest session first, most-recent expanded by
  default**; each screen row shows screen + `duration_ms` (dimmed "—"/"unknown" when absent).
- **"Before session tracking" bucket:** sessionless historic events (no `session_id`) collapse into ONE
  dashed-border block at the BOTTOM, collapsed by default, labeled "N screen views · durations not
  available", showing a flat chronological trail with no durations. This preserves historic navigation
  history without inference.

## Read These Files (in order, before writing any code)

```
designs/telemetry-dashboard.html                               → FROZEN design contract (read fully)
.kiro/steering/conventions.md                                  → Laravel/Blade/Alpine conventions
resources/views/telemetry/dashboard.blade.php                  → the CURRENT view you rebuild (read fully; reuse chart + device list + fetch patterns)
app/Telemetry/Controllers/TelemetryApiController.php           → the endpoints the UI calls (summary/trail/blueprint + dwell)
routes/web.php (telemetry.* routes only)                       → route names for fetch URLs
```
Do NOT read anything outside `logger/`.

---

## Milestone 1: Overview screen

### Step 1: Restructure into two Alpine view-states
Introduce a `view` state (`'overview'` | `'device'`) in the `telemetryDashboard()` Alpine component. The
existing Devices/Trail/Blueprint TAB bar is REMOVED — its content is redistributed (aggregates → Overview
cards, per-device → deep-dive). Keep the existing `loadSummary`/`loadBlueprint`/`loadTrail`/chart wiring;
re-lay-out, don't rewrite the fetch logic.

### Step 2: Build the Overview cards
Port the three cards from the mock: Devices (keep chart + 3-way toggle), Blueprint (all fields aggregate),
Dwell (overall median headline + per-screen median list, fed by the new dwell API). Trim the date filter to
the 5 presets + `Custom…` toggle. Put the reused paginated device list at the bottom; tapping a row sets
`view='device'` and loads that device.

### Milestone 1 Checkpoint
```bash
php artisan test --parallel 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
No server-side/render errors; existing telemetry feature tests still pass. Manually confirm the Overview
matches the mock at 375px width. Delete `.test-output.txt`.

---

## Milestone 2: Device deep-dive screen

### Step 3: Deep-dive layout
Build the `view==='device'` screen from the mock: sticky back header, device header + stat tiles
(Sessions/Events/Engaged), per-device blueprint chips, per-device dwell-by-screen.

### Step 4: Session-grouped trail + historic bucket
From the enriched `trail()` data, partition the device's events client-side:
- Events WITH `session_id` → group by `session_id` into collapsible session blocks, **newest first**,
  most-recent expanded; each screen row shows `duration_ms` (dimmed when absent).
- Events WITHOUT `session_id` → ONE dashed "Before session tracking" block at the bottom, collapsed, flat
  chronological trail, no durations.
Use `grid-template-rows` for the collapse animation (NOT max-height — iOS-safe, per `feature-workflow.md`).

### Milestone 2 Checkpoint
```bash
php artisan test --parallel 2>&1 > .test-output.txt; tail -40 .test-output.txt
```
No errors; existing tests green. Manually confirm: tapping a device opens the deep-dive; back returns;
sessions newest-first with most-recent open; the historic bucket appears for a device with pre-cutover
events. Delete `.test-output.txt`.

---

## Milestone 3: verify + cleanup

### Step 5: Cross-check against the mock
Re-read `designs/telemetry-dashboard.html` and confirm the Blade output matches structure/classes/
interactions. Test at iPhone SE width (375×667). Confirm collapse has no scroll jumps.

### Step 6: Cleanup sweep
No dead Blade/JS, no orphaned tab-era markup, no unused Alpine state from the old layout, no leftover
`.test-output.txt`. Confirm no changes leaked into `TelemetryReportService`, the API, ingest, or the
migration.

### Milestone 3 Checkpoint
After tests are green and cleanup is done, print:
```
AGY_COMPLETE: All milestones passed.
```

---

## Success Criteria

- [ ] Dashboard is a two-screen UI (Overview + device deep-dive) matching `designs/telemetry-dashboard.html`.
- [ ] Overview: trimmed date filter (5 presets + Custom…), Devices card (chart + New/Cumulative/Active kept), Blueprint card (all fields), Dwell card (overall median + per-screen median list, NO total, NO unknown), device list.
- [ ] Deep-dive: back header, device stat tiles, per-device blueprint + dwell, session-grouped trail (newest first, most-recent open), "Before session tracking" bucket for sessionless events.
- [ ] Collapse uses `grid-template-rows`, not max-height.
- [ ] Implemented in Blade + Alpine (NOT React, NOT the mock's vanilla JS). Chart.js reused.
- [ ] `php artisan test --parallel` green, zero regressions.
- [ ] No changes to `TelemetryReportService`, the API, ingest, or the migration.
- [ ] All changes confined to `logger/` — no `../`, no root, no sibling app.

## Do Not

- Do NOT change `TelemetryReportService`, the API controllers/routes, ingest, or the migration — UI only.
- Do NOT add a total/sum dwell headline or any "unknown" count — median only.
- Do NOT drop sessionless historic events — they render in the "Before session tracking" bucket.
- Do NOT use max-height for collapse (iOS-fails); use `grid-template-rows`.
- Do NOT introduce React or a build step; stay Blade + Alpine + Tailwind + Chart.js.
- Do NOT read/reference/write the root workspace, root `contracts/`, or any sibling app.
- Do NOT add composer/npm dependencies. Do NOT commit or push.
- Do NOT run `php artisan test` without `--parallel`. Do NOT re-run tests just to see missed output; redirect to a workspace file. Never use `/tmp/`.
- Do NOT write the Post-Execution Retro (reviewer-authored).

## Post-Execution Retro (authored by the REVIEWER, not the executor)
- **Attempts:** {1 (clean) / N — root cause}
- **Follow-up fixes needed:** {0 / count + subjects}
- **Design fidelity:** {did the Blade match the mock? deviations?}
- **Tests added:** {number}
- **Prompt gap:** {what info was missing?}
- **Steering updates needed:** {yes/no + what}
