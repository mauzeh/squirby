# Requirements Document

## Introduction

This feature modifies the existing exercise promotion system to replace bulk promotion with individual exercise promotion buttons. Instead of using checkboxes and a bulk promote button, administrators will have individual promote buttons next to each user exercise's edit/delete actions.

## Glossary

- **Exercise_System**: The application's exercise management functionality
- **Admin_User**: A user with the "Admin" role who can promote exercises
- **User_Exercise**: An exercise owned by a specific user (user_id is not null)
- **Global_Exercise**: An exercise available to all users (user_id is null)
- **Promotion_Action**: The process of converting a user exercise to a global exercise

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to promote individual exercises using dedicated buttons, so that I can quickly promote exercises without bulk selection.

#### Acceptance Criteria

1. WHEN an Admin_User views the exercises index, THE Exercise_System SHALL display a promote button for each User_Exercise
2. WHEN an Admin_User views the exercises index, THE Exercise_System SHALL NOT display promote buttons for Global_Exercise entries
3. WHEN an Admin_User clicks a promote button, THE Exercise_System SHALL convert the User_Exercise to a Global_Exercise
4. WHEN a promotion is successful, THE Exercise_System SHALL redirect to the exercises index with a success message
5. WHEN a non-admin user views the exercises index, THE Exercise_System SHALL NOT display any promote buttons

### Requirement 2

**User Story:** As an administrator, I want the bulk promotion functionality removed while keeping bulk deletion, so that the interface focuses on individual promotion actions but maintains bulk deletion capability.

#### Acceptance Criteria

1. THE Exercise_System SHALL maintain checkboxes for exercise selection (used for bulk deletion)
2. THE Exercise_System SHALL NOT display a bulk promote button in the table footer
3. THE Exercise_System SHALL remove bulk promotion JavaScript functionality while maintaining bulk deletion JavaScript
4. THE Exercise_System SHALL remove the bulk promotion route and controller method
5. THE Exercise_System SHALL maintain the bulk delete button and functionality

### Requirement 3

**User Story:** As an administrator, I want individual promotion to maintain the same security and data integrity as bulk promotion, so that the system remains secure and reliable.

#### Acceptance Criteria

1. WHEN promoting an exercise, THE Exercise_System SHALL verify admin permissions using the existing policy
2. WHEN promoting an exercise, THE Exercise_System SHALL preserve all exercise metadata and relationships
3. WHEN promoting an exercise, THE Exercise_System SHALL preserve all associated lift logs
4. IF a User_Exercise is already global, THEN THE Exercise_System SHALL prevent promotion attempts
5. WHEN promotion fails due to authorization, THE Exercise_System SHALL return a 403 error