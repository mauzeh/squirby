# Band Color Palette Reconciliation — Prompt for Antigravity CLI (Logger)

## Global Execution Rules

1. Execute sequentially; test ONLY at Milestone checkpoints.
2. SELF-CORRECTION LOOP: at a checkpoint, run the test command; if it fails, read the errors, fix, and
   re-run within this turn until green. Do not yield with a failing milestone; never ask for help with
   a test failure — fix it.
3. Do not finish your turn until the current milestone's tests pass.
4. When ALL milestones pass and success criteria are met, print exactly (do NOT write the retro):
   ```
   AGY_COMPLETE: All milestones passed.
   ```

## Before You Start

Read in order:

### 1. Steering (project rules — always follow)
```
docs/antigravity-steering.md            → no git, no Pint, bash safety, MIGRATION operating rule (§6/§8), Consumer Impact Trace (§13)
.kiro/steering/safe-operations.md       → protected files, artisan safety, Pint ban
.kiro/steering/project-conventions.md   → data integrity, naming, migrations forward-only
.kiro/steering/laravel-boost.md         → PHP/Eloquent/testing conventions
.kiro/steering/sync-api-context.md      → sync API + lift_sets/band_color context
```

### 2. The plan (context — NOT execution steps)
```
docs/plans/band-color-palette.md        → scope, consumer trace, frozen palette
```

### 3. Existing code to understand (read before modifying)
```
config/bands.php                                            → palette array to rewrite
config/exercise_types.php                                   → banded_resistance/banded_assistance in: rule
app/Services/BandService.php                                → order/resistance iteration
app/Services/ExerciseTypes/BandedResistanceExerciseType.php → in_array membership + progression lookup
app/Models/LiftSet.php                                      → SoftDeletes + band_color fillable
tests/Unit/BandServiceTest.php                              → existing coverage to extend
```

> **Boundary rule:** stay INSIDE `logger/`. No `../`, no root workspace, no `../../contracts`, no
> sibling apps. Everything you need is inline here or in `logger/`.

---

## What You're Building

Widen Logger's accepted `band_color` set from `red/blue/green` to the full five-color palette
(`orange, red, blue, green, black`), make the check case-insensitive, and soft-delete the 22 legacy
`purple` rows via a forward-only migration. This fixes a live break: Athlete now emits `orange` and
`black`, which Logger currently REJECTS on sync.

---

## Milestone 1: Palette config + case-insensitive validation

### Step 1: Rewrite the palette (`order`-only, NO `resistance`)
In `config/bands.php`, replace the `colors` array with EXACTLY (keep the two scalar keys below it):
```php
'colors' => [
    'orange' => ['order' => 1],
    'red'    => ['order' => 2],
    'blue'   => ['order' => 3],
    'green'  => ['order' => 4],
    'black'  => ['order' => 5],
],
```

### Step 1b: Remove the dead `resistance` reader
The `resistance` field is gone, so its only reader is dead. Delete `BandService::getBandResistance()`
entirely (NOT a no-op stub). Delete its unit test `test_get_band_resistance_returns_correct_value` in
`tests/Unit/BandServiceTest.php`, and any assertion in that file reading `$bands[...]['resistance']`
(e.g. update the `getBands()` test to assert `$bands['red']['order'] === 2` instead). Grep
`getBandResistance` and `resistance` across `app/` + `tests/` to confirm zero references remain.

