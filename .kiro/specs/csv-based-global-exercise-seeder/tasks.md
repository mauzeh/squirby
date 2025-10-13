# Implementation Plan

- [x] 1. Create CSV file with current exercise data
  - Extract all exercise data from the current GlobalExerciseSeeder array
  - Create `database/seeders/csv/exercises_from_real_world.csv` with proper headers (title, description, is_bodyweight)
  - Include all 24 exercises currently defined in the seeder
  - Ensure proper CSV formatting with quotes around text fields containing commas
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 2. Refactor GlobalExerciseSeeder to read from CSV
  - Modify the `run()` method to read from the CSV file using `file()` function
  - Parse each CSV line using `str_getcsv()` similar to IngredientSeeder approach
  - Remove the hardcoded `$exercises` array from the seeder
  - _Requirements: 1.1, 4.1, 4.2_

- [x] 3. Implement CSV row processing logic
  - Create logic to process each CSV row and extract title, description, and is_bodyweight fields
  - Handle boolean conversion for is_bodyweight field (convert "1"/"true" to true, others to false)
  - Skip rows with empty or missing title fields
  - Maintain the existing `firstOrCreate` logic to prevent duplicates
  - _Requirements: 1.3, 1.4, 3.1, 3.2, 4.4_

- [x] 4. Add error handling for file operations
  - Handle case where CSV file doesn't exist with clear error message
  - Handle malformed CSV rows gracefully by skipping them
  - Ensure seeder completes successfully when CSV is valid
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [x] 5. Create tests for the CSV-based seeder
  - Write unit test to verify seeder reads CSV file correctly
  - Test that all exercises from CSV are created in database
  - Test duplicate prevention with `firstOrCreate`
  - Test boolean field conversion for is_bodyweight
  - Test error handling for missing CSV file
  - _Requirements: All requirements verification_

- [x] 6. Verify data migration integrity
  - Run the new seeder and compare exercise count with original
  - Verify all exercise attributes (title, description, is_bodyweight) are preserved
  - Ensure no duplicate exercises are created
  - Test that existing exercises are not duplicated on subsequent runs
  - _Requirements: 2.1, 1.3, 1.4_