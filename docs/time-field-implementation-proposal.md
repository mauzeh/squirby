# Time Field Implementation Proposal

## ✅ Confirmed: No User-Facing Changes for Static Holds

**The static hold form will look IDENTICAL to users.**

**Current form:**
- Hold Duration (seconds)
- Added Weight (lbs)

**After implementation:**
- Hold Duration (seconds) ← same field, same label
- Added Weight (lbs) ← unchanged

**What changes (backend only):**
- Duration stored in `time` column instead of `reps` column
- `reps` is set to 1 automatically (never shown to user)
- Field name in form: `reps` → `time` (but label stays the same)

---

## Overview

Add a dedicated `time` field to the `lift_sets` table to properly store time-based data for exercises like static holds and timed cardio. This replaces the current workaround of storing time in the `reps` field.

## Problem Statement

### Current Issues

**1. Static Holds use `reps` for time:**
```php
// Current workaround
'reps' => 30  // Actually means "30 seconds hold duration"
```
- Semantically incorrect: reps should mean repetitions, not time
- Confusing for developers and users
- Limits future flexibility

**2. No support for timed cardio:**
- Can't track sprint times, race times, or pace
- Athletes who care about speed have no way to log it

**3. Field semantics are overloaded:**
- `reps` means different things for different exercise types
- Makes code harder to understand and maintain

### Why a Dedicated Time Field is Better

**1. Semantic clarity:**
```php
// With dedicated time field
'reps' => 3        // Actual repetitions
'time' => 30.5     // Time in seconds
```

**2. Flexibility:**
- Can track both reps AND time for the same exercise
- Enables new exercise types and tracking modes
- Future-proof for additional time-based features

**3. Better data model:**
- Each field has a single, clear meaning
- Easier to query and analyze
- More intuitive for developers

## Proposed Solution

### Database Schema Change

Add a `time` column to the `lift_sets` table:

```php
Schema::table('lift_sets', function (Blueprint $table) {
    $table->unsignedInteger('time')->nullable()->after('reps');
});
```

**Field specifications:**
- **Type**: `unsignedInteger` - supports up to 4,294,967,295 seconds (~136 years)
- **Nullable**: Yes - only populated for time-based exercises
- **Precision**: Whole seconds only (no fractional seconds needed)
- **Range**: 1s to 4,294,967,295s (effectively unlimited for practical use)

### Updated Schema

```php
// lift_sets table
Schema::create('lift_sets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lift_log_id')->constrained()->onDelete('cascade');
    $table->float('weight');
    $table->integer('reps');
    $table->unsignedInteger('time')->nullable();  // NEW FIELD
    $table->string('notes')->nullable();
    $table->string('band_color')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### Field Usage by Exercise Type

| Exercise Type | Weight | Reps | Time | Band Color |
|--------------|--------|------|------|------------|
| **Regular** | Weight in lbs | Repetitions | null | null |
| **Bodyweight** | Extra weight | Repetitions | null | null |
| **Banded** | 0 | Repetitions | null | Band color |
| **Static Hold** | Extra weight | null or 1 | **Duration (seconds)** | null |
| **Cardio (Distance)** | 0 | Distance (meters) | null | null |
| **Cardio (Timed)** | 0 | Distance (meters, optional) | **Duration (seconds)** | null |

## Migration Strategy

### Phase 1: Add Time Column (Non-Breaking)

**Migration 1: Add column**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->unsignedInteger('time')->nullable()->after('reps');
        });
    }

    public function down(): void
    {
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->dropColumn('time');
        });
    }
};
```

**At this point:**
- ✅ New field exists but is unused
- ✅ All existing code continues to work
- ✅ No data migration needed yet
- ✅ Zero risk to production

### Phase 2: Migrate Static Hold Data

