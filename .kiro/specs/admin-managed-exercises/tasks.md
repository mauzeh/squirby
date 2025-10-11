# Implementation Plan

- [x] 1. Create database migration to make user_id nullable in exercises table
  - Create migration file to modify exercises table structure
  - Add unique constraint for exercise names within scope (title, user_id)
  - Write migration rollback method
  - _Requirements: 1.1, 2.1, 3.4_

- [x] 2. Update Exercise model with new scopes and methods
  - Add scopeGlobal() method to query global exercises (user_id = null)
  - Add scopeUserSpecific() method to query user's exercises
  - Add scopeAvailableToUser() method to get exercises available to a specific user
  - Add isGlobal() helper method
  - Add canBeEditedBy() method for permission checking
  - Add canBeDeletedBy() method for deletion permission checking
  - Write unit tests for all new model methods and scopes
  - _Requirements: 1.1, 1.2, 2.1, 2.2, 3.1, 4.1_

- [x] 3. Create ExercisePolicy for authorization
  - Create policy class with createGlobalExercise() method
  - Add update() method to check edit permissions
  - Add delete() method to check deletion permissions
  - Register policy in AuthServiceProvider
  - Write unit tests for policy methods
  - _Requirements: 3.1, 3.3, 4.1_

- [x] 4. Update ExerciseController to handle global exercises
  - Modify index() method to use availableToUser scope
  - Update store() method to handle global exercise creation
  - Add validation for exercise name conflicts
  - Update create() method to pass admin status to view
  - Add edit() method with authorization
  - Add update() method with global exercise handling
  - Add destroy() method with lift log checking
  - Write feature tests for all controller methods
  - _Requirements: 1.1, 1.2, 2.1, 2.2, 3.1, 3.2, 3.4, 4.1_

- [x] 5. Update exercise views to support global exercise management
  - Modify exercises/index.blade.php to show global vs user exercises
  - Update exercises/create.blade.php to include global exercise option for admins
  - Update exercises/edit.blade.php to handle global exercise editing
  - Add conditional display of edit/delete buttons based on permissions
  - Write feature tests for view rendering and form submissions
  - _Requirements: 1.5, 2.2, 3.1, 3.2_

- [x] 6. Create data seeder for global exercises
  - Create GlobalExerciseSeeder class
  - Add common exercises as global exercises (user_id = null)
  - Update DatabaseSeeder to include GlobalExerciseSeeder
  - Remove duplicate exercises from User model's booted() method
  - Write tests to verify seeder creates global exercises correctly
  - _Requirements: 1.1_

- [x] 7. Update User model to remove exercise seeding
  - Remove exercise creation from User model's booted() method
  - Update user factory if needed for testing
  - Write tests to ensure users no longer get duplicate exercises on creation
  - _Requirements: 1.1, 2.1_

- [x] 8. Add integration tests for complete exercise management workflow
  - Test admin creating global exercises
  - Test user creating personal exercises with name conflict detection
  - Test exercise listing shows both global and personal exercises
  - Test permission restrictions for non-admin users
  - Test deletion prevention when exercises have lift logs
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.1, 3.2, 3.3, 3.4, 4.1, 4.2_