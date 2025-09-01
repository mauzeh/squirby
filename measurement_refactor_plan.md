# Measurement Architecture Refactor Plan

This document outlines the plan to refactor the `Measurements` architecture to be more robust and consistent with the `Exercises` and `Workouts` architecture.

## Current Architecture

*   A single `Measurement` model.
*   Attributes: `name` (string), `value` (string), `unit` (string), `comments` (text, nullable), `logged_at` (timestamp).
*   The `name` attribute is a free-text field, which can lead to inconsistencies.

## Proposed Architecture

We will create two new models:

1.  **`MeasurementType`** (analogous to `Exercise`)
    *   Represents the *type* of measurement.
    *   Attributes: `id`, `name` (e.g., "Body Weight", "Waist Circumference"), `default_unit`.

2.  **`MeasurementLog`** (analogous to `Workout`)
    *   Represents a specific *instance* of a measurement.
    *   Attributes: `id`, `measurement_type_id`, `value`, `unit`, `logged_at`, `comments`.
    *   A `belongsTo` relationship to `MeasurementType`.

## Benefits

*   **Normalization and Consistency:** Prevents typos and inconsistencies in measurement names.
*   **Improved Data Integrity:** Enforces that every measurement log belongs to a predefined type.
*   **Easier Analysis and Charting:** Simplifies grouping measurements by type and generating progress charts.
*   **Centralized Management:** Allows for managing measurement types from a single place.

## Implementation Steps

1.  **Create `MeasurementType` Model and Migration:**
    *   Create a new model `MeasurementType`.
    *   Create a migration for the `measurement_types` table with `name` and `default_unit` columns.

2.  **Create `MeasurementLog` Model and Migration:**
    *   Rename the existing `Measurement` model to `MeasurementLog`.
    *   Create a migration to rename the `measurements` table to `measurement_logs`.
    *   Add a `measurement_type_id` foreign key column to the `measurement_logs` table.

3.  **Update Controllers:**
    *   Rename `MeasurementController` to `MeasurementLogController`.
    *   Create a new `MeasurementTypeController` for managing measurement types (CRUD).
    *   Update the `MeasurementLogController` to work with the new `MeasurementLog` model and `MeasurementType` relationship.

4.  **Update Views:**
    *   Update the measurement forms to use a dropdown or autocomplete to select the `MeasurementType`.
    *   Create views for managing `MeasurementType`s.

5.  **Data Migration:**
    *   Create a data migration script to:
        *   Extract unique `name` values from the old `measurements` table and create new `MeasurementType` records.
        *   Populate the `measurement_type_id` in the `measurement_logs` table based on the old `name` values.

6.  **Update Routes:**
    *   Update `routes/web.php` to reflect the controller and model name changes.
