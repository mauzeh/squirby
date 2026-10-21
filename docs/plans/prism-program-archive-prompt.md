# Prism Program Archive (Logger Slice) — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this feature correctly.

### 1. Steering (project rules — always follow)
```
docs/antigravity-steering.md            → the executor contract — READ THIS FIRST
.kiro/steering/git-workflow.md          → commit freely, NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files to never touch, bash safety, artisan safety
.kiro/steering/project-conventions.md   → architectural principles for this project
.kiro/steering/laravel-boost.md         → Laravel framework conventions (PHP style, testing, Pint)
```

### 2. Feature Plan (what to build — architecture reference)
```
docs/plans/prism-program-archive-plan.md → why + what. This prompt is the HOW. If the two ever
                                            disagree, the plan's design decisions win; follow this
                                            prompt for the execution steps.
```

### 3. Existing Code to Understand (read before modifying)
```
database/migrations/2026_10_20_203702_create_prism_programs_table.php
    → current prism_programs schema: id, gym_id, program_uuid, name, week_count
      (unsignedSmallInteger), metadata (json nullable), device_id, timestamps(6),
      unique (gym_id, program_uuid), index (gym_id, updated_at).
app/Sync/Models/PrismProgram.php
    → the model. Casts metadata => array. You add archived_at here.
app/Sync/Requests/StorePrismProgramRequest.php
    → validates the store/upsert body. You add the archived_at rule here.
app/Sync/Actions/UpsertPrismProgramAction.php
    → the ONLY write path (updateOrCreate). You thread archived_at through here.
app/Sync/Controllers/PrismProgramController.php
    → storeProgram forwards $request->validated() to the action wholesale — NO change needed here,
      but read it so you understand the flow.
app/Sync/Controllers/PrismChangesController.php
    → the /changes delta. §1 builds the programs[] map (you add archived_at). §5 computes the gym
      cursor (you must NOT touch it — see below).
routes/sync.php
    → the Prism sync routes. Read-only; no route changes.
```

### 4. Reference (already implemented — don't rebuild, just understand the pattern)
```
app/Sync/Models/PrismExerciseOverlayRecord.php
    → the precedent. deleted_at is a plain nullable datetime (NOT SoftDeletes) that the action
      writes, /changes serializes, and the cursor folds in. You reuse the COLUMN SHAPE + sync-ride
      pattern, but NOT its apply/cursor semantics. An archived program stays a live, returned row —
      just flagged. Study this file to match the nullable-datetime approach, then diverge on cursor
      (see Phase 3).
```

---

## What You're Building

Prism needs a server-side field to record that a program has been archived gym-wide, so the status
persists and propagates to clients through Logger's existing `/changes` sync delta. That field is a
nullable `archived_at` timestamp on the `prism_programs` table.

Logger is a dumb store for this value: it persists and returns `archived_at` verbatim, exactly as it does
`metadata`. Logger interprets NOTHING about it — "archived" is a Prism-side product concept. A set
timestamp means archived; `null` means not archived (or unarchived). An archived program remains a live,
queryable row that keeps flowing through `/changes` — it is flagged, not hidden. This is explicitly NOT a
`SoftDeletes` column.

End state: the column exists (nullable `dateTime(6)`, migration **Ran**), the model casts and fills it,
the FormRequest validates it, the upsert action persists it, and the `/changes` programs[] rows carry it
as an ISO-8601 string (or `null`). The cursor computation is unchanged. Additive, forward-only, no
backfill, no behavioral change to any existing field.

This is the Logger slice of a cross-app feature. The wire shape is duplicated inline in the plan as the
local source of truth; you do NOT reach into the Prism repo or the root `contracts/` directory.

### Wire shape (authoritative for this slice)
```
POST /api/sync/prism/{gym}/programs/{program_uuid}
  body { name, week_count, metadata, archived_at, device_id } → { status:'ok', updated_at }
    archived_at: NULLABLE date (ISO-8601) | null   (NEW) — null ⇒ not archived / unarchive
    (all other fields unchanged)

GET /api/sync/prism/{gym}/changes?since=<cursor>
  → programs[]: { program_uuid, name, week_count, metadata, archived_at, updated_at }
    archived_at: ISO-8601 string | null            (NEW)
```

---

## Execution Plan

Four phases. Each phase ends with a concrete `php artisan test --parallel` checkpoint. Do NOT run a
checkpoint after every edit — only at the phase boundaries below.

### Phase 1 — Migration + model

