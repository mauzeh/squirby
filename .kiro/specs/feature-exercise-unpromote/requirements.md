# Requirements Document

## Introduction

This feature allows administrators to unpromote global exercises back to user-specific exercises, with safety checks to prevent data integrity issues related to existing lift logs from other users.

## Glossary

- **Exercise_System**: The application's exercise management functionality
- **Admin_User**: A user with the "Admin" role who can unpromote exercises
- **Global_Exercise**: An exercise available to all users (user_id is null)
- **User_Exercise**: An exercise owned by a specific user (user_id is not null)
- **Original_Owner**: The user who originally created the exercise before it was promoted
- **Lift_Log**: A workout entry that references a specific exercise
- **Unpromote_Action**: The process of converting a global exercise back to a user exercise

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to unpromote global exercises back to user exercises, so that I can correct mistaken promotions while maintaining data integrity.

#### Acceptance Criteria

1. WHEN an Admin_User views the exercises index, THE Exercise_System SHALL display an unpromote button for each Global_Exercise that has an identifiable original owner
2. WHEN an Admin_User clicks an unpromote button, THE Exercise_System SHALL check if other users have Lift_Log entries for that exercise
3. IF no other users have Lift_Log entries, THE Exercise_System SHALL convert the Global_Exercise back to a User_Exercise owned by the original owner
4. WHEN unpromote is successful, THE Exercise_System SHALL redirect to the exercises index with a success message
5. WHEN a non-admin user views the exercises index, THE Exercise_System SHALL NOT display any unpromote buttons

### Requirement 2

**User Story:** As an administrator, I want clear feedback when unpromote operations cannot be completed, so that I understand why the action failed and what the implications are.

#### Acceptance Criteria

1. IF other users have Lift_Log entries for the exercise, THE Exercise_System SHALL prevent the unpromote action
2. WHEN unpromote is blocked due to other users' logs, THE Exercise_System SHALL display an error message explaining the conflict
3. THE Exercise_System SHALL specify how many other users have logs with the exercise
4. THE Exercise_System SHALL suggest that the exercise must remain global to preserve other users' workout data
5. WHEN unpromote fails due to authorization, THE Exercise_System SHALL return a 403 error

### Requirement 3

**User Story:** As a system, I want to maintain data integrity during exercise unpromote operations, so that all existing workout data remains accurate and accessible.

#### Acceptance Criteria

1. WHEN an exercise is unpromoted, THE Exercise_System SHALL preserve all existing Lift_Log entries that reference the exercise
2. WHEN an exercise is unpromoted, THE Exercise_System SHALL maintain the exercise's original creation date and metadata
3. WHEN an exercise is unpromoted, THE Exercise_System SHALL assign the exercise to its original owner
4. THE Exercise_System SHALL only allow unpromote if the original owner can be determined
5. WHEN querying exercises after unpromote, THE Exercise_System SHALL show the exercise as a user exercise only to the original owner

### Requirement 4

**User Story:** As an administrator, I want appropriate permissions and safeguards for exercise unpromote operations, so that only authorized users can modify exercise ownership.

#### Acceptance Criteria

1. WHEN a non-administrator attempts unpromote, THE Exercise_System SHALL verify the user has admin role before proceeding
2. THE Exercise_System SHALL verify that the exercise is currently global before allowing unpromote
3. THE Exercise_System SHALL verify that the original owner still exists in the system
4. WHEN an administrator performs unpromote, THE Exercise_System SHALL log the action with details of which exercise was unpromoted
5. THE Exercise_System SHALL prevent unpromote if the original owner cannot be determined

### Requirement 5

**User Story:** As an administrator, I want the unpromote interface to be consistent with existing exercise management operations, so that the user experience is familiar and intuitive.

#### Acceptance Criteria

1. WHEN an administrator views the exercises index, THE Exercise_System SHALL display unpromote buttons similar to existing promote buttons
2. THE Exercise_System SHALL use a confirmation dialog before executing unpromote operations
3. THE Exercise_System SHALL style unpromote buttons distinctly from promote buttons to avoid confusion
4. THE Exercise_System SHALL position unpromote buttons logically within the actions column
5. WHEN unpromote is completed, THE Exercise_System SHALL refresh the exercise list to reflect the ownership change immediately