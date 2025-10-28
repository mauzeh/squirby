# Implementation Plan

- [ ] 1. Create ExerciseMergeService for business logic
  - Create service class with merge compatibility validation methods
  - Implement method to find potential global target exercises
  - Implement core merge logic with database transaction handling
  - Add method to append merge notes to lift log comments
  - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3, 5.1, 5.2, 5.3, 5.6, 6.1, 6.4_

- [ ] 2. Extend Exercise model with merge-related methods
  - Add canBeMergedByAdmin() method to check merge eligibility
  - Add isCompatibleForMerge() method for compatibility validation
  - Add hasOwnerWithGlobalVisibilityDisabled() method for visibility warnings
  - _Requirements: 2.1, 5.1, 5.2, 5.3, 5.4_

- [ ] 3. Add merge routes and controller methods
  - Add GET route for merge target selection page
  - Add POST route for executing merge operation
  - Implement showMerge() controller method with authorization
  - Implement merge() controller method with validation and service integration
  - _Requirements: 1.1, 1.4, 2.1, 2.2, 2.3, 2.4_

- [ ] 4. Create merge target selection view
  - Create merge selection blade template
  - Display source exercise details and compatible targets
  - Add radio button selection for target exercises
  - Include compatibility warnings and user visibility alerts
  - Add merge confirmation form with CSRF protection
  - _Requirements: 2.1, 2.2, 2.3, 5.4_

- [ ] 5. Update exercises index view with merge buttons
  - Add merge button to actions column for eligible exercises
  - Implement display logic for admin users only
  - Style merge button with appropriate icon and color
  - Position button correctly in actions column layout
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [ ] 6. Add merge authorization to ExercisePolicy
  - Create merge policy method for admin-only access
  - Ensure proper authorization checks in controller methods
  - _Requirements: 1.4, 2.4_

- [ ] 7. Write comprehensive tests for merge functionality
  - Create ExerciseMergeServiceTest with compatibility and merge scenarios
  - Create controller tests for merge endpoints and authorization
  - Create integration tests for complete merge workflow
  - Test error handling and transaction rollback scenarios
  - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_