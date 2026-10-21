# Prism ↔ Logger Sync — Logger Slice (Plan)

**Slice:** Logger side of the Prism↔Logger backend sync. **Branch:** `feature/prism-logger-integration`.
**Root design-of-record:** `squirby/docs/sync/prism-logger/design.md` (source of truth for all shared
decisions; this plan implements only Logger's slice). **Cross-app method:** per the root
`.kiro/steering/architect-workflow.md` "Cross-App Work / Feature slicing" — this slice is authored and
tested independently against Logger's own suite; it never reaches into the Prism repo; the wire shapes it
must agree on are duplicated INLINE below as its local source of truth; the cross-app contract/fixtures are
authored LAST at the root after both slices exist.

> **What this is.** The architecture reference for the Logger slice (why + what). The mechanical execution
> steps live in the per-milestone `-prompt.md` files, authored one at a time against fresh code per
> `logger/.kiro/steering/architect-workflow.md`. This plan names and orders the milestones and details
> Milestone 1; later milestones are authored from here as earlier ones land.

---

## Context

Prism (the programming studio) is a client-side React app with localStorage as its source of truth. It
needs a durable, gym-keyed cloud backup, and Logger is the backend. Prism's client-side sync machinery is
already built against a `RemoteRepository` interface backed today by a local stub; this slice builds the
**real server** that a future HTTP implementation of that interface will call. Logger already hosts the
Athlete↔Logger sync under `app/Sync/` and already supports Prism's Google OAuth (multi-client redirect
allowlist, shipped). This slice adds Prism's gym-keyed data storage + endpoints.

## Problem statement

Prism has no server-side persistence. Build it in Logger as a set of **gym-keyed, granular-row** stores
with **push (single-row upsert)** and **pull (indexed delta-since-cursor)** endpoints, reusing Logger's
existing sync infrastructure (Sanctum auth, sync throttles, `EnsureDeviceId` + `LogSyncRequest`
middleware, the centralized `api/sync/*` error shaping, the `app/Sync/` domain-folder convention).

## Architecture

### Guiding constraint (from the design-of-record §3)

**Storage is cheap; computation is expensive** (Forge Hobby, shared with the production DB). Therefore:
**every write is a single-row keyed upsert; every read is an indexed `updated_at > cursor` range scan.**
No read-modify-write of large documents, no server-side JSON parsing/merging. This is why storage is
**granular rows, not opaque blobs** — a blob backend would force read→parse→splice→serialize→write on
every push.

### The gym partition

- A `gyms` table, seeded with **The Cove** (`the-cove`) and **Flagship** (`flagship`). The gym `id` string
  is the **cross-app join key** and MUST match Prism's `src/constants/accessControl.js` (`GYM_DOMAINS`)
  byte-for-byte. Once shipped, a gym id is immutable (renaming orphans data).
- The `users` table is unchanged — `gyms` is a thin partition key added alongside it. Logger's existing
  auth and the Athlete sync depend on `users` as-is.
- **Trust-on-login (Phase 1 deferral):** any authenticated user may read/write any gym's data. Logger
  validates that the gym **exists**; it does NOT (yet) verify the user belongs to it. No membership pivot,
  no roles. This is a deliberate deferral recorded in the design-of-record; the real permission model is
  Phase 2 (PC-21). Every sync endpoint still requires `auth:sanctum` (a valid user must be present).

### Storage model — tables

Mirror the existing opaque-blob pattern (`athlete_blueprints` / `athlete_preferences`) for shape and
conventions, but keyed by **gym** and granular where there are addressable sub-entities. All new tables
live in migrations under `database/migrations/`; models in `app/Sync/Models/`.

| Table | Unique key | Columns (beyond key + timestamps) | Delete semantics |
|-------|-----------|-----------------------------------|------------------|
| `gyms` | `id` (string PK: `the-cove`, `flagship`) | `name` | seeded; never deleted |
| `prism_programs` | `(gym_id, program_uuid)` | `name`, `week_count`, `metadata` (json), `device_id` | **none** — programs are permanent |
| `prism_program_cells` | `(gym_id, program_uuid, cell_id)` | `text` (longtext), `device_id` | **none** — an emptied cell is `text = ''` |
| `prism_exercise_overlay` | `(gym_id, exercise_id)` | `record` (json), `device_id`, `deleted_at` | **soft-delete** — exercises can be removed |
| `prism_preferences` | `(gym_id)` | `data` (json), `device_id` | whole-value upsert |

