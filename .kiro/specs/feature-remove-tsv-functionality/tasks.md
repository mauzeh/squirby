# Implementation Plan

- [ ] 1. Remove TSV import routes and middleware
  - Remove all TSV import routes from `routes/web.php`
  - Remove TSV route group and middleware registration
  - Remove `RestrictTsvImportsInProduction` middleware class
  - _Requirements: 1.1, 1.3, 4.1_

- [ ] 2. Remove TSV import controller methods
  - [ ] 2.1 Remove `FoodLogController::importTsv` method
    - Remove method and related validation rules
    - Clean up TSV service constructor injection
    - _Requirements: 1.1, 3.1, 4.3_

  - [ ] 2.2 Remove `IngredientController::importTsv` method
    - Remove method and related validation rules
    - Clean up TSV service constructor injection
    - _Requirements: 1.1, 3.1, 4.3_

  - [ ] 2.3 Remove `BodyLogController::importTsv` method
    - Remove method and related validation rules
    - Clean up TSV service constructor injection
    - _Requirements: 1.1, 3.1, 4.3_

  - [ ] 2.4 Remove `ExerciseController::importTsv` method
    - Remove method and related validation rules
    - Clean up TSV service constructor injection
    - _Requirements: 1.1, 3.1, 4.3_

  - [ ] 2.5 Remove `LiftLogController::importTsv` method
    - Remove method and related validation rules
    - Clean up TSV service constructor injection
    - _Requirements: 1.1, 3.1, 4.3_

  - [ ] 2.6 Remove `ProgramController::import` method
    - Remove method and related validation rules
    - Clean up TSV service constructor injection
    - _Requirements: 1.1, 3.1, 4.3_

- [ ] 3. Remove TSV import forms from views
  - [ ] 3.1 Remove TSV forms from `resources/views/food_logs/index.blade.php`
    - Remove TSV import form and environment conditionals
    - _Requirements: 1.2, 3.3, 4.2_

  - [ ] 3.2 Remove TSV forms from `resources/views/ingredients/index.blade.php`
    - Remove TSV import form and environment conditionals
    - _Requirements: 1.2, 3.3, 4.2_

  - [ ] 3.3 Remove TSV forms from `resources/views/body-logs/index.blade.php`
    - Remove TSV import form and environment conditionals
    - _Requirements: 1.2, 3.3, 4.2_

  - [ ] 3.4 Remove TSV forms from `resources/views/exercises/index.blade.php`
    - Remove TSV import form and environment conditionals
    - _Requirements: 1.2, 3.3, 4.2_

  - [ ] 3.5 Remove TSV forms from `resources/views/lift-logs/index.blade.php`
    - Remove TSV import form and environment conditionals
    - _Requirements: 1.2, 3.3, 4.2_

  - [ ] 3.6 Remove TSV forms from `resources/views/programs/index.blade.php`
    - Remove TSV import form and environment conditionals
    - _Requirements: 1.2, 3.3, 4.2_

- [ ] 4. Remove TSV service classes
  - [ ] 4.1 Remove `TsvImporterService` class
    - Delete `app/Services/TsvImporterService.php`
    - _Requirements: 1.4, 3.4_

  - [ ] 4.2 Remove `IngredientTsvProcessorService` class
    - Delete `app/Services/IngredientTsvProcessorService.php`
    - _Requirements: 1.5, 3.4_

  - [ ] 4.3 Remove `ProgramTsvImporterService` class
    - Delete `app/Services/ProgramTsvImporterService.php`
    - _Requirements: 1.4, 3.4_

- [ ] 5. Create hardcoded ingredient seeder
  - [ ] 5.1 Design minimal ingredient dataset
    - Create array of 10-15 common ingredients with complete nutritional data
    - Include variety of units (grams, pieces, tablespoons, etc.)
    - Cover major food categories (proteins, carbs, fats, vegetables, dairy)
    - _Requirements: 2.1, 2.3, 5.1, 5.4_

  - [ ] 5.2 Implement new `IngredientSeeder` class
    - Replace CSV/TSV processing with hardcoded data arrays
    - Maintain same seeder interface and admin user association
    - Add proper error handling and logging
    - _Requirements: 2.1, 2.5, 5.5_

- [ ] 6. Create hardcoded exercise seeder
  - [ ] 6.1 Design minimal exercise dataset
    - Create array of 20-25 common exercises covering major muscle groups
    - Include both bodyweight and weighted exercises
    - Cover upper body, lower body, core, and cardio categories
    - _Requirements: 2.2, 2.4, 5.2, 5.3_

  - [ ] 6.2 Implement new `GlobalExercisesSeeder` class
    - Replace CSV processing with hardcoded data arrays
    - Maintain same seeder interface for global exercises
    - Add proper error handling and logging
    - _Requirements: 2.2, 2.5, 5.5_

- [ ] 7. Remove CSV data files and TSV tests
  - [ ] 7.1 Remove CSV files from seeders
    - Delete `database/seeders/csv/ingredients_from_real_world.csv`
    - Delete `database/seeders/csv/exercises_from_real_world.csv`
    - Remove entire `database/seeders/csv/` directory if empty
    - _Requirements: 3.7_

  - [ ] 7.2 Remove TSV-related test files
    - Delete TSV service test files
    - Delete TSV controller method tests
    - Delete TSV route tests
    - Delete TSV middleware tests
    - _Requirements: 3.5_

- [ ] 8. Update navigation and route references
  - [ ] 8.1 Clean up route references in views
    - Remove any TSV route references from navigation
    - Update `resources/views/app.blade.php` if it references TSV routes
    - _Requirements: 4.2_

  - [ ] 8.2 Update any documentation or comments
    - Remove TSV references from code comments
    - Update any inline documentation about import features
    - _Requirements: 3.1, 3.2, 3.3_

- [ ] 9. Verify removal completeness
  - [ ] 9.1 Run comprehensive search for TSV references
    - Search codebase for remaining "tsv", "TSV", "importTsv" references
    - Verify no broken references or imports remain
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [ ] 9.2 Test seeder functionality
    - Run `php artisan db:seed` to verify new seeders work
    - Verify seeding performance improvements
    - Test that application functions normally with hardcoded data
    - _Requirements: 2.1, 2.2, 2.5, 5.5_

  - [ ] 9.3 Verify application functionality
    - Test that all data management pages load without TSV forms
    - Verify no broken links or references to removed functionality
    - Confirm application startup and performance improvements
    - _Requirements: 1.2, 4.1, 4.2_