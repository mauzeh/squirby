# Model Renaming and Refactoring Plan

This document outlines the plan to rename several core models to improve clarity and consistency across the codebase. The process is broken down into small, atomic steps, each with pre and post-testing to ensure the application remains stable throughout the refactoring.

## Renaming Goals

The following models will be renamed:

- `DailyLog` -> `FoodLog`
- `Workout` -> `LiftLog`
- `MeasurementLog` -> `BodyLog`

## Implementation Steps

The refactoring will be done in three phases, one for each model.

### Phase 1: Rename `DailyLog` to `FoodLog`

1.  **Pre-testing:** Run the entire test suite to ensure a stable starting point.
2.  **Create `FoodLog` Model and Factory:** Duplicate `app/Models/DailyLog.php` to `app/Models/FoodLog.php` and `database/factories/DailyLogFactory.php` to `database/factories/FoodLogFactory.php`. Update the class names and model references inside the new files.
3.  **Post-testing:** Run the entire test suite to ensure no existing functionality has been broken.
4.  **Create `FoodLogSeeder`:** Duplicate `database/seeders/DailyLogSeeder.php` to `database/seeders/FoodLogSeeder.php` and update it to use the `FoodLogFactory`.
5.  **Update `DatabaseSeeder`:** In `database/seeders/DatabaseSeeder.php`, replace `DailyLogSeeder::class` with `FoodLogSeeder::class`.
6.  **Post-testing:** Run `php artisan migrate:fresh --seed` and then run the entire test suite.
7.  **Create `food_logs` table:** Create a new migration to create the `food_logs` table, duplicating the schema of the `daily_logs` table. Run the migration.
8.  **Data Migration:** Create a data migration to copy all data from `daily_logs` to `food_logs`. Run the migration.
9.  **Post-testing:** Write a test to assert that the data has been migrated correctly.
10. **Refactor Controller, Routes, and Views:**
    - Rename `DailyLogController` to `FoodLogController`.
    - Update the controller to use the `FoodLog` model.
    - Duplicate the `daily-logs` routes and views for `food-logs`.
    - Update the new views to use the new routes and variables.
11. **Update Relationships:** Update any relationships in other models that point to `DailyLog` to now point to `FoodLog`.
12. **Final Testing:** Run the entire test suite.
13. **Cleanup:** Remove the old `DailyLog` model, factory, seeder, controller, routes, views, and the `daily_logs` table migration.

### Phase 2: Rename `Workout` to `LiftLog`

(Follow the same 13 steps as in Phase 1, but for `Workout` -> `LiftLog`)

### Phase 3: Rename `MeasurementLog` to `BodyLog`

(Follow the same 13 steps as in Phase 1, but for `MeasurementLog` -> `BodyLog`)