**Timestamp precision (cursor-critical).** Every data table's `updated_at` is the delta-pull cursor basis,
so it MUST be **microsecond precision and server-stamped** — NOT the default second-resolution
`timestamps()`. Use `$table->timestamps(6)` (MySQL `TIMESTAMP(6)` / `DATETIME(6)`), and the write path
stamps `updated_at` server-side (Eloquent's automatic timestamping, which is server-clock, is correct —
do not accept a client-supplied `updated_at`). Second-resolution timestamps would let two edits in the
same wall-clock second share an instant, and a strict `>` cursor would then silently skip the second one.
See the `/changes` inclusive-boundary rule below.

Notes:
- `cell_id` is Prism's `w{week}d{day}` string. `program_uuid` is Prism's immutable program identity (a
  UUID string). `exercise_id` is Prism's stable exercise slug/id.
- `prism_exercise_overlay.record` is a small opaque JSON blob **at the row level** — correct here because
  it is a single indivisible record the server never interprets. `prism_preferences.data` is the same case.
- **Every data table carries `updated_at`** (standard `timestamps()`), which is the delta-pull cursor
  basis. `prism_exercise_overlay` additionally uses `deleted_at` as a tombstone (NOT Laravel SoftDeletes
  scoping — the delta pull MUST return tombstoned rows, so queries are explicit, not scope-hidden; see
  Risks).
- Gym id is a **string PK** on `gyms`; the data tables carry a `gym_id` string FK → `gyms.id`. (String key
  because the id is the cross-app vocabulary, not an autoincrement.)

### Endpoints

All under the existing `/api/sync` group (registered in `routes/sync.php`), with `auth:sanctum`,
`device-id`, and `log-sync-request`, plus **Prism-dedicated throttles** (see below — the existing
`sync-per-user` 10/min bucket is too small for a 5-second poll and must NOT be reused for the poll).
Controllers in `app/Sync/Controllers/`, business logic in `app/Sync/Actions/`.

| Method + path | Controller@action | Throttle | Does |
|---------------|-------------------|----------|------|
| `POST /prism/{gym}/programs/{program_uuid}` | `PrismProgramController@storeProgram` | `prism-write` | upsert one `prism_programs` row (metadata) |
| `POST /prism/{gym}/programs/{program_uuid}/cells` | `PrismProgramController@storeCells` | `prism-write` | upsert N `prism_program_cells` rows (small batch of changed cells for one program) |
| `POST /prism/{gym}/exercises` | `PrismExerciseController@store` | `prism-write` | upsert / tombstone N `prism_exercise_overlay` rows |
| `POST /prism/{gym}/preferences` | `PrismPreferencesController@store` | `prism-write` | whole-value upsert of the `prism_preferences` row |
| `GET  /prism/{gym}/changes?since=<cursor>` | `PrismChangesController@index` | `prism-poll` | delta pull: all gym rows with `updated_at >= since`; `since` absent ⇒ full gym state (cold start) |

- `{gym}` is the gym id in the path; the controller validates it exists in `gyms` (404 if not) — the only
  gym check in Phase 1 (trust-on-login otherwise).
- **Rate limits (cursor- and poll-aware).** Prism polls every ~5s = ~12 req/min per tab, so the poll must
  not share Athlete's 10/min `sync-per-user` bucket (it would 429 within a minute). Add dedicated limiters
  in `config/rate_limits.php` + `AppServiceProvider`: `prism-poll` keyed per user at **≥ 60/min** (headroom
  for multiple tabs/windows) and `prism-write` keyed per user at a generous write ceiling (e.g. 120/min).
  These are read-mostly/cheap-write endpoints, so the ceilings protect the box without starving a normal
  session.
