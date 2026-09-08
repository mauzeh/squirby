# Olympic-Complex `barbell-complex` Recognition — Prompt for Antigravity CLI (Logger slice)

## Global Execution Rules
1. Execute sequentially; test ONLY at the Milestone checkpoints.
2. SELF-CORRECTION LOOP: run the checkpoint; if tests fail, read `.test-output.txt`, fix, re-run within the
   turn. Do NOT yield with a failing milestone; do NOT ask for help — fix it.
3. When ALL milestones pass AND the cleanup sweep is clean, print exactly:
   ```
   AGY_COMPLETE: All milestones passed.
   ```
   Do NOT write the Post-Execution Retro (reviewer-authored).
4. Test output: `php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt`. Read the
   file; never re-run just to re-see output. Never `/tmp/`. Delete `.test-output.txt` at the end.

---

## Before You Start (read in order)

### 1. Steering (project rules — always follow)
```
docs/antigravity-steering.md             → git (NEVER commit), Pint BAN, DB rules, §13 Consumer Impact Trace, milestones, AGY_COMPLETE
.kiro/steering/safe-operations.md        → files never to edit, bash safety, artisan safety, Pint ban
.kiro/steering/project-conventions.md    → no column repurposing, soft deletes, one-source-of-truth
.kiro/steering/sync-api-context.md       → sync API architecture, SetFieldMapper, data model
.kiro/steering/laravel-boost.md          → Eloquent not DB::, constructor promotion, explicit return types, PHPUnit-only
```

### 2. Plan + the shared FROZEN spec (reference — execute from THIS prompt)
```
docs/plans/olympic-complex-reps-and-logging.md              → this slice (WHAT/WHY, phases, §13 trace, NO-default rule)
../../docs/plans/olympic-complex-reps-and-logging-cross-repo.md → FROZEN §1–§6 (source of truth) — read, do NOT edit
```

### 3. Existing code to understand (read before modifying)
```
app/Sync/Services/SetFieldMapper.php             → barbell group in BOTH switches: `case 'barbell': case 'single-dumbbell': case 'dual-dumbbell': case 'machine':`
app/Sync/Services/ExerciseResolverService.php    → deriveExerciseType() match; the `=> 'regular'` arm
tests/Unit/Sync/SetFieldMapperTest.php           → mirror the barbell round-trip test
tests/Unit/Sync/ExerciseResolverServiceTest.php  → mirror the derive test
```

---

## What You're Building

Make Logger recognize the athlete logType `barbell-complex` so a synced complex set is stored + round-tripped
instead of silently dropped. `barbell-complex` is an ALIAS of `barbell` on the Logger side (same columns,
same `regular` exercise_type, same display, same PR family). THREE additions, no new body/column/strategy/
migration.

---

## HARD RULES — NEVER VIOLATE
- **NEVER commit / add / push.** No git commands.
- **NEVER run Pint.** **NEVER run destructive DB commands** (`migrate:fresh`/`reset`/`db:wipe`).
- **NEVER add a `default` case to `SetFieldMapper::mapToColumns`/`::mapFromColumns`** — unknown logTypes
  must fail loudly (that is the contract's tripwire). Recognize `barbell-complex` by LISTING it in the
  barbell group.
- **NEVER add a new column, strategy, exercise_type, or migration.** Do NOT touch `meta` handling.

## Milestone 1 — SetFieldMapper (both directions) + resolver + unit tests
- `SetFieldMapper::mapToColumns()`: add `case 'barbell-complex':` to the existing barbell fall-through group
  (alongside `case 'barbell':`), sharing the weight+reps body. No new body.
- `SetFieldMapper::mapFromColumns()`: add `case 'barbell-complex':` to the barbell group there too.
- `ExerciseResolverService::deriveExerciseType()`: add `'barbell-complex'` to the `=> 'regular'` match arm
  (alongside `'barbell'`). Do NOT remove the existing `default => 'regular'`; do NOT add a default to
  `SetFieldMapper`.
- Tests: `mapToColumns('barbell-complex', ['weight'=>135,'reps'=>3])` → columns `weight:135, reps:3`;
  `mapFromColumns('barbell-complex', $set)` → `['weight'=>…, 'reps'=>…]`; round-trip; and
  `deriveExerciseType('barbell-complex') === 'regular'`.
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 2 — Sync feature test (accept + persist + round-trip)
- Feature test: POST `/api/squirby/logs` for a `barbell-complex` exercise **weight-only** (`{weight}`, reps
  absent — the real v1 shape) → the lift set persists `weight` with `reps` null (assert NOT dropped to a
  no-op `{unit, weight:0}` set), and the auto-created exercise has `exercise_type = regular`.
- Feature test: `GET /restore` returns `weight` (reps null); AND a set with a reps value (simulated
  Logger-side edit) round-trips `weight`+`reps` — proving the alias handles null and non-null reps like
  `barbell`.
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 3 — Cleanup sweep + final verification
- End-of-Run Cleanup Sweep (grep/search tool, not bash grep): no unused `use`; NO `default` case added to
  either `SetFieldMapper` switch; no dead branch; `meta` handling untouched; delete `.test-output.txt`.
- Final run:
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```
All green. Delete `.test-output.txt`. Then print:
```
AGY_COMPLETE: All milestones passed.
```

---

## Implementation Rules
- Changes confined to `app/Sync/Services/SetFieldMapper.php`, `app/Sync/Services/ExerciseResolverService.php`,
  and their test files. Eloquent not `DB::`; constructor promotion; explicit return types; PHPUnit only;
  factories; `--no-interaction`. Mirror the existing barbell case grouping exactly.

## Success Criteria
- [ ] `barbell-complex` maps weight+reps in BOTH mapper directions like `barbell`; round-trip intact.
- [ ] `deriveExerciseType('barbell-complex') === 'regular'` via an explicit arm.
- [ ] A synced `barbell-complex` log persists weight+reps (never dropped) and restores them.
- [ ] No `default` added to `SetFieldMapper`; no new column/strategy/migration; `meta` untouched.
- [ ] `php artisan test --parallel` green; sweep clean; no deps; no commits.

## Do Not
- Do NOT add a `SetFieldMapper` default case, a column, a strategy, an exercise_type, or a migration.
- Do NOT touch `meta` persistence/echo. Do NOT edit historical migrations or any `contracts/` file.
- Do NOT commit/push, run Pint, or run destructive DB commands.

## Post-Execution Retro (authored by the REVIEWER, not the executor — leave placeholders untouched)
- **Attempts:** {1 (clean) / N (root cause)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {…}
- **Steering updates needed:** {yes/no + what}
