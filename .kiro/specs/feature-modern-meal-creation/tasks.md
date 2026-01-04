# Implementation Plan: Modern Meal Creation System

## Overview

This implementation replaces the existing meal creation system with a modern, component-based interface that mirrors the simple workout creation pattern. The approach involves creating a new SimpleMealController and MealIngredientListService while removing the old MealController and associated views.

## Tasks

- [x] 1. Create MealIngredientListService
- [x] 1.1 Implement MealIngredientListService class
  - Create service class following WorkoutExerciseListService pattern
  - Implement `generateIngredientSelectionList()` method for existing meals
  - Implement `generateIngredientSelectionListForNew()` method for new meal creation
  - Implement `generateQuantityForm()` method for ingredient quantity input
  - Implement `generateIngredientListTable()` method for meal editing interface
  - _Requirements: 3.1, 3.2, 3.5, 6.2, 6.3_

- [x] 1.2 Write unit tests for MealIngredientListService
  - Test component generation methods
  - Test ingredient filtering and sorting
  - Test quantity form generation
  - _Requirements: 3.1, 3.2, 3.5_

- [ ] 2. Create SimpleMealController
- [ ] 2.1 Implement core CRUD methods
  - Create `index()` method using flexible components (keep existing implementation from MealController)
  - Create `create()` method with ingredient selection using MealIngredientListService
  - Create `edit()` method with meal builder interface
  - Create `destroy()` method with proper authorization
  - _Requirements: 1.1, 4.1, 4.2_

- [ ] 2.2 Implement ingredient management methods
  - Create `addIngredient()` method to show quantity form
  - Create `storeIngredient()` method to add ingredient with quantity (handles meal creation for first ingredient)
  - Create `updateQuantity()` method for editing existing ingredient quantities
  - Create `removeIngredient()` method with meal deletion logic for last ingredient
  - Handle duplicate ingredient prevention
  - _Requirements: 1.2, 1.3, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 4.4_

- [ ] 2.3 Write unit tests for SimpleMealController
  - Test CRUD methods
  - Test ingredient management methods
  - Test authorization and validation
  - Test meal deletion when last ingredient removed
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 4.1, 4.2, 4.4_

- [ ] 3. Update routing configuration
- [ ] 3.1 Replace meal routes in web.php
  - Remove existing MealController resource routes
  - Add new SimpleMealController routes following SimpleWorkoutController pattern
  - Ensure route names maintain compatibility where possible
  - _Requirements: 5.4_

- [ ] 3.2 Write integration tests for routing
  - Test all new routes are accessible
  - Test route parameter binding works correctly
  - Test authorization middleware is applied
  - _Requirements: 5.4_

- [ ] 4. Implement nutritional information display
- [ ] 4.1 Add nutritional information to meal views
  - Display nutritional totals in meal index (already exists in current MealController)
  - Show nutritional information in meal edit interface
  - Use existing NutritionService for calculations
  - _Requirements: 7.4_

- [ ] 4.2 Write unit tests for nutritional display
  - Test nutritional calculation integration
  - Test display formatting and accuracy
  - _Requirements: 7.4_

- [ ] 5. Remove old meal system components
- [ ] 5.1 Delete old MealController and associated blade views
  - Remove `app/Http/Controllers/MealController.php`
  - Remove `resources/views/meals/create.blade.php`
  - Remove `resources/views/meals/edit.blade.php`
  - Remove any other meal-related blade templates
  - _Requirements: 5.4_

- [ ] 5.2 Remove tests for old meal system
  - Remove any existing tests that test the old MealController
  - Remove any tests for the old blade views
  - Clean up test files that are no longer needed
  - _Requirements: 5.4_

- [ ] 6. Checkpoint - Ensure all tests pass
- Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Final integration and testing
- [ ] 7.1 Test complete user workflows
  - Test meal creation from start to finish
  - Test meal editing and ingredient management
  - Test meal deletion and cleanup
  - Verify compatibility with existing addMealToLog functionality
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 4.1, 4.2, 5.1, 5.2, 5.5_

- [ ] 7.2 Write integration tests for complete workflows
  - Test end-to-end meal creation process
  - Test meal editing with multiple ingredients
  - Test compatibility with existing meal logging
  - _Requirements: 5.2_

- [ ] 8. Final checkpoint - Ensure all tests pass
- Ensure all tests pass, ask the user if questions arise.

## Notes

- All tasks are now required for comprehensive implementation from the start
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Unit tests validate specific examples and edge cases
- The implementation completely replaces the existing meal system rather than extending it
- Current MealController index() method already uses flexible components and can be preserved
- Follow WorkoutExerciseListService and SimpleWorkoutController patterns for consistency