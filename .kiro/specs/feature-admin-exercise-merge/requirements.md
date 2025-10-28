# Requirements Document

## Introduction

This feature enables administrators to merge duplicate exercises created by users with existing global exercises. When users create exercises with slightly different names that represent the same movement (e.g., "Bench Press" vs "Barbell Bench Press" vs "BP"), administrators can consolidate the data by merging the user's exercise data into the existing global exercise and removing the duplicate.

## Glossary

- **Source_Exercise**: The user-created exercise that will be merged and subsequently deleted
- **Target_Exercise**: The existing global exercise that will receive the merged data
- **Exercise_Merge_System**: The administrative system component that handles exercise consolidation
- **Lift_Log**: Individual workout records associated with exercises
- **Exercise_Intelligence**: AI-generated insights and recommendations associated with exercises
- **Program_Entry**: Planned workout entries that reference exercises

## Requirements

### Requirement 1: Exercise Merge Interface

**User Story:** As an administrator, I want to see merge options directly on the exercise index page, so that I can quickly identify and merge duplicate exercises.

#### Acceptance Criteria

1. WHEN an administrator views the exercises index page, THE Exercise_Merge_System SHALL display a merge button next to user exercises that can be merged
2. THE Exercise_Merge_System SHALL only show merge buttons for user exercises (not global exercises)
3. THE Exercise_Merge_System SHALL not display merge buttons for exercises that have no potential global targets
4. THE Exercise_Merge_System SHALL hide merge buttons from non-administrator users

### Requirement 2: Exercise Merge Selection

**User Story:** As an administrator, I want to select which exercises to merge, so that I can consolidate duplicate data accurately.

#### Acceptance Criteria

1. WHEN an administrator selects a source exercise, THE Exercise_Merge_System SHALL display all potential global target exercises
2. THE Exercise_Merge_System SHALL show a preview of data that will be merged including lift logs count, program entries count, and exercise intelligence status
3. THE Exercise_Merge_System SHALL prevent merging exercises with incompatible attributes (bodyweight vs weighted, different band types)
4. THE Exercise_Merge_System SHALL require explicit confirmation before proceeding with the merge operation

### Requirement 3: Data Migration

**User Story:** As an administrator, I want all associated data to be transferred when merging exercises, so that no user data is lost during consolidation.

#### Acceptance Criteria

1. WHEN a merge is executed, THE Exercise_Merge_System SHALL transfer all lift logs from the source exercise to the target exercise
2. WHEN a merge is executed, THE Exercise_Merge_System SHALL transfer all program entries from the source exercise to the target exercise
3. IF the source exercise has exercise intelligence AND the target exercise does not, THEN THE Exercise_Merge_System SHALL transfer the intelligence data
4. IF both exercises have exercise intelligence, THEN THE Exercise_Merge_System SHALL preserve the target exercise intelligence and archive the source intelligence
5. THE Exercise_Merge_System SHALL update all foreign key references to point to the target exercise

### Requirement 4: Exercise Cleanup

**User Story:** As an administrator, I want the duplicate exercise to be removed after successful merge, so that the system maintains data integrity.

#### Acceptance Criteria

1. WHEN all data has been successfully transferred, THE Exercise_Merge_System SHALL delete the source exercise
2. THE Exercise_Merge_System SHALL verify that no orphaned references remain before deletion
3. IF the deletion fails, THEN THE Exercise_Merge_System SHALL rollback all merge operations
4. THE Exercise_Merge_System SHALL log the merge operation with source and target exercise details

### Requirement 5: Merge Validation

**User Story:** As an administrator, I want the system to validate merge compatibility, so that I don't accidentally merge incompatible exercises.

#### Acceptance Criteria

1. THE Exercise_Merge_System SHALL prevent merging exercises with different is_bodyweight values
2. THE Exercise_Merge_System SHALL prevent merging exercises with different band_type values
3. THE Exercise_Merge_System SHALL allow merging exercises where one has null band_type and the other has a specific band_type value
4. WHEN the source exercise owner has global exercise visibility disabled, THEN THE Exercise_Merge_System SHALL warn the administrator that the user will lose access to their exercise data after merge
5. THE Exercise_Merge_System SHALL only allow merging user exercises into global exercises, not into other users' exercises
6. THE Exercise_Merge_System SHALL display clear error messages when merge validation fails

### Requirement 6: Lift Log Annotation

**User Story:** As a user whose exercise was merged, I want to see a record of the original exercise name in my lift logs, so that I understand the history of my data.

#### Acceptance Criteria

1. WHEN lift logs are transferred during a merge, THE Exercise_Merge_System SHALL append a note to each transferred lift log's comments field
2. THE Exercise_Merge_System SHALL include the original exercise name in the appended note
3. IF a lift log already has comments, THEN THE Exercise_Merge_System SHALL append the merge note with appropriate formatting
4. THE Exercise_Merge_System SHALL use a consistent format for merge annotations across all transferred lift logs