# Banded Movements Feature - Detailed Task List

This document outlines the step-by-step tasks for implementing the "Banded Movements" feature, based on the `banded_movements_plan.md`. Each task includes specific instructions and verification steps.

## Phase 1: Core Data Model & Configuration

### Task 1.1: Database Migrations

#### 1.1.1 Add `band_type` to `exercises` table
- **Instructions:**
    - Create a new migration: `php artisan make:migration add_band_type_to_exercises_table --table=exercises`
    - In the `up()` method, add a `string` column named `band_type` that is `nullable` to the `exercises` table. This column should be constrained to an enum-like set of values: 'resistance', 'assistance'.
    - In the `down()` method, drop the `band_type` column.
- **Verification:**
    - Run `php artisan migrate`.
    - Confirm the `band_type` column exists in the `exercises` table (e.g., `php artisan db:table exercises --json`).
    - Run `php artisan migrate:rollback` and `php artisan migrate` to ensure the migration is reversible.

#### 1.1.2 Add `band_color` to `lift_sets` table
- **Instructions:**
    - Create a new migration: `php artisan make:migration add_band_color_to_lift_sets_table --table=lift_sets`
    - In the `up()` method, add a `string` column named `band_color` that is `nullable` to the `lift_sets` table.
    - In the `down()` method, drop the `band_color` column.
- **Verification:**
    - Run `php artisan migrate`.
    - Confirm the `band_color` column exists in the `lift_sets` table (e.g., `php artisan db:table lift_sets --json`).
    - Run `php artisan migrate:rollback` and `php artisan migrate` to ensure the migration is reversible.

### Task 1.2: Model Updates

#### 1.2.1 Update `app/Models/Exercise.php`
- **Instructions:**
    - Add `band_type` to the `$fillable` array.
    - Add helper methods `isBandedResistance(): bool` and `isBandedAssistance(): bool` to check the `band_type` status.
- **Verification:**
    - Manually inspect the `Exercise` model to confirm `band_type` is in `$fillable` and helper methods are present and correctly implemented.

#### 1.2.2 Update `app/Models/LiftSet.php`
- **Instructions:**
    - Add `band_color` to the `$fillable` array.
- **Verification:**
    - Manually inspect the `LiftSet` model to confirm `band_color` is in `$fillable`.

#### 1.2.3 Update `app/Models/LiftLog.php`
- **Instructions:**
    - Review and adjust any methods that implicitly assume `weight` (e.g., `getOneRepMaxAttribute`, `getDisplayWeightAttribute`) to handle `band_color` for banded exercises, considering `band_type`. This will likely involve conditional logic based on `exercise->band_type`.
- **Verification:**
    - Manually inspect the `LiftLog` model to confirm adjustments for `band_color` handling.

### Task 1.3: Configuration

#### 1.3.1 Create `config/bands.php`
- **Instructions:**
    - Create a new file `config/bands.php`.
    - Define an array of band colors, their order (easiest to hardest), and associated resistance values. Example:
        ```php
        return [
            'colors' => [
                'red' => ['resistance' => 10, 'order' => 1],
                'blue' => ['resistance' => 20, 'order' => 2],
                'green' => ['resistance' => 30, 'order' => 3],
                'black' => ['resistance' => 40, 'order' => 4],
            ],
            'default_reps_on_band_change' => 8,
            'max_reps_before_band_change' => 15,
        ];
        ```
- **Verification:**
    - Manually confirm the file `config/bands.php` exists and contains the specified configuration.

## Phase 2: Business Logic & Services

### Task 2.1: `BandService` (New Service)

#### 2.1.1 Create `app/Services/BandService.php`
- **Instructions:**
    - Create the `app/Services/BandService.php` file.
    - Implement the following methods:
        - `getBands(): array`: Returns the configured band array.
        - `getBandResistance(string $color): ?int`: Returns resistance for a given color.
        - `getNextHarderBand(string $currentColor, string $bandType): ?string`: Returns the next band in the progression, considering the `band_type`.
        - `getPreviousEasierBand(string $currentColor, string $bandType): ?string`: Returns the previous band in the progression, considering the `band_type`.