**Migration 2: Copy static hold times from reps to time field**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find all static hold exercises
        $staticHoldExercises = DB::table('exercises')
            ->where('exercise_type', 'static_hold')
            ->pluck('id');
        
        if ($staticHoldExercises->isEmpty()) {
            return;
        }
        
        // Get all lift logs for static hold exercises
        $staticHoldLiftLogs = DB::table('lift_logs')
            ->whereIn('exercise_id', $staticHoldExercises)
            ->pluck('id');
        
        if ($staticHoldLiftLogs->isEmpty()) {
            return;
        }
        
        // Copy reps (duration) to time field for static hold sets
        DB::table('lift_sets')
            ->whereIn('lift_log_id', $staticHoldLiftLogs)
            ->update([
                'time' => DB::raw('reps'),  // Copy reps value to time
                'reps' => 1,                 // Set reps to 1 (one hold)
            ]);
    }

    public function down(): void
    {
        // Find all static hold exercises
        $staticHoldExercises = DB::table('exercises')
            ->where('exercise_type', 'static_hold')
            ->pluck('id');
        
        if ($staticHoldExercises->isEmpty()) {
            return;
        }
        
        // Get all lift logs for static hold exercises
        $staticHoldLiftLogs = DB::table('lift_logs')
            ->whereIn('exercise_id', $staticHoldExercises)
            ->pluck('id');
        
        if ($staticHoldLiftLogs->isEmpty()) {
            return;
        }
        
        // Restore reps from time field
        DB::table('lift_sets')
            ->whereIn('lift_log_id', $staticHoldLiftLogs)
            ->update([
                'reps' => DB::raw('time'),  // Copy time value back to reps
                'time' => null,             // Clear time field
            ]);
    }
};
```

**After this migration:**
- ✅ Static hold data is in the correct field
- ✅ `reps` is set to 1 (semantic: "1 hold performed")
- ✅ `time` contains the duration in seconds
- ✅ Rollback is safe and tested

### Phase 3: Update Static Hold Exercise Type

**Update `StaticHoldExerciseType` to use time field:**

**IMPORTANT**: The form will look identical to users. We're just changing which database field stores the duration.

```php
public function processLiftData(array $data): array
{
    $processedData = $data;
    
    // Nullify band_color for static hold exercises
    $processedData['band_color'] = null;
    
    // Validate time (now stored in time field, not reps)
    if (!isset($processedData['time'])) {
        throw InvalidExerciseDataException::missingField('time', $this->getTypeName());
    }
    
    if (!is_numeric($processedData['time'])) {
        throw InvalidExerciseDataException::forField('time', $this->getTypeName(), 'hold duration must be a number');
    }
    
    $duration = (int) $processedData['time'];
    
    if ($duration < self::MIN_DURATION) {
        throw InvalidExerciseDataException::forField('time', $this->getTypeName(), 'hold duration must be at least ' . self::MIN_DURATION . ' second');
    }
    
    if ($duration > self::MAX_DURATION) {
        throw InvalidExerciseDataException::forField('time', $this->getTypeName(), 'hold duration cannot exceed ' . self::MAX_DURATION . ' seconds');
    }
    
    $processedData['time'] = $duration;
    
    // Set reps to 1 (semantic: "1 hold performed")
    // This is set automatically in the backend, NOT shown in the form
    $processedData['reps'] = 1;
    
    // Validate weight if provided
    if (isset($processedData['weight'])) {
        if (!is_numeric($processedData['weight'])) {
            throw InvalidExerciseDataException::invalidWeight($processedData['weight'], $this->getTypeName());
        }
        
        if ($processedData['weight'] < 0) {
            throw InvalidExerciseDataException::forField('weight', $this->getTypeName(), 'weight cannot be negative');
        }
    } else {
        $processedData['weight'] = 0;
    }
    
    return $processedData;
}
```

**Update form field definitions (change field name from 'reps' to 'time'):**

```php
public function getFormFieldDefinitions(array $defaults = [], ?User $user = null): array
{
    $labels = $this->getFieldLabels();
    $increments = $this->getFieldIncrements();
    
    return [
        [
            'name' => 'time',  // CHANGED from 'reps'
            'label' => $labels['time'],  // CHANGED from 'reps'
            'type' => 'numeric',
            'defaultValue' => $defaults['time'] ?? 30,  // CHANGED from 'reps'
            'increment' => $increments['time'],  // CHANGED from 'reps'
            'min' => self::MIN_DURATION,
            'max' => self::MAX_DURATION,
        ],
        [
            'name' => 'weight',
            'label' => $labels['weight'],
            'type' => 'numeric',
            'defaultValue' => $defaults['weight'] ?? 0,
            'increment' => $increments['weight'],
            'min' => 0,
            'max' => 500,
        ]
    ];
}
```

**Key points:**
- Form shows `time` field (not `reps`) - but label is the same: "Hold Duration (seconds):"
- Form shows `weight` field (unchanged)
- `reps` is set to 1 automatically in the backend (never shown to user)
- **User experience is identical** - same two fields, same labels

**Update display methods:**

```php
public function formatWeightDisplay(LiftLog $liftLog): string
{
    // Now read from time field instead of reps
    $duration = $liftLog->liftSets->first()?->time ?? 0;
    $weight = $liftLog->display_weight;
    
    if (!is_numeric($duration) || $duration <= 0) {
        return '0s hold';
    }
    
    $durationDisplay = $this->formatDuration((int)$duration);
    
    // If there's added weight, include it in the display
    if (is_numeric($weight) && $weight > 0) {
        $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
        return "{$durationDisplay} +{$weightFormatted} lbs";
    }
    
    return $durationDisplay;
}
```

**Update PR detection:**

```php
public function calculateCurrentMetrics(LiftLog $liftLog): array
{
    $bestHold = 0;
    $weightedHolds = []; // [weight => duration]
    
    foreach ($liftLog->liftSets as $set) {
        // Now read from time field instead of reps
        if ($set->time > 0) {
            // Track best overall hold
            $bestHold = max($bestHold, $set->time);
            
            // Track best hold at each weight
            $weight = $set->weight ?? 0;
            if (!isset($weightedHolds[$weight]) || $set->time > $weightedHolds[$weight]) {
                $weightedHolds[$weight] = $set->time;
            }
        }
    }
    
    return [
        'best_hold' => $bestHold,
        'weighted_holds' => $weightedHolds,
    ];
}
```

### Phase 4: Update LiftSet Model

**Add time field to fillable and casts:**

```php
class LiftSet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lift_log_id',
        'weight',
        'reps',
        'time',  // NEW
        'notes',
        'band_color',
    ];

    protected $casts = [
        'weight' => 'float',
        'reps' => 'integer',
        'time' => 'integer',  // NEW - whole seconds only
        'band_color' => 'string',
    ];
    
    // ... rest of model
}
```

### Phase 5: Update Configuration

**Update `config/exercise_types.php` for static holds:**

**IMPORTANT**: The form fields change from `['reps']` to `['time']`, but the user sees the same form with the same labels.

```php
'static_hold' => [
    'class' => \App\Services\ExerciseTypes\StaticHoldExerciseType::class,
    'validation' => [
        'time' => 'required|integer|min:1|max:300',  // CHANGED from 'reps', whole seconds
        'reps' => 'nullable|integer|in:1',            // Always 1, set automatically
        'weight' => 'nullable|numeric|min:0',
    ],
    'chart_type' => 'static_hold_progression',
    'supports_1rm' => false,
    'form_fields' => ['time', 'weight'],  // CHANGED from ['reps', 'weight']
    'progression_types' => ['static_hold_progression'],
    'display_format' => 'time_weight_sets',
    'field_labels' => [
        'time' => 'Hold Duration (seconds):',  // CHANGED from 'reps' (same label text)
        'weight' => 'Added Weight (lbs):',
        'sets' => 'Sets:',
    ],
    'field_increments' => [
        'time' => 1,   // CHANGED from 'reps', 1 second increments
        'weight' => 5,
        'sets' => 1,
    ],
    'field_mins' => [
        'time' => 1,   // CHANGED from 'reps'
        'weight' => 0,
        'sets' => 1,
    ],
    'field_maxes' => [
        'time' => 300,  // CHANGED from 'reps' (5 minutes)
        'weight' => 500,
        'sets' => 20,
    ],
],
```

**What the user sees (unchanged):**
```
Hold Duration (seconds): [30]
Added Weight (lbs): [0]
```

**What changed (backend only):**
- Field name: `reps` → `time`
- Database storage: `reps` column → `time` column
- `reps` is now always set to 1 automatically

## User Experience

### Static Hold Form (Before and After)

**The form looks IDENTICAL to users. Only the backend field name changes.**

**Current form (before change):**
```
┌─────────────────────────────────────┐
│ Hold Duration (seconds): [30]       │
│ Added Weight (lbs):      [0]        │
│                                     │
│ [Add Set]                           │
└─────────────────────────────────────┘
```

**New form (after change):**
```
┌─────────────────────────────────────┐
│ Hold Duration (seconds): [30]       │
│ Added Weight (lbs):      [0]        │
│                                     │
│ [Add Set]                           │
└─────────────────────────────────────┘
```

**What changed:**
- Backend: Field name `reps` → `time`
- Backend: Database column `reps` → `time`
- Backend: `reps` is now always set to 1 automatically
- **Frontend: NOTHING** - same fields, same labels, same behavior

### Why This Matters

Users will not notice any difference. The change is purely internal:
- Better data model (time stored in time field)
- Clearer code semantics
- Foundation for future time-based features

But the user interface remains exactly the same.

### 1. Semantic Clarity

**Before:**
```php
// Confusing: reps doesn't mean repetitions
$liftSet->reps = 30;  // Actually 30 seconds, not 30 reps
```

**After:**
```php
// Clear: each field has obvious meaning
$liftSet->reps = 1;    // 1 hold performed (set automatically, not in form)
$liftSet->time = 30;   // 30 seconds duration (user enters this)
```

**User form (unchanged):**
```
Hold Duration (seconds): [30]
Added Weight (lbs): [0]
```

The user experience is **identical** - they still see the same two fields with the same labels. The only difference is which database column stores the duration.

### 2. Flexibility for Future Features

**Enables new tracking modes:**
- Timed cardio (sprint times, race times)
- Rest periods between sets
- Tempo training (time under tension)
- Timed circuits
- EMOM (Every Minute On the Minute) workouts

**Example - Tempo Training:**
```php
// Bench Press with 3-1-2 tempo (3s down, 1s pause, 2s up)
$liftSet->weight = 185;
$liftSet->reps = 5;
$liftSet->time = 30;  // Total time under tension: 5 reps × 6s = 30s
```

### 3. Better Data Analysis

**Can query time-based metrics:**
```sql
-- Average hold duration for L-sits
SELECT AVG(time) FROM lift_sets 
WHERE lift_log_id IN (
    SELECT id FROM lift_logs WHERE exercise_id = ?
);

