# Implementation Plan

- [x] 1. Update routes to replace bulk promotion with individual promotion
  - Remove the bulk promotion route `exercises/promote-selected`
  - Add new individual promotion route `exercises/{exercise}/promote`
  - _Requirements: 2.4_

- [ ] 2. Implement individual promotion controller method
  - [ ] 2.1 Remove the `promoteSelected` method from ExerciseController
    - Delete the existing bulk promotion method
    - _Requirements: 2.4_
  
  - [ ] 2.2 Create new `promote` method for individual exercise promotion
    - Add method that accepts Exercise model binding
    - Implement authorization using existing `promoteToGlobal` policy
    - Validate exercise is not already global
    - Update exercise to global status by setting user_id to null
    - Return redirect with success message
    - _Requirements: 1.3, 1.4, 3.1, 3.2, 3.4_

- [ ] 3. Update exercises index view for individual promotion
  - [ ] 3.1 Remove bulk promotion UI elements
    - Remove bulk promote button from table footer
    - Remove bulk promotion form from footer
    - _Requirements: 2.2_
  
  - [ ] 3.2 Add individual promote buttons to actions column
    - Add promote button next to edit/delete buttons for user exercises
    - Show button only for admin users and non-global exercises
    - Style button with globe icon and green background
    - Add JavaScript confirmation dialog
    - _Requirements: 1.1, 1.2, 1.5_
  
  - [ ] 3.3 Update JavaScript to remove bulk promotion logic
    - Remove bulk promotion form submission handling
    - Keep all bulk deletion JavaScript functionality intact
    - _Requirements: 2.3, 2.5_

- [ ] 4. Update tests for individual promotion
  - [ ] 4.1 Modify ExerciseBulkPromotionTest for individual promotion
    - Update test class name to ExerciseIndividualPromotionTest
    - Change test methods to use individual promotion route
    - Update assertions for individual promotion responses
    - _Requirements: 1.3, 1.4, 3.1, 3.2, 3.3_
  
  - [ ] 4.2 Add tests for promote button visibility and functionality
    - Test promote button appears for admin users on user exercises
    - Test promote button does not appear for non-admin users
    - Test promote button does not appear for global exercises
    - Test individual promotion success and error cases
    - _Requirements: 1.1, 1.2, 1.5_