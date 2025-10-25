# Requirements Document

## Introduction

This feature will replace the current exercise creation form in the mobile lift logs entry interface with an autocomplete search system and "Save as new" functionality. The interface will provide a streamlined experience for finding existing exercises or creating new ones through a unified search interface, similar to the mobile food entry system.

## Glossary

- **Mobile_Lift_Entry_System**: The existing mobile-optimized interface for logging lift workouts
- **Exercise_Autocomplete_Interface**: A unified search field that allows users to find existing exercises or create new ones
- **Exercise**: A user-specific or global workout movement that can be added to programs and logged
- **Save_As_New_Button**: A button that appears when typing in the autocomplete to create a new exercise with the typed name
- **Exercise_Creation_Form**: The current form interface for creating new exercises (to be replaced)
- **Program_Quick_Add**: The functionality that adds an exercise to the current day's program

## Requirements

### Requirement 1

**User Story:** As a mobile user, I want to search for exercises using an autocomplete interface instead of a separate creation form, so that I can quickly find existing exercises or create new ones in a unified workflow.

#### Acceptance Criteria

1. THE Mobile_Lift_Entry_System SHALL replace the current "Create new exercise" form with an Exercise_Autocomplete_Interface
2. THE Exercise_Autocomplete_Interface SHALL provide a single large input field for searching exercises
3. THE Exercise_Autocomplete_Interface SHALL display autocomplete suggestions as the user types
4. THE Exercise_Autocomplete_Interface SHALL search through both user-created and global exercises available to the user
5. THE Exercise_Autocomplete_Interface SHALL maintain the same visual styling as the existing mobile lift entry interface

### Requirement 2

**User Story:** As a user, I want to see exercise suggestions immediately when I focus on the search field, so that I can quickly select from my most commonly used exercises.

#### Acceptance Criteria

1. WHEN the Exercise_Autocomplete_Interface gains focus, THE Mobile_Lift_Entry_System SHALL display recommended exercises
2. THE Mobile_Lift_Entry_System SHALL show the same exercise recommendations currently displayed in the exercise list
3. THE Mobile_Lift_Entry_System SHALL display user-created exercises with appropriate visual indicators
4. THE Mobile_Lift_Entry_System SHALL maintain the existing exercise recommendation logic
5. THE Mobile_Lift_Entry_System SHALL limit initial suggestions to prevent interface overflow

### Requirement 3

**User Story:** As a user, I want to create a new exercise when my search doesn't match existing exercises, so that I can add custom movements to my workout routine.

#### Acceptance Criteria

1. WHEN a user types text that doesn't match existing exercises, THE Exercise_Autocomplete_Interface SHALL display a Save_As_New_Button
2. THE Save_As_New_Button SHALL be labeled "Save as new exercise" followed by the typed text
3. WHEN the Save_As_New_Button is clicked, THE Mobile_Lift_Entry_System SHALL create a new exercise with the typed name
4. THE Mobile_Lift_Entry_System SHALL automatically add the newly created exercise to the current day's program
5. THE Mobile_Lift_Entry_System SHALL use the same exercise creation logic as the current form

### Requirement 4

**User Story:** As a user, I want the autocomplete to filter exercises in real-time as I type, so that I can quickly narrow down to the exercise I want to add.

#### Acceptance Criteria

1. THE Exercise_Autocomplete_Interface SHALL filter exercise suggestions in real-time as the user types
2. THE Mobile_Lift_Entry_System SHALL perform case-insensitive matching on exercise names
3. THE Mobile_Lift_Entry_System SHALL show partial matches (substring matching)
4. THE Mobile_Lift_Entry_System SHALL maintain the existing exercise visibility rules (user vs global exercises)
5. THE Mobile_Lift_Entry_System SHALL display "No exercises found" when no matches exist

### Requirement 5

**User Story:** As a user, I want to select an exercise from the autocomplete suggestions, so that I can quickly add it to my program without additional steps.

#### Acceptance Criteria

1. WHEN a user clicks on an exercise suggestion, THE Mobile_Lift_Entry_System SHALL add that exercise to the current day's program
2. THE Mobile_Lift_Entry_System SHALL use the existing Program_Quick_Add functionality
3. THE Mobile_Lift_Entry_System SHALL hide the Exercise_Autocomplete_Interface after selection
4. THE Mobile_Lift_Entry_System SHALL show the "Add exercise" button again after selection
5. THE Mobile_Lift_Entry_System SHALL maintain the same redirect behavior as the current system

### Requirement 6

**User Story:** As a user, I want the autocomplete interface to be accessible from both the top and bottom "Add exercise" buttons, so that I can add exercises regardless of where I am on the page.

#### Acceptance Criteria

1. THE Mobile_Lift_Entry_System SHALL provide Exercise_Autocomplete_Interface functionality for both top and bottom "Add exercise" buttons
2. THE Mobile_Lift_Entry_System SHALL maintain separate autocomplete instances to prevent conflicts
3. THE Mobile_Lift_Entry_System SHALL hide other autocomplete interfaces when one is active
4. THE Mobile_Lift_Entry_System SHALL provide consistent behavior between top and bottom interfaces
5. THE Mobile_Lift_Entry_System SHALL maintain the existing cancel functionality to hide the interface

### Requirement 7

**User Story:** As a user, I want the interface to handle keyboard navigation and mobile touch interactions effectively, so that I can use the autocomplete on any device.

#### Acceptance Criteria

1. THE Exercise_Autocomplete_Interface SHALL support keyboard navigation (arrow keys, enter, escape)
2. THE Exercise_Autocomplete_Interface SHALL support touch interactions for mobile devices
3. WHEN the escape key is pressed, THE Mobile_Lift_Entry_System SHALL hide the autocomplete interface
4. WHEN enter is pressed on a suggestion, THE Mobile_Lift_Entry_System SHALL select that exercise
5. THE Exercise_Autocomplete_Interface SHALL maintain focus management for accessibility

### Requirement 8

**User Story:** As a user, I want the new autocomplete system to completely replace the old form-based creation, so that I have a consistent and streamlined exercise addition experience.

#### Acceptance Criteria

1. THE Mobile_Lift_Entry_System SHALL remove the existing "Create new exercise" link and form
2. THE Mobile_Lift_Entry_System SHALL remove the Exercise_Creation_Form from the mobile entry interface
3. THE Mobile_Lift_Entry_System SHALL maintain all existing exercise creation validation rules
4. THE Mobile_Lift_Entry_System SHALL preserve all existing exercise properties (bodyweight, band type, etc.)
5. THE Mobile_Lift_Entry_System SHALL not modify any existing exercise management functionality outside of mobile entry