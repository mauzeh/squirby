# Exercise Type Consolidation Requirements

## Introduction

This feature consolidates the exercise type system by migrating from multiple type-identification fields (`is_bodyweight`, `band_type`) to a single unified `exercise_type` field. This refactoring simplifies the codebase, improves query performance, and creates a cleaner foundation for future exercise type additions like cardio exercises.

## Glossary

- **Exercise Type Field**: A single database column that explicitly stores the exercise type using string values
- **Type Consolidation**: The process of migrating from multiple boolean/enum fields to a single type field
- **Legacy Fields**: The existing `is_bodyweight` and `band_type` columns that will be deprecated
- **Exercise Type Strategy**: The strategy pattern implementation that handles type-specific behavior
- **Migration Script**: Database migration that populates the new exercise_type field based on existing data
- **Backward Compatibility**: Maintaining existing functionality during the transition period
- **Deprecation Period**: The time frame where old methods are marked as deprecated but still functional

## Requirements

### Requirement 1

**User Story:** As a developer working with the exercise system, I want a single field to identify exercise types, so that querying and type detection is simplified and consistent.

#### Acceptance Criteria

1. WHEN the system determines exercise types, THE Exercise_Type_System SHALL use only the exercise_type field for identification
2. WHEN querying exercises by type, THE Database_Queries SHALL use the exercise_type field with a single WHERE condition
3. WHEN creating new exercises, THE System SHALL set the exercise_type field based on exercise characteristics
4. WHEN the exercise_type field is populated, THE System SHALL support the following values: 'regular', 'bodyweight', 'banded_resistance', 'banded_assistance'
5. WHERE exercise type detection occurs, THE System SHALL not require checking multiple fields

### Requirement 2

**User Story:** As a database administrator, I want banded exercises to be clearly distinguished by assistance vs resistance type, so that I can efficiently query and manage different band exercise categories.

#### Acceptance Criteria

1. WHEN storing resistance band exercises, THE System SHALL set exercise_type to 'banded_resistance'
2. WHEN storing assistance band exercises, THE System SHALL set exercise_type to 'banded_assistance'
3. WHEN querying banded exercises, THE Database_Queries SHALL use exercise_type IN ('banded_resistance', 'banded_assistance')
4. WHEN displaying banded exercises, THE System SHALL differentiate between resistance and assistance types
5. WHERE band exercises exist, THE System SHALL not require checking the band_type field

### Requirement 3

**User Story:** As a developer maintaining the codebase, I want the migration to preserve all existing exercise data, so that no workout history or exercise information is lost during the consolidation.

#### Acceptance Criteria

1. WHEN the migration runs, THE Migration_Script SHALL populate exercise_type for all existing exercises
2. WHEN exercises have band_type = 'resistance', THE Migration_Script SHALL set exercise_type = 'banded_resistance'
3. WHEN exercises have band_type = 'assistance', THE Migration_Script SHALL set exercise_type = 'banded_assistance'
4. WHEN exercises have is_bodyweight = true AND band_type IS NULL, THE Migration_Script SHALL set exercise_type = 'bodyweight'
5. WHEN exercises have no special type indicators, THE Migration_Script SHALL set exercise_type = 'regular'

### Requirement 4

**User Story:** As a developer working with existing code, I want backward compatibility during the transition, so that existing functionality continues to work while we migrate to the new system.

#### Acceptance Criteria

1. WHEN legacy methods are called, THE System SHALL continue to function using the new exercise_type field
2. WHEN deprecated methods are used, THE System SHALL log deprecation warnings for developer awareness
3. WHEN the ExerciseTypeFactory determines types, THE Factory SHALL use exercise_type as the primary source
4. WHEN legacy field accessors are used, THE System SHALL derive values from exercise_type field
5. WHERE backward compatibility is maintained, THE System SHALL not break existing functionality

### Requirement 5

**User Story:** As a developer adding new exercise types, I want a clean and extensible type system, so that adding types like cardio requires minimal changes to existing code.

#### Acceptance Criteria

1. WHEN new exercise types are added, THE System SHALL only require configuration updates and new strategy classes
2. WHEN the ExerciseTypeFactory creates strategies, THE Factory SHALL use simple string matching on exercise_type
3. WHEN exercise type detection occurs, THE System SHALL not require complex conditional logic
4. WHEN querying exercises by type, THE Database_Queries SHALL use direct equality or IN clauses
5. WHERE type extensibility is needed, THE System SHALL support adding new types without schema changes

### Requirement 6

**User Story:** As a system administrator, I want the migration to be safe and reversible, so that I can confidently deploy the changes and rollback if needed.

#### Acceptance Criteria

1. WHEN the migration runs, THE Migration_Script SHALL validate data integrity before and after the migration
2. WHEN populating exercise_type, THE Migration_Script SHALL ensure no exercises are left with NULL exercise_type
3. WHEN the migration completes, THE System SHALL provide a report of exercises migrated by type
4. WHEN rollback is needed, THE Migration_Script SHALL support reversing the exercise_type population
5. WHERE data validation fails, THE Migration_Script SHALL halt and report specific errors

### Requirement 7

**User Story:** As a developer working with the Exercise model, I want clean and intuitive methods for type checking, so that the code is readable and maintainable.

#### Acceptance Criteria

1. WHEN checking exercise types, THE Exercise_Model SHALL provide isType(string $type) method
2. WHEN legacy type methods are called, THE Exercise_Model SHALL mark them as deprecated
3. WHEN type-specific behavior is needed, THE System SHALL use the strategy pattern with exercise_type
4. WHEN developers query exercise types, THE Model SHALL provide clear and consistent method names
5. WHERE type checking occurs, THE System SHALL use the unified exercise_type field exclusively

### Requirement 8

**User Story:** As a quality assurance engineer, I want comprehensive testing of the migration and new type system, so that I can verify the consolidation works correctly across all use cases.

#### Acceptance Criteria

1. WHEN migration tests run, THE Test_Suite SHALL verify correct exercise_type population for all scenarios
2. WHEN backward compatibility tests run, THE Test_Suite SHALL verify legacy methods still work
3. WHEN type detection tests run, THE Test_Suite SHALL verify ExerciseTypeFactory uses exercise_type correctly
4. WHEN integration tests run, THE Test_Suite SHALL verify no functionality is broken by the consolidation
5. WHERE edge cases exist, THE Test_Suite SHALL include tests for exercises with unusual type combinations