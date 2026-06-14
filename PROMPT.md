# Sync API Implementation — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this feature correctly.

### 1. Steering (project rules — always follow)

```
.kiro/steering/git-workflow.md          → commit freely, NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files to never touch, bash safety, artisan safety
.kiro/steering/project-conventions.md   → architectural principles for this project
.kiro/steering/laravel-boost.md         → Laravel framework conventions (PHP style, testing, Pint)
```

### 2. Feature Spec (what to build)

```
.kiro/steering/sync-api-context.md      → project context, data model awareness, existing patterns
.kiro/specs/squirby-sync-api/requirements.md → requirements (23 requirements with acceptance criteria)
.kiro/specs/squirby-sync-api/design.md       → architecture, file map, components, data models, development principles
.kiro/specs/squirby-sync-api/tasks.md        → ordered implementation plan with dependencies
```

### 3. Existing Code to Understand (read before modifying)

```
app/Models/LiftLog.php                  → the main model you're extending
app/Models/LiftSet.php                  → the set model you're extending
app/Models/Exercise.php                 → exercise resolution target (canonical_name, title, aliases)
app/Models/ExerciseAlias.php            → alias system for name matching
app/Models/User.php                     → adding HasApiTokens trait
app/Actions/LiftLogs/CreateLiftLogAction.php → existing pattern for Actions (follow this style)
app/Services/ExerciseTypes/CardioExerciseType.php → the strategy you'll modify for the distance migration
app/Services/Charts/CardioProgressionChartGenerator.php → reads cardio data for charts
app/Events/LiftLogCompleted.php         → event to dispatch after storing a log
routes/api.php                          → currently empty (don't use this, create routes/sync.php)
config/cors.php                         → add api/sync/* to paths
config/logging.php                      → add sync_requests channel
bootstrap/app.php                       → register middleware and route file here (Laravel 12)
```

## Execution Plan

Follow the tasks in `.kiro/specs/squirby-sync-api/tasks.md` in order. Each numbered section is a phase. Complete each phase before moving to the next.

### Phase order:

1. **Schema & models** (tasks 1.1–1.8) — migrations, new models, update existing models
2. **Infrastructure** (tasks 2.1–2.5) — middleware, CORS, routes, rate limiters, Sanctum install
3. **Data migration & upstream changes** (tasks 3.1–3.6) — cardio distance migration, strategy updates, verify tests
4. **Checkpoint** — run `php artisan test --parallel` to verify nothing broke
5. **Services** (tasks 5.1, 5.4) — SetFieldMapper and ExerciseResolverService
6. **Actions** (tasks 7.1–7.2) — StoreSyncLogAction and DeleteSyncLogAction
7. **Controllers** (tasks 8.1–8.5) — all 5 controllers
8. **Checkpoint** — verify routes respond with `php artisan route:list --path=api/sync`
9. **Error handling & logging** (tasks 10.1–10.5) — exception handler, request logging, CLI commands, operations doc
10. **Tests** (tasks 11.1–11.6) — unit tests, smoke tests, cardio migration regression tests
11. **Final checkpoint** — run full test suite: `php artisan test --parallel`

### HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command. The user handles all version control.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form. It reformats the entire codebase and creates massive noise. Write clean code yourself — follow the style of sibling files.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`.

### Implementation rules:

- **All new code goes in `app/Sync/`** — never put sync code in `app/Http/Controllers/`, `app/Services/`, etc.
- **Routes go in `routes/sync.php`** — register in bootstrap/app.php, not in routes/api.php
- **Always use `php artisan test --parallel` when running tests.** This project has 2000+ tests; parallel execution takes ~11 seconds vs 100+ sequential. Never run without `--parallel`.
- **To run a specific test file:** `php artisan test --parallel tests/Feature/SomeTest.php`
- **To filter by name:** `php artisan test --parallel --filter=testName`
- **The cardio migration (phase 3) is the riskiest part** — it changes how existing data is read. Run the full test suite after this phase. If tests fail, fix them before proceeding.
- **Never use `DB::` facade** — use Eloquent models and relationships
- **Use PHP 8 constructor promotion** in all new classes
- **Add explicit return types** to all methods
- **Use `php artisan make:migration` and `php artisan make:test --phpunit`** to generate files, then move/rename as needed into the `app/Sync/` structure

### What success looks like:

1. `php artisan migrate` runs cleanly (all new migrations + data migration)
2. `php artisan test --parallel` passes (all existing tests + all new tests)
3. `php artisan route:list --path=api/sync` shows 7 routes
4. The operations doc exists at `docs/sync-api-operations.md`
5. All code is in `app/Sync/` (except routes, migrations, config changes)
6. No existing web UI functionality is broken
