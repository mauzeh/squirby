# Requirements Document

## Introduction

This specification defines the complete refactoring of the Programs system to work like Mobile Food Forms. The current Programs table stores workout planning parameters (sets, reps, priority, comments) which are unnecessary since these values should be calculated dynamically by the TrainingProgressionService. This refactoring will replace Programs entirely with a lightweight Mobile Lift Forms table for UI state (similar to mobile_food_forms).

The goal is to align the lift logging experience with the food logging experience, where forms are temporary UI conveniences that store only selection state (which exercises to show), while all workout parameters are calculated on-the-fly based on training history and progression algorithms.

## Glossary

- **Program**: The existing workout planning entity that stores exercise_id, date, sets, reps, priority, and comments for a planned workout
- **Mobile Lift Form**: A new lightweight entity that stores only user_id, date, and exercise_id to indicate which exercises should appear as quick-entry forms in the mobile lift logging interface
- **Mobile Entry Interface**: The mobile-optimized UI for logging lifts, food, and measurements
- **Lift Log**: The actual workout data logged by users (what was performed)
- **LiftLogService**: The service class that generates mobile entry forms and manages lift logging business logic
- **Auto-cleanup**: Automatic deletion of old mobile lift forms after a specified number of days
- **Form State**: The temporary selection of which exercises appear as forms in the mobile interface

## Requirements

### Requirement 1: Create Mobile Lift Forms Table

**User Story:** As a developer, I want a new mobile_lift_forms table that mirrors the structure of mobile_food_forms, so that lift form state is managed consistently with food form state.

#### Acceptance Criteria

1. THE System SHALL create a new database table named "mobile_lift_forms"
2. THE mobile_lift_forms table SHALL contain columns: id, user_id, date, exercise_id, created_at, updated_at
3. THE mobile_lift_forms table SHALL have a foreign key constraint on user_id that cascades on delete
4. THE mobile_lift_forms table SHALL have a foreign key constraint on exercise_id that cascades on delete
5. THE mobile_lift_forms table SHALL have a unique constraint on the combination of user_id, date, and exercise_id
6. THE mobile_lift_forms table SHALL have an index on user_id and date for efficient queries

### Requirement 2: Create MobileLiftForm Model

**User Story:** As a developer, I want a MobileLiftForm Eloquent model that provides clean access to mobile lift form data, so that I can query and manipulate form state using Laravel conventions.

#### Acceptance Criteria

1. THE System SHALL create a model class named "MobileLiftForm" in the App\Models namespace
2. THE MobileLiftForm model SHALL define fillable fields: user_id, date, exercise_id
3. THE MobileLiftForm model SHALL cast the date field as a date type
4. THE MobileLiftForm model SHALL define a belongsTo relationship to User
5. THE MobileLiftForm model SHALL define a belongsTo relationship to Exercise
6. THE MobileLiftForm model SHALL provide a scope method "forUserAndDate" that filters by user_id and date

### Requirement 3: Migrate LiftLogService to Use Mobile Lift Forms

**User Story:** As a developer, I want LiftLogService to generate forms from mobile_lift_forms instead of programs, so that the mobile lift interface works consistently with the mobile food interface.

#### Acceptance Criteria

1. WHEN generating forms for the mobile interface, THE LiftLogService SHALL query mobile_lift_forms instead of programs
2. THE LiftLogService SHALL calculate sets and reps dynamically using TrainingProgressionService instead of reading from programs
3. THE LiftLogService SHALL calculate weight suggestions dynamically using TrainingProgressionService instead of reading from programs
4. THE LiftLogService SHALL maintain all existing form generation logic for exercise types, messages, and field definitions
5. THE LiftLogService SHALL remove any references to program priority, comments, or completion status from form generation

### Requirement 4: Add Mobile Lift Form Management Methods

**User Story:** As a user, I want to add and remove exercises from my mobile lift forms, so that I can customize which exercises appear in my mobile interface.

#### Acceptance Criteria

1. THE LiftLogService SHALL provide a method "addExerciseForm" that accepts user_id, exercise_id, and date
2. WHEN adding an exercise form, THE System SHALL create a mobile_lift_forms record if it does not already exist
3. WHEN adding an exercise form that already exists, THE System SHALL return an error message
4. THE LiftLogService SHALL provide a method "removeForm" that accepts user_id and form_id
5. WHEN removing a form, THE System SHALL delete the corresponding mobile_lift_forms record
6. THE LiftLogService SHALL provide a method "createExercise" that creates a new exercise and adds it to mobile lift forms

