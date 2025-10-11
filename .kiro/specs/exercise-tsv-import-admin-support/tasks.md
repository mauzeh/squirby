# Implementation Plan

- [x] 1. Update TsvImporterService to support global exercise imports
  - Add importAsGlobal parameter to importExercises method
  - Add admin permission validation for global imports
  - Create processExerciseImport method to route to appropriate import logic
  - Create processGlobalExerciseImport method for global exercise handling
  - Create processUserExerciseImport method for user exercise handling with global conflict checking
  - Update return array to include detailed lists of importedExercises, updatedExercises, and skippedExercises with specific exercise details and change tracking
  - Write unit tests for all new service methods and import modes
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_

- [x] 2. Update ExerciseController to handle global import option
  - Add import_as_global validation to importTsv method
  - Add admin permission check for global imports in controller
  - Update service call to pass importAsGlobal parameter
  - Create buildImportSuccessMessage method to generate detailed lists of imported, updated, and skipped exercises with specific changes
  - Update error handling to provide specific feedback for different failure modes
  - Write feature tests for controller with both import modes
  - _Requirements: 1.1, 2.1, 4.3, 4.4_

- [x] 3. Update exercise import view to show global import option for admins
  - Add conditional checkbox for import_as_global option (admin only)
  - Add explanatory text about global vs personal exercises
  - Update form styling and layout for new option
  - Write feature tests for view rendering with different user roles
  - _Requirements: 4.1, 4.2_

- [x] 4. Update existing unit tests to work with new service signature
  - Modify ExerciseTsvImportTest to use new importExercises method signature
  - Add tests for global import mode functionality
  - Add tests for conflict detection between global and user exercises
  - Add tests for admin permission validation
  - Update test assertions to verify detailed exercise lists and change tracking in return array
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_

- [x] 5. Update existing feature tests to work with new import functionality
  - Modify ExerciseTsvImportFeatureTest to test both import modes
  - Add tests for admin vs non-admin access to global import option
  - Add tests for detailed import result messages showing specific exercise lists and changes
  - Add tests for global exercise conflict scenarios
  - Update existing test assertions to match new behavior
  - _Requirements: 1.1, 2.1, 2.3, 4.1, 4.2, 4.3, 4.4_

- [x] 6. Add integration tests for complete TSV import workflow with admin-managed exercises
  - Test admin importing global exercises that conflict with existing user exercises
  - Test user importing exercises that conflict with existing global exercises
  - Test mixed scenarios with both global and user exercises in database
  - Test detailed import result lists showing specific exercises imported, updated, and skipped with change details
  - Test backward compatibility with existing exercise data
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4, 4.3, 4.4_

- [x] 7. Update lift log TSV import to work with new exercise scoping
  - Modify importLiftLogs method to use availableToUser scope instead of user-specific query
  - Update lift log import tests to work with global exercises
  - Ensure lift log imports can find both global and user exercises
  - Write tests for lift log import with mixed global/user exercise scenarios
  - _Requirements: 2.2, 3.1_