- **Cursor:** one per gym, with an **inclusive boundary**. The `/changes` query is `updated_at >= since`
  (NOT strict `>`), and the response `cursor` is the max `updated_at`/`deleted_at` across the returned
  rows, following the Athlete `ChangesController` high-water-mark pattern (never rewind behind the incoming
  `since`). The inclusive boundary re-sends the row exactly at the cursor instant; that is harmless because
  the client de-duplicates by value (per-cell text diff), and it is what prevents a same-microsecond second
  write from being skipped. Cold start = `since` absent ⇒ return everything for the gym.
- **Push batches are small and bounded** (a handful of changed cells / records per request — the surgical
  model), NOT 500-row bulk like Athlete's log batch. Each is a single `upsert()` call on the model.

### Wire shapes (duplicated inline — local source of truth for this slice)

> Per the isolation rule, these shapes are copied here from the design-of-record; this slice does not read
> the root doc or the Prism repo at author time. The root contract reconciles them LAST.

```
// POST /prism/{gym}/programs/{program_uuid}  — request
{ "name": string, "week_count": int, "metadata": object, "device_id": string }
// response: { "status": "ok", "updated_at": iso8601 }

// POST /prism/{gym}/programs/{program_uuid}/cells  — request
{ "cells": [ { "cell_id": "w{week}d{day}", "text": string } ], "device_id": string }
// response: { "status": "ok", "updated_at": iso8601 }   // max updated_at of the upserted cells

// POST /prism/{gym}/exercises  — request
{ "upserts": [ { "exercise_id": string, "record": object } ],
  "removals": [ { "exercise_id": string } ],
  "device_id": string }
// response: { "status": "ok", "updated_at": iso8601 }

// POST /prism/{gym}/preferences  — request
{ "data": object, "device_id": string }
// response: { "status": "ok", "updated_at": iso8601 }

// GET /prism/{gym}/changes?since=<iso8601|absent>  — response
{ "status": "ok",
  "cursor": iso8601,
  "programs":  [ { "program_uuid": string, "name": string, "week_count": int, "metadata": object, "updated_at": iso8601 } ],
  "cells":     [ { "program_uuid": string, "cell_id": string, "text": string, "updated_at": iso8601 } ],
  "exercises": [ { "exercise_id": string, "record": object|null, "deleted_at": iso8601|null, "updated_at": iso8601 } ],
  "preferences": { "data": object, "updated_at": iso8601 } | null }
```

All error responses use Logger's centralized `api/sync/*` shaping: `{ "status": "error", "message": … }`
with the matching HTTP code (422 validation, 401 auth, 429 throttle).

## Milestones (roadmap)

Carved as independently-landable, same-session slices. Detail is authored one prompt at a time against
fresh code.

- **M1 — Gym foundation + storage schema.** The `gyms` table + seed (The Cove, Flagship), the four data
  tables (migrations + `app/Sync/Models/` models), `$fillable`/`casts()`, forward-only + reversible
  `down()`. No endpoints yet. Checkpoint: migration runs; a model unit test round-trips each table.
  **(Detailed below.)**
- **M2 — Push endpoints + actions + Prism throttles.** `PrismProgramController` (program + cells),
  `PrismExerciseController`, `PrismPreferencesController`; `app/Sync/Actions/` upsert actions; routes in
  `routes/sync.php` with the new `prism-write` / `prism-poll` throttles (registered in
  `config/rate_limits.php` + `AppServiceProvider`, sized per the rate-limit note above); gym-exists
  validation. Feature tests per endpoint (upsert creates, re-upsert updates, tombstone sets `deleted_at`,
  unknown gym → 404, unauthenticated → 401) plus a throttle test proving a sustained 5s-poll cadence does
  NOT get 429'd under `prism-poll`.
