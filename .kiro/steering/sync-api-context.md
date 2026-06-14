---
inclusion: manual
---

<!--
  WHY THIS FILE EXISTS:
  
  This steering file provides project-specific context for an LLM implementing
  the Sync API feature (spec at .kiro/specs/squirby-sync-api/). It is NOT auto-included
  in Kiro sessions because it's only relevant when actively working on the sync API.
  
  It is also intended to be read by a separate LLM (e.g., Gemini, Claude in another
  tool) that operates inside this Laravel project without awareness of the broader
  product ecosystem. This file gives that LLM enough context to implement correctly
  without needing access to sibling projects.
-->

# Sync API — Project Context

## What this app is

A fitness logging web application used by ~15 athletes and 2 coaches at a single gym. Athletes log exercises (sets, reps, weight), track personal records (PRs), follow each other, and celebrate PRs via a social feed. Coaches manage exercises and review athlete progress.

The app has a server-rendered Blade front-end (Alpine.js + Tailwind) and uses session-based auth (Laravel Breeze). There is no existing API — `routes/api.php` is empty.

## The Sync API

An external front-end application (a React app, not part of this codebase) needs to durably store and retrieve workout logs, personalization data, and preferences via a REST API. The full spec lives at:

- `.kiro/specs/squirby-sync-api/requirements.md`
- `.kiro/specs/squirby-sync-api/design.md`
- `.kiro/specs/squirby-sync-api/tasks.md`

**Read all three before implementing.**

## Key architectural rules

1. **All sync code lives in `app/Sync/`** — controllers, actions, services, models, middleware, commands. Do not scatter sync-related files into `app/Http/Controllers/`, `app/Services/`, etc.
2. **Do not modify existing web UI behavior.** The web UI (Blade views, existing controllers, existing routes) must continue to work unchanged. Sync code is additive only.
3. **Routes live in `routes/sync.php`**, registered separately in the RouteServiceProvider. Not in `routes/api.php` or `routes/web.php`.
4. **Sanctum is used for API auth only.** The web UI continues to use session auth. Adding `HasApiTokens` to the User model is the only change to existing auth.
5. **No new composer dependencies without approval** (exception: `laravel/sanctum` is pre-approved for this feature).

## Data model awareness

### Existing tables you'll interact with

- `users` — name, email, password, weight_unit. Add `HasApiTokens` trait.
- `exercises` — title, canonical_name, exercise_type (regular/bodyweight/banded_*/cardio/static_hold), user_id (null = global). Has aliases via `exercise_aliases`.
- `lift_logs` — exercise_id, user_id, logged_at, comments, workout_id, is_pr, pr_count. Soft deletes.
- `lift_sets` — lift_log_id, weight, unit, reps, time, band_color, notes. Soft deletes.
- `personal_records` — user_id, exercise_id, lift_log_id, pr_type, value, previous_pr_id.

### The cardio hack (being fixed in this feature)

Currently, cardio exercises store distance in the `reps` column (e.g., 500m = `reps: 500`) and force `weight: 0`. The `CardioExerciseType` strategy interprets `reps` as meters at display time.

This feature adds a proper `distance` column to `lift_sets` and migrates existing cardio data out of `reps`. After migration, `CardioExerciseType` reads from `distance` instead of `reps`.

### ExerciseType strategy pattern

Each exercise type (regular, bodyweight, banded_resistance, banded_assistance, cardio, static_hold) has a strategy class in `app/Services/ExerciseTypes/`. These strategies handle:
- Validation rules for the web UI logging form
- Data processing (normalize input for storage)
- Display formatting
- PR detection metrics
- Chart generation

The sync API does NOT reuse these strategies for validation — it has its own `SetFieldMapper` service. But the strategies are affected by the cardio migration (they need to read `distance` instead of `reps`).

### PR detection

PR detection runs automatically when a `LiftLogCompleted` event is dispatched. The sync API should dispatch this event after storing a log. PR detection is not the sync API's responsibility — it piggybacks on the existing event system.

## Conventions specific to this project

- **Actions pattern:** Business logic lives in Action classes (see `app/Actions/LiftLogs/CreateLiftLogAction.php` for the pattern). Actions are injected via constructor, have a single `execute()` method.
- **Exercise matching:** The existing `ExerciseMatchingService` does fuzzy matching for the web UI's WOD parser. The sync API uses a simpler, deterministic `ExerciseResolverService` (exact canonical_name → title → alias → auto-create).
- **Soft deletes everywhere:** LiftLog, LiftSet, Exercise, ExerciseAlias all use SoftDeletes. Always scope queries to exclude trashed records.
- **Activity logging:** Models use Spatie ActivityLog trait. New models in `app/Sync/` do not need this (they're simple data stores).
- **Unit handling:** `lift_sets.unit` stores the weight unit per set (lbs or kg). The user's preference is on `users.weight_unit`. Both can change independently — a user might switch mid-session.

## Testing conventions

- PHPUnit only (no Pest). Use `php artisan make:test --phpunit`.
- Run tests with `php artisan test --filter=TestName`.
- Use model factories for test data.
- Run `vendor/bin/pint --dirty` before finalizing.
- Tests for sync code go in `tests/Unit/Sync/` and `tests/Feature/Sync/`.
