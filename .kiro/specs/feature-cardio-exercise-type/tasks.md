# Cardio Exercise Type Implementation Plan

- [ ] 1. Create CardioExerciseType strategy class
  - Create new CardioExerciseType class extending BaseExerciseType
  - Implement getTypeName() method returning 'cardio'
  - Implement processLiftData() to force weight=0 and band_color=null
  - Implement formatWeightDisplay() to show distance instead of weight
  - Add distance validation (minimum 50m, maximum 50km)
  - _Requirements: 1.1, 1.3, 5.1, 5.3_

- [ ] 2. Create database migration for exercise_type field
  - Create migration to add exercise_type column to exercises table
  - Add database index on exercise_type for efficient querying
  - Populate exercise_type field for existing exercises based on characteristics
  - Update Exercise model fillable array to include exercise_type
  - Add cardio and nonCardio query scopes to Exercise model
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 3. Add cardio configuration to exercise types config
  - Add 'cardio' entry to config/exercise_types.php
  - Define validation rules for cardio exercises (reps as distance, weight must be 0)
  - Set supports_1rm to false for cardio exercises
  - Configure form_fields to only include 'reps' (distance)
  - Set progression_types to ['cardio_progression']
  - _Requirements: 1.4, 1.5, 5.2_

- [ ] 4. Implement cardio-specific display formatting
  - Update formatWeightDisplay() to return distance in meters (e.g., "500m")
  - Add method to format complete cardio display (e.g., "500m × 7 rounds")
  - Ensure cardio exercises don't show weight-related information
  - Handle edge cases for very short or very long distances
  - _Requirements: 2.3, 2.4, 2.5_

- [ ] 5. Update mobile entry interface for cardio exercises
  - Modify LiftLogService::generateProgramForms() to detect cardio exercises
  - Change "Reps:" label to "Distance (m):" for cardio exercises
  - Change "Sets:" label to "Rounds:" for cardio exercises
  - Set appropriate increment values (50m for distance, 1 for rounds)
  - Remove weight field from cardio exercise forms
  - _Requirements: 2.1, 2.2, 2.5_

- [ ] 6. Add cardio progression logic to TrainingProgressionService
  - Create cardio-specific progression logic in getSuggestionDetailsWithLog()
  - For distances < 1000m: suggest increasing distance by 50-100m
  - For distances >= 1000m: suggest adding additional rounds
  - Handle cases with no cardio history (provide sensible defaults)
  - Ensure cardio exercises don't use weight-based progression models
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 7. Update exercise type detection logic
  - Update ExerciseTypeFactory to use exercise_type database field for type determination
  - Add fallback logic for exercises without exercise_type set (keyword detection)
  - Ensure CardioExerciseType is created when exercise_type = 'cardio'
  - Update exercise creation to automatically set exercise_type based on characteristics
  - _Requirements: 4.2, 4.3, 6.1, 6.2_

- [ ] 8. Create comprehensive test suite for cardio exercise type
  - Create CardioExerciseTypeTest for strategy class testing
  - Test processLiftData() forces weight=0 and validates distance
  - Test formatWeightDisplay() returns correct distance format
  - Test validation rules reject invalid distances and non-zero weights
  - Test integration with existing exercise type system
  - _Requirements: 5.5_

- [ ] 9. Update existing "Run" exercise to use cardio type
  - Create migration script to identify and update cardio exercises
  - Update the "Run" exercise (ID 29) to use cardio type classification
  - Test that existing running workout data displays correctly with new formatting
  - Verify progression suggestions work with historical running data
  - Ensure no data loss during the exercise type migration
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 10. Integration testing and validation
  - Test complete cardio workout logging flow from mobile entry
  - Verify cardio exercises display correctly in workout history
  - Test cardio progression suggestions with real user data
  - Validate cardio exercises work seamlessly with existing exercise management
  - Test edge cases (very short/long distances, many rounds)
  - _Requirements: 4.1, 4.4, 4.5_