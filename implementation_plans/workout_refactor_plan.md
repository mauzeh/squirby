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

1.  **Create `WorkoutSet` Model and Migration:**
    *   Create a new model `WorkoutSet`.
    *   Create a migration for the `workout_sets` table with `workout_id`, `weight`, `reps`, and `notes` (optional) columns.

2.  **Update `Workout` Model and Migration:**
    *   Create a migration to remove the `reps` and `rounds` columns from the `workouts` table.
    *   Update the `Workout` model to have a `hasMany` relationship with the `WorkoutSet` model.

3.  **Data Migration:**
    *   Create a data migration script using raw DB queries to:
        *   For each existing `Workout` record, create multiple `WorkoutSet` records based on the `rounds` and `reps` values.
        *   This will ensure that no data is lost during the refactoring.

4.  **Update Controllers:**
    *   Update `WorkoutController`'s `store` and `update` methods to accept `rounds` and `reps` from the form and then create the corresponding `WorkoutSet` records in the background.

5.  **Views:**
    *   The user interface will remain unchanged. The existing workout logging form with `rounds` and `reps` fields will be preserved.

6.  **Update Seeder:**
    *   Update the `WorkoutSeeder` to work with the new `Workout` and `WorkoutSet` models.

## Holistic Feature Consideration

To ensure a smooth refactoring process, we need to consider the following related features:

### 1. TSV Import/Export

*   **Import:** The TSV import file format will remain the same. The `importTsv` method in the `WorkoutController` will be updated to parse the old format and create the new `Workout` and `WorkoutSet` records accordingly.
*   **Export:** The TSV export format will be updated to generate the old format from the new data structure.

### 2. Workout Analysis and Charting

*   The `showLogs` method in the `ExerciseController` and the `index` method in the `WorkoutController` will be updated to work with the new `WorkoutSet` model.
*   The one-rep max calculation will be moved to the `WorkoutSet` model. For charts that show the one-rep max progression, we will use the highest `one_rep_max` from all the sets in a workout.