- **M3 — The `/changes` delta pull.** `PrismChangesController` with the one-cursor-per-gym high-water-mark
  (mirroring Athlete's `ChangesController`), an **inclusive `updated_at >= since` boundary**, cold-start
  (absent `since`) = full dump, tombstones included. Feature tests: delta returns rows at-or-after `since`;
  **two cells written in the same microsecond-second are BOTH delivered** (the inclusive-boundary
  regression test — microsecond precision + `>=`); cold start returns all; cursor never rewinds; removed
  overlay rows appear as tombstones.
- **M4 — Cost + hardening pass.** Confirm every write is a single-row upsert and every read is indexed
  (add the `(gym_id, updated_at)` indexes); confirm no N+1 in `/changes`; dead-code sweep; full suite.

> The real HTTP client that calls these endpoints is the **Prism slice**
> (`prism/docs/plans/prism-logger-sync-plan.md`), authored independently. The reconciling **root contract**
> in `squirby/contracts/` is authored LAST, after both slices exist.

---

## Milestone 1 — Gym foundation + storage schema (detailed)

**Goal:** the gym partition and the four granular stores exist and round-trip, with zero endpoints and zero
impact on existing web UI or Athlete sync.

### Files

New migrations (forward-only, reversible `down()`, additive — safe to auto-apply on Forge deploy):
- `database/migrations/{ts}_create_gyms_table.php`
- `database/migrations/{ts}_create_prism_programs_table.php`
- `database/migrations/{ts}_create_prism_program_cells_table.php`
- `database/migrations/{ts}_create_prism_exercise_overlay_table.php`
- `database/migrations/{ts}_create_prism_preferences_table.php`
- `database/seeders/GymSeeder.php` (or a seed inside the `gyms` migration — decide in the prompt; seeding
  the two known gyms must be idempotent and run in all environments the sync touches).

New models in `app/Sync/Models/` (mirroring `AthleteBlueprint` — `$fillable`, `casts()`, no ActivityLog):
- `Gym.php` — `$incrementing = false`, `$keyType = 'string'`, `$fillable = ['id', 'name']`.
- `PrismProgram.php` — `casts: ['metadata' => 'array']`.
- `PrismProgramCell.php`.
- `PrismExerciseOverlayRecord.php` — `casts: ['record' => 'array']`. Note: does NOT use the Laravel
  `SoftDeletes` trait (tombstone is a plain `deleted_at` column the delta query reads explicitly; see
  Risks).
- `PrismPreference.php` — `casts: ['data' => 'array']`.

### Schema detail (exact)

All data tables use **`timestamps(6)`** (microsecond precision) — NOT the default second-resolution
`timestamps()` — because `updated_at` is the delta cursor basis (see the cursor rule). Timestamps are
server-stamped (Eloquent automatic timestamping); never accept a client `updated_at`.

```
gyms:                      id (string, PK), name (string), timestamps          -- (precision moot; no cursor)
prism_programs:            id, gym_id (string, FK gyms.id, cascade), program_uuid (string),
                           name (string), week_count (unsignedSmallInt), metadata (json nullable),
                           device_id (string 36 nullable), timestamps(6)
                           UNIQUE (gym_id, program_uuid); INDEX (gym_id, updated_at)
prism_program_cells:       id, gym_id (string, FK), program_uuid (string), cell_id (string 16),
                           text (longText), device_id (string 36 nullable), timestamps(6)
                           UNIQUE (gym_id, program_uuid, cell_id); INDEX (gym_id, updated_at)
prism_exercise_overlay:    id, gym_id (string, FK), exercise_id (string), record (json nullable),
                           device_id (string 36 nullable), deleted_at (timestamp(6) nullable), timestamps(6)
                           UNIQUE (gym_id, exercise_id); INDEX (gym_id, updated_at)
prism_preferences:         id, gym_id (string, FK, UNIQUE), data (json), device_id (string 36 nullable),
                           timestamps(6)
```

### Checkpoint

`php artisan test --parallel tests/Unit/Sync/Prism/` — a model round-trip test per table (create → read
→ update, casts work, the unique key upserts rather than duplicates). Plus `php artisan migrate` run for
real against the dev DB (per `project-conventions.md` — a migration is not done until it has RUN) and
`migrate:status` shows all five **Ran**, and the `gyms` seed produced exactly the two rows.

## Risks (each with a concrete mitigation)

- **Tombstone vs Laravel SoftDeletes (highest-risk).** The overlay needs removals to *appear* in the delta
  pull. Laravel's `SoftDeletes` trait auto-scopes `deleted_at IS NULL`, which would **hide** tombstones
  from the pull — the opposite of what's needed. Mitigation: do NOT use the `SoftDeletes` trait on
  `PrismExerciseOverlayRecord`; `deleted_at` is a plain nullable column, and the `/changes` query selects
  rows by `updated_at > since` regardless of `deleted_at`, returning tombstones explicitly. A test asserts
  a removed record appears in the delta with a non-null `deleted_at`.
- **Gym id must match Prism byte-for-byte.** A mismatch silently partitions data into the wrong bucket.
  Mitigation: the `GymSeeder` hardcodes `the-cove` / `flagship` with a comment citing Prism's
  `accessControl.js` as the authority; the root contract test (authored last) asserts the vocabulary
  matches across both apps.
- **Forward-only migrations auto-run on deploy.** Per `project-conventions.md`, a merged migration runs
  against prod MySQL on the Forge deploy. Mitigation: all five migrations are additive (new tables only,
  no changes to existing tables), each has a clean reversible `down()` (`dropIfExists`), and none touches
  `users`/`lift_logs`/`exercises` — zero blast radius on existing behavior.
- **Cost / request-path.** Mitigation (M4): the `(gym_id, updated_at)` composite index makes the delta a
  range scan; pushes are single-row upserts; `/changes` eager-structures its selects to avoid N+1. No
  per-request JSON parsing of large documents anywhere.
- **Existing web UI + Athlete sync untouched.** Mitigation: all code is additive under `app/Sync/` + new
  tables; no shared model or existing route is modified. Run the full suite at M2/M4 to confirm.

## Constraints

- All new code in `app/Sync/` (controllers/actions/models) and `routes/sync.php`; nothing in
  `app/Http/Controllers/`, `routes/api.php`, or `routes/web.php`.
- Eloquent only (no `DB::` facade) except where a raw builder is genuinely required; constructor promotion;
  explicit return types; PHPUnit (no Pest); `php artisan test --parallel`.
- No new composer dependencies.
- Never modify a migration that has run; forward-only; reversible `down()`.
- This slice does NOT read the Prism repo or the root `contracts/` at author time — shared shapes are the
  inline copies above.

## Success criteria (slice-level)

- [ ] Five migrations exist, run clean against the dev DB, and have reversible `down()`.
- [ ] `gyms` seeded with exactly `the-cove` and `flagship`.
- [ ] Push endpoints upsert single rows keyed by the composite keys; re-push updates in place (no dupes).
- [ ] Exercise removal writes a `deleted_at` tombstone that the delta pull returns.
- [ ] `updated_at` is `timestamps(6)` microsecond precision, server-stamped; `/changes` uses an inclusive
      `>= since` boundary; two same-second writes are both delivered (regression test); absent `since`
      returns full gym state; the cursor is a non-rewinding high-water mark.
- [ ] Dedicated `prism-poll` (≥60/min) and `prism-write` throttles exist; a sustained 5s poll cadence does
      not 429.
- [ ] Unknown gym → 404; unauthenticated → 401; validation failure → 422 — all via the centralized shaping.
- [ ] Full suite green (`php artisan test --parallel`); existing web UI + Athlete sync unaffected.
- [ ] No git commits by the executor; no Pint; no destructive DB commands.

## Related

- `squirby/docs/sync/prism-logger/design.md` — the cross-app design-of-record (source of truth).
- `prism/docs/plans/prism-logger-sync-plan.md` — the sibling Prism slice (authored independently).
- Existing opaque-blob precedent to mirror: `app/Sync/Models/AthleteBlueprint.php`,
  `app/Sync/Controllers/{Blueprint,Preferences}Controller.php`,
  `database/migrations/2026_06_15_000002_create_athlete_preferences_table.php`.
- Existing delta precedent to mirror for `/changes`: `app/Sync/Controllers/ChangesController.php`
  (cursor high-water mark).
- `.kiro/steering/project-conventions.md`, `.kiro/steering/sync-api-context.md`,
  `.kiro/steering/architect-workflow.md`.
