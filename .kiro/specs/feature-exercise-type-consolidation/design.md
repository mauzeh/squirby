# Exercise Type Consolidation Design

## Overview

This design consolidates the exercise type identification system from multiple fields (`is_bodyweight`, `band_type`) to a single unified `exercise_type` field. The consolidation simplifies type detection, improves query performance, and creates a clean foundation for future exercise types.

The key innovation is replacing complex conditional logic with simple string-based type identification while maintaining full backward compatibility during the transition period.

## Architecture

### Current vs New Type System

**Current System (Complex):**
```
Exercise Type Detection:
├── band_type IS NOT NULL → BandedExerciseType
│   ├── band_type = 'resistance' → BandedExerciseType
│   └── band_type = 'assistance' → BandedExerciseType
├── is_bodyweight = true → BodyweightExerciseType
└── Default → RegularExerciseType
```

**New System (Simplified):**
```
Exercise Type Detection:
├── exercise_type = 'banded_resistance' → BandedResistanceExerciseType
├── exercise_type = 'banded_assistance' → BandedAssistanceExerciseType
├── exercise_type = 'bodyweight' → BodyweightExerciseType
├── exercise_type = 'regular' → RegularExerciseType
└── exercise_type = 'cardio' → CardioExerciseType (future)
```

### Database Schema Changes

**New Field:**
```sql
ALTER TABLE exercises ADD COLUMN exercise_type VARCHAR(50) AFTER band_type;
CREATE INDEX idx_exercises_exercise_type ON exercises(exercise_type);
```

**Exercise Type Values:**
| exercise_type | Description | Replaces |
|---------------|-------------|----------|
| `regular` | Standard weighted exercises | Default case |
| `bodyweight` | Bodyweight exercises | `is_bodyweight = true` |
| `banded_resistance` | Resistance band exercises | `band_type = 'resistance'` |
| `banded_assistance` | Assistance band exercises | `band_type = 'assistance'` |

## Components and Interfaces

### Migration Strategy

**Phase 1: Add and Populate exercise_type**
```php
// Migration: add_exercise_type_field_and_populate
public function up(): void
{
    Schema::table('exercises', function (Blueprint $table) {
        $table->string('exercise_type', 50)->nullable()->after('band_type');
        $table->index('exercise_type');
    });
    
    $this->populateExerciseTypes();
    
    // Make the field non-nullable after population
    Schema::table('exercises', function (Blueprint $table) {
        $table->string('exercise_type', 50)->nullable(false)->change();
    });
}

private function populateExerciseTypes(): void
{
    // Banded resistance exercises
    DB::table('exercises')
        ->where('band_type', 'resistance')
        ->update(['exercise_type' => 'banded_resistance']);
    
    // Banded assistance exercises  
    DB::table('exercises')
        ->where('band_type', 'assistance')
        ->update(['exercise_type' => 'banded_assistance']);
    
    // Bodyweight exercises (only if not already banded)
    DB::table('exercises')
        ->where('is_bodyweight', true)
        ->whereNull('exercise_type')
        ->update(['exercise_type' => 'bodyweight']);
    
    // Regular exercises (everything else)
    DB::table('exercises')
        ->whereNull('exercise_type')
        ->update(['exercise_type' => 'regular']);
    
    // Validation: ensure no NULL exercise_type values
    $nullCount = DB::table('exercises')->whereNull('exercise_type')->count();
    if ($nullCount > 0) {
        throw new Exception("Migration failed: {$nullCount} exercises still have NULL exercise_type");
    }
}
```

### Updated ExerciseTypeFactory

**Simplified Type Detection:**
```php
class ExerciseTypeFactory
{
    private static function determineExerciseType(Exercise $exercise): string
    {
        // Much simpler - just return the exercise_type field
        return $exercise->exercise_type ?? 'regular';
    }
    
    private static function generateKey(Exercise $exercise): string
    {
        return sprintf('exercise_%d_%s', $exercise->id, $exercise->exercise_type);
    }
}
```

### Exercise Model Updates

