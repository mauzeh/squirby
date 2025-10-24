# Implementation Plan

- [x] 1. Enhance TsvImporterService ingredient import method
  - Modify the `importIngredients` method to return detailed results like exercise import
  - Add tracking for imported, updated, and skipped ingredients with reasons
  - Implement case-insensitive ingredient name matching
  - _Requirements: 1.1, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4_

- [x] 2. Create ingredient import processing helper methods
  - Add `processIngredientImport` method to handle individual ingredient processing
  - Implement `detectIngredientChanges` method to track what fields changed during updates
  - Add logic to determine import action (imported/updated/skipped) with detailed reasons
  - _Requirements: 3.3, 4.2, 4.3, 4.4_

- [x] 3. Update ingredient import to use existing TSV processor with enhanced tracking
  - Modify the callback function in `ingredientTsvProcessorService->processTsv` to collect detailed results
  - Ensure all nutritional fields are properly tracked for changes
  - Maintain existing 15-column TSV format validation and processing
  - _Requirements: 2.1, 2.4, 4.1, 4.5_

- [x] 4. Enhance IngredientController importTsv method
  - Update the controller method to handle the new detailed result structure
  - Add comprehensive error handling with user-friendly messages
  - Implement proper validation for TSV data input
  - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [x] 5. Implement detailed success message generation
  - Create `buildImportSuccessMessage` method similar to exercise import
  - Generate HTML-formatted messages showing imported, updated, and skipped ingredients
  - Include change details for updated ingredients (field: 'old' → 'new' format)
  - Display counts and lists of affected ingredients with proper escaping
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 6. Add comprehensive unit tests for ingredient import service
  - Test importing new ingredients with detailed result tracking
  - Test updating existing ingredients with change detection
  - Test skipping ingredients with identical data
  - Test case-insensitive ingredient name matching
  - Test invalid row handling and reporting
  - _Requirements: 1.1, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

- [x] 7. Add feature tests for ingredient import controller
  - Test end-to-end import workflow through web interface
  - Test detailed success message display with HTML formatting
  - Test error handling and validation messages
  - Test mixed import scenarios (new, updated, skipped ingredients)
  - _Requirements: 5.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 8.1, 8.2, 8.3_

- [x] 8. Test production environment restrictions
  - Verify TSV import form is hidden in production/staging environments
  - Test that import requests are properly rejected in restricted environments
  - Ensure consistency with exercise import environment restrictions
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 9. Validate ingredient import behavior matches exercise import patterns
  - Compare ingredient import results with exercise import for consistency
  - Ensure error messages follow the same patterns and formatting
  - Verify success messages have the same structure and detail level
  - Test edge cases to ensure identical behavior patterns
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_