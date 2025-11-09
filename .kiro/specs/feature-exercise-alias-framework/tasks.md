# Implementation Plan

- [ ] 1. Create database migration and ExerciseAlias model
  - Create migration for exercise_aliases table with user_id, exercise_id, alias_name, timestamps
  - Add unique constraint on (user_id, exercise_id)
  - Add foreign key constraints with cascade delete
  - Add indexes on user_id and exercise_id
  - Create ExerciseAlias model with fillable fields
  - Define belongsTo relationships for user and exercise
  - Create scopes: forUser($userId), forExercise($exerciseId)
  - _Requirements: 1.4, 1.5, 1.6, 5.1, 5.2, 7.1, 7.2, 7.3, 7.4_

- [ ] 2. Create ExerciseAliasService for alias operations
  - Create service class in app/Services/ExerciseAliasService.php
  - Implement createAlias(User $user, Exercise $exercise, string $aliasName): ExerciseAlias
  - Implement getUserAliases(User $user): Collection with request-level caching
  - Implement applyAliasesToExercises(Collection $exercises, User $user): Collection
  - Implement getDisplayName(Exercise $exercise, User $user): string
  - Implement hasAlias(User $user, Exercise $exercise): bool
  - Add request-level cache property and logic
  - Add error handling with graceful fallback to exercise title
  - _Requirements: 1.2, 1.4, 2.1, 2.2, 3.1, 3.2, 6.1, 6.2, 6.3_

- [ ] 3. Enhance Exercise model with alias support
  - Add aliases() hasMany relationship to Exercise model
  - Add getDisplayNameForUser(User $user): string helper method
  - Add hasAliasForUser(User $user): bool helper method
  - Update model to support eager loading of aliases
  - _Requirements: 2.1, 2.2, 3.1, 3.2, 4.1, 4.2_

- [ ] 4. Update ExerciseMergeService to support alias creation
  - Add $createAlias parameter to mergeExercises() method signature
  - Create private method createAliasForOwner(Exercise $source, Exercise $target, bool $createAlias)
  - Call createAliasForOwner() after data transfer but before source deletion
  - Ensure alias creation is within the same transaction
  - Add alias creation to merge audit logging
  - Handle duplicate alias errors gracefully (log and continue)
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [ ] 5. Update merge view with alias creation checkbox
  - Add checkbox input to resources/views/exercises/merge.blade.php
  - Set checkbox name to "create_alias" with value "1"
  - Check checkbox by default
  - Add descriptive label explaining alias functionality
  - Show source exercise title in the description
  - Style checkbox section consistently with existing form
  - _Requirements: 1.1, 1.7_

- [ ] 6. Update ExerciseController merge methods
  - Update showMerge() to pass necessary data to view
  - Update merge() to accept create_alias parameter from request
  - Pass createAlias boolean to ExerciseMergeService::mergeExercises()
  - Default to true if parameter not provided
  - Update success message to mention alias creation if applicable
  - _Requirements: 1.1, 1.2, 1.3, 1.7_

- [ ] 7. Implement alias display in exercise lists
  - Create ExerciseAliasComposer in app/Http/View/Composers/
  - Register composer for exercises.index view in AppServiceProvider
  - Update ExerciseController::index() to eager load aliases for current user
  - Apply aliases to exercise collection before passing to view
  - Ensure alphabetical sorting works with display names
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 6.1_

- [ ] 8. Implement alias display in lift log views
  - Register ExerciseAliasComposer for lift-logs.* views
  - Update LiftLogController to eager load exercise aliases
  - Update LiftLogTablePresenter to use display names
  - Apply aliases in chart generation (ChartService)
  - Ensure aliases appear in lift log exports
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 6.1_

- [ ] 9. Implement alias display in program views
  - Register ExerciseAliasComposer for programs.* views
  - Update ProgramController to eager load exercise aliases
  - Apply aliases in program entry displays
  - Apply aliases in program templates
  - Ensure aliases persist through workout logging from programs
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 6.1_

- [ ] 10. Implement alias display in exercise selection interfaces
  - Update exercise dropdown components to use display names
  - Update exercise autocomplete to search both alias and title
  - Apply aliases in exercise picker modals
  - Ensure aliases work in quick-add exercise forms
  - _Requirements: 2.3, 2.4_

- [ ] 11. Write unit tests for ExerciseAliasService
  - Test createAlias() creates alias with correct data
  - Test getUserAliases() returns keyed collection
  - Test getUserAliases() uses request-level cache
  - Test applyAliasesToExercises() modifies exercise titles
  - Test getDisplayName() returns alias when exists
  - Test getDisplayName() returns title when no alias
  - Test getDisplayName() handles errors gracefully
  - Test hasAlias() returns correct boolean
  - _Requirements: 2.1, 2.2, 3.1, 3.2, 6.1, 6.2, 6.3_

- [ ] 12. Write unit tests for ExerciseAlias model
  - Test user relationship
  - Test exercise relationship
  - Test forUser scope
  - Test forExercise scope
  - Test unique constraint enforcement
  - Test cascade delete on user deletion
  - Test cascade delete on exercise deletion
  - _Requirements: 5.1, 5.2, 7.1, 7.2, 7.3, 7.4_

- [ ] 13. Write integration tests for merge with alias creation
  - Test merge with alias creation enabled creates alias
  - Test merge with alias creation disabled does not create alias
  - Test alias is created before source deletion
  - Test alias creation within transaction
  - Test rollback on alias creation failure
  - Test merge with checkbox checked by default
  - Test duplicate alias handling (should not fail)
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [ ] 14. Write feature tests for alias display
  - Test exercise list shows aliases for user with aliases
  - Test exercise list shows titles for user without aliases
  - Test lift log table shows aliases
  - Test lift log charts use aliases
  - Test program view shows aliases
  - Test exercise selection dropdowns show aliases
  - Test export includes alias names
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4_

- [ ] 15. Write performance tests
  - Test exercise list with aliases uses single additional query
  - Test lift logs with aliases uses single additional query
  - Test programs with aliases uses single additional query
  - Verify request-level caching prevents duplicate queries
  - Measure response time impact (should be < 10ms)
  - _Requirements: 6.1, 6.2, 6.3, 6.4_