**New Methods:**
```php
class Exercise extends Model
{
    protected $fillable = [
        'title',
        'description', 
        'canonical_name',
        'is_bodyweight',    // Deprecated but kept for compatibility
        'user_id',
        'band_type',        // Deprecated but kept for compatibility
        'exercise_type',    // New primary field
    ];
    
    /**
     * Check if this exercise is of a specific type
     */
    public function isType(string $type): bool
    {
        return $this->exercise_type === $type;
    }
    
    /**
     * Check if this is a banded exercise (either type)
     */
    public function isBanded(): bool
    {
        return in_array($this->exercise_type, ['banded_resistance', 'banded_assistance']);
    }
    
    /**
     * Check if this is a resistance band exercise
     */
    public function isBandedResistance(): bool
    {
        return $this->exercise_type === 'banded_resistance';
    }
    
    /**
     * Check if this is an assistance band exercise  
     */
    public function isBandedAssistance(): bool
    {
        return $this->exercise_type === 'banded_assistance';
    }
    
    /**
     * @deprecated Use isType('bodyweight') instead
     */
    public function isBodyweight(): bool
    {
        \Log::warning('Exercise::isBodyweight() is deprecated. Use isType("bodyweight") instead.');
        return $this->exercise_type === 'bodyweight';
    }
    
    // Scopes for efficient querying
    public function scopeOfType($query, string $type)
    {
        return $query->where('exercise_type', $type);
    }
    
    public function scopeBanded($query)
    {
        return $query->whereIn('exercise_type', ['banded_resistance', 'banded_assistance']);
    }
    
    public function scopeBodyweight($query)
    {
        return $query->where('exercise_type', 'bodyweight');
    }
    
    public function scopeRegular($query)
    {
        return $query->where('exercise_type', 'regular');
    }
}
```

### Configuration Updates

**Updated exercise_types.php:**
```php
return [
    'types' => [
        'regular' => [
            'class' => \App\Services\ExerciseTypes\RegularExerciseType::class,
            'validation' => [
                'weight' => 'required|numeric|min:0|max:2000',
                'reps' => 'required|integer|min:1|max:100',
            ],
            'chart_type' => 'weight_progression',
            'supports_1rm' => true,
            'form_fields' => ['weight', 'reps'],
            'progression_types' => ['weight_progression', 'volume_progression'],
            'display_format' => 'weight_lbs',
        ],
        
        'bodyweight' => [
            'class' => \App\Services\ExerciseTypes\BodyweightExerciseType::class,
            'validation' => [
                'weight' => 'nullable|numeric|in:0',
                'reps' => 'required|integer|min:1|max:100',
            ],
            'chart_type' => 'reps_progression',
            'supports_1rm' => false,
            'form_fields' => ['reps'],
            'progression_types' => ['reps_progression'],
            'display_format' => 'reps_only',
        ],
        
        'banded_resistance' => [
            'class' => \App\Services\ExerciseTypes\BandedResistanceExerciseType::class,
            'validation' => [
                'weight' => 'nullable|numeric|in:0',
                'reps' => 'required|integer|min:1|max:100',
                'band_color' => 'required|string|in:' . implode(',', config('bands.colors')),
            ],
            'chart_type' => 'band_progression',
            'supports_1rm' => false,
            'form_fields' => ['reps', 'band_color'],
            'progression_types' => ['band_progression'],
            'display_format' => 'band_reps',
        ],
        
        'banded_assistance' => [
            'class' => \App\Services\ExerciseTypes\BandedAssistanceExerciseType::class,
            'validation' => [
                'weight' => 'nullable|numeric|in:0',
                'reps' => 'required|integer|min:1|max:100',
                'band_color' => 'required|string|in:' . implode(',', config('bands.colors')),
            ],
            'chart_type' => 'band_progression',
            'supports_1rm' => false,
            'form_fields' => ['reps', 'band_color'],
            'progression_types' => ['band_progression'],
            'display_format' => 'band_reps',
        ],
    ],
];
```

## Data Models

### Exercise Type Strategy Classes

**Split BandedExerciseType into Two Classes:**

