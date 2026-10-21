# Prism Program Archive — Logger Slice (Plan)

**Slice:** Logger side of the gym-level program archive. Adds a nullable `archived_at` to `prism_programs`
so Prism's archive status persists and flows through the existing `/changes` delta. **Branch:**
`feature/prism-logger-integration` (same branch the Prism sync work lives on).

**Root plan-of-record:** `squirby/docs/sync/prism-logger/gym-program-archive-plan.md` (source of truth for
shared decisions; this plan implements ONLY Logger's slice). **Cross-app method:** per the root
`.kiro/steering/architect-workflow.md` "Cross-App Work / Feature slicing" — authored and tested
independently against this repo's PHPUnit suites; never reaches into the Prism repo; the shared wire shape
is duplicated INLINE below as the local source of truth; the root contract is authored LAST.

> **What this is.** The architecture reference (why + what) for the Logger slice, per
> `logger/.kiro/steering/architect-workflow.md` and the Plan Document Format in
> `docs/antigravity-steering.md` §9. The mechanical execution steps live in
> `prism-program-archive-prompt.md`, authored from `docs/plans/template-prompt.md`.

---

## Context

Logger stores Prism programs as granular rows. `prism_programs` today
(`database/migrations/2026_10_20_203702_create_prism_programs_table.php`) has:
`id, gym_id, program_uuid, name, week_count (unsignedSmallInteger), metadata (json nullable), device_id,
timestamps(6)`, unique `(gym_id, program_uuid)`, index `(gym_id, updated_at)`. The model
(`app/Sync/Models/PrismProgram.php`) casts `metadata => array`. Writes go through
`UpsertPrismProgramAction` via `PrismProgramController@storeProgram` (validated by
`StorePrismProgramRequest`). Reads go through `PrismChangesController@index`, which serializes the program
row and computes a gym cursor.

The exercise overlay already has the precedent we mirror: `prism_exercise_overlay.deleted_at`
(`PrismExerciseOverlayRecord`) is a plain nullable datetime (NOT `SoftDeletes`) that is written by the
action, serialized by `/changes`, and folded into the cursor. We reuse the *column shape + sync-ride*, but
NOT its apply semantics — an archived program is still a live, returned row, just flagged.

## Problem statement

Prism needs a server field to record that a program has been archived gym-wide, so the status persists and
propagates via the existing delta. That field is a nullable `archived_at` timestamp on `prism_programs`.
Logger stores and returns it verbatim; it interprets nothing about it (archive is a Prism-side product
concept — Logger just persists the timestamp, exactly as it persists `metadata`).

## Architecture

### The change in one sentence

Add a nullable `archived_at dateTime(6)` to `prism_programs`, thread it through the model, the
FormRequest, the upsert action, and the `/changes` serializer — additive, forward-only, no backfill.

### Wire shape (duplicated inline — local source of truth)

> Confirmed against this repo's current Prism code (routes/sync.php, PrismProgramController,
> StorePrismProgramRequest, UpsertPrismProgramAction, PrismChangesController). The root contract
> (`squirby/contracts/`) reconciles LAST.

```
POST /api/sync/prism/{gym}/programs/{program_uuid}
  body { name, week_count, metadata, archived_at, device_id } → { status:'ok', updated_at }
    name:        required string ≤255                 (unchanged)
    week_count:  required integer 0..65535            (unchanged)
    metadata:    nullable array/object                (unchanged)
    archived_at: NULLABLE date (ISO-8601) | null      (NEW) — null ⇒ not archived / unarchive
    device_id:   nullable string ≤36                  (unchanged, injected by controller)

GET /api/sync/prism/{gym}/changes?since=<cursor>
  → programs[]: { program_uuid, name, week_count, metadata, archived_at, updated_at }
    archived_at: ISO-8601 string | null               (NEW)
```

### Why `archived_at` needs NO cursor-computation change

