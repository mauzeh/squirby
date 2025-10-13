# Requirements Document

## Introduction

This feature adds a quick program entry capability to the programs index page, allowing users to rapidly add program entries by selecting exercises from a dropdown interface similar to the one used on the lift-logs index page. When an exercise is selected, it automatically creates a program entry for the currently viewed date without requiring navigation to a separate form page.

## Requirements

### Requirement 1

**User Story:** As a user viewing the programs index page, I want to quickly add a program entry by selecting an exercise from the interface, so that I can efficiently plan my workouts without navigating through multiple pages.

#### Acceptance Criteria

1. WHEN I am on the programs index page THEN I SHALL see an exercise selector interface with the top 5 exercises as clickable buttons
2. WHEN I am on the programs index page THEN I SHALL see a dropdown button that reveals additional exercises on hover
3. WHEN I click on any exercise button (top 5) THEN the system SHALL automatically create a program entry for the current date in view
4. WHEN I hover over the dropdown button THEN the system SHALL display a list of remaining exercises
5. WHEN I click on any exercise from the dropdown THEN the system SHALL automatically create a program entry for the current date in view
6. WHEN a program entry is created THEN the system SHALL refresh the page to show the new entry
7. WHEN a program entry is created THEN the system SHALL display a success message confirming the addition

### Requirement 2

**User Story:** As a user, I want the exercise selector to behave identically to the lift-logs interface, so that I have a consistent and familiar experience across the application.

#### Acceptance Criteria

1. WHEN I interact with the exercise selector THEN it SHALL have identical visual design and behavior as the lift-logs exercise selector
2. WHEN I view the top exercises THEN they SHALL be displayed as clickable buttons identical to lift-logs
3. WHEN I hover over the dropdown button THEN it SHALL open a dropdown menu identical to lift-logs
4. WHEN I view the exercise list in the dropdown THEN it SHALL show exercises in the same format as the lift-logs interface
5. WHEN no exercises are available THEN the system SHALL display an appropriate message
6. WHEN I interact with the dropdown THEN it SHALL open on hover and close when mouse leaves, identical to lift-logs behavior

### Requirement 3

**User Story:** As a user, I want the quick add feature to respect the current date context, so that program entries are created for the correct date I'm viewing.

#### Acceptance Criteria

1. WHEN I am viewing a specific date on the programs index THEN the quick add SHALL create entries for that date
2. WHEN I navigate to different dates THEN the quick add SHALL automatically adjust to create entries for the new date
3. WHEN I create a program entry THEN it SHALL appear in the correct date section of the interface
4. WHEN the date context changes THEN the exercise selector SHALL remain functional for the new date

### Requirement 4

**User Story:** As a user, I want the quick add feature to handle errors gracefully, so that I understand what went wrong if the program entry creation fails.

#### Acceptance Criteria

1. WHEN program entry creation fails due to validation errors THEN the system SHALL display specific error messages
2. WHEN program entry creation fails due to server errors THEN the system SHALL display a user-friendly error message
3. WHEN duplicate program entries are attempted THEN the system SHALL handle this appropriately based on business rules

