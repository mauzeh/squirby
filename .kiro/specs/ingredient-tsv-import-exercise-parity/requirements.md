# Requirements Document

## Introduction

This feature will update the Ingredient TSV import functionality to match the Exercise TSV import system exactly. Currently, ingredients use a complex 15-column TSV format with basic import/update logic. We need to implement the same sophisticated import system that exercises use, including global vs personal ingredients, detailed result tracking, conflict resolution, and admin controls.

## Requirements

### Requirement 1: Personal Ingredient Support Only

**User Story:** As a user, I want to import personal ingredients that are only visible to me, maintaining the current personal-only ingredient model.

#### Acceptance Criteria

1. WHEN any user imports ingredients THEN the system SHALL create personal ingredients (user_id = current user)
2. WHEN importing ingredients THEN the system SHALL NOT provide global import options
3. WHEN displaying ingredients THEN the system SHALL show only ingredients owned by the current user
4. WHEN a user accesses ingredients THEN they SHALL only see ingredients where user_id = current user
5. WHEN importing THEN the system SHALL maintain the existing personal ingredient model without global functionality

### Requirement 2: Maintain Current TSV Format

**User Story:** As a user, I want to continue using the existing comprehensive 15-column TSV format for ingredients while gaining the enhanced import functionality.

#### Acceptance Criteria

1. WHEN importing ingredients THEN the system SHALL continue to use the existing 15-column format
2. WHEN parsing TSV THEN the system SHALL validate the expected header format
3. WHEN nutritional data is provided THEN the system SHALL import all nutritional values as currently implemented
4. WHEN invalid rows are encountered THEN the system SHALL collect and report them without stopping the import
5. WHEN TSV format is incorrect THEN the system SHALL provide clear error messages about expected format

### Requirement 3: Conflict Resolution and Matching

**User Story:** As a user, I want the system to intelligently handle conflicts between my existing ingredients using case-insensitive matching.

#### Acceptance Criteria

1. WHEN importing ingredients THEN the system SHALL check for conflicts only within the user's existing ingredients (case-insensitive)
2. WHEN an ingredient exists with the same name THEN the system SHALL update it if data differs, or skip if data is identical
3. WHEN matching ingredient names THEN the system SHALL use case-insensitive comparison within the user's ingredients
4. WHEN conflicts occur THEN the system SHALL track the reason for skipping and report it to the user
5. WHEN duplicate names exist in import data THEN the system SHALL handle subsequent occurrences appropriately

### Requirement 4: Detailed Result Tracking

**User Story:** As a user, I want detailed feedback about what happened during the import process, including what was imported, updated, or skipped.

#### Acceptance Criteria

1. WHEN import completes THEN the system SHALL return detailed results including imported, updated, and skipped counts
2. WHEN ingredients are imported THEN the system SHALL track each imported ingredient with its details
3. WHEN ingredients are updated THEN the system SHALL track what fields changed (from/to values)
4. WHEN ingredients are skipped THEN the system SHALL track the reason for skipping
5. WHEN invalid rows exist THEN the system SHALL collect and count them separately
6. WHEN no changes occur THEN the system SHALL clearly indicate that all data already exists

### Requirement 5: Maintain Current User Interface

**User Story:** As a user, I want to keep using the existing ingredient import interface while gaining enhanced import functionality.

#### Acceptance Criteria

1. WHEN any user views the ingredients index THEN they SHALL see the existing TSV import form unchanged
2. WHEN the import form is displayed THEN it SHALL maintain its current styling and layout
3. WHEN the form includes help text THEN it SHALL remain as currently implemented
4. WHEN the form is submitted THEN it SHALL use enhanced validation and processing while maintaining the same user experience
5. WHEN displaying the form THEN it SHALL continue to work exactly as it currently does

### Requirement 6: Enhanced Success Messages

**User Story:** As a user, I want detailed HTML-formatted success messages that clearly show what happened during the import.

#### Acceptance Criteria

1. WHEN import succeeds THEN the system SHALL display an HTML-formatted success message
2. WHEN ingredients are imported THEN the message SHALL list each imported ingredient with its details
3. WHEN ingredients are updated THEN the message SHALL show what changed (field: 'old' → 'new')
4. WHEN ingredients are skipped THEN the message SHALL list each skipped ingredient with the reason
5. WHEN invalid rows exist THEN the message SHALL mention the count of invalid rows
6. WHEN no changes occur THEN the message SHALL clearly state that no new data was imported or updated

### Requirement 7: Production Environment Restrictions

**User Story:** As a system administrator, I want TSV import functionality to be restricted in production environments for security.

#### Acceptance Criteria

1. WHEN in production environment THEN the TSV import form SHALL NOT be displayed
2. WHEN in staging environment THEN the TSV import form SHALL NOT be displayed  
3. WHEN in development environment THEN the TSV import form SHALL be displayed normally
4. WHEN TSV import is attempted in production THEN the system SHALL reject the request
5. WHEN environment restrictions apply THEN they SHALL be consistent with exercise import restrictions

### Requirement 8: Data Validation and Error Handling

**User Story:** As a user, I want proper validation and error handling during the import process.

#### Acceptance Criteria

1. WHEN TSV data is empty THEN the system SHALL return a validation error
2. WHEN TSV data is malformed THEN the system SHALL collect invalid rows and continue processing valid ones
3. WHEN service exceptions occur THEN the system SHALL handle them gracefully with user-friendly error messages
4. WHEN validation fails THEN the system SHALL redirect back with appropriate error messages
5. WHEN processing fails THEN the system SHALL not leave the database in an inconsistent state