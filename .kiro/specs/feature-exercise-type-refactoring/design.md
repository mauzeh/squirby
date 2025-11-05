# Design Document

## Overview

This design implements a Strategy Pattern combined with Factory Pattern to decouple exercise type logic from the application's controllers, services, and views. The solution replaces scattered conditional logic with a clean, extensible architecture that centralizes exercise type behavior while maintaining backward compatibility.

## Architecture

### High-Level Architecture

```
┌─────────────────┐    ┌──────────────────────┐    ┌─────────────────────┐
│   Controllers   │───▶│  ExerciseTypeFactory │───▶│ ExerciseTypeStrategy│
└─────────────────┘    └──────────────────────┘    └─────────────────────┘
                                                              │
┌─────────────────┐    ┌──────────────────────┐              │
│    Services     │───▶│  ExerciseTypeFactory │──────────────┘
└─────────────────┘    └──────────────────────┘              │
                                                              │
┌─────────────────┐    ┌──────────────────────┐              │
│   Presenters    │───▶│  ExerciseTypeFactory │──────────────┘
└─────────────────┘    └──────────────────────┘              │
                                                              │
┌─────────────────┐                                          │
│ View Components │─────────────────────────────────────────┘
└─────────────────┘
```

### Strategy Pattern Implementation

The core of the design uses the Strategy Pattern to encapsulate exercise type behavior:

- **ExerciseTypeInterface**: Defines the contract for all exercise type strategies
- **RegularExerciseType**: Handles traditional weight-based exercises
- **BandedExerciseType**: Handles resistance/assistance band exercises  
- **BodyweightExerciseType**: Handles bodyweight exercises

### Factory Pattern Implementation

The Factory Pattern eliminates conditional logic for strategy creation:

- **ExerciseTypeFactory**: Creates appropriate strategy instances based on exercise properties
- **ConfigurableFactory**: Supports configuration-driven strategy creation for extensibility

## Components and Interfaces

### Core Interface

```php
interface ExerciseTypeInterface
{
    // Validation
    public function getValidationRules(User $user = null): array;
    public function getFormFields(): array;
    
    // Data Processing
    public function processLiftData(array $data): array;
    public function processExerciseData(array $data): array;
    
    // Capabilities
    public function canCalculate1RM(): bool;
    public function getChartType(): string;
    public function getSupportedProgressionTypes(): array;
    
    // Display Formatting
    public function formatWeightDisplay(LiftLog $liftLog): string;
    public function format1RMDisplay(LiftLog $liftLog): string;
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string;
    
    // Configuration
    public function getTypeName(): string;
    public function getTypeConfig(): array;
}
```

### Strategy Implementations

#### RegularExerciseType
- **Validation**: Requires weight field
- **Data Processing**: Stores weight, nullifies band_color
- **Capabilities**: Supports 1RM calculation, uses one_rep_max charts
- **Display**: Shows weight in lbs/kg format

#### BandedExerciseType
- **Validation**: Requires band_color field
- **Data Processing**: Sets weight to 0, stores band_color
- **Capabilities**: No 1RM calculation, uses volume progression charts
- **Display**: Shows "Band: [color]" format
- **Subtypes**: Handles both resistance and assistance band logic

#### BodyweightExerciseType
- **Validation**: Optional weight field (for extra weight)
- **Data Processing**: Handles bodyweight + extra weight calculations
- **Capabilities**: Supports 1RM with bodyweight inclusion
- **Display**: Shows "Bodyweight" or "Bodyweight +X lbs" format

### Factory Implementation

```php
class ExerciseTypeFactory
{
    private static array $strategies = [];
    
    public static function create(Exercise $exercise): ExerciseTypeInterface
    {
        $key = self::generateKey($exercise);
        
        if (!isset(self::$strategies[$key])) {
            self::$strategies[$key] = self::createStrategy($exercise);
        }
        
        return self::$strategies[$key];
    }
    
    private static function createStrategy(Exercise $exercise): ExerciseTypeInterface
    {
        if ($exercise->band_type) {
            return new BandedExerciseType($exercise->band_type);
        }
        
        if ($exercise->is_bodyweight) {
            return new BodyweightExerciseType();
        }
        
        return new RegularExerciseType();
    }
}
```

### Configuration-Driven Extension

```php
// config/exercise_types.php
return [
    'types' => [
        'regular' => [
            'class' => RegularExerciseType::class,
            'validation' => [
                'weight' => 'required|numeric|min:0'
            ],
            'chart_type' => 'one_rep_max',
            'supports_1rm' => true,
            'form_fields' => ['weight']
        ],
        'banded' => [
            'class' => BandedExerciseType::class,
            'validation' => [
                'band_color' => 'required|string|in:red,blue,green'
            ],
            'chart_type' => 'volume_progression',
            'supports_1rm' => false,
            'form_fields' => ['band_color']
        ],
        'bodyweight' => [
            'class' => BodyweightExerciseType::class,
            'validation' => [
                'weight' => 'nullable|numeric|min:0'
            ],
            'chart_type' => 'bodyweight_progression',
            'supports_1rm' => true,
            'form_fields' => ['weight']
        ]
    ]
];
```

