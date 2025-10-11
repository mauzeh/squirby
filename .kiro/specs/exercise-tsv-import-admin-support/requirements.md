# Requirements Document

## Introduction

This feature updates the existing Exercise TSV import functionality to work with the new admin-managed exercises system. The import should respect the two-tier exercise management where administrators can create global exercises and users can create personal exercises, while preventing conflicts and maintaining data integrity.

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to import exercises via TSV that become global exercises, so that I can efficiently populate the global exercise library for all users.

#### Acceptance Criteria

1. WHEN an administrator imports exercises via TSV THEN the system SHALL provide an option to create them as global exercises
2. WHEN an administrator chooses to import as global exercises THEN the system SHALL set user_id to NULL for all imported exercises
3. WHEN importing global exercises THEN the system SHALL check for conflicts with existing global exercises by name
4. WHEN a global exercise name conflict is detected THEN the system SHALL update the existing global exercise instead of creating a duplicate

### Requirement 2

**User Story:** As a user, I want to import exercises via TSV that become my personal exercises, so that I can efficiently add custom exercises to my workout library.

#### Acceptance Criteria

1. WHEN a user imports exercises via TSV THEN the system SHALL create them as user-specific exercises by default
2. WHEN importing user exercises THEN the system SHALL check for conflicts with both global exercises and the user's existing exercises
3. WHEN a user tries to import an exercise that matches a global exercise name THEN the system SHALL skip the import and report the conflict
4. WHEN a user imports an exercise that matches their existing personal exercise THEN the system SHALL update the existing exercise

### Requirement 3

**User Story:** As a system, I want to maintain data integrity during TSV imports, so that exercise names remain unique within their scope and no duplicate exercises are created.

#### Acceptance Criteria

1. WHEN importing exercises THEN the system SHALL use case-insensitive name matching for conflict detection
2. WHEN conflicts are detected THEN the system SHALL provide detailed feedback about which exercises were skipped or updated
3. WHEN updating existing exercises during import THEN the system SHALL only update if the data actually differs
4. WHEN import is complete THEN the system SHALL provide a summary of imported, updated, and skipped exercises

### Requirement 4

**User Story:** As a user interface, I want to provide clear options for exercise import modes, so that administrators and users understand how their imports will be processed.

#### Acceptance Criteria

1. WHEN an administrator views the exercise import interface THEN the system SHALL display an option to import as global exercises
2. WHEN a non-administrator views the exercise import interface THEN the system SHALL only show the personal exercise import option
3. WHEN displaying import results THEN the system SHALL clearly indicate whether exercises were created as global or personal
4. WHEN import errors occur THEN the system SHALL provide specific error messages about conflicts and validation failures