-- Total time under tension for all exercises
SELECT SUM(time) FROM lift_sets 
WHERE lift_log_id IN (
    SELECT id FROM lift_logs WHERE user_id = ?
);

-- Progression of hold duration over time
SELECT logged_at, MAX(time) as best_hold
FROM lift_sets
JOIN lift_logs ON lift_sets.lift_log_id = lift_logs.id
WHERE exercise_id = ?
GROUP BY logged_at
ORDER BY logged_at;
```

### 4. Cleaner Code

**Before (overloaded semantics):**
```php
// What does reps mean here? Depends on exercise type!
if ($exerciseType === 'static_hold') {
    $duration = $liftSet->reps;  // reps = seconds
} else {
    $repetitions = $liftSet->reps;  // reps = repetitions
}
```

**After (clear semantics):**
```php
// Always clear what each field means
$repetitions = $liftSet->reps;   // Always repetitions
$duration = $liftSet->time;      // Always time in seconds
```

## Testing Strategy

### Unit Tests

**Test time field validation:**
```php
public function test_static_hold_requires_time_field()
{
    $strategy = new StaticHoldExerciseType();
    
    $this->expectException(InvalidExerciseDataException::class);
    
    $strategy->processLiftData([
        'weight' => 0,
        // Missing 'time' field
    ]);
}

