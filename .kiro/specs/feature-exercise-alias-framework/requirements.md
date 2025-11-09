# Requirements Document

## Introduction

This feature enables users to maintain personalized names (aliases) for exercises after their custom exercises are merged into global exercises by administrators. When an admin merges a user's "BP" exercise into the global "Bench Press" exercise, the user will continue to see "BP" in their workout logs, exercise lists, and program entries, preserving their familiar terminology while benefiting from the consolidated global exercise data.

## Glossary

- **Exercise_Alias**: A user-specific alternative name for an exercise that displays instead of the exercise's title
- **Exercise_Title**: The official name of the exercise stored in the title field (e.g., "Bench Press")
- **Alias_System**: The system component that manages and applies exercise aliases for users
- **User_Context**: The specific user viewing or interacting with exercise data
- **Merged_Exercise**: A user exercise that was consolidated into a global exercise through the merge operation
- **Display_Name**: The name shown to the user, which may be either the exercise title or an alias

## Requirements

### Requirement 1: Alias Creation During Merge

**User Story:** As an administrator merging exercises, I want to control whether aliases are created for affected users, so that I can decide when name preservation is appropriate.

#### Acceptance Criteria

1. WHEN an administrator initiates an exercise merge, THE Alias_System SHALL provide an option to create an alias for the affected user
2. WHEN the administrator enables alias creation during merge, THE Alias_System SHALL create an exercise alias for the original exercise owner
3. WHEN the administrator disables alias creation during merge, THE Alias_System SHALL not create any alias
4. THE Alias_System SHALL store the original exercise title as the alias name
5. THE Alias_System SHALL associate the alias with the target exercise and the original owner's user account
6. THE Alias_System SHALL create the alias within the same database transaction as the merge operation
7. THE Alias_System SHALL default to creating aliases (checkbox checked) to preserve user familiarity unless the administrator explicitly opts out

### Requirement 2: Alias Display in Exercise Lists

**User Story:** As a user with exercise aliases, I want to see my personalized names in exercise selection lists, so that I can quickly find exercises using my familiar terminology.

#### Acceptance Criteria

1. WHEN a user views an exercise list, THE Alias_System SHALL display the user's alias instead of the exercise title where an alias exists
2. WHEN a user views an exercise list, THE Alias_System SHALL display the exercise title where no alias exists
3. THE Alias_System SHALL apply aliases consistently across all exercise selection interfaces including dropdown menus, autocomplete fields, and exercise index pages
4. THE Alias_System SHALL maintain alphabetical sorting based on the displayed name (alias or exercise title)

### Requirement 3: Alias Display in Workout History

**User Story:** As a user with exercise aliases, I want to see my personalized names in my workout logs, so that my training history remains familiar and readable.

#### Acceptance Criteria

1. WHEN a user views their lift logs, THE Alias_System SHALL display the user's alias for exercises where an alias exists
2. WHEN a user views their lift logs, THE Alias_System SHALL display the exercise title where no alias exists
3. THE Alias_System SHALL apply aliases to lift log tables, charts, and progress tracking views
4. WHEN a user exports their workout data, THE Alias_System SHALL include the alias name in the exported data

### Requirement 4: Alias Display in Programs

**User Story:** As a user with exercise aliases, I want to see my personalized names in my training programs, so that my planned workouts use terminology I recognize.

#### Acceptance Criteria

1. WHEN a user views their training programs, THE Alias_System SHALL display the user's alias for exercises where an alias exists
2. WHEN a user creates or edits a program entry, THE Alias_System SHALL show aliases in exercise selection interfaces
3. THE Alias_System SHALL display aliases in program templates and scheduled workout views
4. WHEN a user logs a workout from a program, THE Alias_System SHALL maintain the alias display throughout the logging process

### Requirement 5: Alias Data Integrity

**User Story:** As a system administrator, I want the alias system to maintain data integrity, so that aliases are stored correctly and consistently.

#### Acceptance Criteria

1. THE Alias_System SHALL allow multiple users to have the same alias name for different exercises (aliases are user-scoped)
2. THE Alias_System SHALL prevent duplicate aliases for the same user-exercise combination using database constraints

### Requirement 6: Alias Performance Optimization

**User Story:** As a user with many exercise aliases, I want the system to load quickly, so that aliases don't negatively impact my experience.

#### Acceptance Criteria

1. THE Alias_System SHALL load user aliases efficiently using database indexing on user_id and exercise_id
2. THE Alias_System SHALL cache user aliases in memory during a user session to minimize database queries
3. WHEN displaying lists of exercises, THE Alias_System SHALL use eager loading to fetch aliases with exercises in a single query
4. THE Alias_System SHALL not add more than one additional database query per page load for alias resolution
5. THE Alias_System SHALL invalidate alias cache when a user creates, updates, or deletes an alias

### Requirement 7: Alias Migration and Data Integrity

**User Story:** As a system administrator, I want aliases to maintain data integrity during exercise operations, so that user preferences are preserved through system changes.

#### Acceptance Criteria

1. WHEN an exercise is deleted, THE Alias_System SHALL delete all associated aliases
2. WHEN exercises are merged, THE Alias_System SHALL preserve existing aliases and create new ones for affected users
3. IF a user already has an alias for the target exercise during a merge, THEN THE Alias_System SHALL keep the existing alias and not create a duplicate
4. THE Alias_System SHALL use database foreign key constraints to ensure aliases always reference valid exercises
5. THE Alias_System SHALL use database foreign key constraints to ensure aliases always reference valid users




