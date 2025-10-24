# Design Document

## Overview

This design outlines the simplification of the recommendation system by removing all API complexity and implementing a clean, server-side only architecture with an improved button-based user interface. The solution eliminates unnecessary AJAX calls, duplicate routes, and complex JavaScript in favor of a straightforward form-based approach that maintains all functionality while improving maintainability.

## Architecture

### Current State
- Multiple API routes (`/api/recommendations`, `/api/recommendations/filters`, `/recommendations/api`)
- Mixed form submissions and AJAX calls
- Complex JavaScript for refresh functionality
- Dropdown-based filter interface with separate submit buttons

### Target State
- Single web route (`/recommendations`)
- Pure server-side filtering through form submissions
- Button-based filter interface with automatic submission
- No JavaScript dependencies for core functionality
- Simplified controller with single index method

## Components and Interfaces

### Route Structure
```
GET /recommendations - Main recommendation page with filtering
```

### Controller Design
The `RecommendationController` will be simplified to contain only:
- `index()` method for handling both initial page load and filtered requests
- Removal of `api()` and `getFilters()` methods
- Direct integration with `RecommendationEngine` service

### Filter Interface Components
1. **Movement Archetype Buttons**: Horizontal button group for push/pull/squat/hinge/carry/core
2. **Difficulty Level Buttons**: Horizontal button group for levels 1-5
3. **Clear Filters Button**: Single button to reset all filters
4. **Hidden Form Fields**: To maintain filter state and enable button-based submission## Dat
a Models

### Request Flow
1. User clicks filter button
2. JavaScript updates hidden form fields
3. Form submits automatically via POST/GET
4. Controller processes filters server-side
5. Page reloads with filtered results and updated button states

### Filter State Management
- URL parameters maintain filter state for bookmarking
- Button active states determined by current URL parameters
- Form hidden fields synchronized with button selections

## Error Handling

### Server-Side Error Handling
- Graceful handling of invalid filter parameters
- Fallback to default recommendations on service errors
- User-friendly error messages displayed in the view
- Proper HTTP status codes for different error scenarios

### Client-Side Error Handling
- Minimal JavaScript only for button interaction feedback
- No complex error handling for AJAX failures (since no AJAX)
- Form validation through HTML5 and server-side validation

## Testing Strategy

### Unit Tests
- Controller index method with various filter combinations
- Filter parameter validation and sanitization
- Error handling scenarios
- RecommendationEngine integration

### Integration Tests
- Complete filter workflow from button click to result display
- URL parameter handling and state persistence
- Error scenarios and fallback behavior

### Feature Tests
- Button interaction and form submission
- Filter state preservation across page loads
- Clear filters functionality
- Responsive design on different screen sizes## Impl
ementation Details

### Route Cleanup
- Remove `/api/recommendations` route from `routes/api.php`
- Remove `/api/recommendations/filters` route from `routes/api.php`  
- Remove `/recommendations/api` route from `routes/web.php`
- Keep only `GET /recommendations` route in `routes/web.php`

### Controller Simplification
- Remove `api()` method from `RecommendationController`
- Remove `getFilters()` method from `RecommendationController`
- Remove `filterRecommendations()` private method (integrate into index)
- Simplify `index()` method to handle all filtering logic directly

### Frontend Changes
- Replace dropdown selects with button groups
- Remove all JavaScript fetch calls and AJAX functionality
- Implement button-based form submission with hidden fields
- Add visual feedback for button states (active/inactive)
- Remove "Apply Filters" and "Refresh" buttons
- Keep "Clear Filters" button with simple form reset functionality

### CSS/Styling Updates
- Design button group layouts for filters
- Add active/inactive button states
- Ensure responsive design for mobile devices
- Maintain consistent styling with existing app theme

### JavaScript Minimization
- Remove all AJAX-related JavaScript code
- Keep minimal JavaScript only for:
  - Button click handling to update hidden form fields
  - Form auto-submission on button clicks
  - Visual feedback during form submission
- No external dependencies or complex state management