# Implementation Plan: Banded Movements Feature

This plan outlines the steps to introduce "banded movements" as a new type of exercise progression, allowing athletes to track their progress using resistance bands. The feature will integrate with existing exercise management, lift logging, and program functionalities.

## Phase 1: Core Data Model & Configuration

### 1.1 Database Migrations
- **Add `band_type` to `exercises` table:**
    - Create a new migration: `php artisan make:migration add_band_type_to_exercises_table --table=exercises`
    - Add `band_type` (string, nullable, enum: 'resistance', 'assistance') column.
- **Add `band_color` to `lift_sets` table:**
    - Create a new migration: `php artisan make:migration add_band_color_to_lift_sets_table --table=lift_sets`
    - Add `band_color` (string, nullable) column.

### 1.2 Model Updates
- **`app/Models/Exercise.php`:**
    - Add `band_type` to the `$fillable` array.
    - Add helper methods `isBandedResistance(): bool` and `isBandedAssistance(): bool` to check the `band_type` status.
- **`app/Models/LiftSet.php`:**
    - Add `band_color` to the `$fillable` array.
- **`app/Models/LiftLog.php`:**
    - Review and adjust any methods that implicitly assume `weight` (e.g., `getOneRepMaxAttribute`, `getDisplayWeightAttribute`) to handle `band_color` for banded exercises, considering `band_type`.

### 1.3 Configuration
- **Create `config/bands.php`:**
    - Define an array of band colors, their order (easiest to hardest), and associated resistance values (e.g., `['red' => 10, 'blue' => 20, 'green' => 30, 'black' => 40]`). This will be used for progression logic.

## Phase 2: Business Logic & Services

### 2.1 `BandService` (New Service)
- **Create `app/Services/BandService.php`:**
    - Encapsulate band-related logic:
        - `getBands(): array`: Returns the configured band array.
        - `getBandResistance(string $color): ?int`: Returns resistance for a given color.
        - `getNextHarderBand(string $currentColor, string $bandType): ?string`: Returns the next band in the progression, considering the `band_type`.
        - `getPreviousEasierBand(string $currentColor, string $bandType): ?string`: Returns the previous band in the progression, considering the `band_type`.

### 2.2 `TrainingProgressionService` Updates
- **Modify `app/Services/TrainingProgressionService.php`:**
    - Update `getSuggestionDetails` method:
        - If `exercise->band_type` is not null, implement the banded progression logic:
            - If `last_logged_reps` is less than 15:
                - Suggest `min(last_logged_reps + 1, 15)` reps with the *same band*.
            - If `last_logged_reps` is 15 or more:
                - Suggest the *next harder band* (determined by `BandService` based on `band_type`) at 8 reps.
        - This will require injecting `BandService` into `TrainingProgressionService`.

### 2.3 `OneRepMaxCalculatorService` Review
- **Modify `app/Services/OneRepMaxCalculatorService.php`:**
    - **Exclude banded exercises from 1RM calculations altogether.**
    - For banded exercises, the service **must throw an exception** (e.g., `NotApplicableException`) when 1RM is requested, clearly indicating that 1RM is not a meaningful metric for this exercise type.
    - This decision is based on the variable resistance of bands making a consistent 1RM calculation impractical or misleading.

## Phase 3: User Interface (UI) / User Experience (UX)

### 3.1 Exercise Forms (`exercises/create.blade.php`, `exercises/edit.blade.php`)
- Add a new `band_type` selection (e.g., dropdown or radio buttons) that includes options like "None", "Resistance", "Assistance".
- The `is_bodyweight` checkbox will remain visible. Its value will be ignored or overridden in the backend (`ExerciseController`) if a `band_type` other than "None" is selected.

### 3.2 Lift Log Entry Forms (`lift-logs/mobile-entry.blade.php`, `lift-logs/edit.blade.php`)
- For exercises where `exercise->isBanded()` is true:
    - Replace the `weight` input field with a `band_color` dropdown selector (Red, Blue, Green, Black).
    - The `reps` input field remains.
- For exercises where `exercise->isBanded()` is false, retain the `weight` input.

### 3.3 Lift Log Display (`lift-logs/index.blade.php`, `exercises/logs.blade.php`)
- For banded exercises, display the `band_color` used instead of weight.
- Update any progression displays to reflect band color changes.

### 3.4 Program Forms (`programs/_form_create.blade.php`, `programs/_form.blade.php`)
- Update the auto-calculation display to show the suggested `band_color` and `reps` for banded exercises.
- Ensure the `sets` and `reps` fields are correctly populated by the auto-calculation logic for banded exercises.

## Phase 4: Testing

### 4.1 Unit Tests
- **`BandServiceTest.php` (New):**
    - Test `getBands`, `getBandResistance`, `getNextHarderBand`, `getPreviousEasierBand`.
- **`TrainingProgressionServiceTest.php` (Update):**
    - Add tests for banded exercise progression logic (15 reps -> next band at 8 reps).
    - Test fallbacks when no progression data exists for banded exercises.
- **`ExerciseTest.php` (Update):**
    - Test `isBanded()` helper method.
- **`LiftSetTest.php` (Update):**
    - Test `band_color` assignment.

### 4.2 Feature Tests
- **`BandedExerciseCreationTest.php` (New):**
    - Test creating/editing banded exercises via forms.
    - Test `is_banded` flag interaction.
- **`BandedLiftLoggingTest.php` (New):**
    - Test logging lifts with band colors.
    - Test display of band colors in lift logs.
- **`BandedProgramCreationTest.php` (New):**
    - Test auto-derivation of sets/reps/band for banded exercises in programs.
    - Test quick-add functionality with banded exercises.
- **Integration Tests:**
    - Ensure existing non-banded exercises and logs are unaffected.
    - Test interactions between `is_bodyweight` and `is_banded` (e.g., if an exercise is both, how is it handled?).

## Phase 5: Documentation

### 5.1 User Documentation
- Update relevant sections of the application's user guide (if any) to explain banded movements.

### 5.2 Developer Documentation
- Document the `BandService` and its usage.
- Update `TrainingProgressionService` documentation.
