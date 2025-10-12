# Implementation Plan

- [ ] 1. Create comprehensive feature comparison analysis
  - Document current TSV import behavior for both lift-logs and exercises
  - Create detailed comparison matrix of features, error handling, and user experience
  - Identify specific gaps and inconsistencies between implementations
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [ ] 2. Standardize TsvImporterService return structures
  - [ ] 2.1 Create unified return structure interface
    - Define standardized array structure for all TSV import methods
    - Include consistent fields: importedCount, updatedCount, skippedCount, items arrays
    - Add importMode and warnings fields for comprehensive feedback
    - _Requirements: 2.1, 2.2, 3.4, 4.1_

  - [ ] 2.2 Update importLiftLogs method to use standardized structure
    - Modify return array to match unified structure
    - Enhance item tracking with detailed descriptions and change information
    - Maintain backward compatibility with existing controller logic
    - _Requirements: 3.1, 3.5, 4.2_

  - [ ] 2.3 Update importExercises method to use standardized structure
    - Align return structure with unified format
    - Ensure consistent item detail formatting
    - Preserve existing change tracking functionality
    - _Requirements: 3.2, 3.5, 4.2_

- [ ] 3. Create ImportMessageBuilder service
  - [ ] 3.1 Implement base message building service
    - Create service class with methods for success and error message building
    - Implement consistent HTML formatting and structure
    - Add support for detailed item lists and change descriptions
    - _Requirements: 4.1, 4.2, 4.3, 6.4_

  - [ ] 3.2 Add specialized formatting methods
    - Implement formatItemList method for consistent list display
    - Create formatChangeDetails method for update descriptions
    - Add conditional logic for small vs large import message handling
    - _Requirements: 4.1, 4.2, 4.4_

  - [ ] 3.3 Integrate message builder with controllers
    - Update LiftLogController to use ImportMessageBuilder
    - Update ExerciseController to use ImportMessageBuilder
    - Ensure consistent message formatting across both controllers
    - _Requirements: 4.1, 4.2, 4.3, 8.4_

- [ ] 4. Enhance error handling consistency
  - [ ] 4.1 Standardize validation error messages
    - Create consistent error message formats for empty data, invalid formats
    - Implement uniform validation logic across both import types
    - Add helpful guidance for fixing common data issues
    - _Requirements: 6.1, 6.4, 8.1_

  - [ ] 4.2 Improve dependency error handling
    - Enhance missing exercise error messages in lift-log imports
    - Add suggestions for resolving exercise name conflicts
    - Implement consistent conflict resolution messaging
    - _Requirements: 6.2, 6.5_

  - [ ] 4.3 Unify permission error handling
    - Standardize admin permission validation across import types
    - Create consistent error messages for unauthorized global imports
    - Ensure uniform authorization checking patterns
    - _Requirements: 5.3, 5.4, 6.4_

- [ ] 5. Implement UI/UX consistency improvements
  - [ ] 5.1 Standardize TSV import form layouts
    - Ensure consistent textarea styling, sizing, and placeholder text
    - Align button placement and styling across both import forms
    - Add consistent help text and usage instructions
    - _Requirements: 8.1, 8.3, 8.5_

  - [ ] 5.2 Unify JavaScript behavior
    - Standardize copy-to-clipboard functionality across both features
    - Ensure consistent form validation and submission handling
    - Implement uniform loading states and user feedback
    - _Requirements: 8.2, 8.3_

  - [ ] 5.3 Improve success/error message display
    - Standardize success and error message container styling
    - Ensure consistent message positioning and formatting
    - Add proper HTML escaping and security measures
    - _Requirements: 8.4, 4.5_

- [ ] 6. Add comprehensive testing for consistency
  - [ ] 6.1 Create cross-feature consistency tests
    - Write tests comparing message formats between lift-log and exercise imports
    - Test return structure consistency across both import methods
    - Validate error handling parity between features
    - _Requirements: 1.1, 4.1, 6.4_

  - [ ] 6.2 Add UI consistency validation tests
    - Test form layout and styling consistency
    - Validate JavaScript behavior across both import features
    - Ensure consistent success/error message display
    - _Requirements: 8.1, 8.2, 8.4_

  - [ ] 6.3 Enhance permission and authorization tests
    - Test consistent admin permission handling across import types
    - Validate production environment restrictions work identically
    - Ensure uniform authorization error messages
    - _Requirements: 5.3, 5.4, 7.4_

- [ ] 7. Validate production environment restrictions
  - [ ] 7.1 Verify consistent environment controls
    - Test that both lift-log and exercise imports are hidden in production/staging
    - Validate middleware protection works identically for both features
    - Ensure consistent 404 error responses for restricted environments
    - _Requirements: 7.1, 7.2, 7.4_

  - [ ] 7.2 Test development environment functionality
    - Verify both import forms display correctly in development
    - Test full import functionality works in unrestricted environments
    - Validate consistent behavior across environment types
    - _Requirements: 7.3, 8.1_

- [ ] 8. Create documentation and usage guidelines
  - [ ] 8.1 Document TSV format requirements
    - Create clear documentation for lift-log TSV format (7 columns)
    - Document exercise TSV format requirements (3 columns)
    - Add examples and common error scenarios
    - _Requirements: 2.1, 2.2, 2.3_

  - [ ] 8.2 Create user guidance documentation
    - Document duplicate detection behavior for both import types
    - Explain update logic and when existing data gets modified
    - Provide troubleshooting guide for common import issues
    - _Requirements: 3.1, 3.2, 6.5_

  - [ ] 8.3 Add developer implementation guidelines
    - Document standardized return structures and message building
    - Create guidelines for maintaining consistency in future features
    - Provide examples of proper error handling and user feedback
    - _Requirements: 4.1, 6.4, 8.4_