# Requirements Document

## Introduction

This feature adds support for banded exercises in the TSV export/import functionality for both Exercise and LiftLog data. Currently, the application supports banded exercises (resistance and assistance bands) in the core functionality, but the TSV import/export does not handle the `band_type` field for exercises or the `band_color` field for lift sets.

## Glossary

- **TSV_System**: The tab-separated values import/export functionality in the application
- **Exercise_TSV**: The TSV format used for importing/exporting exercise data
- **LiftLog_TSV**: The TSV format used for importing/exporting lift log data
- **Band_Type**: The type of band exercise ('resistance' or 'assistance')
- **Band_Color**: The specific color/resistance level of the band used in a lift set
- **TsvImporterService**: The service class responsible for processing TSV imports
- **Banded_Exercise**: An exercise with a non-null band_type field

## Requirements

### Requirement 1

**User Story:** As a user, I want to export my exercises to TSV format including band type information, so that I can backup or share my complete exercise data including banded exercises.

#### Acceptance Criteria

1. WHEN THE TSV_System exports exercises, THE Exercise_TSV SHALL include a band_type column
2. WHEN an exercise has a band_type value, THE Exercise_TSV SHALL display the band_type value in the band_type column
3. WHEN an exercise has no band_type value, THE Exercise_TSV SHALL display 'none' in the band_type column
4. THE Exercise_TSV SHALL include the band_type column in the TSV format

### Requirement 2

**User Story:** As a user, I want to import exercises from TSV format including band type information, so that I can restore or import exercise data with banded exercise support.

#### Acceptance Criteria

1. WHEN THE TsvImporterService processes Exercise_TSV with band_type column, THE TsvImporterService SHALL parse the band_type value
2. WHEN the band_type value is 'resistance' or 'assistance', THE TsvImporterService SHALL set the exercise band_type field accordingly
3. WHEN the band_type value is 'none', THE TsvImporterService SHALL set the exercise band_type field to null
4. WHEN the band_type value is invalid, THE TsvImporterService SHALL reject the row and include it in invalid rows
5. THE TsvImporterService SHALL expect the band_type column to be present in the TSV format

### Requirement 3

**User Story:** As a user, I want to export my lift logs to TSV format including band color information, so that I can backup or share my complete workout data including banded exercise sessions.

#### Acceptance Criteria

1. WHEN THE TSV_System exports lift logs, THE LiftLog_TSV SHALL include a band_color column
2. WHEN a lift set has a band_color value, THE LiftLog_TSV SHALL display the band_color value in the band_color column
3. WHEN a lift set has no band_color value, THE LiftLog_TSV SHALL display 'none' in the band_color column
4. THE LiftLog_TSV SHALL include the band_color column in the TSV format

### Requirement 4

**User Story:** As a user, I want to import lift logs from TSV format including band color information, so that I can restore or import workout data with banded exercise sessions.

#### Acceptance Criteria

1. WHEN THE TsvImporterService processes LiftLog_TSV with band_color column, THE TsvImporterService SHALL parse the band_color value
2. WHEN the exercise is a Banded_Exercise and band_color is a valid band color, THE TsvImporterService SHALL set the lift set band_color field accordingly
3. WHEN the exercise is a Banded_Exercise and band_color is 'none', THE TsvImporterService SHALL reject the row as invalid
4. WHEN the exercise is not a Banded_Exercise and band_color is 'none', THE TsvImporterService SHALL set the band_color field to null
5. WHEN the exercise is not a Banded_Exercise and band_color is not 'none', THE TsvImporterService SHALL reject the row as invalid
6. THE TsvImporterService SHALL expect the band_color column to be present in the TSV format

### Requirement 5

**User Story:** As a user, I want the TSV import validation to properly handle banded exercises, so that I get clear error messages when importing invalid banded exercise data.

#### Acceptance Criteria

1. WHEN importing exercises with invalid band_type values, THE TsvImporterService SHALL include descriptive error messages in the invalid rows report
2. WHEN importing lift logs for banded exercises without band_color, THE TsvImporterService SHALL include descriptive error messages in the invalid rows report
3. WHEN importing lift logs with band_color for non-banded exercises, THE TsvImporterService SHALL process the row successfully but ignore the band_color value
4. THE TsvImporterService SHALL validate that band_type values are only 'resistance', 'assistance', or 'none'
5. THE TsvImporterService SHALL validate that band_color values match the configured band colors when provided for banded exercises