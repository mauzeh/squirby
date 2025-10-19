# Implementation Plan

- [x] 1. Add unpromote authorization to ExercisePolicy
  - Add `unpromoteToUser` method to verify admin permissions and exercise eligibility
  - Ensure only admins can unpromote exercises and only global exercises can be unpromoted
  - _Requirements: 4.1, 4.2_

- [x] 2. Implement unpromote controller method
  - [x] 2.1 Add `unpromote` method to ExerciseController
    - Add method that accepts Exercise model binding
    - Implement authorization using new `unpromoteToUser` policy
    - Add validation to ensure exercise is currently global
    - _Requirements: 1.3, 2.2, 4.2_
  
  - [x] 2.2 Implement original owner determination logic
    - Create `determineOriginalOwner` private method
    - Query lift logs to find earliest `logged_at` timestamp
    - Return the user associated with the earliest lift log
    - Handle case where no lift logs exist
    - _Requirements: 3.4, 4.4_
  
  - [x] 2.3 Add safety checks for other users' lift logs
    - Query lift logs to count distinct users (excluding original owner)
    - Prevent unpromote if other users have logs with the exercise
    - Provide detailed error messages with user counts
    - _Requirements: 2.1, 2.2, 2.3_
  
  - [x] 2.4 Complete unpromote operation
    - Update exercise user_id to original owner
    - Return success message with exercise title
    - Redirect to exercises index
    - _Requirements: 1.3, 3.1, 3.3_

- [x] 3. Add unpromote route
  - Add POST route for individual exercise unpromote
  - Use exercise model binding for automatic lookup
  - Follow existing naming convention with exercises.unpromote
  - _Requirements: 1.1_

- [x] 4. Update exercises index view
  - [x] 4.1 Add unpromote button to actions column
    - Add unpromote button next to existing action buttons for global exercises
    - Show button only for admin users and global exercises
    - Style button with orange background and user icon
    - Position after promote button, before delete button
    - _Requirements: 1.1, 5.1, 5.3, 5.4_
  
  - [x] 4.2 Add confirmation dialog and tooltip
    - Add JavaScript confirmation with detailed explanation
    - Include tooltip explaining unpromote functionality
    - Match styling patterns of existing action buttons
    - _Requirements: 5.2, 5.5_

- [ ] 5. Add comprehensive tests
  - [x] 5.1 Add policy tests for unpromote authorization
    - Test admin users can unpromote global exercises
    - Test non-admin users cannot unpromote exercises
    - Test cannot unpromote non-global exercises
    - _Requirements: 4.1, 4.2_
  
  - [x] 5.2 Add controller tests for unpromote functionality
    - Test successful unpromote when safe (no other users' logs)
    - Test blocked unpromote when other users have logs
    - Test original owner determination from lift logs
    - Test error handling for exercises without logs
    - Test authorization failures return 403
    - _Requirements: 1.3, 2.1, 2.2, 3.4, 4.2_
  
  - [x] 5.3 Add integration tests for UI functionality
    - Test unpromote button appears for admin users on global exercises
    - Test unpromote button does not appear for non-admin users
    - Test unpromote button does not appear for user exercises
    - Test successful unpromote updates exercise ownership
    - Test error scenarios display appropriate messages
    - _Requirements: 1.1, 1.5, 5.1, 5.5_