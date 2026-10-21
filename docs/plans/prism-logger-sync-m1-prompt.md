# Prism ↔ Logger Sync — Milestone 1 (Gym foundation + storage schema) — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this milestone correctly.

### 1. Steering (project rules — always follow)
```
.kiro/steering/git-workflow.md          → commit freely, NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files to never touch, bash safety, artisan safety
.kiro/steering/project-conventions.md   → architectural principles (forward-only migrations, shared-model safety, "a migration is not done until it has RUN")
.kiro/steering/laravel-boost.md         → Laravel framework conventions (PHP style, testing, Pint)
.kiro/steering/sync-api-context.md      → sync endpoint inventory + design constraints for app/Sync/
```

### 2. Feature Plan (what to build)
```
docs/plans/prism-logger-sync-plan.md    → architecture reference. Implement the "Milestone 1 — Gym foundation + storage schema (detailed)" section ONLY. Later milestones (M2–M4) are out of scope for this prompt.
```

### 3. Existing Code to Understand (read before modifying)
```
app/Sync/Models/AthleteBlueprint.php                  → the opaque-blob model pattern to mirror: namespace App\Sync\Models, explicit $table, $fillable array, casts() METHOD (not $casts property), no ActivityLog trait
app/Sync/Models/AthletePreference.php                 → second mirror example (json cast, device_id column)
database/migrations/2026_06_15_000002_create_athlete_preferences_table.php  → migration shape to mirror: up() with Schema::create + columns, down() with Schema::dropIfExists
database/seeders/DatabaseSeeder.php                   → how seeders are registered/called in this app
```

### 4. Reference (already implemented — don't rebuild, just understand)
```
app/Sync/Models/AthleteEvent.php         → a third app/Sync model for convention reference
tests/Unit/Sync/                          → existing Sync unit tests; match their structure/namespace for the new model round-trip tests
```

---

## What You're Building

Prism is a client-side React programming studio with localStorage as its source of truth. It needs a durable, gym-keyed cloud backup, and Logger is the backend. This milestone builds **only the storage foundation** — the gym partition and four granular data stores — with **zero endpoints** and **zero impact** on existing web UI or the Athlete↔Logger sync.

You will add one `gyms` partition table (seeded with two known gyms), four granular Prism data tables (programs, program cells, exercise overlay, preferences), the Eloquent models for each under `app/Sync/Models/`, and a model round-trip unit test per table. Every table except `gyms` uses **microsecond-precision, server-stamped** timestamps because `updated_at` is the delta-pull cursor basis in a later milestone — second resolution would let two edits in one wall-clock second collide and be silently skipped by the cursor.

End state: five additive, forward-only migrations that run clean against the dev DB and have reversible `down()`; `gyms` seeded with exactly `the-cove` and `flagship`; five models with correct `$fillable` and `casts()`; and a passing unit test per table proving create → read → update and that the composite unique key upserts rather than duplicates. No controllers, no routes, no throttles — those are Milestone 2.

---

## Execution Plan

Implement the "Milestone 1" section of `docs/plans/prism-logger-sync-plan.md`. Phases:

### Phase 1 — Migrations
Create the five migrations via `php artisan make:migration --no-interaction`, in this order (later tables FK `gyms.id`):
1. `create_gyms_table`
2. `create_prism_programs_table`
3. `create_prism_program_cells_table`
4. `create_prism_exercise_overlay_table`
5. `create_prism_preferences_table`

Exact schema (every data table uses `$table->timestamps(6)` — microsecond precision — NOT `timestamps()`; `gyms` may use plain `timestamps()` since it carries no cursor):

