# Cardio Exercise Type Requirements

## Introduction

This feature introduces a new Cardio exercise type to the exercise type system, specifically designed for distance-based cardiovascular exercises like running, cycling, and rowing. The Cardio type addresses the limitations of using the current Bodyweight type for cardio activities, which have fundamentally different progression patterns and display requirements.

## Glossary

- **Cardio Exercise Type**: A new exercise type strategy for distance-based cardiovascular exercises
- **Distance**: The measurement of how far the exercise was performed (e.g., 500m, 1000m)
- **Rounds**: The number of intervals or sets performed (equivalent to "sets" in other exercise types)
- **Exercise Type Strategy**: The strategy pattern implementation that handles type-specific behavior
- **Exercise Type Field**: A database column that explicitly stores the exercise type for easy identification
- **Progression Model**: The algorithm that suggests improvements for subsequent workouts
- **Form Fields**: The input fields displayed in the mobile entry interface
- **Display Format**: How exercise data is presented to users in logs and summaries

## Requirements

### Requirement 1

**User Story:** As a user who does cardio exercises, I want a dedicated Cardio exercise type, so that my running and other cardio activities are properly categorized and tracked.

#### Acceptance Criteria

1. WHEN the system processes a cardio exercise, THE Exercise_Type_System SHALL use the Cardio exercise type strategy
2. WHEN displaying cardio exercises, THE System SHALL show distance and rounds instead of weight and reps
3. WHEN creating cardio exercises, THE System SHALL set weight to zero and nullify band_color
4. WHERE cardio exercises exist, THE System SHALL not support 1RM calculations
5. WHEN validating cardio data, THE System SHALL require distance (reps field) and rounds but not weight

### Requirement 2

**User Story:** As a user logging cardio workouts, I want the interface to use cardio-appropriate terminology, so that the experience feels natural for distance-based exercises.

#### Acceptance Criteria

1. WHEN displaying cardio exercise forms, THE Mobile_Entry_Interface SHALL show "Distance (m):" instead of "Reps:"
2. WHEN displaying cardio exercise forms, THE Mobile_Entry_Interface SHALL show "Rounds:" instead of "Sets:"
3. WHEN showing cardio workout history, THE System SHALL format display as "500m × 7 rounds" instead of "500 reps × 7 sets"
4. WHEN displaying cardio exercise logs, THE System SHALL use distance-appropriate units and terminology
5. WHERE cardio exercises are shown, THE System SHALL not display weight-related fields or labels

### Requirement 3

**User Story:** As a user with cardio exercise history, I want intelligent progression suggestions, so that my cardio workouts can improve systematically over time.

#### Acceptance Criteria

1. WHEN generating cardio progression suggestions, THE Training_Progression_Service SHALL use cardio-specific progression logic
2. WHEN the last cardio session had moderate distance, THE System SHALL suggest increasing distance by 50-100 meters
3. WHEN the last cardio session had high distance, THE System SHALL suggest adding additional rounds
4. WHEN no cardio history exists, THE System SHALL provide appropriate default starting values
5. WHERE cardio progression is calculated, THE System SHALL not use weight-based progression models

### Requirement 4

**User Story:** As a user managing different exercise types, I want cardio exercises to integrate seamlessly with the existing exercise type system, so that all my workouts are managed consistently.

#### Acceptance Criteria

1. WHEN the exercise type factory creates strategies, THE Factory SHALL support creating Cardio exercise type instances
2. WHEN the system determines exercise types, THE Type_Detection_Logic SHALL correctly identify cardio exercises
3. WHEN cardio exercises are processed, THE System SHALL use the same interfaces as other exercise types
4. WHEN cardio exercises are stored, THE System SHALL use an explicit exercise_type database field for easy identification
5. WHERE cardio exercises exist, THE System SHALL maintain compatibility with existing exercise management features

### Requirement 5

**User Story:** As a developer working with the exercise type system, I want the Cardio type to follow established patterns, so that the codebase remains maintainable and consistent.

#### Acceptance Criteria

1. WHEN implementing the Cardio exercise type, THE Implementation SHALL extend BaseExerciseType
2. WHEN defining cardio configuration, THE System SHALL use the existing exercise_types.php configuration structure
3. WHEN processing cardio data, THE Cardio_Strategy SHALL implement all required ExerciseTypeInterface methods
4. WHEN handling cardio exercises, THE System SHALL follow the same error handling patterns as other types
5. WHERE cardio functionality is added, THE Implementation SHALL include comprehensive test coverage

### Requirement 6

**User Story:** As a database administrator or developer, I want cardio exercises to be easily distinguishable in the database, so that I can efficiently query and manage cardio exercises separately from other exercise types.

#### Acceptance Criteria

1. WHEN querying the database, THE System SHALL provide an explicit exercise_type column to identify cardio exercises
2. WHEN creating cardio exercises, THE System SHALL automatically set the exercise_type field to 'cardio'
3. WHEN filtering exercises by type, THE Database_Queries SHALL use the exercise_type field for efficient lookups
4. WHEN migrating existing exercises, THE System SHALL populate the exercise_type field based on exercise characteristics
5. WHERE cardio exercises exist, THE Database SHALL support direct SQL queries to find all cardio exercises using the exercise_type field

### Requirement 7

**User Story:** As a user with existing running data, I want my historical cardio exercises to work with the new Cardio type, so that my workout history remains intact and functional.

#### Acceptance Criteria

1. WHEN cardio exercises are migrated to the new type, THE System SHALL preserve all existing workout data
2. WHEN displaying historical cardio workouts, THE System SHALL use the new cardio-appropriate formatting
3. WHEN calculating cardio progressions, THE System SHALL use historical data from before the migration
4. WHEN users access old cardio logs, THE System SHALL display them with the new cardio terminology
5. WHERE existing cardio exercises exist, THE Migration SHALL update exercise type classification without data loss