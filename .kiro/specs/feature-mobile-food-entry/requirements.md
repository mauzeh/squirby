# Requirements Document

## Introduction

This feature will create a mobile-optimized food logging interface similar to the existing lift logs mobile entry system. The interface will provide a streamlined, touch-friendly experience for quickly logging food consumption on mobile devices using real-time logging (no time field required) and a unified autocomplete interface for both meals and ingredients.

## Glossary

- **Mobile_Food_Entry_System**: The mobile-optimized interface for logging food consumption
- **Food_Log**: A record of consumed food with ingredient, quantity, time, and notes
- **Ingredient**: A user-specific food item with nutritional information that can be logged
- **Meal**: A predefined collection of ingredients that can be added to the food log as a group
- **Daily_Nutrition_Totals**: Calculated nutritional values for all food consumed on a given date
- **Unified_Search_Interface**: Single autocomplete field that searches both meals and ingredients
- **Increment_Controls**: Touch-friendly plus/minus buttons for adjusting quantities with unit-specific increments

## Requirements

### Requirement 1

**User Story:** As a mobile user, I want to access a dedicated mobile food entry interface, so that I can quickly log my food consumption without navigating complex desktop layouts.

#### Acceptance Criteria

1. THE Mobile_Food_Entry_System SHALL provide a dedicated route accessible via `/food-logs/mobile-entry`
2. THE Mobile_Food_Entry_System SHALL use the same styling as the existing lift logs mobile entry interface
3. THE Mobile_Food_Entry_System SHALL display a mobile-optimized layout with large touch targets
4. THE Mobile_Food_Entry_System SHALL use a dark theme consistent with the existing mobile lift entry interface
5. THE Mobile_Food_Entry_System SHALL be responsive and work effectively on screen sizes from 320px to 768px wide

### Requirement 2

**User Story:** As a user, I want to navigate between different dates, so that I can log food for any day and review my nutrition history.

#### Acceptance Criteria

1. THE Mobile_Food_Entry_System SHALL display date navigation controls with Previous, Today, and Next buttons
2. WHEN a user clicks Previous or Next, THE Mobile_Food_Entry_System SHALL navigate to the adjacent date
3. WHEN a user clicks Today, THE Mobile_Food_Entry_System SHALL navigate to the current date
4. THE Mobile_Food_Entry_System SHALL display contextual date labels (Today, Yesterday, Tomorrow, or specific date)
5. THE Mobile_Food_Entry_System SHALL accept date parameters via URL query string in YYYY-MM-DD format

### Requirement 3

**User Story:** As a user, I want to log food in real-time without specifying time, so that I can quickly capture consumption as it happens.

#### Acceptance Criteria

1. THE Mobile_Food_Entry_System SHALL assume real-time logging with no time field required
2. THE Mobile_Food_Entry_System SHALL automatically set the logged_at time to the current time when creating Food_Log entries
3. THE Mobile_Food_Entry_System SHALL round the logged time to the nearest 15-minute interval
4. THE Mobile_Food_Entry_System SHALL display the logging form at the top of the interface
5. THE Mobile_Food_Entry_System SHALL show previously logged food entries for the day below the form

### Requirement 4

**User Story:** As a user, I want a unified search interface for both meals and ingredients, so that I can quickly find and select what I want to log without switching between different input modes.

#### Acceptance Criteria

1. THE Mobile_Food_Entry_System SHALL provide a Unified_Search_Interface with a large autocomplete input field
2. THE Mobile_Food_Entry_System SHALL search both meals and ingredients in a single field
3. THE Mobile_Food_Entry_System SHALL display top meals and ingredients before the user begins typing
4. THE Mobile_Food_Entry_System SHALL show autocomplete suggestions as the user types
5. THE Mobile_Food_Entry_System SHALL only display user-specific ingredients (no global ingredients)

### Requirement 5

**User Story:** As a user, I want different input controls based on whether I select an ingredient or meal, so that I can provide the appropriate quantity information for each type.

#### Acceptance Criteria

1. WHEN an ingredient is selected, THE Mobile_Food_Entry_System SHALL show quantity and notes fields
2. WHEN a meal is selected, THE Mobile_Food_Entry_System SHALL show portion and notes fields
3. THE Mobile_Food_Entry_System SHALL use the same increment/decrement button styling as lift logs mobile entry
4. THE Mobile_Food_Entry_System SHALL provide Increment_Controls with plus and minus buttons
5. THE Mobile_Food_Entry_System SHALL make no changes to existing data models

### Requirement 6

**User Story:** As a user, I want unit-specific increment amounts for quantity controls, so that I can adjust portions appropriately for different types of measurements.

#### Acceptance Criteria

1. WHEN the ingredient unit is grams or milliliters, THE Mobile_Food_Entry_System SHALL increment/decrement by 10 units
2. WHEN the ingredient unit is kilograms, pounds or liters, THE Mobile_Food_Entry_System SHALL increment/decrement by 0.1 units
3. WHEN the ingredient unit is pieces or servings, THE Mobile_Food_Entry_System SHALL increment/decrement by 0.25 units
4. THE Mobile_Food_Entry_System SHALL prevent quantities from going below zero
5. THE Mobile_Food_Entry_System SHALL display the current quantity prominently between the increment controls, just like the weight field when logging lifts on mobile

### Requirement 7

**User Story:** As a user, I want to see what I've already logged for the day below the entry form, so that I can review my consumption and avoid duplicate entries.

#### Acceptance Criteria

1. THE Mobile_Food_Entry_System SHALL display all Food_Log entries for the selected date below the logging form
2. THE Mobile_Food_Entry_System SHALL show entry details including ingredient name, quantity, unit, and time
3. THE Mobile_Food_Entry_System SHALL provide delete option for each logged entry
4. THE Mobile_Food_Entry_System SHALL display calculated calories and macros for each entry
5. THE Mobile_Food_Entry_System SHALL show entries in chronological order (oldest at the bottom, newest at the top)

### Requirement 8

**User Story:** As a user, I want the mobile interface to exclude ingredient and meal creation options, so that I can focus on logging without interface clutter.

#### Acceptance Criteria

1. THE Mobile_Food_Entry_System SHALL NOT include options to create new ingredients
2. THE Mobile_Food_Entry_System SHALL NOT include options to create new meals
3. THE Mobile_Food_Entry_System SHALL only allow selection from existing user ingredients and meals
4. THE Mobile_Food_Entry_System SHALL maintain a streamlined interface focused on logging only