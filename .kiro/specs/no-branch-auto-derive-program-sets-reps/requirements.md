# Requirements Document

## Introduction

This feature will enhance the program creation functionality by automatically deriving sets and reps values using the existing training progression logic, instead of requiring manual input from users. The system will leverage the TrainingProgressionService to intelligently suggest appropriate sets and reps based on the user's training history for each exercise.

## Requirements

### Requirement 1

**User Story:** As a user creating a program entry, I want the sets and reps to be automatically calculated based on my training progression, so that I don't need to manually specify these values and the program reflects optimal training parameters.

#### Acceptance Criteria

1. WHEN a user accesses the program creation form THEN the sets and reps input fields SHALL be removed from the form
2. WHEN a user selects an exercise for a program entry THEN the system SHALL automatically calculate the appropriate sets and reps using the TrainingProgressionService
3. WHEN the program entry is saved THEN the calculated sets and reps values SHALL be stored in the database
4. IF no progression data exists for the selected exercise THEN the system SHALL use the default values from the training configuration (3 sets, 10 reps)

### Requirement 2

**User Story:** As a user editing an existing program entry, I want to maintain full control over the sets and reps values, so that I can make manual adjustments as needed without automatic recalculation.

#### Acceptance Criteria

1. WHEN a user accesses the program edit form THEN the sets and reps input fields SHALL remain available for manual editing
2. WHEN a user edits a program entry THEN the system SHALL NOT automatically recalculate sets and reps values
3. WHEN the program entry is updated THEN the manually entered sets and reps values SHALL be stored in the database
4. WHEN editing existing program entries THEN the current sets and reps values SHALL be preserved and editable

### Requirement 3

**User Story:** As a user, I want the quick-add functionality to continue working seamlessly with auto-derived sets and reps, so that the mobile entry workflow remains efficient.

#### Acceptance Criteria

1. WHEN a user uses the quick-add functionality THEN the system SHALL automatically calculate sets and reps using the TrainingProgressionService
2. WHEN a new exercise is created through quick-create THEN the system SHALL use default values from the training configuration
3. WHEN the quick-add operation completes THEN the program entry SHALL be created with the calculated sets and reps values

### Requirement 4

**User Story:** As a system, I want to maintain backward compatibility with existing program entries, so that previously created programs continue to function correctly.

#### Acceptance Criteria

1. WHEN displaying existing program entries THEN the system SHALL show the stored sets and reps values without modification
2. WHEN existing program entries are edited THEN the manual sets and reps input fields SHALL remain available
3. WHEN the system processes existing data THEN no existing program entries SHALL be modified automatically

### Requirement 5

**User Story:** As a user, I want clear feedback about how sets and reps are determined, so that I understand the system's decision-making process.

#### Acceptance Criteria

1. WHEN the program creation form is displayed THEN the system SHALL show informational text explaining that sets and reps are automatically calculated
2. WHEN progression data is available THEN the system SHALL indicate that values are based on training history
3. WHEN no progression data exists THEN the system SHALL indicate that default values are being used
4. IF the selected exercise is a bodyweight exercise THEN the system SHALL show appropriate messaging about the calculation method