```
gyms:
  $table->string('id')->primary();
  $table->string('name');
  $table->timestamps();

prism_programs:
  $table->id();
  $table->string('gym_id');
  $table->string('program_uuid');
  $table->string('name');
  $table->unsignedSmallInteger('week_count');
  $table->json('metadata')->nullable();
  $table->string('device_id', 36)->nullable();
  $table->timestamps(6);
  $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
  $table->unique(['gym_id', 'program_uuid']);
  $table->index(['gym_id', 'updated_at']);

prism_program_cells:
  $table->id();
  $table->string('gym_id');
  $table->string('program_uuid');
  $table->string('cell_id', 16);
  $table->longText('text');
  $table->string('device_id', 36)->nullable();
  $table->timestamps(6);
  $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
  $table->unique(['gym_id', 'program_uuid', 'cell_id']);
  $table->index(['gym_id', 'updated_at']);

prism_exercise_overlay:
  $table->id();
  $table->string('gym_id');
  $table->string('exercise_id');
  $table->json('record')->nullable();
  $table->string('device_id', 36)->nullable();
  $table->timestamp('deleted_at', 6)->nullable();
  $table->timestamps(6);
  $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
  $table->unique(['gym_id', 'exercise_id']);
  $table->index(['gym_id', 'updated_at']);

prism_preferences:
  $table->id();
  $table->string('gym_id')->unique();
  $table->json('data');
  $table->string('device_id', 36)->nullable();
  $table->timestamps(6);
  $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
```

Every `down()` is `Schema::dropIfExists('<table>')`. Drop in reverse dependency order if you combine any — each migration drops its own table.

### Phase 2 — Gym seed
Create `database/seeders/GymSeeder.php`. Seed exactly two rows, **idempotently** (use `Gym::updateOrCreate(['id' => ...], ['name' => ...])` so re-running does not duplicate or error):
- `id = 'the-cove'`, `name = 'The Cove'`
- `id = 'flagship'`, `name = 'Flagship'`

Add a PHPDoc/class comment on `GymSeeder` stating these ids are the cross-app join key and MUST match Prism's `src/constants/accessControl.js` (`GYM_DOMAINS`) byte-for-byte; renaming a shipped gym id orphans data. Register `GymSeeder` in `database/seeders/DatabaseSeeder.php` so it runs in every environment the sync touches (match how the other seeders are invoked there).

### Phase 3 — Models
Create five models under `app/Sync/Models/` (namespace `App\Sync\Models`), mirroring `AthleteBlueprint` — explicit `$table`, `$fillable`, `casts()` METHOD, no ActivityLog trait, no `SoftDeletes` trait anywhere:

- `Gym.php` — `protected $table = 'gyms';` `public $incrementing = false;` `protected $keyType = 'string';` `$fillable = ['id', 'name']`. No casts needed.
- `PrismProgram.php` — `$table = 'prism_programs'`; `$fillable = ['gym_id', 'program_uuid', 'name', 'week_count', 'metadata', 'device_id']`; `casts(): ['metadata' => 'array']`.
- `PrismProgramCell.php` — `$table = 'prism_program_cells'`; `$fillable = ['gym_id', 'program_uuid', 'cell_id', 'text', 'device_id']`; no casts.
- `PrismExerciseOverlayRecord.php` — `$table = 'prism_exercise_overlay'`; `$fillable = ['gym_id', 'exercise_id', 'record', 'device_id', 'deleted_at']`; `casts(): ['record' => 'array', 'deleted_at' => 'datetime']`. **Do NOT use the Laravel `SoftDeletes` trait** — `deleted_at` is a plain column the delta query will read explicitly in M3; the trait would auto-scope `deleted_at IS NULL` and hide tombstones.
- `PrismPreference.php` — `$table = 'prism_preferences'`; `$fillable = ['gym_id', 'data', 'device_id']`; `casts(): ['data' => 'array']`.

### Phase 4 — Checkpoint (migrate for real + unit tests)
1. Run the migrations against the real dev DB: `php artisan migrate --no-interaction`. Then `php artisan migrate:status` and confirm all five new migrations show **Ran** (a passing `RefreshDatabase` test does NOT prove the migration ran — this project requires the real run).
2. Confirm the seed produced exactly the two gyms (e.g. `php artisan db:seed --class=GymSeeder --no-interaction` is idempotent and `gyms` holds exactly `the-cove` and `flagship`).
3. Create one model round-trip unit test per table under `tests/Unit/Sync/Prism/` (`php artisan make:test --phpunit --no-interaction Sync/Prism/<Name>Test`). Each test asserts: create persists; read returns the row with casts applied (array casts hydrate `metadata`/`record`/`data` as arrays); update mutates in place; and an upsert on the composite unique key updates rather than inserting a duplicate (assert row count stays 1 after a second upsert with the same key).
4. Run `php artisan test --parallel tests/Unit/Sync/Prism/` — all green.
5. Run the full suite once to confirm zero regressions: `php artisan test --parallel`.