- **Verification:**
    - Create `tests/Unit/BandServiceTest.php` and write comprehensive unit tests for all methods in `BandService`.

### Task 2.2: `TrainingProgressionService` Updates

#### 2.2.1 Modify `app/Services/TrainingProgressionService.php`
- **Instructions:**
    - Inject `BandService` into `TrainingProgressionService`.
    - Update `getSuggestionDetails` method:
        - If `exercise->band_type` is not null, implement the banded progression logic:
            - If `last_logged_reps` is less than `config('bands.max_reps_before_band_change', 15)`:
                - Suggest `min(last_logged_reps + 1, config('bands.max_reps_before_band_change', 15))` reps with the *same band*.
            - If `last_logged_reps` is `config('bands.max_reps_before_band_change', 15)` or more:
                - Suggest the *next harder band* (determined by `BandService` based on `band_type`) at `config('bands.default_reps_on_band_change', 8)` reps.
        - This will require injecting `BandService` into `TrainingProgressionService`.
- **Verification:**
    - Update `tests/Unit/TrainingProgressionServiceTest.php` to include tests for banded exercise progression logic and fallbacks.

### Task 2.3: `OneRepMaxCalculatorService` Review

#### 2.3.1 Modify `app/Services/OneRepMaxCalculatorService.php`
- **Instructions:**
    - **Exclude banded exercises from 1RM calculations altogether.**
    - For banded exercises (where `exercise->band_type` is not null), the service **must throw an exception** (e.g., `NotApplicableException`) when 1RM is requested, clearly indicating that 1RM is not a meaningful metric for this exercise type.
- **Verification:**
    - Update `tests/Unit/OneRepMaxCalculatorServiceTest.php` to include tests that verify an exception is thrown for banded exercises.

## Phase 3: User Interface (UI) / User Experience (UX)

### Task 3.1: Exercise Forms (`exercises/create.blade.php`, `exercises/edit.blade.php`)

#### 3.1.1 Add `band_type` selection
- **Instructions:**
    - In both `exercises/create.blade.php` and `exercises/edit.blade.php`, add a new form field for `band_type`. This should be a dropdown or radio buttons with options "None", "Resistance", "Assistance".
    - The `is_bodyweight` checkbox will remain visible. Its value will be ignored or overridden in the backend (`ExerciseController`) if a `band_type` other than "None" is selected.
- **Verification:**
    - Manually test the forms in a browser to ensure the `band_type` selection appears and `is_bodyweight` remains visible.
    - Create/update feature tests for exercise creation/editing to verify `band_type` is saved correctly and `is_bodyweight` is handled as per backend logic.

### Task 3.2: Lift Log Entry Forms (`lift-logs/mobile-entry.blade.php`, `lift-logs/edit.blade.php`)

#### 3.2.1 Adjust input for banded exercises
- **Instructions:**
    - For exercises where `exercise->band_type` is not null:
        - Replace the `weight` input field with a `band_color` dropdown selector (Red, Blue, Green, Black, based on `config/bands.php`).
        - The `reps` input field remains.
    - For exercises where `exercise->band_type` is null, retain the `weight` input.
- **Verification:**
    - Manually test the forms in a browser with both banded and non-banded exercises.
    - Create/update feature tests for lift log entry to verify `band_color` is saved correctly.

### Task 3.3: Lift Log Display (`lift-logs/index.blade.php`, `exercises/logs.blade.php`)

#### 3.3.1 Display `band_color` instead of weight
- **Instructions:**
    - For banded exercises, display the `band_color` used instead of weight.
    - Update any progression displays to reflect band color changes.
