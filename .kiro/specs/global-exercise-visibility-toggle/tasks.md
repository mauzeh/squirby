# Implementation Plan

- [x] 1. Create database migration for user preference
  - Create migration to add `show_global_exercises` boolean column to users table with default true
  - Include backfill for existing users to maintain current behavior
  - _Requirements: 1.4, 2.2, 3.1_

- [x] 2. Update User model with new preference field
  - Add `show_global_exercises` to fillable attributes in User model
  - Add boolean cast for the new field
  - Add helper method `shouldShowGlobalExercises()` that returns the preference with true as fallback
  - _Requirements: 1.4, 2.1, 3.2_

- [x] 3. Modify Exercise model scope for preference-based filtering
  - Update `scopeAvailableToUser` method to accept optional `$showGlobal` parameter
  - Implement filtering logic that shows only user exercises when `$showGlobal` is false
  - Maintain admin user behavior (always see all exercises)
  - Preserve existing ordering logic
  - _Requirements: 1.2, 1.3_

- [x] 4. Update ProfileUpdateRequest validation
  - Add validation rule for `show_global_exercises` field as nullable boolean
  - Ensure backward compatibility with existing form submissions
  - _Requirements: 1.4, 2.2_

- [ ] 5. Add global exercise preference to profile settings form
  - Add checkbox input for global exercise visibility in `update-profile-information-form.blade.php`
  - Include proper label "Show global exercises in mobile entry"
  - Add help text explaining the setting
  - Ensure form includes the field in submission
  - _Requirements: 1.1, 1.4, 3.3_

- [ ] 6. Update LiftLogController to respect user preference
  - Modify `mobileEntry` method to get user's global exercise preference
  - Pass preference to Exercise query using updated scope method
  - Maintain existing exercise filtering and mapping logic
  - _Requirements: 1.2, 1.3, 2.1_

- [ ] 7. Write unit tests for new functionality
  - Test User model `shouldShowGlobalExercises()` method
  - Test Exercise scope `availableToUser` with preference parameter
  - Test ProfileUpdateRequest validation with new field
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [ ] 8. Write feature tests for complete workflow
  - Test profile settings form submission with global exercise preference
  - Test mobile entry interface respects user preference
  - Test admin users always see all exercises
  - Test default behavior for new users
  - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3_