### Step 2: Make band_color case-insensitive
- The `in:` rule in `config/exercise_types.php` is built from `array_keys(config('bands.colors'))`
  (lowercase). Ensure the incoming `band_color` is normalized to lowercase BEFORE it is validated /
  membership-checked. Prefer normalizing at the earliest validation boundary (Form Request /
  `prepareForValidation` or the strategy's input processing) rather than patching each call site.
- In `BandedResistanceExerciseType`, lowercase the band color before `in_array(..., array_keys(config('bands.colors')))`
  AND before the `config('bands.colors')[$color]['order']` lookup, so a capitalized value can't miss.
- Grep `bands.colors`, `invalidBandColor`, `getNextHarderBand` across `app/` to confirm you covered
  every membership/lookup site. (`getBandResistance` should return zero hits after Step 1b.)

### Milestone 1 Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```
Read `.test-output.txt`. Fix any failures (likely `BandServiceTest` asserting the old three colors or
the removed `resistance` field — update `getBands()` expectations to the five-color `order`-only shape;
the resistance test is already deleted in Step 1b). Delete `.test-output.txt` when green.

---

## Milestone 2: Purple cleanup migration

### Step 3: Create the migration
```bash
php artisan make:migration delete_legacy_purple_band_colors_from_lift_sets --no-interaction
```
In `up()` (use Eloquent, SoftDeletes → sets `deleted_at`):
```php
public function up(): void
{
    \App\Models\LiftSet::whereRaw('LOWER(band_color) = ?', ['purple'])->delete();
}
```
In `down()`: no-op with a PHPDoc note — deleted demo rows are intentionally not resurrected. Do NOT
hard-delete. Do NOT touch any non-purple row.

### Step 4: Tests
- **BandServiceTest** — `getBands()` returns the five colors with `order` 1–5 (no `resistance` key);
  progression across five colors: `getNextHarderBand('orange','resistance') === 'red'`,
  `getNextHarderBand('green','resistance') === 'black'`, `getNextHarderBand('black','resistance') === null`,
  `getPreviousEasierBand('red','resistance') === 'orange'`. (No `getBandResistance` assertions — the
  method is removed.)
- **Banded validation** — a banded_resistance log with `band_color = 'orange'` and one with `'black'`
  validate/persist successfully; a mixed-case value (e.g. `'Black'`) is accepted (case-insensitivity);
  an unknown color (e.g. `'purple'`) is still rejected.
- **Migration test** — seed lift_sets with a `purple` row (and a mixed-case `'Purple'` if reachable)
  plus a `green` row; run the migration; assert purple rows are soft-deleted (`deleted_at` set /
  excluded from default scope) and the `green` row is untouched.

### Milestone 2 Checkpoint
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```
All green. Delete `.test-output.txt`.

---

## Milestone 3: Run the migration + final verification

### Step 5: RUN the migration (a migration is not "done" until Run — antigravity-steering §8)
```bash
php artisan migrate --no-interaction
php artisan migrate:status
```
Confirm `delete_legacy_purple_band_colors_from_lift_sets` shows **Ran**. Then verify zero live purple
rows remain with a read-only tinker query:
```bash
php artisan tinker --execute="echo \App\Models\LiftSet::whereRaw('LOWER(band_color) = ?', ['purple'])->count();"
```
Expect `0`.

### Step 6: End-of-Run Cleanup Sweep (MANDATORY — antigravity-steering §4 sweep)
By inspection + grep (no Pint): no unused `use` imports in files you edited; no fallback/compat shims
(e.g. no leftover three-color hardcode, no `?? 'red'` default); NO surviving reference to
`getBandResistance` or a `resistance` config key (the removed method + field are fully gone, not
stubbed); no stray `dd()`/`dump()`/debug `Log::`; no orphaned helper left from a removed approach;
delete any temp script and `.test-output.txt`.

### Step 7: Final run
```bash
php artisan test --parallel > .test-output.txt 2>&1; tail -40 .test-output.txt
```
Fully green, then delete `.test-output.txt`.

### Milestone 3 Checkpoint
After sweep is clean, migration shows **Ran**, and tests pass, print:
```
AGY_COMPLETE: All milestones passed.
```

---

## Success Criteria

- [ ] `config/bands.php` `colors` = orange/red/blue/green/black, `order` 1–5, NO `resistance` field.
- [ ] `BandService::getBandResistance()` + its test removed; zero `getBandResistance`/`resistance` refs in `app/` + `tests/`.
- [ ] `orange` and `black` banded logs validate + persist; mixed-case accepted; unknown color rejected.
- [ ] New forward-only migration soft-deletes all `purple` rows (case-insensitive); non-purple untouched.
- [ ] Migration RUN — `migrate:status` shows **Ran**; tinker count of live purple rows is `0`.
- [ ] BandServiceTest (order-only) + banded validation + migration tests updated/added and green.
- [ ] `php artisan test --parallel` fully green.
- [ ] No git commits, no Pint, no new composer dependencies.

## Do Not

- Do NOT commit, push, or run any git command.
- Do NOT run Pint or any formatter.
- Do NOT run destructive DB commands; migration is forward-only.
- Do NOT edit the root workspace, `../../contracts`, or `athlete/`.
- Do NOT hard-delete purple rows; do NOT re-add purple to the palette or remap purple rows.
- Do NOT re-add a numeric `resistance` field; do NOT leave `getBandResistance()` as a stub.
- Do NOT change band_color display strategies.
- Do NOT write the Post-Execution Retro (reviewer-authored).

## Post-Execution Retro (authored by the REVIEWER, not the executor)
- **Attempts:** {1 (clean) / N — root cause}
- **Tests added:** {count}
- **Migration Ran + verified:** {yes/no}
- **Prompt improvements for next time:** {…}
- **Steering updates needed:** {yes/no + what}