---

## HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`. Forward-only: `php artisan migrate` only.

---

## Implementation Rules

- **All new models go in `app/Sync/Models/`** (namespace `App\Sync\Models`). Nothing in `app/Models/`, `app/Http/`, or elsewhere.
- **No controllers, routes, throttles, or actions in this milestone** — those are Milestone 2. Scope is schema + models + seed + tests only.
- **Migrations are forward-only and additive.** New tables only; do NOT touch `users`, `lift_logs`, `exercises`, or any existing table. Each migration has a reversible `down()` (`dropIfExists`). Never modify a migration that has run.
- **Timestamps are server-stamped by Eloquent.** Never accept or write a client-supplied `updated_at`. Data tables use `timestamps(6)`.
- **Casts live in a `casts()` method**, matching `AthleteBlueprint` — not a `$casts` property.
- **No `SoftDeletes` trait** on `PrismExerciseOverlayRecord` or anywhere else.
- **Never use `DB::` facade** in models/tests — use Eloquent. (The seeder uses `Gym::updateOrCreate`.)
- **Use PHP 8 constructor promotion** and **explicit return types** in any new class/method.
- **PHPUnit only** (no Pest). Always run tests with `php artisan test --parallel`.
- **No new composer dependencies.**

---

## Success Criteria

- [ ] Five migrations exist, run clean via `php artisan migrate`, and `php artisan migrate:status` shows all five **Ran**.
- [ ] Each migration has a reversible `down()` (`Schema::dropIfExists`).
- [ ] `gyms` is seeded with exactly two rows: `the-cove` / `The Cove` and `flagship` / `Flagship`; re-running `GymSeeder` does not duplicate or error.
- [ ] `GymSeeder` carries the comment citing Prism's `accessControl.js` as the id authority.
- [ ] Five models exist under `app/Sync/Models/` with correct `$table`, `$fillable`, and `casts()`; `Gym` has `$incrementing = false` + `$keyType = 'string'`; no model uses `SoftDeletes`.
- [ ] Data tables use `timestamps(6)`; `prism_exercise_overlay` has a plain nullable `deleted_at` (not the trait).
- [ ] Composite unique keys exist (`(gym_id, program_uuid)`, `(gym_id, program_uuid, cell_id)`, `(gym_id, exercise_id)`, unique `gym_id` on preferences) and `(gym_id, updated_at)` indexes exist on the four data tables.
- [ ] One model round-trip unit test per table under `tests/Unit/Sync/Prism/`, all passing, each proving upsert-on-unique-key updates in place (no duplicate row).
- [ ] `php artisan test --parallel tests/Unit/Sync/Prism/` green.
- [ ] Full suite green (`php artisan test --parallel`) — existing web UI + Athlete sync unaffected.
- [ ] No git commits made; no Pint run; no new composer dependencies.

---

## Do Not

- Do NOT commit or push.
- Do NOT run Pint.
- Do NOT run destructive database commands (`migrate:fresh`, `migrate:reset`, `db:wipe`).
- Do NOT build any endpoint, route, controller, action, or throttle — that is Milestone 2.
- Do NOT use the `SoftDeletes` trait on `PrismExerciseOverlayRecord` (it would hide tombstones from the future delta pull).
- Do NOT use the default second-resolution `timestamps()` on the four data tables — use `timestamps(6)`.
- Do NOT modify any existing table or any migration that has already run.
- Do NOT read the Prism repo or the root `squirby/contracts/` — the gym ids and wire shapes in the plan are this slice's local source of truth.
- Do NOT put code outside `app/Sync/Models/`, `database/migrations/`, `database/seeders/`, or `tests/Unit/Sync/Prism/`.

---

## Post-Execution Retro (authored by the REVIEWER, not the executor)

> The executor must NOT fill this in — leave the `{placeholder}` values untouched. After the run, the human reviewer reconstructs this section from the commit trail at archive time and moves both plan + prompt to `completed/`. See `.kiro/steering/architect-workflow.md` → Post-Execution Review Protocol.

- **Attempts:** {1 (clean) / N (root cause of failures)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {what to add/change}
- **Steering updates needed:** {yes/no, what}