1. Create the migration with:
   ```
   php artisan make:migration add_archived_at_to_prism_programs_table --no-interaction
   ```
   `up()` — forward-only ALTER, additive + nullable, positioned after `metadata`:
   ```php
   Schema::table('prism_programs', function (Blueprint $table): void {
       $table->dateTime('archived_at', 6)->nullable()->after('metadata');
   });
   ```
   `down()`:
   ```php
   Schema::table('prism_programs', function (Blueprint $table): void {
       $table->dropColumn('archived_at');
   });
   ```
   Do NOT use `SoftDeletes`, do NOT name the column `deleted_at`, do NOT add a non-null default, do NOT
   backfill.

2. In `app/Sync/Models/PrismProgram.php`:
   - Add `'archived_at'` to the `$fillable` array.
   - Add `'archived_at' => 'datetime'` to the `casts()` method (match the existing `casts()` style; if the
     model uses a `$casts` property instead, follow whichever the file already uses).

**Checkpoint:** `php artisan test --parallel tests/Unit/Sync/Prism/PrismProgramTest.php`
(first extend this test to assert `archived_at` is fillable and cast to a Carbon/datetime instance).

### Phase 2 — Write path

3. In `app/Sync/Requests/StorePrismProgramRequest.php`, add to `rules()`:
   ```php
   'archived_at' => ['nullable', 'date'],
   ```
   Match the array-vs-string rule style the file already uses for its other fields.

4. In `app/Sync/Actions/UpsertPrismProgramAction.php`, add `archived_at` to the `updateOrCreate` values
   array:
   ```php
   'archived_at' => $attributes['archived_at'] ?? null,
   ```
   Use `?? null` (an absent key and an explicit `null` both mean "not archived"; there is no separate
   clear-vs-absent semantics to preserve). Make NO change to `PrismProgramController@storeProgram` — it
   already forwards `$request->validated()` wholesale, so once the FormRequest allows the field it reaches
   the action automatically.

**Checkpoint:** `php artisan test --parallel tests/Feature/Sync/Prism/PrismProgramStoreTest.php`
(extend this test for the round-trip: store with an `archived_at` timestamp and confirm it persisted;
store the same program again with `archived_at: null` and confirm it cleared/unarchived).

### Phase 3 — Read path

5. In `app/Sync/Controllers/PrismChangesController.php`, in the §1 programs map, add to the serialized
   row:
   ```php
   'archived_at' => $program->archived_at?->toIso8601String(),
   ```
   Make NO change to §5 (the cursor / `max('updated_at')` high-water-mark). An archive/unarchive is an
   `updateOrCreate` of the same row, which bumps the microsecond `updated_at`, so the row is ALREADY
   caught by the existing `updated_at >= since` delta and the existing cursor. Unlike
   `prism_exercise_overlay.deleted_at`, `archived_at` does NOT get its own `max(...)` term.

**Checkpoint:** `php artisan test --parallel tests/Feature/Sync/Prism/PrismChangesTest.php`
(extend this test to assert: the programs[] row carries `archived_at` — ISO-8601 when set, `null` when
not; and that archiving a program bumps its `updated_at` so the row appears in a subsequent `since`-delta.
Use `assertArrayHasKey`/subset-style assertions. If any existing assertion in this file uses exact-array
equality that the new key would break, update it to accommodate the new key.)

### Phase 4 — Full suite + run the migration

6. Run the full suite: `php artisan test --parallel`.
7. RUN the migration against the local DB: `php artisan migrate --no-interaction`.
8. Confirm it took: `php artisan migrate:status` must show the new migration as **Ran**. A green
   `RefreshDatabase` feature test is NOT proof the migration ran (it uses in-memory SQLite) — the
   migration must show **Ran** against the real DB.
9. Dead-code sweep: no leftover TODO/FIXME, no commented-out code, no backward-compat shims.

---

## HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form (`safe-operations.md` forbids it absolutely).
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`. Use
  `migrate` (forward-only) only.
- **NEVER touch `.env`, `.env.local`, `.env.backup`, `vendor/`, `node_modules/`, `*.sql` backups, or
  `storage/logs/`.**

---

## Implementation Rules

- **All code stays in the `app/Sync/` domain.** Do not scatter Prism sync code elsewhere.
- **Routes are unchanged** — do not edit `routes/sync.php`.
- **Always use `php artisan test --parallel`** when running tests (2000+ tests; parallel is ~seconds vs
  minutes). Run a single file with `php artisan test --parallel tests/Feature/Sync/Prism/SomeTest.php`.
- **Never use the `DB::` facade** — use Eloquent models and relationships.
- **Use PHP 8 constructor promotion** in any new/edited classes; add **explicit return types** to all
  methods.
- **PHPUnit only** — this project uses PHPUnit, not Pest. Create tests with
  `php artisan make:test --phpunit` if you need a new file (you should only be EXTENDING the three
  existing tests).
- **Pass `--no-interaction`** to all artisan commands.
- **Forward-only, additive, nullable migration** with a correct `down()`. Do not modify any migration that
  has already run.
- **`archived_at` is a plain nullable datetime, not `SoftDeletes`.**

---

## Success Criteria

- [ ] `prism_programs.archived_at` exists as a nullable `dateTime(6)` positioned after `metadata`;
      `php artisan migrate:status` shows the migration **Ran**.
- [ ] `PrismProgram` has `archived_at` in `$fillable` and casts it to `datetime`
      (`grep -n archived_at app/Sync/Models/PrismProgram.php`).
- [ ] `StorePrismProgramRequest` validates `archived_at` as `nullable|date`
      (`grep -n archived_at app/Sync/Requests/StorePrismProgramRequest.php`).
- [ ] `UpsertPrismProgramAction` persists `archived_at` (a timestamp sets it; null clears it)
      (`grep -n archived_at app/Sync/Actions/UpsertPrismProgramAction.php`).
- [ ] `/changes` programs[] rows carry `archived_at` as ISO-8601 or null
      (`grep -n archived_at app/Sync/Controllers/PrismChangesController.php` shows it in the §1 map and
      NOT in the §5 cursor).
- [ ] Archiving a program bumps `updated_at` and the row appears in a `since`-delta — asserted by
      `PrismChangesTest`.
- [ ] `php artisan test --parallel tests/Unit/Sync/Prism/PrismProgramTest.php`,
      `tests/Feature/Sync/Prism/PrismProgramStoreTest.php`, and
      `tests/Feature/Sync/Prism/PrismChangesTest.php` all pass.
- [ ] Full suite green: `php artisan test --parallel`.
- [ ] No git commits made by the executor; no Pint run.

---

## Do Not

- Do NOT commit or push; do NOT run Pint; do NOT run destructive DB commands.
- Do NOT use `SoftDeletes` or name the column `deleted_at` — archived programs must remain queryable and
  keep flowing through `/changes`.
- Do NOT add `archived_at` to the cursor high-water-mark computation in `PrismChangesController` §5 (the
  row's `updated_at` already covers it).
- Do NOT backfill existing rows or add a non-null default.
- Do NOT change `PrismProgramController@storeProgram` (it forwards `validated()` wholesale).
- Do NOT touch any non-Prism table, the web UI, or Athlete-facing code.
- Do NOT reach into the Prism repo or the root `contracts/` directory — the inline wire shape above is the
  source of truth for this slice.
- Do NOT delete or significantly alter existing tests beyond extending them for `archived_at` and fixing
  any exact-array-equality assertion the new key breaks.

---

## Post-Execution Retro (authored by the REVIEWER, not the executor)

> The executor must NOT fill this in — leave the `{placeholder}` values untouched. After the run, the
> human reviewer reconstructs this section at archive time and moves both plan + prompt to `completed/`.
> See `.kiro/steering/architect-workflow.md` → Post-Execution Review Protocol.

- **Attempts:** 1 (clean). All phases landed as specified with no follow-up fixes needed. Full suite green (2229 passing), the three targeted Prism classes green (18 tests), and the migration confirmed **Ran** against the real DB (batch 46) with `archived_at` present on `prism_programs` after `metadata`.
- **Tests added:** 2 new test methods (no new files — the three existing tests were extended per the Consumer Impact Trace). `PrismProgramStoreTest::test_program_upsert_sets_and_clears_archived_at` (set + unarchive round-trip) and `PrismChangesTest::test_changes_programs_carry_archived_at_and_archive_appears_in_since_delta` (serialization + since-delta bump). `PrismProgramTest::test_program_create_read_update_and_upsert` was extended in place to assert the `datetime` cast and persistence of `archived_at`.
- **Prompt improvements for next time:** The prompt told the executor to run tests as `php artisan test --parallel tests/Unit/.../X.php tests/Feature/.../Y.php` with multiple paths; the parallel (ParaTest) runner rejects multiple path arguments ("Too many arguments, expected arguments 'path'"). Future prompts should say to run one path per invocation, or use `--filter='ClassA|ClassB'` when checkpointing several classes at once. Did not affect the outcome.
- **Steering updates needed:** No. No new failure mode surfaced; the existing steering (forward-only migration, no Pint, no commits, cursor discipline, exact-array-equality caution) was sufficient and was followed. The one friction point (multi-path `--parallel`) is a prompt-authoring note, not a steering gap.