public function test_static_hold_validates_time_range()
{
    $strategy = new StaticHoldExerciseType();
    
    // Too short
    $this->expectException(InvalidExerciseDataException::class);
    $strategy->processLiftData(['time' => 0]);
    
    // Too long
    $this->expectException(InvalidExerciseDataException::class);
    $strategy->processLiftData(['time' => 400]);
    
    // Valid
    $result = $strategy->processLiftData(['time' => 30]);
    $this->assertEquals(30, $result['time']);
}
```

**Test data migration:**
```php
public function test_static_hold_data_migration()
{
    // Create old-style static hold (time in reps field)
    $exercise = Exercise::factory()->create(['exercise_type' => 'static_hold']);
    $liftLog = LiftLog::factory()->create(['exercise_id' => $exercise->id]);
    $liftSet = LiftSet::factory()->create([
        'lift_log_id' => $liftLog->id,
        'reps' => 45,  // Old style: duration in reps
        'time' => null,
    ]);
    
    // Run migration
    Artisan::call('migrate', ['--step' => 1]);
    
    // Verify data was migrated
    $liftSet->refresh();
    $this->assertEquals(1, $liftSet->reps);   // Now 1 (one hold)
    $this->assertEquals(45, $liftSet->time);  // Duration moved to time field
}
```

### Integration Tests

**Test static hold logging with new field:**
```php
public function test_can_log_static_hold_with_time_field()
{
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create([
        'exercise_type' => 'static_hold',
        'user_id' => $user->id,
    ]);
    
    $response = $this->actingAs($user)->post(route('lift-logs.store'), [
        'exercise_id' => $exercise->id,
        'logged_at' => now(),
        'sets' => [
            ['time' => 30, 'weight' => 0],
            ['time' => 25, 'weight' => 0],
            ['time' => 20, 'weight' => 0],
        ],
    ]);
    
    $response->assertRedirect();
    
    $liftLog = LiftLog::latest()->first();
    $this->assertCount(3, $liftLog->liftSets);
    $this->assertEquals(30, $liftLog->liftSets[0]->time);
    $this->assertEquals(1, $liftLog->liftSets[0]->reps);
}
```

**Test PR detection with time field:**
```php
public function test_static_hold_time_pr_detection()
{
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create([
        'exercise_type' => 'static_hold',
        'user_id' => $user->id,
    ]);
    
    // First hold: 30 seconds
    $this->createStaticHoldLog($user, $exercise, 30);
    
    // Second hold: 35 seconds (PR!)
    $liftLog = $this->createStaticHoldLog($user, $exercise, 35);
    
    $this->assertTrue($liftLog->is_pr);
    $this->assertEquals(1, $liftLog->pr_count);
    
    $pr = PersonalRecord::where('lift_log_id', $liftLog->id)->first();
    $this->assertEquals('time', $pr->pr_type);
    $this->assertEquals(35, $pr->value);
    $this->assertEquals(30, $pr->previous_value);
}
```

## Rollback Plan

### If Issues Arise

**Phase 1 rollback (drop column):**
```bash
php artisan migrate:rollback --step=1
```
- Drops the time column
- No data loss (column was empty)
- System returns to previous state

**Phase 2 rollback (restore reps):**
```bash
php artisan migrate:rollback --step=1
```
- Copies time back to reps
- Clears time field
- Static holds work as before

**Phase 3+ rollback:**
- Revert code changes via git
- Run Phase 2 rollback migration
- System returns to using reps for time

## Implementation Checklist

### Database
- [ ] Create migration to add `time` column to `lift_sets`
- [ ] Create migration to copy static hold data from `reps` to `time`
- [ ] Update `LiftSet` model fillable and casts
- [ ] Test migrations on staging database

### Code Updates
- [ ] Update `StaticHoldExerciseType::processLiftData()` to use time field
- [ ] Update `StaticHoldExerciseType::formatWeightDisplay()` to read from time
- [ ] Update `StaticHoldExerciseType::calculateCurrentMetrics()` to use time
- [ ] Update `StaticHoldExerciseType::compareToPrevious()` to use time
- [ ] Update `StaticHoldExerciseType::getFormFieldDefinitions()` to show time field
- [ ] Update configuration in `config/exercise_types.php`

### Testing
- [ ] Write unit tests for time field validation
- [ ] Write unit tests for data migration
- [ ] Write integration tests for static hold logging
- [ ] Write integration tests for PR detection
- [ ] Test backward compatibility
- [ ] Test rollback procedures

### Documentation
- [ ] Update API documentation
- [ ] Update developer documentation
- [ ] Add migration notes to CHANGELOG
- [ ] Document new field in database schema docs

### Deployment
- [ ] Deploy migration to staging
- [ ] Verify static hold data migration
- [ ] Test static hold logging on staging
- [ ] Deploy to production during low-traffic window
- [ ] Monitor for errors
- [ ] Verify production data migration

## Timeline Estimate

- **Phase 1** (Add column): 1 hour
- **Phase 2** (Migrate data): 2 hours
- **Phase 3** (Update code): 4 hours
- **Phase 4** (Update model): 1 hour
- **Phase 5** (Update config): 1 hour
- **Testing**: 4 hours
- **Documentation**: 2 hours
- **Total**: ~15 hours (2 days)

## Conclusion

Adding a dedicated `time` field to `lift_sets` is a foundational improvement that:

1. **Fixes semantic issues** with current static hold implementation
2. **Enables time-based cardio** tracking (sprint times, race times)
3. **Future-proofs** the system for additional time-based features
4. **Improves code clarity** and maintainability
5. **Enhances data analysis** capabilities

This is a prerequisite for the time-based cardio feature and should be implemented first.

**Risk Level**: Low
- Non-breaking migration strategy
- Comprehensive rollback plan
- Backward compatible approach
- Well-tested implementation

**Value**: High
- Unlocks multiple new features
- Improves data model quality
- Better user experience
- Cleaner codebase
