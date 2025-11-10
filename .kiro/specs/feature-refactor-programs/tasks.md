# Implementation Plan

- [x] 1. Create MobileLiftForm model and migration
  - Create migration file to create mobile_lift_forms table with columns: id, user_id, date, exercise_id, timestamps
  - Add unique constraint on user_id, date, exercise_id combination
  - Add index on user_id and date
  - Add foreign key constraints with cascade delete
  - Create MobileLiftForm model class with fillable fields, casts, relationships, and scopes
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

- [x] 2. Update LiftLogService to use MobileLiftForm
  - [x] 2.1 Update generateForms() method to query mobile_lift_forms instead of programs
    - Remove program-related queries and logic
    - Query MobileLiftForm with exercise relationship
    - Remove completion status checks
    - Remove priority ordering logic
    - Calculate sets/reps/weight dynamically using TrainingProgressionService
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  
  - [x] 2.2 Implement addExerciseForm() method
    - Validate exercise exists
    - Check for duplicate forms
    - Create MobileLiftForm record
    - Return success/error with config messages
    - _Requirements: 4.1, 4.2, 4.3_
  
  - [x] 2.3 Implement removeForm() method
    - Parse form ID format
    - Find and delete MobileLiftForm record
    - Return success/error with config messages
    - _Requirements: 4.4, 4.5_
  
  - [x] 2.4 Implement createExercise() method
    - Validate exercise doesn't exist
    - Create Exercise record
    - Create MobileLiftForm record
    - Return success/error with config messages
    - _Requirements: 4.6_
  
  - [x] 2.5 Update generateItemSelectionList() method
    - Remove program-related logic
    - Get recommended exercises from RecommendationEngine
    - Include recent, custom, and regular exercises
    - Sort by priority: recommended > recent > custom > regular
    - Maintain existing UI functionality
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

- [x] 3. Update MobileEntryController
  - [x] 3.1 Update lifts() method
    - Change generateProgramForms() call to generateForms()
    - Remove any program-related logic
    - Verify existing addLiftForm(), removeForm(), createExercise() methods work correctly
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 4. Remove Programs system
  - [x] 4.1 Delete Program model and related files
    - Delete app/Models/Program.php
    - Delete app/Http/Controllers/ProgramController.php
    - Delete app/Http/Requests/StoreProgramRequest.php
    - Delete app/Http/Requests/UpdateProgramRequest.php
    - _Requirements: 7.1, 7.2, 7.3, 7.6_
  
  - [x] 4.2 Delete program views
    - Delete resources/views/programs/ directory and all files
    - _Requirements: 7.5_
  
  - [x] 4.3 Delete program tests
    - Delete any program-related test files
    - _Requirements: 7.7_
  
  - [x] 4.4 Remove program routes
    - Remove all program routes from routes/web.php
    - _Requirements: 7.4, 9.1, 9.2, 9.3, 9.4, 9.5_
  
  - [x] 4.5 Update services to remove program references
    - Update or remove RecommendationEngine program references
    - Remove program-related logic from LiftDataCacheService
    - Remove any other program references in services
    - _Requirements: 7.8, 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [x] 4.6 Remove program links from navigation and views
    - Search for and remove any links to program routes
    - Remove program-related UI elements
    - _Requirements: 8.3_

- [x] 5. Create migration to drop programs table
  - Create migration file to drop programs table
  - Run migration to apply changes
  - _Requirements: 7.1_

- [x] 6. Verify all tests succeed, and fix any remaining failing tests.

- [-] 7. Write tests for MobileLiftForm
  - [x] 7.1 Write unit tests for MobileLiftForm model
    - Test relationships (user, exercise)
    - Test forUserAndDate scope
    - Test fillable fields and casts
    - _Requirements: 11.1_
  
  - [x] 7.2 Write unit tests for LiftLogService methods
    - Test generateForms() with mobile_lift_forms
    - Test addExerciseForm() success and error cases
    - Test removeForm() success and error cases
    - Test createExercise() success and error cases
    - Test generateItemSelectionList() without program logic
    - Test dynamic calculation of sets/reps/weight
    - _Requirements: 11.2_
  
  - [x] 7.3 Write integration tests for MobileEntryController
    - Test lifts() page loads with mobile lift forms
    - Test addLiftForm() creates mobile_lift_forms record
    - Test removeForm() deletes mobile_lift_forms record
    - Test createExercise() creates exercise and mobile_lift_forms record
    - Test error handling for all operations
    - _Requirements: 11.3_
  
  - [x] 7.4 Verify all program references removed
    - Test that program routes return 404
    - Test that no program-related code remains
    - Test that mobile lift forms work identically to mobile food forms
    - _Requirements: 11.4, 11.5_

- [ ] 8. Manual verification and cleanup
  - Manually test mobile lift entry interface
  - Verify exercise selection list works correctly
  - Verify form add/remove operations work
  - Verify exercise creation works
  - Verify error messages display correctly
  - Search codebase for any remaining "Program" references
  - Update any documentation that references programs
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_
