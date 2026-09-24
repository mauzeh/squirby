# Band Color Palette Reconciliation — Logger Plan

## Before You Start

Read these first:

### Steering (project rules — always follow)
```
docs/antigravity-steering.md              → delegated-run rules: no git, no Pint, bash safety, migration operating rule
.kiro/steering/safe-operations.md         → protected files, artisan safety, Pint ban
.kiro/steering/project-conventions.md     → data integrity, domain folders, naming
.kiro/steering/laravel-boost.md           → PHP/Eloquent/testing conventions
.kiro/steering/sync-api-context.md        → sync API + lift_sets/band_color context
```

### The frozen shared spec (reference — the source of truth this slice mirrors)
```
../../docs/plans/band-color-palette-cross-repo.md   → FROZEN §F1–F5. DO NOT re-decide anything here.
```

> **Boundary rule (directional isolation):** stay inside `logger/`. Do NOT read or write the root
> workspace, `../../contracts`, sibling apps, or `athlete/`. The frozen shapes you need are copied
> INLINE below so you never reach up. (The one path above is a read-only pointer for the human; the
> executor works only from the inline copies in this plan.)

## What You're Building

Logger currently accepts only `red`, `blue`, `green` as `band_color` values (validated in
`config/exercise_types.php` via `array_keys(config('bands.colors'))` and enforced again in
`BandedResistanceExerciseType`). Athlete now emits a five-color palette — `orange`, `red`, `blue`,
`green`, `black` — so new **Orange** (X-Light) and **Black** (X-Heavy) banded sets are currently
REJECTED on sync. This is a live, confirmed break for real users.

This slice:
1. Resets `config/bands.php` to the canonical five-color palette (lowercase ids, sensible even
   resistance increments, easy→hard `order`).
2. Makes `band_color` validation and membership checks **case-insensitive**.
3. Adds a forward-only migration that soft-deletes the 22 legacy `purple` rows (demo/test data,
   orphaned by both apps).
4. Adds/updates Logger-side tests proving the full palette validates and purple is gone.

End state: a banded set with any of the five palette colors (any case) is accepted by the Sync API
and by `BandedResistanceExerciseType`; no `purple` rows remain live; `BandService` orders progression
across all five colors.

## Existing Code to Understand (read before modifying)

```
config/bands.php                                        → the palette array (colors ⇒ resistance+order)
config/exercise_types.php                               → banded_resistance + banded_assistance validation rules (the in: rule)
app/Services/BandService.php                            → getBands / getBandResistance / getNextHarderBand / getPreviousEasierBand
app/Services/ExerciseTypes/BandedResistanceExerciseType.php → the in_array(band_color, availableBands) check + progression
app/Models/LiftSet.php                                  → SoftDeletes, band_color in $fillable
tests/Unit/BandServiceTest.php                          → existing BandService coverage to extend
```

