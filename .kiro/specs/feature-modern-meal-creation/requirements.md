# Requirements Document

## Introduction

This feature modernizes the meal creation system to use the flexible component system, similar to how simple workouts are created. The current meal creation uses traditional Laravel views and requires users to select all ingredients and quantities upfront in a single form. The new system will allow incremental ingredient addition with live macro calculations, providing a more intuitive and mobile-friendly experience.

## Glossary

- **Simple_Meal_System**: The modernized meal creation system using flexible components
- **Ingredient_Selection_List**: Component displaying available ingredients for selection
- **Quantity_Form**: Separate page for specifying ingredient quantities using flexible components
- **Meal_Builder**: The incremental meal construction interface
- **Component_System**: The flexible UI component framework used throughout the application

## Requirements

### Requirement 1: Modern Meal Creation Interface

**User Story:** As a user, I want to create meals using a modern interface similar to workout creation, so that I can build meals incrementally with immediate feedback.

#### Acceptance Criteria

1. WHEN a user visits the meal creation page, THE Simple_Meal_System SHALL display an ingredient selection interface using the flexible component system
2. WHEN a user selects an ingredient, THE Simple_Meal_System SHALL navigate to a quantity specification form before adding to the meal
3. WHEN the first ingredient is added, THE Simple_Meal_System SHALL create the meal in the database and redirect to the edit interface
4. WHEN ingredients are added or modified, THE Simple_Meal_System SHALL maintain data consistency without requiring live calculations
5. WHEN a user adds an ingredient already in the meal, THE Simple_Meal_System SHALL prevent duplicate additions and show a warning message

### Requirement 2: Incremental Ingredient Management

**User Story:** As a user, I want to add ingredients one by one with quantities, so that I can build meals naturally like creating a shopping list.

#### Acceptance Criteria

1. WHEN a user clicks an ingredient from the selection list, THE Simple_Meal_System SHALL navigate to a quantity form page
2. WHEN the quantity form is displayed, THE Quantity_Form SHALL show the ingredient name and provide quantity input using flexible components
3. WHEN a valid quantity is entered and submitted, THE Simple_Meal_System SHALL add the ingredient to the meal and redirect back to the meal builder
3. WHEN a user wants to modify an existing ingredient quantity, THE Simple_Meal_System SHALL navigate to the same quantity form with pre-filled values
4. WHEN a user removes an ingredient, THE Simple_Meal_System SHALL update the meal immediately
5. WHEN the last ingredient is removed from a meal, THE Simple_Meal_System SHALL delete the entire meal (same behavior as simple workouts)

### Requirement 3: Ingredient Selection and Search

**User Story:** As a user, I want to easily find and select ingredients from my available ingredients, so that I can quickly build meals without scrolling through long lists.

#### Acceptance Criteria

1. WHEN the ingredient selection list is displayed, THE Ingredient_Selection_List SHALL show all user ingredients in alphabetical order
2. WHEN a user types in the search field, THE Ingredient_Selection_List SHALL filter ingredients by name in real-time
3. WHEN no ingredients match the search, THE Ingredient_Selection_List SHALL display a "no results" message
4. WHEN a user searches for a non-existent ingredient, THE Ingredient_Selection_List SHALL provide an option to create a new ingredient
5. WHEN ingredients are displayed, THE Ingredient_Selection_List SHALL show only ingredients belonging to the current user

### Requirement 4: Meal Editing and Management

**User Story:** As a user, I want to edit existing meals using the same modern interface, so that I can modify recipes and adjust quantities easily.

#### Acceptance Criteria

1. WHEN a user edits an existing meal, THE Meal_Builder SHALL display current ingredients with their quantities
2. WHEN editing a meal, THE Simple_Meal_System SHALL provide the same ingredient addition capabilities as creation
3. WHEN a user modifies ingredient quantities, THE Simple_Meal_System SHALL update the meal_ingredients pivot table immediately
4. WHEN a user removes the last ingredient, THE Simple_Meal_System SHALL delete the meal and redirect to the meals index (same behavior as simple workouts)
5. WHEN meal changes are made, THE Simple_Meal_System SHALL maintain the same validation rules as the existing system

### Requirement 5: Integration with Existing Meal System

**User Story:** As a user, I want the new meal creation system to work seamlessly with existing meal logging functionality, so that my workflow remains consistent.

#### Acceptance Criteria

1. WHEN meals are created through the new system, THE Simple_Meal_System SHALL store data in the same meals and meal_ingredients tables
2. WHEN meals are created or edited, THE Simple_Meal_System SHALL maintain compatibility with the existing addMealToLog functionality
3. WHEN meals are displayed in the meal index, THE Simple_Meal_System SHALL show the same nutritional information and actions
4. WHEN users access meal editing from the index, THE Simple_Meal_System SHALL redirect to the new modern interface
5. WHEN meal data is saved, THE Simple_Meal_System SHALL preserve all existing fields including name, comments, and user_id

### Requirement 6: Component System Integration

**User Story:** As a system architect, I want the meal creation system to use the same component architecture as workouts, so that the interface is consistent and maintainable.

#### Acceptance Criteria

1. WHEN building the meal interface, THE Component_System SHALL use ComponentBuilder (C::) for all UI elements
2. WHEN displaying ingredient lists, THE Simple_Meal_System SHALL use the same item list component pattern as workout exercise selection
3. WHEN showing meal ingredients, THE Simple_Meal_System SHALL use table components similar to workout exercise tables
4. WHEN handling user interactions, THE Simple_Meal_System SHALL follow the same routing patterns as SimpleWorkoutController
5. WHEN rendering views, THE Simple_Meal_System SHALL use the mobile-entry.flexible view template

### Requirement 7: Mobile-First Design

**User Story:** As a mobile user, I want the meal creation interface to work smoothly on my phone, so that I can build meals while shopping or cooking.

#### Acceptance Criteria

1. WHEN using the meal creation interface on mobile, THE Component_System SHALL provide touch-friendly ingredient selection
2. WHEN entering quantities on mobile, THE Quantity_Form SHALL use appropriate input types and mobile-friendly form components
3. WHEN viewing ingredient lists on mobile, THE Ingredient_Selection_List SHALL support smooth scrolling and filtering
4. WHEN displaying macro totals on mobile, THE Simple_Meal_System SHALL show nutritional information when viewing the completed meal
5. WHEN navigating the interface on mobile, THE Simple_Meal_System SHALL provide the same back button and navigation patterns as simple workouts