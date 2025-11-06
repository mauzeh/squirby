# Exercise Type Consolidation Implementation Plan

- [ ] 1. Create database migration for exercise_type field
  - Create migration to add exercise_type column to exercises table
  - Add database index on exercise_type for efficient querying
  - Implement data population logic to migrate from is_bodyweight and band_type fields
  - Add validation to ensure no exercises are left with NULL exercise_type
  - Include rollback support that preserves existing legacy fields
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 2. Update Exercise model with new exercise_type field support
  - Add exercise_type to fillable array and define appropriate casting
  - Implement isType(string $type) method for clean type checking
  - Add new query scopes: ofType(), banded(), bodyweight(), regular()
  - Create backward compatibility accessors for is_bodyweight and band_type fields
  - Add deprecation warnings to legacy methods like isBodyweight()
  - _Requirements: 4.1, 4.2, 4.4, 7.1, 7.2, 7.4, 7.5_

- [ ] 3. Simplify ExerciseTypeFactory to use exercise_type field
  - Update determineExerciseType() method to return exercise_type field directly
  - Simplify generateKey() method to use only exercise_type
  - Remove complex conditional logic for type detection
  - Update createStrategy() method to handle new exercise type values
  - Ensure factory supports all new exercise_type values: regular, bodyweight, banded_resistance, banded_assistance
  - _Requirements: 1.1, 1.3, 1.5, 5.2, 5.3_

- [ ] 4. Split BandedExerciseType into separate resistance and assistance classes
  - Create BandedResistanceExerciseType class extending BaseExerciseType
  - Create BandedAssistanceExerciseType class extending BaseExerciseType
  - Implement getTypeName() methods returning 'banded_resistance' and 'banded_assistance'
  - Move band-specific logic from original BandedExerciseType to new classes
  - Update processLiftData() and formatWeightDisplay() methods for each type
  - _Requirements: 2.1, 2.2, 2.4, 5.1_

- [ ] 5. Update exercise_types.php configuration for new type structure
  - Add 'banded_resistance' configuration entry with appropriate validation rules
  - Add 'banded_assistance' configuration entry with appropriate validation rules
  - Update existing 'bodyweight' and 'regular' configurations if needed
  - Remove or deprecate old 'banded' configuration entry
  - Ensure all new exercise types have proper class mappings and validation rules
  - _Requirements: 5.1, 5.4_

- [ ] 6. Create comprehensive migration validation and testing
  - Implement migration validation logic to check data integrity
  - Create test cases for migration with various exercise type combinations
  - Test edge cases like exercises with both is_bodyweight=true and band_type set
  - Verify migration report shows correct exercise counts by type
  - Test rollback functionality preserves original data
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 8.1_

- [ ] 7. Update all database queries to use exercise_type field
  - Find and update queries that use is_bodyweight or band_type fields
  - Replace complex WHERE conditions with simple exercise_type equality checks
  - Update any raw SQL queries to use the new exercise_type field
  - Ensure efficient use of the new exercise_type index
  - Test query performance improvements with the simplified conditions
  - _Requirements: 1.2, 1.4, 2.3, 2.5_

- [ ] 8. Update existing tests to use new exercise_type field
  - Update existing exercise model tests to use exercise_type instead of legacy fields
  - Modify factory tests to create exercises with exercise_type field
  - Update integration tests that rely on exercise type detection
  - Ensure all tests pass with the new consolidated type system
  - Remove any tests that specifically test legacy field behavior
  - _Requirements: 1.1, 1.2, 8.3, 8.4_

- [ ] 9. Update ExerciseTypeFactory tests for new type detection logic
  - Update existing factory tests to use exercise_type field instead of legacy fields
  - Add tests for new exercise types: banded_resistance, banded_assistance
  - Test factory creates correct strategy instances for each exercise_type value
  - Verify factory handles invalid or missing exercise_type values gracefully
  - Test strategy caching works correctly with new exercise_type-based keys
  - _Requirements: 1.1, 1.3, 5.2, 8.3_

- [ ] 10. Integration testing and validation across the application
  - Test exercise creation with new exercise_type field
  - Verify exercise type detection works correctly in mobile entry forms
  - Test exercise management features work with consolidated type system
  - Validate that exercise type strategies are created correctly throughout the app
  - Ensure no functionality is broken by the type system consolidation
  - _Requirements: 1.1, 1.2, 5.1, 5.4, 8.4, 8.5_