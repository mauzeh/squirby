# Requirements Document

## Introduction

This analysis compares the TSV import functionality between lift-logs and exercises to identify feature gaps and opportunities for improvement. The goal is to ensure both systems provide consistent, robust import capabilities with similar user experiences and administrative controls.

## Requirements

### Requirement 1: Feature Parity Analysis

**User Story:** As a developer, I want to understand the differences between lift-log and exercise TSV import features, so that I can identify areas for improvement and ensure consistency.

#### Acceptance Criteria

1. WHEN comparing TSV import features THEN the system SHALL document all functional differences between lift-logs and exercises
2. WHEN analyzing import capabilities THEN the system SHALL identify missing features in each implementation
3. WHEN reviewing user experience THEN the system SHALL note inconsistencies in messaging, validation, and error handling
4. WHEN examining administrative controls THEN the system SHALL compare permission systems and global/personal data handling

### Requirement 2: Import Data Structure Comparison

**User Story:** As a user, I want to understand the data format requirements for each TSV import type, so that I can prepare my data correctly.

#### Acceptance Criteria

1. WHEN importing lift-logs THEN the system SHALL require 7 columns: date, time, exercise_name, weight, reps, rounds, notes
2. WHEN importing exercises THEN the system SHALL require 3 columns: title, description, is_bodyweight
3. WHEN validating TSV format THEN the system SHALL provide clear error messages for invalid column counts
4. WHEN processing data THEN the system SHALL handle missing optional fields gracefully

### Requirement 3: Duplicate Detection and Update Logic

**User Story:** As a user, I want consistent duplicate detection across both import types, so that I can reliably update existing data without creating duplicates.

#### Acceptance Criteria

1. WHEN importing lift-logs THEN the system SHALL detect duplicates based on user_id, exercise_id, logged_at, weight, reps, and rounds
2. WHEN importing exercises THEN the system SHALL detect duplicates based on case-insensitive title matching within scope (global vs personal)
3. WHEN finding duplicates THEN the system SHALL update existing records if data differs
4. WHEN data is identical THEN the system SHALL skip import without error
5. WHEN updating records THEN the system SHALL track and report what changed

### Requirement 4: Success Message Consistency

**User Story:** As a user, I want detailed feedback about import results, so that I can understand what was imported, updated, or skipped.

#### Acceptance Criteria

1. WHEN importing lift-logs THEN the system SHALL provide detailed lists of imported and updated entries for small batches (<10 items)
2. WHEN importing exercises THEN the system SHALL provide detailed lists of imported, updated, and skipped exercises with change details
3. WHEN imports are large THEN the system SHALL provide summary counts instead of detailed lists
4. WHEN errors occur THEN the system SHALL provide specific error messages with problematic data highlighted
5. WHEN partial success occurs THEN the system SHALL show both successful operations and warnings for failed items

### Requirement 5: Administrative Controls and Permissions

**User Story:** As an administrator, I want consistent permission controls across import types, so that I can manage global data appropriately.

#### Acceptance Criteria

1. WHEN importing exercises THEN the system SHALL allow admins to import as global exercises
2. WHEN importing lift-logs THEN the system SHALL only allow personal data import (no global lift-logs concept)
3. WHEN non-admins attempt global imports THEN the system SHALL reject the request with appropriate error messages
4. WHEN checking permissions THEN the system SHALL use consistent role-based authorization

### Requirement 6: Error Handling and Validation

**User Story:** As a user, I want clear error messages when my TSV data has problems, so that I can fix issues and retry the import.

#### Acceptance Criteria

1. WHEN TSV data is empty THEN the system SHALL show appropriate error messages
2. WHEN exercise names are not found THEN lift-log import SHALL list missing exercises and skip those rows
3. WHEN data format is invalid THEN the system SHALL identify problematic rows and continue processing valid ones
4. WHEN validation fails THEN the system SHALL provide actionable error messages
5. WHEN conflicts occur THEN the system SHALL explain the conflict and suggest resolution

### Requirement 7: Production Environment Restrictions

**User Story:** As a system administrator, I want TSV import functionality restricted in production environments, so that data integrity is maintained.

#### Acceptance Criteria

1. WHEN in production environment THEN the system SHALL hide TSV import forms for both lift-logs and exercises
2. WHEN in staging environment THEN the system SHALL hide TSV import forms for both lift-logs and exercises  
3. WHEN in development environment THEN the system SHALL show TSV import forms for both lift-logs and exercises
4. WHEN TSV import requests are made in restricted environments THEN the system SHALL return 404 errors
5. WHEN middleware protection is active THEN the system SHALL block unauthorized import attempts

### Requirement 8: User Experience Consistency

**User Story:** As a user, I want similar interfaces and workflows for both import types, so that I can easily use either feature.

#### Acceptance Criteria

1. WHEN viewing import forms THEN both lift-logs and exercises SHALL have similar UI layouts and styling
2. WHEN using import functionality THEN both systems SHALL provide TSV export capabilities for reference
3. WHEN copying export data THEN both systems SHALL include "Copy to Clipboard" functionality
4. WHEN import completes THEN both systems SHALL redirect to appropriate index pages with status messages
5. WHEN forms are displayed THEN both SHALL include helpful placeholder text and instructions