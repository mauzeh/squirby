# Implementation Plan

- [x] 1. Update Exercise TSV Import Support
  - Modify `TsvImporterService::importExercises()` to handle band_type column
  - Add band_type validation ('resistance', 'assistance', 'none')
  - Update exercise creation/update logic to handle band_type mapping ('none' → null)
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 2. Add Exercise TSV Export Functionality
  - Create `ExerciseController::exportTsv()` method
  - Generate TSV format with Title, Description, Is Bodyweight, Band Type columns
  - Map band_type values (null → 'none', preserve 'resistance'/'assistance')
  - Add route for exercise TSV export
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 3. Update LiftLog TSV Import Support
  - Modify `TsvImporterService::importLiftLogs()` to handle band_color column
  - Add cross-validation between exercise band_type and lift set band_color
  - Validate band_color values against configured bands and 'none'
  - Update lift set creation logic to handle band_color mapping ('none' → null)
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

- [ ] 4. Add LiftLog TSV Export Functionality
  - Create `LiftLogController::exportTsv()` method
  - Generate TSV format with existing columns plus Band Color column
  - Map band_color values (null → 'none', preserve actual colors)
  - Handle multiple lift sets per log (export each set as separate row)
  - Add route for lift log TSV export
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ] 5. Enhance Import Validation and Error Handling
  - Add descriptive error messages for invalid band_type values
  - Add descriptive error messages for band_color validation failures
  - Validate band_color against configured band colors from config/bands.php
  - Update error reporting to include band-specific validation failures
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ] 6. Add Unit Tests for TSV Import/Export
  - Test exercise import with valid band_type values ('resistance', 'assistance', 'none')
  - Test exercise import with invalid band_type values
  - Test lift log import with valid band_color for banded exercises
  - Test lift log import with invalid band_color combinations
  - Test export methods generate correct TSV format with band data
  - _Requirements: All requirements_

- [ ] 7. Add Integration Tests
  - Test complete import/export cycle for banded exercises
  - Test mixed data scenarios (banded and non-banded exercises)
  - Test error handling for various invalid data combinations
  - Test data integrity after full import/export cycle
  - _Requirements: All requirements_