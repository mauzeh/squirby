# Requirements Document

## Introduction

This specification outlines the refactoring of the current tightly coupled exercise type system into a flexible, maintainable architecture using the Strategy Pattern and Factory Pattern. The current system has exercise type logic scattered across controllers, models, services, and views, making it difficult to maintain and extend. This refactoring will centralize exercise type behavior while maintaining backward compatibility.

## Glossary

- **Exercise_Type_Strategy**: A class that encapsulates all behavior specific to one type of exercise (regular, banded, bodyweight)
- **Exercise_Type_Factory**: A factory class responsible for creating the appropriate exercise type strategy based on exercise properties
- **Lift_Log_Controller**: The controller responsible for handling lift log creation and updates
- **Exercise_Controller**: The controller responsible for handling exercise creation and updates
- **One_Rep_Max_Calculator**: Service responsible for calculating one rep max values
- **Lift_Log_Presenter**: Presenter responsible for formatting lift log data for display
- **Exercise_Form_Component**: View component for rendering exercise creation/edit forms
- **Lift_Log_Form_Component**: View component for rendering lift log creation/edit forms

## Requirements

### Requirement 1

**User Story:** As a developer, I want exercise type behavior to be encapsulated in strategy classes, so that I can easily maintain and extend exercise functionality without modifying existing code.

#### Acceptance Criteria

1. WHEN creating an exercise type strategy, THE Exercise_Type_Strategy SHALL implement a common interface with methods for validation, data processing, display formatting, and capability checking
2. WHEN determining exercise capabilities, THE Exercise_Type_Strategy SHALL provide a method that returns whether 1RM calculation is supported
3. WHEN processing lift data, THE Exercise_Type_Strategy SHALL transform input data according to exercise type rules
4. WHEN formatting display data, THE Exercise_Type_Strategy SHALL provide consistent formatting methods for weight and progress display
5. WHERE different exercise types exist, THE Exercise_Type_Strategy SHALL handle validation rules specific to each type

### Requirement 2

**User Story:** As a developer, I want a factory to create appropriate exercise type strategies, so that I can eliminate conditional logic throughout the application.

#### Acceptance Criteria

1. WHEN given an exercise object, THE Exercise_Type_Factory SHALL return the appropriate strategy instance based on exercise properties
2. WHEN an exercise has band_type property set, THE Exercise_Type_Factory SHALL return a BandedExerciseType strategy
3. WHEN an exercise has is_bodyweight set to true and no band_type, THE Exercise_Type_Factory SHALL return a BodyweightExerciseType strategy
4. WHEN an exercise has neither band_type nor is_bodyweight set, THE Exercise_Type_Factory SHALL return a RegularExerciseType strategy
5. WHERE new exercise types are added, THE Exercise_Type_Factory SHALL support extension without modification of existing code

### Requirement 3

**User Story:** As a developer, I want controllers to use exercise type strategies for validation and data processing, so that controller logic remains clean and focused on HTTP concerns.

#### Acceptance Criteria

1. WHEN storing a lift log, THE Lift_Log_Controller SHALL use the exercise type strategy to determine validation rules
2. WHEN processing lift log data, THE Lift_Log_Controller SHALL delegate data transformation to the exercise type strategy
3. WHEN updating a lift log, THE Lift_Log_Controller SHALL use the same strategy-based approach as creation
4. WHEN creating an exercise, THE Exercise_Controller SHALL use exercise type strategies for any type-specific processing
5. WHERE validation fails, THE Lift_Log_Controller SHALL return appropriate error messages based on exercise type requirements

### Requirement 4

**User Story:** As a developer, I want services to use exercise type strategies to determine capabilities, so that service logic is decoupled from exercise type checking.

#### Acceptance Criteria

1. WHEN calculating one rep max, THE One_Rep_Max_Calculator SHALL check exercise type strategy capabilities before performing calculations
2. WHEN generating charts, THE Chart_Service SHALL use exercise type strategy to determine appropriate chart type
3. WHEN an exercise type does not support 1RM calculation, THE One_Rep_Max_Calculator SHALL throw a NotApplicableException
4. WHEN formatting progression suggestions, THE Training_Progression_Service SHALL delegate to exercise type strategy
5. WHERE new calculation types are needed, THE services SHALL extend through strategy pattern rather than conditional logic

### Requirement 5

**User Story:** As a developer, I want presenters to use exercise type strategies for formatting, so that display logic is consistent and maintainable.

#### Acceptance Criteria

1. WHEN formatting weight display, THE Lift_Log_Presenter SHALL use exercise type strategy formatting methods
2. WHEN formatting 1RM display, THE Lift_Log_Presenter SHALL check exercise type capabilities before displaying values
3. WHEN displaying progress information, THE Lift_Log_Presenter SHALL use exercise type strategy for appropriate formatting
4. WHEN rendering table data, THE Lift_Log_Presenter SHALL delegate all type-specific formatting to strategies
5. WHERE new display formats are needed, THE Lift_Log_Presenter SHALL support them through strategy extension

### Requirement 6

**User Story:** As a developer, I want view components to replace conditional rendering logic, so that views are cleaner and more reusable.

#### Acceptance Criteria

1. WHEN rendering exercise forms, THE Exercise_Form_Component SHALL determine form fields based on exercise type strategy
2. WHEN rendering lift log forms, THE Lift_Log_Form_Component SHALL show appropriate input fields based on exercise type
3. WHEN displaying validation errors, THE form components SHALL show exercise type appropriate error messages
4. WHEN an exercise type changes, THE form components SHALL dynamically update available fields
5. WHERE new form fields are needed, THE components SHALL support them through strategy-driven configuration

### Requirement 7

**User Story:** As a developer, I want the refactoring to maintain backward compatibility, so that existing functionality continues to work during the migration.

#### Acceptance Criteria

1. WHEN the refactoring is implemented, THE existing API endpoints SHALL continue to function without changes
2. WHEN database operations occur, THE data structure SHALL remain unchanged during the migration
3. WHEN existing tests run, THE test suite SHALL pass without modification during the transition period
4. WHEN users interact with the application, THE user experience SHALL remain identical during the refactoring
5. WHERE legacy code exists, THE system SHALL support both old and new approaches during the migration phase

### Requirement 8

**User Story:** As a developer, I want exercise type configuration to be externalized, so that new exercise types can be added through configuration rather than code changes.

#### Acceptance Criteria

1. WHEN defining exercise types, THE system SHALL use configuration files to specify type properties and behaviors
2. WHEN adding new exercise types, THE configuration SHALL support extension without code modification
3. WHEN validation rules change, THE configuration SHALL allow rule updates without touching controller code
4. WHEN chart types are modified, THE configuration SHALL specify which chart generator to use for each exercise type
5. WHERE exercise type properties are needed, THE system SHALL read them from centralized configuration