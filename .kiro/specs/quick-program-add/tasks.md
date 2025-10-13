# Implementation Plan

- [x] 1. Modify top-exercises-buttons component to support program routing
  - Add new props for routeType and date to component signature
  - Implement conditional routing logic for programs vs exercises
  - Update both button links and dropdown item links to use appropriate routes
  - _Requirements: 2.1, 2.2, 2.3_

- [x] 2. Update ProgramController to provide exercise data for selector
  - Add exercise data fetching to the index method
  - Fetch top exercises using existing Exercise model methods
  - Fetch all available exercises for dropdown
  - Pass exercise data to the view
  - _Requirements: 1.1, 1.2_

- [x] 3. Modify ProgramController quickAdd method redirect behavior
  - Update redirect destination from lift-logs.mobile-entry to programs.index
  - Maintain date parameter in redirect
  - Keep existing program creation logic intact
  - _Requirements: 1.6, 1.7_

- [x] 4. Integrate exercise selector into programs index view
  - Create inline layout with Add Program Entry button and exercise selector
  - Position exercise selector to the right of the Add Program Entry button
  - Pass required props to top-exercises-buttons component
  - Remove existing standalone Add Program Entry button
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 5. Test the complete integration
  - Write feature test for quick program add functionality
  - Test button clicks create program entries correctly
  - Test dropdown selections create program entries correctly
  - Test date context is preserved correctly
  - Test success messages are displayed
  - _Requirements: 1.6, 1.7, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3_