## Data Models

### No Database Changes Required

The refactoring maintains the existing database schema:
- `exercises` table: `is_bodyweight`, `band_type` fields remain unchanged
- `lift_sets` table: `weight`, `band_color` fields remain unchanged
- All existing data remains valid and accessible

### Model Enhancements

```php
// app/Models/Exercise.php
class Exercise extends Model
{
    public function getTypeStrategy(): ExerciseTypeInterface
    {
        return ExerciseTypeFactory::create($this);
    }
    
    // Deprecated methods for backward compatibility
    public function isBandedResistance(): bool
    {
        return $this->getTypeStrategy() instanceof BandedExerciseType 
            && $this->band_type === 'resistance';
    }
}
```

## Error Handling

### Strategy-Specific Exceptions

```php
abstract class ExerciseTypeException extends Exception {}

class UnsupportedOperationException extends ExerciseTypeException
{
    public static function for1RM(string $exerciseType): self
    {
        return new self("1RM calculation not supported for {$exerciseType} exercises");
    }
}

class InvalidExerciseDataException extends ExerciseTypeException
{
    public static function forField(string $field, string $exerciseType): self
    {
        return new self("Invalid {$field} for {$exerciseType} exercise");
    }
}
```

### Graceful Degradation

- **Legacy Code Support**: Old conditional logic continues to work during migration
- **Fallback Strategies**: Default to RegularExerciseType if strategy creation fails
- **Error Recovery**: Catch strategy exceptions and provide meaningful user feedback

## Testing Strategy

### Unit Testing Approach

1. **Strategy Testing**: Each strategy class has comprehensive unit tests
2. **Factory Testing**: Factory creation logic tested with various exercise configurations
3. **Integration Testing**: Controller and service integration with strategies
4. **Backward Compatibility**: Existing tests continue to pass during migration

### Test Structure

```php
// tests/Unit/ExerciseTypes/RegularExerciseTypeTest.php
class RegularExerciseTypeTest extends TestCase
{
    public function test_validation_rules_require_weight()
    public function test_processes_lift_data_correctly()
    public function test_supports_1rm_calculation()
    public function test_formats_weight_display()
}

// tests/Unit/ExerciseTypeFactoryTest.php
class ExerciseTypeFactoryTest extends TestCase
{
    public function test_creates_regular_type_for_standard_exercise()
    public function test_creates_banded_type_for_band_exercise()
    public function test_creates_bodyweight_type_for_bodyweight_exercise()
}
```

### Migration Testing

- **Feature Flags**: Use feature flags to toggle between old and new implementations
- **A/B Testing**: Run both implementations in parallel to verify consistency
- **Regression Testing**: Automated tests ensure no functionality is lost

## Performance Considerations

### Strategy Caching

- **Factory Caching**: Cache strategy instances to avoid repeated creation
- **Lazy Loading**: Create strategies only when needed
- **Memory Management**: Implement strategy cleanup for long-running processes

### Database Impact

- **No Additional Queries**: Strategy pattern doesn't add database overhead
- **Eager Loading**: Continue using existing eager loading patterns
- **Query Optimization**: Maintain current query optimization strategies

## Migration Strategy

### Phase 1: Foundation (Week 1-2)
- Create interface and base strategy classes
- Implement factory pattern
- Add configuration system
- Create unit tests for core components

### Phase 2: Controller Integration (Week 3-4)
- Refactor LiftLogController to use strategies
- Refactor ExerciseController to use strategies
- Update validation logic
- Maintain backward compatibility

### Phase 3: Service Layer (Week 5-6)
- Refactor OneRepMaxCalculatorService
- Refactor ChartService
- Update TrainingProgressionService
- Implement strategy-based capabilities checking

### Phase 4: Presentation Layer (Week 7-8)
- Refactor LiftLogTablePresenter
- Create view components
- Update form rendering logic
- Remove conditional display logic

### Phase 5: Cleanup (Week 9-10)
- Remove deprecated methods
- Clean up old conditional logic
- Performance optimization
- Documentation updates

## Extensibility

### Adding New Exercise Types

1. **Create Strategy Class**: Implement ExerciseTypeInterface
2. **Update Configuration**: Add type configuration
3. **Update Factory**: Add creation logic (if not config-driven)
4. **Add Tests**: Create comprehensive test suite
5. **Update Documentation**: Document new type capabilities

### Example: Adding Cable Exercise Type

```php
class CableExerciseType implements ExerciseTypeInterface
{
    public function getValidationRules(User $user = null): array
    {
        return [
            'weight' => 'required|numeric|min:0',
            'cable_attachment' => 'required|string'
        ];
    }
    
    public function canCalculate1RM(): bool
    {
        return true;
    }
    
    public function getChartType(): string
    {
        return 'cable_progression';
    }
    
    // ... implement other interface methods
}
```

This design provides a clean, maintainable, and extensible solution that eliminates tight coupling while preserving all existing functionality.