An archive/unarchive is an `updateOrCreate` of the same program row, which bumps the server-stamped
`updated_at` (microsecond, `timestamps(6)`). So the row is already caught by the existing
`updated_at >= since` program delta and the existing `max('updated_at')` cursor high-water-mark in
`PrismChangesController`. Unlike `prism_exercise_overlay.deleted_at` (which the cursor must separately fold
because a tombstone's meaningful instant is its `deleted_at`), `archived_at` does NOT get its own
`max(...)` term — the row's `updated_at` already covers it. **Do not add `archived_at` to the cursor
computation.**

---

## Consumer Impact Trace (mandatory — new column on an existing synced table)

Per `logger/.kiro/steering/architect-workflow.md` precision rule 5 + executor §13. `archived_at` is a NEW
nullable column. Enumerate every reader/writer:

**Writers of `prism_programs`:**
- `UpsertPrismProgramAction::execute()` — the ONLY write path. Must include `archived_at` in the
  `updateOrCreate` values. ⚠ Decision baked in here: the action writes `archived_at` from the validated
  attributes using **`array_key_exists`**, not `??` — because `null` is a MEANINGFUL value (unarchive).
  `$attributes['archived_at'] ?? null` cannot distinguish "unarchive (sent null)" from "field absent." Prism
  always sends the key on a whole-program push (it sends `archived_at: null` to unarchive), so the action
  sets `'archived_at' => $attributes['archived_at'] ?? null` is acceptable ONLY because an absent key and an
  explicit null both mean "not archived." Use `?? null` (absent ⇒ null ⇒ not archived, consistent with
  D-ARCHIVE-4). There is no separate "clear" semantics to preserve, so `?? null` is correct and simplest.

**Readers / serializers of `prism_programs`:**
- `PrismChangesController@index` §1 (programs map) — must add `'archived_at' => $program->archived_at?->toIso8601String()`.
- `PrismChangesController@index` §5 (cursor) — **no change** (see above).
- No Blade, no web UI, no API resource, no `ExerciseType` strategy, no event payload reads
  `prism_programs` — it is a Prism-sync-only table unused by Athlete or the web app. Blast radius is the
  two Prism files above + the model + the request.

**Tests asserting the old program shape:**
- `tests/Feature/Sync/Prism/PrismProgramStoreTest.php` — store/upsert assertions; extend for `archived_at`
  round-trip (store with a timestamp, store with null, confirm persisted).
- `tests/Feature/Sync/Prism/PrismChangesTest.php` — `/changes` serialization; extend to assert the
  programs[] row now carries `archived_at` (ISO-8601 when set, null when not), and that an archive bumps
  `updated_at` so the row appears in a since-delta.
- `tests/Unit/Sync/Prism/PrismProgramTest.php` — model fillable/casts; extend for the `archived_at` cast.
- No existing test asserts the ABSENCE of `archived_at` in a way that breaks (new nullable field), but
  confirm `PrismChangesTest` uses subset/`assertArrayHasKey`-style assertions rather than exact-array
  equality that a new key would break; if any exact-equality assertion exists, update it.

---

## Files

- **NEW migration** `database/migrations/<ts>_add_archived_at_to_prism_programs_table.php` — forward-only
  ALTER. `up()`: `$table->dateTime('archived_at', 6)->nullable()->after('metadata');`. `down()`:
  `$table->dropColumn('archived_at');`. Additive + nullable + no backfill ⇒ safe to auto-apply on deploy
  (`project-conventions.md`). NOT a `SoftDeletes` column (no `deleted_at` rename, no trait).
- **MODIFY** `app/Sync/Models/PrismProgram.php` — add `'archived_at'` to `$fillable`; add
  `'archived_at' => 'datetime'` to `casts()`.
- **MODIFY** `app/Sync/Requests/StorePrismProgramRequest.php` — add
  `'archived_at' => ['nullable', 'date']` to `rules()`.
- **MODIFY** `app/Sync/Actions/UpsertPrismProgramAction.php` — add `'archived_at' => $attributes['archived_at'] ?? null`
  to the `updateOrCreate` values array.
- **MODIFY** `app/Sync/Controllers/PrismChangesController.php` — add
  `'archived_at' => $program->archived_at?->toIso8601String()` to the §1 programs map. No §5 change.
- **MODIFY tests** as enumerated in the Consumer Impact Trace.

The controller `storeProgram` already forwards `$request->validated()` to the action wholesale, so once the
FormRequest allows `archived_at`, it reaches the action with NO controller change.

## Entity default (new stored field)

On an existing program row, `archived_at` defaults to **`NULL`** (not archived). A freshly-created program
via `updateOrCreate` that omits `archived_at` stores `NULL`. There is no non-null default and no backfill.

## Execution plan (phases with checkpoints)

1. **Migration + model.** Add the migration + fillable + cast. Checkpoint:
   `php artisan test --parallel tests/Unit/Sync/Prism/PrismProgramTest.php` (cast asserted).
2. **Write path.** FormRequest rule + action thread-through. Checkpoint:
   `php artisan test --parallel tests/Feature/Sync/Prism/PrismProgramStoreTest.php` (archive + unarchive
   round-trip persisted).
3. **Read path.** `/changes` serialization. Checkpoint:
   `php artisan test --parallel tests/Feature/Sync/Prism/PrismChangesTest.php` (programs[] carries
   `archived_at`; archive bumps `updated_at` into a since-delta).
4. **Full suite + migrate.** `php artisan test --parallel`, then RUN the migration locally
   (`php artisan migrate`) and confirm `php artisan migrate:status` shows it **Ran** (a green
   `RefreshDatabase` test is NOT proof the migration ran — `project-conventions.md`). Dead-code sweep.

## Hard rules

- Never commit/push; never run Pint; never destructive DB (`safe-operations.md`, executor §3/§6).
- Forward-only migration; nullable column; `$fillable`+`casts()` updated; correct `down()`. No modifying a
  migration that has run.
- Eloquent, not `DB::`; explicit return types; PHPUnit only; code stays in the `app/Sync/` domain.

## Success criteria

- [ ] `prism_programs.archived_at` exists as a nullable `dateTime(6)`; migration shows **Ran**.
- [ ] `PrismProgram` casts `archived_at => datetime` and has it in `$fillable`.
- [ ] `StorePrismProgramRequest` validates `archived_at` as `nullable|date`.
- [ ] `UpsertPrismProgramAction` persists `archived_at` (a timestamp sets it; null clears it).
- [ ] `/changes` programs[] rows carry `archived_at` (ISO-8601 or null); an archive bumps `updated_at` and
      appears in a `since`-delta; the cursor computation is unchanged.
- [ ] Full Prism PHPUnit suite green; no exact-array-equality test broken by the new key.
- [ ] No git commits by the executor; no Pint.

## Do Not

- Do NOT use `SoftDeletes` or name the column `deleted_at` — archived programs must remain queryable and
  keep flowing through `/changes`.
- Do NOT add `archived_at` to the cursor high-water-mark computation (the row's `updated_at` already covers
  it).
- Do NOT backfill existing rows or add a non-null default.
- Do NOT touch any non-Prism table, the web UI, or Athlete-facing code.
- Do NOT reach into the Prism repo or the root `contracts/` — the wire shape above is the inline source of
  truth.

## Post-Execution Retro

- **Attempts:** {placeholder}
- **Follow-up fixes:** {placeholder}
- **Tests added:** {placeholder}
- **Prompt gaps:** {placeholder}
- **Steering updates needed:** {placeholder}

## Related

- Root plan: `squirby/docs/sync/prism-logger/gym-program-archive-plan.md`.
- Prism slice: `squirby/prism/docs/plans/prism-program-archive-plan.md` (authored independently).
- Deploy sequence: `squirby/docs/launch/prism-cove-deployment-plan.md` §4b.
- Precedent: `app/Sync/Models/PrismExerciseOverlayRecord.php` (`deleted_at` nullable datetime + `/changes`
  serialization + action).
