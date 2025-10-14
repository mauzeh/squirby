# Requirements Document

## Introduction

This feature adds support for band-based resistance exercises with progressive overload capabilities. Athletes will be able to track exercises that use resistance bands (red, blue, green, black - from light to heavy) and follow a hypertrophy progression model where they progress from 8-12 reps on a lighter band before moving to 8 reps on the next heavier band. The system will support this progression in both the mobile entry screen and the standard lift-log creation interface.

## Requirements

### Requirement 1

**User Story:** As an athlete, I want to mark exercises as "banded" so that the system knows these exercises use resistance bands instead of traditional weights.

#### Acceptance Criteria

1. WHEN creating or editing an exercise THEN the system SHALL provide an option to mark the exercise as "banded"
2. WHEN an exercise is marked as banded THEN the system SHALL store this attribute in the database
3. WHEN viewing exercise details THEN the system SHALL display whether the exercise is banded


### Requirement 2

**User Story:** As an athlete, I want to select from standard gym bands (red, blue, green, black) when logging banded exercises so that I can accurately track my resistance level.

#### Acceptance Criteria

1. WHEN logging a banded exercise THEN the system SHALL present band options as colored buttons: red, blue, green, black
2. WHEN selecting a band THEN the system SHALL require only a single tap/click on the colored button to activate it
3. WHEN a band is selected THEN the system SHALL visually indicate the active selection
4. WHEN displaying lift logs THEN the system SHALL show the band type instead of weight
5. IF no band is selected for a banded exercise THEN the system SHALL require band selection before saving

### Requirement 3

**User Story:** As an athlete, I want the mobile entry screen to support band selection for banded exercises so that I can quickly log my workouts on my phone.

#### Acceptance Criteria

1. WHEN accessing mobile entry for a banded exercise THEN the system SHALL display colored band buttons for direct tap selection
2. WHEN tapping a band button on mobile THEN the system SHALL immediately activate that band with minimal interaction
3. WHEN logging sets on mobile for banded exercises THEN the system SHALL associate the selected band with each set
4. WHEN viewing previous sets on mobile THEN the system SHALL display the band used for each set

### Requirement 4

**User Story:** As an athlete, I want the system to support hypertrophy progression for banded exercises so that I can systematically increase resistance as I get stronger.

#### Acceptance Criteria

1. WHEN an athlete completes 12 reps with a band THEN the system SHALL suggest progressing to the next heavier band at 8 reps
2. WHEN viewing progression suggestions THEN the system SHALL show the recommended next band
3. IF an athlete is using the heaviest band (black) THEN the system SHALL suggest increasing reps beyond 12

### Requirement 5

**User Story:** As an athlete, I want the system to recommend a band color based on my exercise history so that I can quickly select the appropriate resistance level.

#### Acceptance Criteria

1. WHEN logging a banded exercise THEN the system SHALL recommend a band color based on previous workout history
2. WHEN no previous history exists THEN the system SHALL recommend the lightest band (red)
3. WHEN displaying band recommendations THEN the system SHALL visually highlight the recommended band button
4. WHEN an athlete selects a different band THEN the system SHALL still allow the selection without restriction

### Requirement 6

**User Story:** As an athlete, I want the standard lift-log creation screen to support band selection so that I have consistent functionality across all interfaces.

#### Acceptance Criteria

1. WHEN creating a lift log for a banded exercise THEN the system SHALL show colored band buttons instead of weight input
2. WHEN clicking a band button on desktop THEN the system SHALL immediately activate that band with a single click
3. WHEN editing a lift log for a banded exercise THEN the system SHALL allow changing the band selection via the same colored buttons
4. WHEN copying previous sets THEN the system SHALL copy the band information for banded exercises
5. WHEN validating lift log entries THEN the system SHALL ensure band selection is present for banded exercises