Also find (grep, don't guess) every reader of `config('bands.colors')` and every `in_array(..., $availableBands)`
or `InvalidExerciseDataException::invalidBandColor` call site — those are the case-insensitivity consumers.

## FROZEN palette (mirror EXACTLY — from cross-repo §F1, `order`-only)

```php
// config/bands.php → 'colors'  (NO numeric 'resistance' field — order-only)
'orange' => ['order' => 1],  // X-Light
'red'    => ['order' => 2],  // Light
'blue'   => ['order' => 3],  // Medium
'green'  => ['order' => 4],  // Heavy
'black'  => ['order' => 5],  // X-Heavy
```

Keep the two existing scalar keys unchanged: `'default_reps_on_band_change' => 8`,
`'max_reps_before_band_change' => 15`.

**Drop the `resistance` field entirely (cross-repo §F6).** Its only reader was
`BandService::getBandResistance()`, which has zero non-test callers — dead config. Removing it means
also removing that method and its test (see Execution Plan). All behavior uses `order`, never
`resistance`. Display in both apps is derived from the `band_color` STRING (`ucfirst` in Logger), never
a number (cross-repo §F7).

## Consumer Impact Trace (mandatory — data/config shape change)

`config('bands.colors')` keys are the allowed band set. Changing them affects:

1. **What reads it:**
   - `config/exercise_types.php` — builds the `in:` validation string from `array_keys`. Adding
     `orange`/`black` to the config automatically widens the allowed set. ← primary fix path.
   - `app/Services/BandService.php` — `getBands()`, `getNextHarderBand()`, `getPreviousEasierBand()`
     iterate the array by `order`. New five-color order must sort correctly. `getBandResistance()` is
     the ONE reader of the removed `resistance` field → it is DELETED (dead code), not updated.
   - `app/Services/ExerciseTypes/BandedResistanceExerciseType.php` — `in_array($band_color, array_keys(config('bands.colors')))`
     and `config('bands.colors')[$bandColor]['order']` for progression. Must accept the new colors AND
     compare case-insensitively. (Reads `order`, not `resistance` — unaffected by the field removal.)
2. **What interprets it at display time:** band_color is rendered as the STRING, `ucfirst`-capitalized
   at render time — `formatWeightDisplay` ("Band: Black"), `formatSingleSetBadge` ("Black band"),
   `formatTableCellDisplay`, and the picker `getFieldOptions('band_color')`
   (`{value: color, label: ucfirst(color)}` from `array_keys`). None read `resistance`. Lowercase
   storage/config is correct; capitalization is derived. Display is unaffected by the id-set change.
3. **PRs:** none. Banded is a no-PR family in both engines (`config/pr_families.php` → null); no PR path
   reads band color/resistance/order. Nothing to trace.
4. **What tests assert on the old shape:** `tests/Unit/BandServiceTest.php` asserts the OLD three-color
   set and `resistance` values (incl. `test_get_band_resistance_returns_correct_value` and a
   `$bands['red']['resistance']` assertion). The resistance test is DELETED with the method; the
   `getBands()` test is updated to the five-color `order`-only shape. Grep `bands.colors`,
   `getBandResistance`, `getNextHarderBand`, `'red'`/`'blue'`/`'green'` in `tests/` and update remaining
   assertions to the new palette. Do NOT delete tests that still have a purpose — only remove the
   resistance test whose subject no longer exists; add `orange`/`black` + case-insensitivity cases.

## Execution Plan (phases; test only at checkpoints)

1. **Palette config** — rewrite `config/bands.php` `colors` to the frozen five-color `order`-only map
   (no `resistance` field).
2. **Remove dead resistance code** — delete `BandService::getBandResistance()` and its unit test
   `test_get_band_resistance_returns_correct_value` (+ any `$bands[...]['resistance']` assertion). Grep
   to confirm zero remaining references to `getBandResistance` / `resistance` in `app/` + `tests/`.
3. **Case-insensitivity** — normalize band_color to lowercase before the membership/validation
   comparison everywhere it is checked (validation rule construction stays keyed on lowercase config
   keys; `BandedResistanceExerciseType` lowercases the incoming value before `in_array` and before the
   `['order']` lookup). Resolve upstream: normalize at the earliest validation boundary, not scattered.
4. **Purple cleanup migration** — `php artisan make:migration delete_legacy_purple_band_colors_from_lift_sets --no-interaction`.
   In `up()`: soft-delete via Eloquent — `LiftSet::whereRaw('LOWER(band_color) = ?', ['purple'])->delete()`
   (SoftDeletes sets `deleted_at`). `down()`: no-op with a PHPDoc note that deleted demo rows are not
   resurrected. Forward-only.
5. **Checkpoint** — `php artisan test --parallel`.
6. **Tests** — update `BandServiceTest` `getBands()` to the five-color `order`-only shape and extend
   `getNextHarderBand`/`getPreviousEasierBand` across all five colors; add banded validation coverage
   for `orange`/`black` acceptance and mixed-case acceptance; add a migration test asserting purple
   rows are soft-deleted and a non-purple row is untouched.
7. **RUN the migration** — `php artisan migrate --no-interaction`, then `php artisan migrate:status`
   confirms it shows **Ran** (per antigravity-steering §8 / §6: a migration is not done until Run).
8. **Final checkpoint** — `php artisan test --parallel`.

## Hard Rules

- NEVER commit / push / run any git command.
- NEVER run Pint or any formatter.
- NEVER run destructive DB commands (`migrate:fresh`, `migrate:reset`, `db:wipe`). Only forward `migrate`.
- Migration is forward-only; new migration file, never edit an existing one.
- New `band_color` handling must be case-insensitive (frozen §F2).

## Implementation Rules

- Use Eloquent (`LiftSet`), not the `DB::` facade, in the migration.
- `config()` not `env()`; keep config keys lowercase.
- Explicit return types, constructor promotion, PHPUnit (never Pest), factories for test data.
- Match the sibling migration + BandService test patterns; introduce no new conventions.

## Success Criteria

- [ ] `config/bands.php` `colors` = the frozen five-color `order`-only map (orange/red/blue/green/black; order 1–5; NO `resistance` field).
- [ ] `BandService::getBandResistance()` and its unit test removed; zero references to `getBandResistance`/`resistance` remain in `app/` + `tests/`.
- [ ] Sync validation + `BandedResistanceExerciseType` accept all five colors, case-insensitively.
- [ ] New forward-only migration soft-deletes all `purple` rows (case-insensitive); non-purple untouched.
- [ ] Migration RUN — `php artisan migrate:status` shows it **Ran**; zero live `purple` rows remain.
- [ ] `BandServiceTest` (order-only) + banded validation + migration tests updated/added and green.
- [ ] `php artisan test --parallel` fully green.
- [ ] No git commits, no Pint, no new dependencies.

## Do Not

- Do NOT edit the root workspace, `../../contracts`, or `athlete/` (directional isolation).
- Do NOT hard-delete purple rows — soft delete only.
- Do NOT re-add `purple` to the palette or remap purple rows to another color.
- Do NOT change display strategies (band_color display is unaffected).
- Do NOT re-add a numeric `resistance` field to the config — `order` is the only numeric field.
- Do NOT leave `getBandResistance()` behind as a no-op or "just in case" — remove it fully (dead code).

## Post-Execution Retro (authored by the REVIEWER, not the executor)
- **Attempts:** {1 (clean) / N — root cause}
- **Tests added:** {count}
- **Migration Ran + verified:** {yes/no}
- **Prompt improvements for next time:** {…}
- **Steering updates needed:** {yes/no + what}