- **Verification:**
    - Manually test the display in a browser for banded exercises.
    - Create/update feature tests to assert the correct display of `band_color`.

### Task 3.4: Program Forms (`programs/_form_create.blade.php`, `programs/_form.blade.php`)

#### 3.4.1 Update auto-calculation display
- **Instructions:**
    - Update the auto-calculation display to show the suggested `band_color` and `reps` for banded exercises.
    - Ensure the `sets` and `reps` fields are correctly populated by the auto-calculation logic for banded exercises.
- **Verification:**
    - Manually test program creation/editing with banded exercises.
    - Create/update feature tests for program creation/editing to verify correct auto-calculation and display.

## Phase 4: Testing

### Task 4.1: Unit Tests

#### 4.1.1 `BandServiceTest.php` (New)
- **Instructions:**
    - Create `tests/Unit/BandServiceTest.php`.
    - Write comprehensive unit tests for `getBands`, `getBandResistance`, `getNextHarderBand`, `getPreviousEasierBand` methods, covering both 'resistance' and 'assistance' band types.
- **Verification:**
    - All tests in `BandServiceTest.php` pass.

#### 4.1.2 `TrainingProgressionServiceTest.php` (Update)
- **Instructions:**
    - Add tests for banded exercise progression logic (linear rep progression, then band change).
    - Test fallbacks when no progression data exists for banded exercises.
    - Test both 'resistance' and 'assistance' band types.
- **Verification:**
    - All tests in `TrainingProgressionServiceTest.php` pass.

#### 4.1.3 `ExerciseTest.php` (Update)
- **Instructions:**
    - Add tests for `isBandedResistance()` and `isBandedAssistance()` helper methods.
- **Verification:**
    - All tests in `ExerciseTest.php` pass.

#### 4.1.4 `LiftSetTest.php` (Update)
- **Instructions:**
    - Add tests for `band_color` assignment and retrieval.
- **Verification:**
    - All tests in `LiftSetTest.php` pass.

### Task 4.2: Feature Tests

#### 4.2.1 `BandedExerciseCreationTest.php` (New)
- **Instructions:**
    - Create `tests/Feature/BandedExerciseCreationTest.php`.
    - Test creating/editing banded exercises via forms, verifying `band_type` is saved correctly.
    - Test interaction with `is_bodyweight` (backend handling).
- **Verification:**
    - All tests in `BandedExerciseCreationTest.php` pass.

#### 4.2.2 `BandedLiftLoggingTest.php` (New)
- **Instructions:**
    - Create `tests/Feature/BandedLiftLoggingTest.php`.
    - Test logging lifts with band colors via forms.
    - Test display of band colors in lift logs.
- **Verification:**
    - All tests in `BandedLiftLoggingTest.php` pass.

#### 4.2.3 `BandedProgramCreationTest.php` (New)
- **Instructions:**
    - Create `tests/Feature/BandedProgramCreationTest.php`.
    - Test auto-derivation of sets/reps/band for banded exercises in programs.
    - Test quick-add functionality with banded exercises.
- **Verification:**
    - All tests in `BandedProgramCreationTest.php` pass.

#### 4.2.4 Integration Tests
- **Instructions:**
    - Ensure existing non-banded exercises and logs are unaffected.
    - Test interactions between `is_bodyweight` and `band_type` (e.g., if an exercise is both, how is it handled in the backend).
- **Verification:**
    - Run all existing feature tests to ensure no regressions.

## Phase 5: Documentation

### Task 5.1 User Documentation
- **Instructions:**
    - Update relevant sections of the application's user guide (if any) to explain banded movements, including how to create them, log them, and understand their progression.
- **Verification:**
    - User documentation is updated and clear.

### Task 5.2 Developer Documentation
- **Instructions:**
    - Document the `BandService` and its usage.
    - Update `TrainingProgressionService` documentation to reflect banded logic.
- **Verification:**
    - Developer documentation is updated and clear.
