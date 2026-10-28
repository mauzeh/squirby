# Interference-Dismissal Sync (Logger slice) — Prompt for Antigravity CLI

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
docs/plans/interference-dismissal-sync.md   → architecture, file inventory, wire shapes, constraints
```

### 3. Existing Code to Understand (read before modifying) — THE PATTERN TO COPY
```
app/Sync/Models/PrismExerciseOverlayRecord.php          → the granular + tombstone model to mirror
app/Sync/Actions/UpsertPrismExercisesAction.php         → the upsert+tombstone action to copy one-to-one
app/Sync/Controllers/PrismExerciseController.php         → the write controller shape to mirror
app/Sync/Controllers/PrismChangesController.php          → the /changes delta — add a SIXTH section here
app/Sync/Controllers/Concerns/ValidatesGym.php           → findGymOrFail gym validation
database/migrations/*create_prism_exercise_overlay*.php  → the migration to copy (timestamps(6), deleted_at)
routes/sync.php                                          → where the new route goes (prism-write group)
app/Sync/Requests/*                                      → FormRequest conventions for prism writes
```

---

## What You're Building

A sixth kind of gym-scoped Prism sync state: **interference dismissals**. A dismissal is an opaque
`resolution_key` string the client supplies; Logger stores it in a new granular table and never parses it.
A present row (`deleted_at = null`) means dismissed; a tombstoned row (`deleted_at` set) means un-dismissed
(reset). You add the table, an upsert/tombstone action, a write endpoint, and a sixth section to the
`/changes` delta — every piece modeled one-to-one on the existing `prism_exercise_overlay` machinery.

Logger stays a dumb store: single-row keyed upserts and indexed `updated_at >= cursor` range scans. No
interference computation. No parsing of the key.

---

## Execution Plan

Follow the phases in `docs/plans/interference-dismissal-sync.md` in order.

### Phase order:
1. **Migration + model** — `prism_interference_dismissals` + `PrismInterferenceDismissal`, copying the exercise-overlay table/model (unique `[gym_id, resolution_key]`, index `[gym_id, updated_at]`, `deleted_at timestamp(6)` nullable, `timestamps(6)`; `resolution_key` as `string(512)`).
2. **Action** — `UpsertPrismInterferenceDismissalsAction`, a one-to-one copy of `UpsertPrismExercisesAction` (upserts set `deleted_at = null`; removals do a second `upsert()` with `deleted_at = now('Y-m-d H:i:s.u')`; return max of touched `updated_at`/`deleted_at`).
3. **Checkpoint** — `php artisan test --parallel`.
4. **Request + controller + route** — `StorePrismInterferenceDismissalsRequest`, `PrismInterferenceDismissalController@store` (`findGymOrFail`, call action, return `{status:'ok', updated_at}`), and one route in the `throttle:prism-write` group of `routes/sync.php`.
5. **`/changes` sixth section** — in `PrismChangesController@index`: a `dismissals` query section mapping `{resolution_key, deleted_at, updated_at}`; fold its `max('updated_at')` into `$updatedInstants` and `max('deleted_at')` (whereNotNull) into `$deletedInstants`; add `'dismissals'` to the response. THE CURSOR FOLD IS MANDATORY.
6. **Tests** — see Success Criteria. Include the cursor-advance test and cross-gym isolation.
7. **Final checkpoint** — `php artisan test --parallel`.

---

## HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`. Use plain `php artisan migrate` to run the new forward-only migration locally.

---

## Implementation Rules

- **All new code goes in `app/Sync/`** — Models in `App\Sync\Models`, Actions in `App\Sync\Actions`, Controllers in `App\Sync\Controllers`, Requests in `App\Sync\Requests`.
- **The route goes in `routes/sync.php`**, inside the existing `throttle:prism-write` middleware group (NOT the `prism-poll` group).
- **Always use `php artisan test --parallel` when running tests.** For this feature: `php artisan test --parallel tests/Feature/Sync/Prism/`.
- **Never use `DB::` facade** — use Eloquent models (`upsert`, `where`, `max`).
- **Use PHP 8 constructor promotion** in the controller (inject the action).
- **Add explicit return types** to all methods (`: JsonResponse`, `: Carbon`, `: array`).
- **The migration is forward-only** — no `down()` that drops data beyond the standard `dropIfExists`. It runs on Forge deploy; do not assume it can be re-run against existing data.
- **Do NOT parse `resolution_key`.** It is opaque. Store, index, range-scan — never split or interpret it.

---

## Success Criteria

- [ ] `prism_interference_dismissals` migration: unique `[gym_id, resolution_key]`, index `[gym_id, updated_at]`, `deleted_at timestamp(6)` nullable, `timestamps(6)`, `resolution_key string(512)`, `gym_id` FK cascadeOnDelete.
- [ ] `POST /api/sync/prism/{gym}/interference-dismissals` upserts `dismissals[]` (deleted_at null) and tombstones `removals[]` (deleted_at now) in ONE request; returns `{status:'ok', updated_at}`.
- [ ] A `removals[]` array of N keys tombstones all N rows in a single action call (reset-all fan-out).
- [ ] `GET /api/sync/prism/{gym}/changes` response includes a `dismissals` array of `{resolution_key, deleted_at, updated_at}`.
- [ ] `?since=` filtering on `/changes` returns only dismissals with `updated_at >= since`.
- [ ] A dismissal-only change advances the `/changes` cursor (test: cursor after a dismissal > cursor before it).
- [ ] The Cove's dismissals never appear in Flagship's `/changes` (cross-gym isolation test).
- [ ] A long `resolution_key` round-trips intact (no truncation).
- [ ] All tests pass (`php artisan test --parallel`). No git commits. No Pint. No new composer deps.

---

## Do Not

- Do NOT commit or push.
- Do NOT run Pint.
- Do NOT run destructive database commands.
- Do NOT put code outside `app/Sync/`.
- Do NOT add the route to the `prism-poll` group — writes belong in `prism-write`.
- Do NOT parse, split, or interpret `resolution_key` anywhere on the server.
- Do NOT add a "delete all dismissals for a program/gym" verb — reset-all is a batched `removals[]` tombstone list from the client, nothing more.
- Do NOT forget the cursor HWM fold in `PrismChangesController` — a dismissal change must move the cursor.
- Do NOT verify gym membership (Phase 1 trusts any login; only validate the gym exists).

---

## Post-Execution Retro (authored by the REVIEWER, not the executor)

> The executor must NOT fill this in — leave the `{placeholder}` values untouched. After the run, the human reviewer reconstructs this section from the commit trail at archive time (the `snapshot:` commit, the `cleanup:`/`fix:` follow-ups) and moves both plan + prompt to `completed/`. See `.kiro/steering/architect-workflow.md` → Post-Execution Review Protocol.

- **Attempts:** {1 (clean) / N (root cause of failures)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {what to add/change}
- **Steering updates needed:** {yes/no, what}
