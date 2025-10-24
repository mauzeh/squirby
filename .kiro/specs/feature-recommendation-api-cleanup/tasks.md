# Implementation Plan

- [x] 1. Remove API routes and clean up routing configuration
  - Remove `/api/recommendations` route from `routes/api.php`
  - Remove `/api/recommendations/filters` route from `routes/api.php`
  - Remove `/recommendations/api` route from `routes/web.php`
  - Keep only the main `GET /recommendations` route in `routes/web.php`
  - _Requirements: 2.2, 2.3, 2.4_

- [x] 2. Simplify RecommendationController to server-side only
  - Remove `api()` method from RecommendationController
  - Remove `getFilters()` method from RecommendationController
  - Remove private `filterRecommendations()` method and integrate logic into index method
  - Update `index()` method to handle all filtering logic directly without JSON responses
  - Remove all JSON response formatting and API-related code
  - _Requirements: 2.1, 2.3, 4.2, 4.4_

- [x] 3. Implement button-based filter interface
  - Replace movement archetype dropdown with horizontal button group
  - Replace difficulty level dropdown with horizontal button group (1-5)
  - Remove "Number of Recommendations" dropdown and use fixed default count
  - Add hidden form fields to maintain filter state for button-based submission
  - _Requirements: 1.4, 5.1, 5.2_

- [x] 4. Remove form submission buttons and implement auto-submission
  - Remove "Apply Filters" button from the interface
  - Remove "Refresh" button and all related AJAX functionality
  - Keep "Clear Filters" button with simple form reset functionality
  - Implement JavaScript for automatic form submission when filter buttons are clicked
  - _Requirements: 5.3, 5.4, 5.5_
  
- [x] 5. Update frontend styling and user experience
  - Design and implement button group layouts for movement archetype filters
  - Design and implement button group layouts for difficulty level filters
  - Add active/inactive visual states for filter buttons
  - Ensure responsive design works on mobile devices
  - Add visual feedback for button presses before form submission
  - _Requirements: 1.2, 3.3, 3.4_

- [x] 6. Remove AJAX functionality and JavaScript complexity
  - Remove all fetch calls and AJAX-related JavaScript from recommendations view
  - Remove refresh button functionality and related event handlers
  - Implement minimal JavaScript only for button interactions and form submission
  - Remove auto-submit functionality for dropdown changes (no longer needed)
  - _Requirements: 2.4, 4.4_

- [x] 7. Update URL parameter handling and state management
  - Ensure filter state is properly maintained in URL parameters for bookmarking
  - Update button highlighting logic to read from URL parameters on page load
  - Implement proper form field synchronization with button selections
  - Test filter state preservation across page reloads
  - _Requirements: 1.5, 3.4_

- [x] 8. Update tests to reflect simplified architecture
  - Remove all API endpoint tests from RecommendationControllerTest
  - Update existing web controller tests to cover new button-based filtering
  - Add tests for filter parameter validation and error handling
  - Test button state management and URL parameter handling
  - Ensure all existing functionality still works through web interface
  - _Requirements: 6.1, 6.2, 6.3_