### Requirement 5: Update MobileEntryController Routes

**User Story:** As a user, I want to interact with mobile lift forms through the mobile entry interface, so that I can add and remove exercises from my workout forms.

#### Acceptance Criteria

1. THE MobileEntryController SHALL handle POST requests to add exercise forms via addLiftForm method
2. THE MobileEntryController SHALL handle DELETE requests to remove forms via removeForm method
3. THE MobileEntryController SHALL handle POST requests to create new exercises via createExercise method
4. WHEN a form operation succeeds, THE System SHALL redirect to mobile-entry.lifts with a success message
5. WHEN a form operation fails, THE System SHALL redirect to mobile-entry.lifts with an error message

### Requirement 6: Update Item Selection List Generation

**User Story:** As a user, I want to see available exercises in the mobile interface, so that I can select which ones to add to my forms.

#### Acceptance Criteria

1. THE existing LiftLogService "generateItemSelectionList" method SHALL be updated to remove program-related logic
2. THE item selection list SHALL include recently used exercises with type "recent"
3. THE item selection list SHALL include user's custom exercises with type "custom"
4. THE item selection list SHALL include available global exercises with type "regular"
5. THE item selection list SHALL sort exercises by type priority, then alphabetically by name
6. THE item selection list SHALL continue to use the existing create form for adding new exercises
7. THE item selection list SHALL maintain all existing UI functionality and styling

### Requirement 7: Remove Programs Table and Related Code

**User Story:** As a developer, I want to completely remove the Programs system, so that the codebase is simplified and only uses mobile lift forms for exercise selection.

#### Acceptance Criteria

1. THE System SHALL drop the programs table from the database
2. THE System SHALL delete the Program model class
3. THE System SHALL delete the ProgramController class
4. THE System SHALL delete all program-related routes
5. THE System SHALL delete all program-related views
6. THE System SHALL delete StoreProgramRequest and UpdateProgramRequest validation classes
7. THE System SHALL delete all program-related test files
8. THE System SHALL remove any program-related references from other services and controllers

### Requirement 8: Update References to Programs

**User Story:** As a developer, I want all code that referenced programs to be updated or removed, so that the application functions correctly without the Programs system.

#### Acceptance Criteria

1. THE RecommendationEngine SHALL be updated to work without programs or removed if no longer needed
2. THE LiftLogService SHALL remove all program-related logic including completion status checks
3. THE MobileEntryController SHALL remove all program-related parameters and logic
4. THE navigation and views SHALL remove links to program routes
5. THE System SHALL remove any program-related configuration or constants

### Requirement 9: Update Routes and Route Names

**User Story:** As a developer, I want clear route names for mobile lift form operations, so that the codebase is maintainable and consistent.

#### Acceptance Criteria

1. THE System SHALL define a route "mobile-entry.add-lift-form" for adding exercise forms
2. THE System SHALL define a route "mobile-entry.remove-lift-form" for removing forms
3. THE System SHALL define a route "mobile-entry.create-exercise" for creating new exercises
4. THE routes SHALL accept date as a query parameter for maintaining context
5. THE routes SHALL redirect back to mobile-entry.lifts after operations

### Requirement 10: Error Handling and Validation

**User Story:** As a user, I want clear error messages when form operations fail, so that I understand what went wrong and how to fix it.

#### Acceptance Criteria

1. THE System SHALL maintain existing error handling patterns from LiftLogService and MobileEntryController
2. THE System SHALL continue to use existing error messages for form operations
3. THE System SHALL not introduce new error handling logic unless required by the refactoring
4. THE error handling SHALL remain consistent with the existing mobile entry interface

### Requirement 11: Testing Strategy

**User Story:** As a developer, I want comprehensive tests for mobile lift forms, so that I can confidently refactor without breaking existing functionality.

#### Acceptance Criteria

1. THE System SHALL provide unit tests for MobileLiftForm model methods
2. THE System SHALL provide unit tests for LiftLogService form generation methods
3. THE System SHALL provide integration tests for MobileEntryController form operations
4. THE tests SHALL verify that all program references have been removed
5. THE tests SHALL verify that mobile lift forms work identically to mobile food forms
