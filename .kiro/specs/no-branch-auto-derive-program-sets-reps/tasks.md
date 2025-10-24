# Implementation Plan

- [x] 1. Update ProgramController to integrate TrainingProgressionService
  - Inject TrainingProgressionService into constructor
  - Add private calculateSetsAndReps() method that uses TrainingProgressionService
  - Modify create() method to calculate and pass sets/reps data to view
  - Modify store() method to use calculated sets/reps values before saving
  - _Requirements: 1.2, 1.3, 1.4_

- [x] 2. Verify ProgramController edit functionality remains unchanged
  - Confirm edit() method continues to work with existing manual input fields
  - Confirm update() method continues to validate and save manual sets/reps input
  - Ensure no auto-calculation logic is applied to program editing
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 3. Update request validation classes for creation only
  - Remove 'sets' and 'reps' validation rules from StoreProgramRequest
  - Keep UpdateProgramRequest unchanged with existing sets/reps validation
  - Keep all other existing validation rules intact
  - _Requirements: 1.1, 2.1_

- [x] 4. Create separate form for program creation without manual input fields
  - Create new programs/_form_create.blade.php without sets and reps input fields
  - Add informational section explaining automatic calculation to creation form
  - Update programs/create.blade.php to use new _form_create.blade.php
  - Keep programs/edit.blade.php unchanged using existing _form.blade.php
  - _Requirements: 1.1, 2.1, 5.1, 5.2, 5.3_

- [x] 5. Verify quick-add functionality works with auto-calculation
  - Test that quickAdd() method in ProgramController continues to work correctly
  - Test that quickCreate() method uses appropriate default values
  - Ensure mobile entry workflow remains functional
  - _Requirements: 3.1, 3.2, 3.3_

- [x] 6. Write unit tests for ProgramController auto-calculation logic
  - Test calculateSetsAndReps() method with existing progression data
  - Test calculateSetsAndReps() method with no progression data (defaults)
  - Test store() method uses calculated values correctly
  - Test update() method continues to work with manual input (no changes)
  - Test edit functionality remains unchanged
  - _Requirements: 1.2, 1.3, 1.4, 2.1, 2.2, 2.3_

- [x] 7. Write feature tests for complete program creation workflow
  - Test program creation form renders without sets/reps input fields
  - Test program creation saves with auto-calculated values
  - Test program editing form continues to show manual sets/reps input fields
  - Test program editing continues to work with manual input validation
  - Test error handling when TrainingProgressionService fails
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 4.1, 4.2_

- [x] 8. Add integration tests for backward compatibility
  - Test that existing program entries display correctly without modification
  - Test that editing existing programs works with unchanged manual input functionality
  - Test that no existing program data is automatically modified
  - Verify all existing edit functionality continues to work as expected
  - _Requirements: 4.1, 4.2, 4.3_

- [x] 9. Test edge cases and error handling scenarios
  - Test behavior when TrainingProgressionService returns null
  - Test behavior when configuration defaults are missing
  - Test behavior with bodyweight exercises
  - Write tests for all fallback scenarios to ensure system robustness
  - _Requirements: 1.4, 5.4_