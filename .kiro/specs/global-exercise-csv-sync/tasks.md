# Implementation Plan

- [x] 1. Update CSV file structure to support band_type column
  - Add band_type column to the existing CSV file header
  - Ensure existing exercises have appropriate band_type values (null/empty for non-banded exercises)
  - _Requirements: 1.6, 2.6_

- [x] 2. Enhance console command data collection methods
  - [x] 2.1 Update global exercises query to include band_type field
    - Modify the Exercise query to select band_type along with other fields
    - Add validation to skip exercises without canonical_name
    - _Requirements: 1.1, 4.3_
  
  - [x] 2.2 Implement CSV parsing with band_type support
    - Create method to parse existing CSV file and index by canonical_name
    - Add proper error handling for malformed CSV entries
    - _Requirements: 1.2, 4.4_

- [x] 3. Implement exercise comparison and matching logic
  - [x] 3.1 Create exercise comparison method
    - Compare title, description, is_bodyweight, and band_type fields
    - Return detailed array of differences between database and CSV data
    - Handle null values and data type conversions properly
    - _Requirements: 2.1, 2.2, 2.6_
  
  - [x] 3.2 Implement change identification system
    - Match exercises by canonical_name between database and CSV
    - Categorize exercises as "update needed", "new entry", or "no change"
    - Track specific field changes for reporting
    - _Requirements: 1.3, 1.4, 2.1_

- [x] 4. Add user feedback and confirmation system
  - [x] 4.1 Implement change reporting
    - Display total number of global exercises found
    - Show count of updates and new entries
    - List specific exercises being modified with change details
    - _Requirements: 3.1, 3.2, 3.3, 3.4_
  
  - [x] 4.2 Add user confirmation prompt
    - Ask for user confirmation before making changes
    - Allow user to cancel operation safely
    - _Requirements: 3.5_

- [x] 5. Implement CSV synchronization
  - [x] 5.1 Implement complete CSV rewrite
    - Write entire CSV file with updated and new data
    - Maintain proper CSV formatting and header structure
    - Preserve existing entry order where possible, append new entries
    - Handle file operations safely with proper error handling
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 6. Rename and update command
  - [x] 6.1 Rename command class and update signature
    - Rename class from PersistMissingGlobalExercises to PersistGlobalExercises
    - Change command signature from 'exercises:persist-missing-global' to 'exercises:sync-global-to-csv'
    - Update command description to reflect new synchronization functionality
    - Update related tests to match the name of the class
    - _Requirements: 1.1_
  
  - [x] 6.2 Enhance environment and file validation
    - Keep existing local environment restriction
    - Add comprehensive CSV file existence and permission checks
    - _Requirements: 4.1, 4.2_

- [-] 7. Update GlobalExercisesSeeder to handle band_type field
  - [-] 7.1 Add band_type field processing to seeder
    - Read band_type column from CSV
    - Validate band_type values ('resistance', 'assistance', or empty)
    - Set band_type field on exercise model during import
    - _Requirements: 6.1, 6.2, 6.3_
  
  - [ ] 7.2 Add band_type change reporting to seeder
    - Include band_type changes in console output when updating exercises
    - Show old and new band_type values when changes occur
    - _Requirements: 6.4_

- [ ] 8. Create comprehensive tests for enhanced functionality
  - [ ] 8.1 Write unit tests for new methods
    - Test CSV parsing with band_type column
    - Test exercise comparison logic with all field types
    - Test change identification and categorization
    - _Requirements: All requirements_
  
  - [ ] 8.2 Write integration tests for complete sync process
    - Test end-to-end synchronization with mixed scenarios
    - Test CSV rewrite functionality
    - Test error handling for various failure scenarios
    - _Requirements: All requirements_
  
  - [ ] 8.3 Update existing tests for command and seeder changes
    - Modify any existing tests that depend on the old command behavior
    - Update GlobalExercisesSeederTest to include band_type testing
    - _Requirements: All requirements_