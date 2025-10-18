# Implementation Plan

- [x] 1. Enhance Exercise model availableToUser scope for admin visibility
  - Modify the `scopeAvailableToUser()` method in `app/Models/Exercise.php` to check user role
  - Implement conditional query logic: admin users get all exercises, regular users get current scoped behavior
  - Ensure the scope accepts user ID, retrieves User model, and checks for Admin role using `hasRole('Admin')`
  - Maintain existing ordering patterns and preserve current functionality for regular users
  - _Requirements: 1.1, 1.2, 2.1, 2.2, 3.1_

- [x] 2. Create comprehensive test coverage for enhanced model scope
  - Write unit test for the `availableToUser` scope with admin user to verify it returns all exercises
  - Write unit test for the `availableToUser` scope with regular user to verify current scoped behavior
  - Write feature test to verify admin users can see all exercises in the exercise list
  - Write feature test to verify regular users continue to see only their scoped exercises
  - Test edge cases like invalid user IDs, users with no role, and mixed exercise datasets
  - _Requirements: 1.1, 2.1, 3.1, 3.2_

- [x] 3. Verify system-wide functionality and security
  - Test that all existing controller methods using `availableToUser` scope work correctly for both admin and regular users
  - Verify that admin users can properly edit and delete exercises from other users (via existing authorization policies)
  - Ensure that the enhanced scope doesn't break any existing functionality in showLogs, promote, or other exercise operations
  - Test role verification edge cases and ensure proper fallback behavior
  - _Requirements: 3.1, 3.2_