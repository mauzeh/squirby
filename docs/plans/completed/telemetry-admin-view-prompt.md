# Telemetry Admin View — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this feature correctly.

### 1. Executor contract + steering (project rules — always follow)
```
docs/antigravity-steering.md            → executor contract: git (NEVER commit), Pint BAN, §6 DB rules, §7 domain folders, §13 consumer trace, §14 logic placement, cleanup sweep, AGY_COMPLETE
.kiro/steering/git-workflow.md          → branch assumptions; NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files never to edit, bash safety, artisan safety, Pint ban
.kiro/steering/project-conventions.md   → domain folders, no column repurposing, read-only intent
.kiro/steering/laravel-boost.md         → Laravel/PHP conventions (Eloquent over DB::, constructor promotion, return types)
```

### 2. Feature Spec (what to build)
```
docs/plans/telemetry-admin-view.md      → requirements, architecture, frozen design decisions, execution plan, success criteria
```
Read the plan for context and the frozen decisions. Execute from THIS prompt.

### 3. Existing Code to Understand (read before modifying)
```
routes/web.php                                       → admin group `Route::middleware(['auth','verified','admin'])->group(...)`. Add ALL telemetry routes here: page `telemetry` + data `api/telemetry/summary` + `api/telemetry/trail`.
routes/api.php                                       → EMPTY (stock scaffolding). No frontend-API convention; do NOT put telemetry here.
routes/sync.php                                      → real API precedent (api/sync/*, App\Sync\Controllers\, auth:sanctum for the mobile app). Telemetry mirrors the domain-folder controllers but NOT the Sanctum/api-group wiring — it is session-authed. The collection endpoint POST /api/sync/telemetry lives here; do not touch it.
bootstrap/app.php                                    → the 'admin' middleware alias → IsAdmin::class; shows sync.php on the `api` group (telemetry does NOT do this).
app/Http/Middleware/IsAdmin.php                      → the gate (hasRole('Admin')). Reuse via the 'admin' alias; write no new auth.
app/Http/Controllers/UserController.php              → admin route wiring reference ONLY — do NOT copy its ComponentBuilder/mobile-entry.flexible rendering.
app/Sync/Models/AthleteEvent.php                     → the model you READ (event_data 'array' cast; no SoftDeletes). Do NOT modify.
database/migrations/2026_09_09_143358_create_athlete_events_table.php → confirms columns. Already run — do NOT modify.
app/Console/Commands/AnalyzeLiftLogs.php             → read-only reporting-aggregate precedent (query builder + DB::raw COUNT(DISTINCT ...) + groupBy).
public/js/chart-component.js                         → house Chart.js CDN pattern (https://cdn.jsdelivr.net/npm/chart.js). Mirror the CDN include; drive Chart.js directly from Alpine.
resources/js/app.js                                  → confirms Alpine is the JS runtime.
```

### 4. Reference (already implemented — don't rebuild, just understand)
```
docs/plans/anonymous-telemetry.md       → the collection slice that created athlete_events + AthleteEvent + POST /api/sync/telemetry. Already shipped. This feature READS what it produced; it changes nothing about collection.
```

---

## What You're Building

A NEW, standalone, mobile-first single-page application in Logger, gated to authenticated administrators,
that visualizes anonymous screen-view telemetry from the EXISTING `athlete_events` table. It answers: how
many distinct anonymous devices have accessed the app since a given date, and where any one of them
navigated. It is reached only by directly visiting its URL (a bookmark) — no navigation links point to it.

The page deliberately BREAKS from the existing web-UI architecture: it does NOT use `mobile-entry.flexible`,
the `ComponentBuilder` (`C::…`) pattern, or `@extends('app')`. It is a purpose-built Blade view that renders
its own minimal HTML shell (own `<head>`, Tailwind + Alpine + CDN Chart.js) and behaves as a small SPA — an
Alpine front end fetches JSON from admin-gated endpoints and renders three sections client-side.

Three sections: **A** — a distinct-device headline count + a Chart.js bar chart of unique devices over time
with a 3-way metric toggle (new / cumulative / active), dynamic Y-axis, gridlines, mobile-capped buckets;
**B** — a paginated device list (20/page, newest first, shortened IDs with expand-to-full, null shown as
"no value"); **C** — a selected device's full ordered navigation trail. This is READ-ONLY: no migration, no
model change, no change to the collection slice.