```php
// New: BandedResistanceExerciseType
class BandedResistanceExerciseType extends BaseExerciseType
{
    public function getTypeName(): string
    {
        return 'banded_resistance';
    }
    
    public function processLiftData(array $data): array
    {
        $data['weight'] = 0; // Force weight to 0
        
        // Validate band color is provided
        if (empty($data['band_color'])) {
            throw new InvalidExerciseDataException('Band color is required for resistance band exercises');
        }
        
        return $data;
    }
    
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        return $liftLog->band_color . ' band';
    }
}

// New: BandedAssistanceExerciseType  
class BandedAssistanceExerciseType extends BaseExerciseType
{
    public function getTypeName(): string
    {
        return 'banded_assistance';
    }
    
    public function processLiftData(array $data): array
    {
        $data['weight'] = 0; // Force weight to 0
        
        // Validate band color is provided
        if (empty($data['band_color'])) {
            throw new InvalidExerciseDataException('Band color is required for assistance band exercises');
        }
        
        return $data;
    }
    
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        return $liftLog->band_color . ' assistance';
    }
}
```

### Backward Compatibility Layer

**Legacy Field Support:**
```php
class Exercise extends Model
{
    /**
     * Accessor for legacy is_bodyweight field
     * @deprecated Will be removed in future version
     */
    public function getIsBodyweightAttribute(): bool
    {
        if (isset($this->attributes['is_bodyweight'])) {
            // During transition period, use actual field if available
            return (bool) $this->attributes['is_bodyweight'];
        }
        
        // After migration, derive from exercise_type
        return $this->exercise_type === 'bodyweight';
    }
    
    /**
     * Accessor for legacy band_type field
     * @deprecated Will be removed in future version
     */
    public function getBandTypeAttribute(): ?string
    {
        if (isset($this->attributes['band_type'])) {
            // During transition period, use actual field if available
            return $this->attributes['band_type'];
        }
        
        // After migration, derive from exercise_type
        return match($this->exercise_type) {
            'banded_resistance' => 'resistance',
            'banded_assistance' => 'assistance',
            default => null,
        };
    }
}
```

## Error Handling

### Migration Validation

**Data Integrity Checks:**
```php
class ExerciseTypeConsolidationMigration
{
    private function validateMigration(): void
    {
        // Check for exercises that couldn't be categorized
        $uncategorized = DB::table('exercises')->whereNull('exercise_type')->count();
        if ($uncategorized > 0) {
            throw new Exception("Migration incomplete: {$uncategorized} exercises without exercise_type");
        }
        
        // Validate type distribution makes sense
        $typeCounts = DB::table('exercises')
            ->select('exercise_type', DB::raw('COUNT(*) as count'))
            ->groupBy('exercise_type')
            ->get();
        
        foreach ($typeCounts as $typeCount) {
            Log::info("Exercise type migration: {$typeCount->exercise_type} = {$typeCount->count} exercises");
        }
        
        // Ensure no invalid exercise_type values
        $validTypes = ['regular', 'bodyweight', 'banded_resistance', 'banded_assistance'];
        $invalidTypes = DB::table('exercises')
            ->whereNotIn('exercise_type', $validTypes)
            ->count();
            
        if ($invalidTypes > 0) {
            throw new Exception("Migration error: {$invalidTypes} exercises have invalid exercise_type values");
        }
    }
}
```

### Deprecation Warnings

**Graceful Deprecation:**
```php
class Exercise extends Model
{
    /**
     * @deprecated Use isType('bodyweight') instead. Will be removed in v2.0
     */
    public function isBodyweight(): bool
    {
        if (config('app.debug')) {
            Log::warning('Deprecated method Exercise::isBodyweight() called', [
                'exercise_id' => $this->id,
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);
        }
        
        return $this->isType('bodyweight');
    }
}
```

## Testing Strategy

### Migration Testing

