# Olympic-Complex `meta` Persistence — Prompt for Antigravity CLI (Logger slice)

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
docs/antigravity-steering.md             → git (NEVER commit), Pint BAN, §6 DB rules (nullable + $fillable + cast), §13 Consumer Impact Trace, §15 decomposition + Entity Defaults, milestones, AGY_COMPLETE
.kiro/steering/safe-operations.md        → files never to edit, bash safety, artisan safety, Pint ban
.kiro/steering/project-conventions.md    → forward-only migrations, no column repurposing, soft deletes
.kiro/steering/sync-api-context.md       → sync API architecture, app/Sync/ layout, data model
.kiro/steering/laravel-boost.md          → Eloquent not DB::, constructor promotion, explicit return types, PHPUnit-only
```

### 2. Plan + the shared FROZEN spec (reference — execute from THIS prompt)
```
docs/plans/olympic-complex-meta-persistence.md              → this slice (WHAT/WHY, Entity Default, phases, §13 trace)
../../docs/plans/olympic-complex-meta-persistence-cross-repo.md → FROZEN §1–§6 (source of truth) — read, do NOT edit
```

### 3. Existing code to understand (read before modifying)
```
database/migrations/2026_06_15_000003_add_sync_columns_to_lift_logs_table.php → additive nullable-column pattern to mirror
app/Models/LiftLog.php                               → $fillable + casts()
app/Sync/Controllers/LogController.php               → /logs (single) + /logs/batch (logs.*) validation
app/Sync/Actions/StoreSyncLogAction.php              → create + update-existing-slot branches
app/Sync/Controllers/RestoreController.php           → builds $logData per log
app/Sync/Controllers/ChangesController.php           → builds $logData per log
app/Sync/Services/ExerciseResolverService.php        → CONFIRM meta not used for resolution/title
```

---

## What You're Building

Persist + echo the athlete's OPAQUE, versioned per-log `meta` JSON blob (today
`{"v":1,"complex":{"id":"clean_complex_1"}}`) so complex composition survives cross-device sync/restore.
Add a nullable `json` `lift_logs.meta` column; validate `meta` as `nullable|array`; persist it verbatim in
both `StoreSyncLogAction` branches (whole-log LWW, omit ⇒ null); echo it verbatim from `/restore` and
`/api/sync/changes`. Logger NEVER interprets `meta` and NEVER uses it for exercise resolution — the SAME
opaque-blob contract as `blueprint`/`preferences`. Exercise auto-create/pooling by bucket `canonical_name`
is UNCHANGED.

---

## HARD RULES — NEVER VIOLATE
- **NEVER commit / add / push.** No git commands.
- **NEVER run Pint** (`vendor/bin/pint` in any form).
- **NEVER run destructive DB commands** (`migrate:fresh`, `migrate:reset`, `db:wipe`). Use forward-only
  `migrate`. Never modify a run migration.
- **NEVER interpret `meta`** or use it for `canonical_name`/`title`/resolution.
- **NEVER add a `?? $existingBySlot->meta` preserve-shim** — whole-log LWW (omit ⇒ null) is the agreement.

---

## Milestone 1 — Migration + model, then RUN it
- `php artisan make:migration add_meta_to_lift_logs_table --no-interaction`.
  - `up()`: `Schema::table('lift_logs', function (Blueprint $table) { $table->json('meta')->nullable()->after('movement_index'); });`
  - `down()`: `Schema::table('lift_logs', fn (Blueprint $table) => $table->dropColumn('meta'));`
- `app/Models/LiftLog.php`: add `'meta'` to `$fillable`; add `'meta' => 'array'` to the `casts()` array.
- Test (model): `LiftLog` factory-created with `meta => ['v'=>1,'complex'=>['id'=>'clean_complex_1']]`
  reads back as that array via the cast; `meta => null` stays null.
- **RUN the migration:** `php artisan migrate` then `php artisan migrate:status` — confirm the new migration
  is **Ran** (the root contract slice depends on this — §8).
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 2 — Ingress: validate + persist (create + update-slot + batch)
- `LogController`: add `'meta' => 'nullable|array'` to the single-log rules and
  `'logs.*.meta' => 'nullable|array'` to the batch rules. No inner-key rules (opaque).
- `StoreSyncLogAction::execute`: add `'meta' => $validated['meta'] ?? null` to BOTH the
  `LiftLog::create([...])` array and the `$existingBySlot->update([...])` array. NO preserve-shim.
- Test (`StoreSyncLogActionTest`): create-with-meta persists it; update-existing-slot with a new `meta`
  overwrites; update-existing-slot with the payload OMITTING `meta` sets `meta = null` (whole-log LWW);
  a non-complex payload stores `meta = null`.
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 3 — Egress: echo on restore + changes; feature test
- `RestoreController`: add `'meta' => $liftLog->meta` to the `$logData` array.
- `ChangesController`: add `'meta' => $liftLog->meta` to its `$logData` array (same shape).
- Feature test: POST `/api/squirby/logs` with `meta` → GET `/restore` returns the SAME `meta` (assert
  deep-equal); POST without `meta` → `/restore` returns `meta` null. Repeat the echo assertion for
  `/api/sync/changes` on a live log.
- CONFIRM `ExerciseResolverService` is untouched and does not reference `meta`.
### Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```

## Milestone 4 — Cleanup sweep + final verification
- End-of-Run Cleanup Sweep (grep/search tool, not bash grep): no unused `use`; no
  `?? $existingBySlot->meta` or any preserve-shim/fallback; no dead branch; no `meta` interpretation
  anywhere; delete `.test-output.txt`.
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
- All code stays in `app/Sync/`, `app/Models/LiftLog.php`, and `database/migrations/`. Eloquent not `DB::`;
  constructor promotion; explicit return types; PHPUnit only; factories; `--no-interaction`. New column
  nullable + `$fillable` + `array` cast. Mirror the `add_sync_columns_to_lift_logs_table` migration style.

## Success Criteria
- [ ] `lift_logs.meta` nullable `json`; `LiftLog` fills + casts (`array`); migration RUN (`migrate:status`
      = Ran); no data migration.
- [ ] `meta` validated `nullable|array` on `/logs` AND `/logs/batch`; persisted verbatim in both
      `StoreSyncLogAction` branches; whole-log LWW (omit ⇒ null); non-complex ⇒ null.
- [ ] `/restore` + `/api/sync/changes` echo `meta` byte-identical (null when absent).
- [ ] `meta` never interpreted / never used for resolution; auto-create by bucket `canonical_name` UNCHANGED.
- [ ] `php artisan test --parallel` green; sweep clean; no new column elsewhere; no deps; no commits.

## Do Not
- Do NOT interpret/validate-inner/derive-from `meta`; do NOT use it for resolution/title.
- Do NOT field-merge/preserve-shim `meta` (whole-log LWW). Do NOT put it on `lift_sets` or any slot key.
- Do NOT backfill or add a data migration. Do NOT edit historical migrations or any `contracts/` file.
- Do NOT commit/push, run Pint, or run destructive DB commands.

## Post-Execution Retro (authored by the REVIEWER, not the executor — leave placeholders untouched)
- **Attempts:** {1 (clean) / N (root cause)}
- **Tests added:** {count}
- **Prompt improvements for next time:** {…}
- **Steering updates needed:** {yes/no + what}