---

## Execution Plan

Follow the phases in `docs/plans/telemetry-admin-view.md` (§Execution Plan) in order. **There are NO test
checkpoints — tests are waived for this feature (see HARD RULES).** Each phase ends with an
inspection/manual confirmation, not a test run.

### Phase order:
1. **Service** — `app/Telemetry/Services/TelemetryReportService.php`: `summary()` (distinct-device count +
   3 bucketed series [new/cumulative/active] + paginated device list) and `trail()` (one device's rows
   unrolled from `event_data.events`, sorted by `ts`). Bucket granularity derived from the range, capped
   for mobile. Reads `event_data` ONLY in `trail()`.
2. **Controllers + routes** — TWO thin controllers in `app/Telemetry/Controllers/`:
   `TelemetryDashboardController@index` (View, serves the SPA) and `TelemetryApiController` (service
   injected via constructor promotion) with `summary()` (JSON) + `trail()` (JSON). Add three routes in
   `routes/web.php` inside a `['auth','verified','admin']` group: `GET telemetry` (`telemetry`),
   `GET api/telemetry/summary` (`telemetry.summary`), `GET api/telemetry/trail` (`telemetry.trail`). The
   data endpoints use the `/api/telemetry` URL prefix but stay session-authed on the `web` stack — NOT
   Sanctum, NOT `sync.php`/`api.php`, NOT the `api` middleware group.
3. **SPA view** — `resources/views/telemetry/dashboard.blade.php`: a standalone HTML document (own `<head>`,
   viewport meta, Tailwind, Alpine, CDN Chart.js). Alpine drives: date-filter chips + native date input
   (default `Since launch` = 2026-09-01, zero taps); Section A headline + Chart.js bar chart + 3-way metric
   toggle (swap dataset + rescale Y-axis via `chart.update()`, no refetch) + gridlines; Section B paginated
   device list (shortened IDs w/ expand, null → "no value", tap → fetch trail); Section C ordered trail.
4. **Final checkpoint** — manual sanity + cleanup sweep (no automated tests). Confirm admin-gating on the
   page AND both `/api/telemetry/*` endpoints (session/`web` stack, no Sanctum); default = since launch;
   toggle rescales axis; pagination + expand work; null device shows "no value". Then the §4 cleanup sweep
   (no unused `use`, no dead code, no debug noise, no temp files, no `.test-output.txt`).

---

## HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`.
- **NEVER write tests.** Tests are explicitly WAIVED for this internal read-only tool by the user. Do NOT
  add feature or unit tests. (This intentionally overrides the §4/§10 test-enforcement rule in
  `docs/antigravity-steering.md` for THIS prompt only.)
- **NEVER modify** `athlete_events`, `AthleteEvent`, the collection migration, or `POST /api/sync/telemetry`.
  This feature is read-only over an existing shape.

---

## Implementation Rules

- **All new PHP goes in `app/Telemetry/`** — `Controllers/TelemetryDashboardController.php` (page),
  `Controllers/TelemetryApiController.php` (JSON), `Services/TelemetryReportService.php`. Do NOT scatter
  into `app/Http/Controllers/` or `app/Services/`.
- **The view goes in `resources/views/telemetry/`.** **All routes go in `routes/web.php`**, inside a
  `['auth','verified','admin']` group. Names: `telemetry`, `telemetry.summary`, `telemetry.trail`.
- **Data endpoints stay session-authed on the `web` stack** under the `/api/telemetry` URL prefix. Do NOT
  put them in `routes/sync.php` or `routes/api.php`, do NOT add `auth:sanctum`, do NOT register them in the
  `api` middleware group. The prefix is a URL convention only; they need the web session so the `admin`
  gate sees the logged-in user.
- **BOTH `/api/telemetry/*` endpoints must be admin-gated**, not just the page shell. Gating only the
  shell is a bug.
- **Standalone SPA shell** — own `<!doctype html>` + `<head>` + Tailwind + Alpine + CDN Chart.js. Do NOT
  `@extends('app')`, do NOT use `ComponentBuilder`, do NOT reuse `mobile-entry.flexible` or
  `resources/views/mobile-entry/components/chart.blade.php`.
