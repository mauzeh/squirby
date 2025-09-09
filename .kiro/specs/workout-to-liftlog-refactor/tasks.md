# Implementation Plan

- [x] 1. Create database migration for table renaming
  - Create migration to rename `workouts` table to `lift_logs`
  - Create migration to rename `workout_sets` table to `lift_sets`
  - Update foreign key column `workout_id` to `lift_log_id` in lift_sets table
  - Update any indexes and constraints to use new table names
  - _Requirements: 2.2, 3.1, 3.2_

- [x] 2. Update LiftLog model and relationships
  - [x] 2.1 Rename Workout model to LiftLog
    - Rename `app/Models/Workout.php` to `app/Models/LiftLog.php`
    - Update class name and table reference
    - Update model relationships and methods
    - Update fillable fields and casts
    - _Requirements: 2.1, 2.4_

  - [x] 2.2 Rename WorkoutSet model to LiftSet
    - Rename `app/Models/WorkoutSet.php` to `app/Models/LiftSet.php`
    - Update class name and table reference
    - Update relationship method to reference LiftLog
    - Update foreign key reference from `workout_id` to `lift_log_id`
    - _Requirements: 2.1, 2.4_

  - [x] 2.3 Update related model relationships
    - Update User model `workouts()` method to `liftLogs()`
    - Update Exercise model `workouts()` method to `liftLogs()`
    - Update any other model relationships that reference workouts
    - _Requirements: 2.4_

- [x] 3. Update controller and routes
  - [x] 3.1 Rename WorkoutController to LiftLogController
    - Rename controller file and class name
    - Update all model references to use LiftLog and LiftSet
    - Update success/error messages to use "lift log" terminology
    - Update variable names and method parameters
    - _Requirements: 2.3, 6.3_

  - [x] 3.2 Update route definitions
    - Update web.php to use `lift-logs` routes instead of `workouts`
    - Update all route names and controller references
    - Ensure route model binding works with LiftLog
    - _Requirements: 1.2_

- [x] 4. Update views and UI elements
  - [x] 4.1 Rename view directory and files
    - Rename `resources/views/workouts/` to `resources/views/lift-logs/`
    - Update all view file references in controller
    - _Requirements: 1.1, 6.2_

  - [x] 4.2 Update view content and terminology
    - Update page titles to use "Lift Log" terminology
    - Update form labels, buttons, and headings
    - Update table headers and data display
    - Update chart titles and labels
    - _Requirements: 1.1, 1.3, 6.2, 6.5_

  - [x] 4.3 Update navigation elements
    - Update main navigation to use "Lifts" instead of "Workouts"
    - Update sub-navigation and breadcrumbs
    - Update active state detection for new routes
    - _Requirements: 1.1, 6.1_

- [x] 5. Update service layer dependencies
  - [x] 5.1 Update TsvImporterService
    - Update method `importWorkouts` to reference LiftLog model
    - Update any workout-related variable names and comments
    - Update success/error messages in service
    - _Requirements: 4.6_

  - [x] 5.2 Update OneRepMaxCalculatorService
    - Update method parameters to use LiftLog instead of Workout
    - Update method names that reference workout to use liftLog
    - Update any internal variable names and comments
    - _Requirements: 4.4, 4.5_

- [x] 6. Update model factories and seeders
  - [x] 6.1 Update Workout factory
    - Rename factory file to LiftLogFactory
    - Update factory class name and model reference
    - Update any relationships in factory definitions
    - _Requirements: 5.1, 5.2_

  - [x] 6.2 Update WorkoutSet factory
    - Rename factory file to LiftSetFactory
    - Update factory class name and model reference
    - Update foreign key reference from `workout_id` to `lift_log_id`
    - _Requirements: 5.1, 5.2_

  - [x] 6.3 Update WorkoutSeeder
    - Rename seeder file to LiftLogSeeder
    - Update seeder class name and model references
    - Update any workout-related data creation
    - _Requirements: 5.1, 5.2_

- [x] 7. Update test files
  - [x] 7.1 Rename and update feature tests
    - Rename `WorkoutLoggingTest.php` to `LiftLogLoggingTest.php`
    - Rename `WorkoutImportTest.php` to `LiftLogImportTest.php`
    - Rename `WorkoutExerciseFilteringTest.php` to `LiftLogExerciseFilteringTest.php`
    - Update class names and test method names
    - _Requirements: 5.1, 5.2_

  - [x] 7.2 Update test method implementations
    - Update all model references to use LiftLog and LiftSet
    - Update route references to use new lift-logs routes
    - Update assertions to check for "lift log" terminology
    - Update factory references and test data creation
    - _Requirements: 5.1, 5.2, 5.3_

- [x] 8. Update configuration and miscellaneous files
  - [x] 8.1 Update any configuration references
    - Search for and update any config files that reference workouts
    - Update any environment-specific configurations
    - Update any documentation or comments
    - _Requirements: 2.1_

  - [x] 8.2 Update any remaining references
    - Search codebase for any remaining "workout" references
    - Update variable names, comments, and documentation
    - Update any API documentation or external references
    - _Requirements: 2.1, 2.4_

- [-] 9. Final integration verification
  - [ ] 9.1 Test complete user workflows
    - Test creating, editing, and deleting lift logs
    - Test TSV import/export workflows
    - Test chart display and data visualization
    - Test bulk delete functionality
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [ ] 9.2 Verify UI consistency
    - Check all navigation links work correctly
    - Verify page titles and headings use correct terminology
    - Test form submissions and success/error messages
    - Ensure responsive design is maintained
    - _Requirements: 1.1, 1.2, 1.3, 6.1, 6.2, 6.3, 6.4, 6.5_