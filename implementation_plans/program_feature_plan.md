# Program Feature Implementation Plan

This document outlines the plan to add a program feature to the application, allowing users to manage their scheduled workouts.

**Constraint:** The implementation should not use any custom JavaScript.

## 1. Backend Setup

-   **Model & Migration:**
    -   Generate a `Program` model and migration.
    -   The `programs` table will have `user_id`, `exercise_id`, `date`, `sets`, `reps`, and `weight` (nullable decimal) columns.
-   **Form Requests:**
    -   Create `StoreProgramRequest` and `UpdateProgramRequest` for validation.
-   **Controller:**
    -   Generate a resourceful `ProgramController`.
    -   Implement `index`, `create`, `store`, `edit`, `update`, and `destroy` methods.
    -   Use route-model binding and scope all queries to the authenticated user.
-   **Routes:**
    -   Add `Route::resource('programs', ProgramController::class);` to `routes/web.php`.

## 2. Frontend Setup

-   **Blade Partial:**
    -   Create `resources/views/programs/_form.blade.php` for the shared form fields to reduce code duplication.
-   **Views:**
    -   Create `index.blade.php`, `create.blade.php`, and `edit.blade.php` under `resources/views/programs/`.
    -   The views will be styled to match the `food-logs` section.
-   **Navigation:**
    -   Add a "Program" link to the main navigation layout.

## 3. Data Seeding

-   **Seeder:**
    -   Generate a `ProgramSeeder`.
-   **Example Data:**
    -   The seeder will add the example program (5x3 Back Squat, 5x3 Bench Press) for tomorrow's date for the first user.
-   **Database Seeder:**
    -   Call `ProgramSeeder` from the main `DatabaseSeeder.php`.

## 4. Testing

-   **Unit Tests:**
    -   Create a unit test for the `Program` model to verify its relationships and properties.
    -   Create unit tests for the `StoreProgramRequest` and `UpdateProgramRequest` to ensure the validation rules are correct.

-   **Feature Tests:**
    -   Create a feature test file for the `Program` feature (`tests/Feature/ProgramTest.php`).
    -   Test that an authenticated user can view their own programs.
    -   Test that a user cannot view programs belonging to other users.
    -   Test the full CRUD lifecycle:
        -   A user can successfully create a program with valid data.
        -   Validation errors are returned when creating a program with invalid data.
        -   A user can update their own program.
        -   A user can delete their own program.
    -   Test that guests are redirected to the login page.