- **Chart.js from CDN** (`https://cdn.jsdelivr.net/npm/chart.js`), mirroring `public/js/chart-component.js`.
  Drive it directly from Alpine. Add NOTHING to `package.json`.
- **Read `event_data` ONLY in `trail()`**, and only for a single device. Count/list/chart use indexed
  aggregates on `device_id` + `created_at` and never touch the JSON. Chart is bucketed by `created_at`,
  NOT the inner `ts`.
- **Null `device_id` is INCLUDED**, displayed as "no value".
- **`since` default = `2026-09-01`.** Chips are since-only floors: `Since launch`, `30d`, `7d`, `24h`,
  `12h`, `6h`, `3h`, `1h`, `All`. Plus a native `<input type="date">` custom floor.
- **Never use `DB::` where Eloquent fits** — prefer `AthleteEvent::query()`. Raw date-bucket expressions
  are acceptable for the chart aggregates (reporting precedent), but never to interpret `event_data`.
- **PHP 8 constructor promotion** in all new classes; **explicit return types** on all methods; `config()`
  not `env()`; named routes + `route()`.
- Use `php artisan make:` with `--no-interaction` for any scaffolding.

---

## Success Criteria

- [ ] `GET /telemetry` (`TelemetryDashboardController@index`) renders a standalone mobile-first SPA (own
      HTML shell; NOT `@extends('app')`, NOT `mobile-entry.flexible`/`ComponentBuilder`), gated by
      `['auth','verified','admin']`.
- [ ] `GET /api/telemetry/summary` and `GET /api/telemetry/trail` (`TelemetryApiController`) return JSON,
      BOTH admin-gated on the session/`web` stack (no Sanctum, not in `sync.php`/`api.php`).
- [ ] Section A: distinct-device total for the active `since` + Chart.js bar chart (CDN) with a working
      New/Cumulative/Active toggle that rescales the Y-axis without refetching; gridlines; mobile-capped
      buckets; bucketed by `created_at`.
- [ ] Section B: 20 devices/page, newest activity first, pages through all; IDs shortened with
      expand-to-full; null `device_id` shown as "no value".
- [ ] Section C: selected device's full ordered screen trail (unrolled from `event_data.events` for that
      one device, sorted by `ts`).
- [ ] Date filter: chips (`Since launch`/2026-09-01 default + `30d`/`7d`/`24h`/`12h`/`6h`/`3h`/`1h`/`All`)
      + native date input; default load = since launch, zero taps.
- [ ] All new PHP under `app/Telemetry/`; no migration, no model change, no change to the collection slice.
- [ ] No new npm/composer dependency (Chart.js via CDN).
- [ ] No tests written.
- [ ] No git commits made. Cleanup sweep clean.

---

## Do Not

- Do NOT commit or push.
- Do NOT run Pint.
- Do NOT run destructive database commands.
- Do NOT write tests.
- Do NOT modify `athlete_events` / `AthleteEvent` / the collection migration / `POST /api/sync/telemetry`.
- Do NOT interpret `event_data` outside the single-device trail.
- Do NOT leave any JSON endpoint ungated.
- Do NOT use `@extends('app')` / `ComponentBuilder` / `mobile-entry.flexible` / the legacy chart component.
- Do NOT add npm/composer dependencies, navigation links to the page, or a desktop layout.
- Do NOT put the data endpoints in `routes/sync.php`/`routes/api.php`, add `auth:sanctum`, or use the
  `api` middleware group — they are session-authed and belong in `routes/web.php` on the `web` stack.
- Do NOT put code outside `app/Telemetry/` (PHP), `resources/views/telemetry/` (view), `routes/web.php`.

---

## Post-Execution Retro (authored by the REVIEWER, not the executor)

> The executor must NOT fill this in — leave the `{placeholder}` values untouched. After the run, the human
> reviewer reconstructs this section from the commit trail at archive time. See
> `.kiro/steering/architect-workflow.md` → Post-Execution Review Protocol.

- **Attempts:** {1 (clean) / N (root cause of failures)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {what to add/change}
- **Steering updates needed:** {yes/no, what}
