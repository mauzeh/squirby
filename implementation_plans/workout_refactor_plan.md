# Workout Logging Refactor Plan

This document outlines the plan to refactor the workout logging feature to allow for logging each round of a workout individually, while keeping the user interface unchanged.

## Current Architecture

*   A single `Workout` model.
*   Attributes: `exercise_id`, `weight`, `reps`, `rounds`, `comments`, `logged_at`.
*   This architecture assumes that the same number of reps is performed for a given number of rounds.

## Proposed Architecture

We will introduce a new model to store the details of each individual set, but the user will continue to interact with the application as if they are logging rounds and reps.

1.  **`Workout` Model:**
    *   Represents a specific workout session for a given exercise on a given day.
    *   Will no longer have `reps` and `rounds` columns in the database table.
    *   Will have a method to get the highest `one_rep_max` from its sets.

2.  **`WorkoutSet` Model:**
    *   Represents a single set within a workout.
    *   Attributes: `id`, `workout_id`, `weight`, `reps`, `notes` (optional).
    *   A `belongsTo` relationship to `Workout`.
    *   Will have a `one_rep_max` calculated attribute.

## Benefits

*   **Granular Data:** Allows for tracking the exact reps and weight for each individual set in the backend.
*   **Future Flexibility:** Paves the way for a more advanced UI in the future that can take advantage of the new data model.
*   **Improved Analysis:** Enables more detailed analysis of workout performance over time.

## Implementation Steps

1.  **Build a Testing Safety Net:**
    *   Before any other changes are made, we will create a comprehensive feature test for the existing workout functionality. This is a mandatory prerequisite to any code changes.
    *   A new feature test file will be created at `tests/Feature/WorkoutLoggingTest.php`.
    *   We will write tests for all "happy path" and "unhappy path" scenarios, including workout creation, viewing, updating, deletion, and form validation.
    *   All tests must be passing before proceeding to the next step.

2.  **Create `WorkoutSet` Model and Migration:**
    *   Create a new model `WorkoutSet`.
    *   Create a migration for the `workout_sets` table with `workout_id`, `weight`, `reps`, and `notes` (optional) columns.

3.  **Update `Workout` Model and Migration:**
    *   Create a migration to remove the `reps` and `rounds` columns from the `workouts` table.
    *   Update the `Workout` model to have a `hasMany` relationship with the `WorkoutSet` model.

4.  **Data Migration:**
    *   Create a data migration script using raw DB queries to:
        *   For each existing `Workout` record, create multiple `WorkoutSet` records based on the `rounds` and `reps` values.
        *   This will ensure that no data is lost during the refactoring.

5.  **Update Controllers:**
    *   Update `WorkoutController`'s `store` and `update` methods to accept `rounds` and `reps` from the form and then create the corresponding `WorkoutSet` records in the background.

6.  **Update Tests:**
    *   The test suite will be updated to reflect the new architecture (e.g., asserting the creation of `WorkoutSet` records instead of checking the `reps` and `rounds` columns on the `Workout` model).
    *   All tests must be passing before proceeding to the next step.

7.  **Views:**
    *   The user interface will remain unchanged. The existing workout logging form with `rounds` and `reps` fields will be preserved.

8.  **Update Seeder:**
    *   Update the `WorkoutSeeder` to work with the new `Workout` and `WorkoutSet` models.

## Holistic Feature Consideration

To ensure a smooth refactoring process, we need to consider the following related features:

### 1. TSV Import/Export

*   **Import:** The TSV import file format will remain the same. The `importTsv` method in the `WorkoutController` will be updated to parse the old format and create the new `Workout` and `WorkoutSet` records accordingly.
*   **Export:** The TSV export format will be updated to generate the old format from the new data structure.

### 2. Workout Analysis and Charting

*   The `showLogs` method in the `ExerciseController` and the `index` method in the `WorkoutController` will be updated to work with the new `WorkoutSet` model.
*   The one-rep max calculation will be moved to the `WorkoutSet` model. For charts that show the one-rep max progression, we will use the best set of each workout.

### 3. Maintaining UI Consistency

To ensure the user interface remains unchanged, we will implement the following:

*   **`Workout` Model Accessors:** We will add `getDisplayRepsAttribute`, `getDisplayRoundsAttribute`, and `getDisplayWeightAttribute` methods to the `Workout` model. These accessors will compute the values for display by using the first set's reps and weight, and the total count of sets for rounds.
*   **View Updates:** The `workouts/index.blade.php` and `exercises/logs.blade.php` views will be updated to use these new accessors (`$workout->display_reps`, `$workout->display_rounds`, `$workout->display_weight`) to continue showing the workout information in the "weight (reps x rounds)" format.
