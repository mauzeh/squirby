# Workout Logging Refactor Plan

This document outlines the plan to refactor the workout logging feature to allow for logging each round of a workout individually.

## Current Architecture

*   A single `Workout` model.
*   Attributes: `exercise_id`, `weight`, `reps`, `rounds`, `comments`, `logged_at`.
*   This architecture assumes that the same number of reps is performed for a given number of rounds.

## Proposed Architecture

We will introduce a new model to store the details of each individual set:

1.  **`Workout` Model:**
    *   Represents a specific workout session for a given exercise on a given day.
    *   Will no longer have `reps` and `rounds` columns.

2.  **`WorkoutSet` Model:**
    *   Represents a single set within a workout.
    *   Attributes: `id`, `workout_id`, `weight`, `reps`, `notes` (optional).
    *   A `belongsTo` relationship to `Workout`.
    *   Will have a `one_rep_max` calculated attribute.

## Benefits

*   **Granular Data:** Allows for tracking the exact reps and weight for each individual set.
*   **Flexibility:** Accommodates different training styles, such as pyramid sets, drop sets, etc.
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
    *   Update `WorkoutController` to work with the new `Workout` and `WorkoutSet` models.
    *   The `store` and `update` methods will now need to handle creating and updating multiple `WorkoutSet` records for a single `Workout`.

5.  **Update Views:**
    *   Update the workout logging form to allow users to add and remove individual sets with different `reps` and `weight` values, similar to the provided prototype.
    *   Update the workout display to show the details of each individual set.

6.  **Update Seeder:**
    *   Update the `WorkoutSeeder` to work with the new `Workout` and `WorkoutSet` models.

## Holistic Feature Consideration

To ensure a smooth refactoring process, we need to consider the following related features:

### 1. TSV Import/Export

*   **Import:** The TSV import file format will remain the same. The `importTsv` method in the `WorkoutController` will be updated to parse the old format and create the new `Workout` and `WorkoutSet` records accordingly.
*   **Export:** The TSV export format will be updated to reflect the new data structure, with each row representing a single set.

### 2. Workout Analysis and Charting

*   The `showLogs` method in the `ExerciseController` and the `index` method in the `WorkoutController` will be updated to work with the new `WorkoutSet` model.
*   The one-rep max calculation will be moved to the `WorkoutSet` model. For charts that show the one-rep max progression, we will use the best set of each workout.