**Data Migration Tests:**
```php
class ExerciseTypeConsolidationTest extends TestCase
{
    public function test_migration_populates_exercise_type_correctly()
    {
        // Create test exercises with old field structure
        $resistanceBand = Exercise::create([
            'title' => 'Banded Squat',
            'band_type' => 'resistance',
            'is_bodyweight' => false,
        ]);
        
        $bodyweight = Exercise::create([
            'title' => 'Push-up',
            'is_bodyweight' => true,
            'band_type' => null,
        ]);
        
        // Run migration
        $this->artisan('migrate');
        
        // Verify correct exercise_type assignment
        $resistanceBand->refresh();
        $bodyweight->refresh();
        
        $this->assertEquals('banded_resistance', $resistanceBand->exercise_type);
        $this->assertEquals('bodyweight', $bodyweight->exercise_type);
    }
    
    public function test_migration_handles_edge_cases()
    {
        // Test exercise with both is_bodyweight and band_type
        $conflicted = Exercise::create([
            'title' => 'Conflicted Exercise',
            'is_bodyweight' => true,
            'band_type' => 'resistance', // This should take priority
        ]);
        
        $this->artisan('migrate');
        
        $conflicted->refresh();
        $this->assertEquals('banded_resistance', $conflicted->exercise_type);
    }
}
```

### Backward Compatibility Testing

**Legacy Method Tests:**
```php
class BackwardCompatibilityTest extends TestCase
{
    public function test_legacy_methods_still_work()
    {
        $exercise = Exercise::create([
            'title' => 'Test Exercise',
            'exercise_type' => 'bodyweight',
        ]);
        
        // Legacy method should still work
        $this->assertTrue($exercise->isBodyweight());
        $this->assertNull($exercise->band_type);
    }
}
```

## Implementation Phases

### Phase 1: Database Migration (Safe)
- Add exercise_type column
- Populate based on existing data
- Add database constraints and indexes
- Validate migration success

### Phase 2: Code Updates (Backward Compatible)
- Update ExerciseTypeFactory to use exercise_type
- Add new Exercise model methods
- Split BandedExerciseType into two classes
- Update configuration
- Add deprecation warnings to legacy methods

### Phase 3: Testing and Validation
- Comprehensive test suite for migration
- Backward compatibility testing
- Performance testing of new queries
- Integration testing across the application

### Phase 4: Cleanup (Future)
- Remove deprecated methods (after sufficient warning period)
- Drop legacy columns (is_bodyweight, band_type)
- Remove backward compatibility code
- Update documentation

## Performance Considerations

### Query Optimization

**Before (Complex):**
```sql
-- Finding banded exercises required complex conditions
SELECT * FROM exercises 
WHERE band_type IS NOT NULL;

-- Finding bodyweight exercises
SELECT * FROM exercises 
WHERE is_bodyweight = true AND band_type IS NULL;
```

**After (Simple):**
```sql
-- Finding banded exercises is straightforward
SELECT * FROM exercises 
WHERE exercise_type IN ('banded_resistance', 'banded_assistance');

-- Finding bodyweight exercises
SELECT * FROM exercises 
WHERE exercise_type = 'bodyweight';
```

### Index Strategy

**Optimized Indexes:**
```sql
-- Single index covers all type queries
CREATE INDEX idx_exercises_exercise_type ON exercises(exercise_type);

-- Composite index for user-specific type queries
CREATE INDEX idx_exercises_user_type ON exercises(user_id, exercise_type);
```

## Security Considerations

### Data Validation

**Exercise Type Validation:**
```php
class Exercise extends Model
{
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($exercise) {
            $validTypes = ['regular', 'bodyweight', 'banded_resistance', 'banded_assistance'];
            
            if (!in_array($exercise->exercise_type, $validTypes)) {
                throw new InvalidArgumentException("Invalid exercise_type: {$exercise->exercise_type}");
            }
        });
    }
}
```

### Migration Safety

**Rollback Support:**
```php
public function down(): void
{
    // Rollback is safe - just drop the new column
    // Legacy fields remain intact during transition
    Schema::table('exercises', function (Blueprint $table) {
        $table->dropIndex('idx_exercises_exercise_type');
        $table->dropColumn('exercise_type');
    });
}
```