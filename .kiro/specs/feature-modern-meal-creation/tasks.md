# Implementation Plan: Modern Meal Creation System

## Overview

This implementation replaces the existing meal creation system with a modern, component-based interface that mirrors the simple workout creation pattern. The approach involves creating a new SimpleMealController and MealIngredientListService while removing the old MealController and associated views.

## Tasks

- [ ] 1. Remove existing meal system components
- [ ] 1.1 Delete old MealController and associated blade views
  - Remove `app/Http/Controllers/MealController.php`
  - Remove `resources/views/meals/create.blade.php`
  - Remove `resources/views/meals/edit.blade.php`
  - Remove any other meal-related blade templates
  - _Requirements: 5.4_

- [ ]* 1.2 Write unit tests for old system removal
  - Test that old routes no longer exist
  - Test that old controller methods are not accessible
  - _Requirements: 5.4_

- [ ] 2. Create MealIngredientListService
- [ ] 2.1 Implement MealIngredientListService class
  - Create service class with ingredient selection list generation
  - Implement quantity form generation
  - Implement ingredient list table generation for meals
  - _Requirements: 6.2, 6.3_

- [ ] 2.2 Write property test for ingredient list generation
  - **Property 3: Ingredient list display and filtering**
  - **Validates: Requirements 3.1, 3.2, 3.5**

- [ ] 2.3 Write unit tests for MealIngredientListService
  - Test component generation methods
  - Test ingredient filtering and sorting
  - _Requirements: 3.1, 3.2, 3.5_

- [ ] 3. Create SimpleMealController
- [ ] 3.1 Implement core CRUD methods
  - Create `index()` method using flexible components
  - Create `create()` method with ingredient selection
  - Create `edit()` method with meal builder interface
  - Create `destroy()` method with proper authorization
  - _Requirements: 1.1, 4.1, 4.2_

- [ ] 3.2 Write unit tests for CRUD methods
  - Test index page component generation
  - Test create page ingredient selection
  - Test edit page meal builder
  - Test destroy method authorization
  - _Requirements: 1.1, 4.1, 4.2_

- [ ] 4. Implement ingredient management methods
- [ ] 4.1 Create addIngredient and storeIngredient methods
  - Implement quantity form display
  - Implement ingredient addition with meal creation
  - Handle duplicate ingredient prevention
  - _Requirements: 1.2, 1.3, 1.5, 2.1, 2.2, 2.3_

- [ ] 4.2 Write property test for duplicate prevention
  - **Property 2: Duplicate ingredient prevention**
  - **Validates: Requirements 1.5**

- [ ] 4.3 Write property test for data consistency
  - **Property 1: Data consistency across ingredient operations**
  - **Validates: Requirements 1.4, 2.5, 4.3, 4.5**

- [ ] 4.4 Create updateQuantity and removeIngredient methods
  - Implement quantity editing with pre-filled forms
  - Implement ingredient removal with meal deletion logic
  - Handle last ingredient removal (delete entire meal)
  - _Requirements: 2.4, 2.5, 2.6, 4.4_

- [ ] 4.5 Write unit tests for ingredient management
  - Test quantity form generation and submission
  - Test ingredient removal and meal deletion
  - Test last ingredient removal behavior
  - _Requirements: 2.4, 2.5, 2.6, 4.4_

- [ ] 5. Update routing configuration
- [ ] 5.1 Replace meal routes in web.php
  - Remove existing MealController resource routes
  - Add new SimpleMealController routes
  - Ensure route names match existing expectations
  - _Requirements: 5.4_

- [ ] 5.2 Write integration tests for routing
  - Test all new routes are accessible
  - Test route parameter binding works correctly
  - Test authorization middleware is applied
  - _Requirements: 5.4_

- [ ] 6. Checkpoint - Ensure all tests pass
- Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Implement system compatibility features
- [ ] 7.1 Ensure database compatibility
  - Verify meals created through new system use existing tables
  - Test compatibility with existing addMealToLog functionality
  - Maintain all existing meal fields and relationships
  - _Requirements: 5.1, 5.2, 5.5_

- [ ]* 7.2 Write property test for system compatibility
  - **Property 5: System compatibility preservation**
  - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5**

- [ ] 7.3 Write property test for edit interface consistency
  - **Property 4: Edit interface consistency**
  - **Validates: Requirements 4.1, 4.2**

- [ ] 8. Implement nutritional information display
- [ ] 8.1 Add nutritional information to meal views
  - Display nutritional totals in meal index
  - Show nutritional information in meal edit interface
  - Use existing NutritionService for calculations
  - _Requirements: 7.4_

- [ ]* 8.2 Write property test for nutritional information
  - **Property 6: Nutritional information display**
  - **Validates: Requirements 7.4**

- [ ]* 8.3 Write unit tests for nutritional display
  - Test nutritional calculation integration
  - Test display formatting and accuracy
  - _Requirements: 7.4_

- [ ] 9. Final integration and testing
- [ ] 9.1 Test complete user workflows
  - Test meal creation from start to finish
  - Test meal editing and ingredient management
  - Test meal deletion and cleanup
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 4.1, 4.2_

- [ ]* 9.2 Write integration tests for complete workflows
  - Test end-to-end meal creation process
  - Test meal editing with multiple ingredients
  - Test compatibility with existing meal logging
  - _Requirements: 5.2_

- [ ] 10. Final checkpoint - Ensure all tests pass
- Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- The implementation completely replaces the existing meal system rather than extending it