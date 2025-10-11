# Implementation Plan

- [x] 1. Add bulk promotion authorization to ExercisePolicy
  - Add `promoteToGlobal` method to verify admin permissions and exercise eligibility
  - Ensure only admins can promote exercises and only user-specific exercises can be promoted
  - _Requirements: 4.1, 4.2_

- [x] 2. Implement bulk promotion controller method
  - Add `promoteSelected` method to ExerciseController
  - Validate request data and authorize each selected exercise
  - Update selected exercises to global status by setting user_id to null
  - Return appropriate success/error responses
  - _Requirements: 1.4, 1.5, 2.1, 2.4, 3.1, 3.2, 3.3, 3.4_

- [x] 3. Add bulk promotion route
  - Add POST route for exercises/promote-selected endpoint
  - Follow existing naming convention matching destroy-selected pattern
  - _Requirements: 1.2, 1.3_

- [x] 4. Update exercise index view with promotion UI
  - Add promotion form in table footer next to existing delete form
  - Include admin-only visibility check using blade directive
  - Style promotion button with green background and globe icon
  - _Requirements: 1.1, 1.2, 5.1, 5.2_

- [x] 5. Implement frontend JavaScript for promotion functionality
  - Add form submission handler for promotion form
  - Validate selection and filter to user exercises only
  - Show confirmation dialog and prevent submission if no valid exercises selected
  - Follow same pattern as existing bulk delete JavaScript
  - _Requirements: 1.3, 2.2, 5.3, 5.4, 5.5_

- [x] 6. Write unit tests for promotion functionality
  - Test ExercisePolicy promoteToGlobal method with admin and non-admin users
  - Test controller promoteSelected method with various scenarios
  - Test authorization and validation logic
  - _Requirements: 4.2, 4.3_

- [x] 7. Write feature tests for bulk promotion workflow
  - Test complete admin promotion workflow from UI to database
  - Test permission denial for non-admin users
  - Test error handling for invalid selections
  - Test success message and UI refresh after promotion
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 4.1, 4.3_