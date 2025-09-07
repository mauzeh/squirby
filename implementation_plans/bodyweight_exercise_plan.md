## Implementation Plan: Adding Default Bodyweight Chin-Ups

This plan details the steps to integrate "Chin-Ups" as a default bodyweight exercise for new users, addressing its implications across the application.

**Phase 1: Core Data Model Changes**

*   **Exercise Model (`app/Models/Exercise.php`):**
    *   Add a new field to differentiate between bodyweight and weighted exercises (e.g., `is_bodyweight` boolean). This will be crucial for 1RM calculations.
    *   Update the `ExerciseFactory` (`database/factories/ExerciseFactory.php`) to support this new field.

*   **Workout Model (`app/Models/Workout.php`):**
    *   Review existing fields to see if they implicitly assume weighted exercises (e.g., `weight`, `reps`).
    *   Consider how bodyweight exercises will be logged. For bodyweight, `weight` might be null or 0, and `reps` would still apply.

*   **WorkoutSet Model (`app/Models/WorkoutSet.php`):**
    *   Similar to `Workout` model, ensure it can handle bodyweight exercises.

**Phase 2: Default Data Provisioning**

*   **User Model (`app/Models/User.php`):**
    *   Add "Chin-Ups" to the default exercises created in the `booted()` method. Set `is_bodyweight` to `true` for this exercise.

**Phase 3: 1RM Calculation Adjustments**

*   **OneRepMaxCalculatorService (`app/Services/OneRepMaxCalculatorService.php`):**
    *   Modify the 1RM calculation logic to handle bodyweight exercises.
        *   For bodyweight exercises, 1RM is typically calculated differently (e.g., using bodyweight as part of the "load" or using a different formula).
        *   If `is_bodyweight` is true, the `weight` field from `WorkoutSet` should be interpreted differently or ignored, and bodyweight should be factored in. This implies needing access to the user's current bodyweight.
        *   **Consideration:** How will the service get the user's bodyweight at the time of the workout? This might require linking `Workout` or `WorkoutSet` to `MeasurementLog` or having a `user_bodyweight` field in `Workout` or `WorkoutSet` (which would be a new field). For simplicity, initially, I might assume a default bodyweight or require the user to input it for 1RM calculations for bodyweight exercises. A more robust solution would involve fetching the most recent bodyweight measurement for the user at or before the workout date.

**Phase 4: UI/UX Presentation**

*   **Workout Logging Forms (`resources/views/workouts/create.blade.php`, `resources/views/workouts/edit.blade.php`):**
    *   Adjust the forms to allow logging of bodyweight exercises. This might involve:
        *   Disabling or hiding the weight input field if a bodyweight exercise is selected.
        *   Potentially adding a field for "added weight" if the user adds weight to a bodyweight exercise (e.g., weighted chin-ups).

*   **1RM Presentation (Views/Graphs):**
    *   Identify views that display 1RM values (e.g., `resources/views/exercises/show.blade.php`, `resources/views/workouts/show.blade.php`, or any dedicated 1RM reports).
    *   Adjust how 1RM is displayed for bodyweight exercises. It might be presented as "Bodyweight + Added Weight" or simply "Reps at Bodyweight."
    *   Graphs might need to differentiate between bodyweight and weighted exercises, or use different scales/labels.

**Phase 5: Testing**

*   **Unit Tests:**
    *   Add unit tests for the `Exercise` model to ensure `is_bodyweight` works correctly.
    *   Add unit tests for `OneRepMaxCalculatorService` to verify 1RM calculations for bodyweight exercises.

*   **Feature Tests:**
    *   Add feature tests for user creation to ensure "Chin-Ups" are added correctly.
    *   Add feature tests for workout logging to ensure bodyweight exercises can be logged correctly.
    *   Add feature tests for 1RM presentation to ensure bodyweight 1RMs are displayed as expected.

**Detailed Steps for Implementation:**

**Step 1: Modify Exercise Model**
*   Add `is_bodyweight` column to `exercises` table:
    ```bash
    php artisan make:migration add_is_bodyweight_to_exercises_table --table=exercises
    ```
*   Edit the migration file (`database/migrations/..._add_is_bodyweight_to_exercises_table.php`):
    ```php
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->boolean('is_bodyweight')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('is_bodyweight');
        });
    }
    ```
*   Run `php artisan migrate`.
*   Update `app/Models/Exercise.php` to include `is_bodyweight` in `$fillable`.

**Step 2: Update User Model for Default Exercise**
*   Edit `app/Models/User.php`:
    *   In the `static::created` function, add "Chin-Ups" with `is_bodyweight` set to `true`.
    ```php
                ['title' => 'Chin-Ups', 'description' => 'A bodyweight pulling exercise.', 'is_bodyweight' => true],
    ```

**Step 3: Adjust OneRepMaxCalculatorService**
*   Edit `app/Services/OneRepMaxCalculatorService.php`:
    *   Modify `calculateOneRepMaxForSet` and `calculateWorkoutOneRepMax` methods.
    *   **Initial approach (simplistic):** For bodyweight exercises, if `is_bodyweight` is true, assume `weight` is 0 and the user's bodyweight is the load. This requires fetching the user's bodyweight.
    *   **More robust approach:** Add a `bodyweight` parameter to the 1RM calculation methods, or fetch the user's bodyweight from `MeasurementLog` for the workout date. For now, let's assume we'll need to fetch it. This might require passing the `user_id` to the service or fetching the user's bodyweight within the service.

**Step 4: Update UI/UX**
*   **Workout Logging Forms (`resources/views/workouts/create.blade.php`, `resources/views/workouts/edit.blade.php`):**
    *   Adjust the forms to allow logging of bodyweight exercises. This might involve:
        *   Disabling or hiding the weight input field if a bodyweight exercise is selected.
        *   Potentially adding a field for "added weight" if the user adds weight to a bodyweight exercise (e.g., weighted chin-ups).

*   **1RM Presentation (Views/Graphs):**
    *   Identify views that display 1RM values (e.g., `resources/views/exercises/show.blade.php`, `resources/views/workouts/show.blade.php`, or any dedicated 1RM reports).
    *   Adjust how 1RM is displayed for bodyweight exercises. It might be presented as "Bodyweight + Added Weight" or simply "Reps at Bodyweight."
    *   Graphs might need to differentiate between bodyweight and weighted exercises, or use different scales/labels.

**Phase 5: Testing**

*   **Unit Tests:**
    *   Add unit tests for the `Exercise` model to ensure `is_bodyweight` works correctly.
    *   Add unit tests for `OneRepMaxCalculatorService` to verify 1RM calculations for bodyweight exercises.

*   **Feature Tests:**
    *   Add feature tests for user creation to ensure "Chin-Ups" are added correctly.
    *   Add feature tests for workout logging to ensure bodyweight exercises can be logged correctly.
    *   Add feature tests for 1RM presentation to ensure bodyweight 1RMs are displayed as expected.
