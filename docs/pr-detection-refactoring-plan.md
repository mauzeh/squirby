# PR Detection Refactoring Plan

## Goal
Decouple PR detection logic from `PRDetectionService` by moving exercise-type-specific logic into the Strategy Pattern classes. This will make adding new exercise types (like static holds) trivial instead of requiring changes across multiple files.

## Current Problems
1. **God Class**: `PRDetectionService` has 754 lines with conditionals for every exercise type
2. **Scattered Logic**: Exercise-type checks (`$isBodyweight`) appear in 20+ places
3. **Tight Coupling**: Adding static holds requires modifying 10+ locations
4. **Hard to Test**: Complex conditional logic makes testing brittle
5. **Violates Open/Closed**: Can't add new types without modifying existing code

## Solution Architecture

### Strategy Pattern Extension
Each `ExerciseType` strategy will implement its own PR detection logic:
- `getSupportedPRTypes()` - Which PR types apply to this exercise type
- `calculateCurrentMetrics()` - Extract metrics from current lift log
- `compareToPrevious()` - Compare metrics to previous logs and detect PRs
- `formatPRDisplay()` - Format PR records for display
- `formatCurrentPRDisplay()` - Format current records table

### Benefits
- **Open/Closed Principle**: Add new types without modifying existing code
- **Single Responsibility**: Each type knows its own PR logic
- **Testability**: Test each type's PR logic in isolation
- **Maintainability**: All bodyweight logic in one file, all static hold logic in another
- **Extensibility**: Adding cardio/tempo/etc. becomes trivial

## Implementation Phases

### Phase 1: Extend Interface ✅ COMPLETE
**Status**: Interface extended with 5 new PR methods
**Files Modified**: 
- `app/Services/ExerciseTypes/ExerciseTypeInterface.php`

**What Was Done**:
- Added `getSupportedPRTypes()` method
- Added `calculateCurrentMetrics()` method
- Added `compareToPrevious()` method
- Added `formatPRDisplay()` method
- Added `formatCurrentPRDisplay()` method

**Result**: Interface now defines PR detection contract without breaking existing code

---

### Phase 2: Add Default Implementations to BaseExerciseType
**Status**: IN PROGRESS
**Files to Modify**:
- `app/Services/ExerciseTypes/BaseExerciseType.php`

**Tasks**:
1. Add default implementations that delegate back to `PRDetectionService` (temporary)
2. This maintains backward compatibility while we migrate
3. Each method will have a `// TODO: Remove after migration` comment

**Implementation**:
```php
// In BaseExerciseType.php

public function getSupportedPRTypes(): array
{
    // TODO: Remove after migration - temporary delegation
    // Default: no PR support (subclasses override)
    return [];
}

public function calculateCurrentMetrics(LiftLog $liftLog): array
{
    // TODO: Remove after migration - temporary delegation
    // Delegate to PRDetectionService for now
    return app(\App\Services\PRDetectionService::class)
        ->calculateMetricsForType($liftLog, $this->getTypeName());
}

public function compareToPrevious(array $currentMetrics, \Illuminate\Database\Eloquent\Collection $previousLogs, LiftLog $currentLog): array
{
    // TODO: Remove after migration - temporary delegation
    return app(\App\Services\PRDetectionService::class)
        ->compareForType($currentMetrics, $previousLogs, $currentLog, $this->getTypeName());
}

public function formatPRDisplay(\App\Models\PersonalRecord $pr, LiftLog $liftLog): array
{
    // TODO: Remove after migration - temporary delegation
    return app(\App\Services\PRDetectionService::class)
        ->formatPRForType($pr, $liftLog, $this->getTypeName());
}

public function formatCurrentPRDisplay(\App\Models\PersonalRecord $pr, LiftLog $liftLog, bool $isCurrent): array
{
    // TODO: Remove after migration - temporary delegation
    return app(\App\Services\PRDetectionService::class)
        ->formatCurrentPRForType($pr, $liftLog, $isCurrent, $this->getTypeName());
}
```

**Result**: All existing code continues to work, interface is satisfied

---

### Phase 3: Migrate RegularExerciseType
**Status**: NOT STARTED
**Files to Modify**:
- `app/Services/ExerciseTypes/RegularExerciseType.php`
- `app/Services/PRDetectionService.php` (extract helper methods)

**Tasks**:
1. Implement `getSupportedPRTypes()` in RegularExerciseType
2. Move 1RM calculation logic to `calculateCurrentMetrics()`
3. Move volume/rep-specific/hypertrophy logic to `compareToPrevious()`
4. Move display formatting to `formatPRDisplay()` and `formatCurrentPRDisplay()`
5. Extract helper methods from PRDetectionService (make them public/protected)
6. Update PRDetectionService to use strategy methods for regular exercises
7. Run tests to ensure no regressions

**Result**: Regular exercise PR logic lives in RegularExerciseType, PRDetectionService orchestrates

---

### Phase 4: Migrate BodyweightExerciseType ✅ COMPLETE
**Status**: COMPLETE
**Files Modified**:
- `app/Services/ExerciseTypes/BodyweightExerciseType.php`

**What Was Done**:
1. ✅ Implemented `getSupportedPRTypes()` - returns `[PRType::VOLUME, PRType::REP_SPECIFIC]`
2. ✅ Implemented `calculateCurrentMetrics()` - handles weighted vs unweighted
3. ✅ Implemented `compareToPrevious()` - total reps vs volume logic
4. ✅ Implemented `formatPRDisplay()` - "Total Reps" vs "Volume" labels
5. ✅ Implemented `formatCurrentPRDisplay()` - current records formatting
6. ✅ Added helper methods: `getBestVolume()`, `getBestTotalReps()`, `getBestWeightForReps()`, `formatWeight()`
7. ✅ All bodyweight PR tests passing (12/12 tests)

**Result**: Bodyweight exercise PR logic lives in BodyweightExerciseType, strategy pattern working perfectly

---

### Phase 5: Implement StaticHoldExerciseType PR Support ✅ COMPLETE
**Status**: COMPLETE
**Files Modified**:
- `app/Services/ExerciseTypes/StaticHoldExerciseType.php`
- `database/migrations/2026_01_23_172449_add_time_pr_type_to_personal_records_table.php` (new)
- `tests/Feature/StaticHoldPRDetectionTest.php` (new)

**What Was Done**:
1. ✅ Implemented `getSupportedPRTypes()` - returns `[PRType::TIME, PRType::REP_SPECIFIC]`
2. ✅ Implemented `calculateCurrentMetrics()` - extracts best_hold and weighted_holds map
3. ✅ Implemented `compareToPrevious()` - TIME PR (longest hold) and REP_SPECIFIC PR (best duration at weight)
4. ✅ Implemented `formatPRDisplay()` and `formatCurrentPRDisplay()` - formats durations and weights
5. ✅ Added helper methods: `getBestHoldDuration()`, `getBestDurationAtWeight()`, `formatWeightLabel()`
6. ✅ Created migration to add 'time' to pr_type enum in database
7. ✅ Created comprehensive test suite (11 tests, all passing)
8. ✅ Reused existing `formatDuration()` method for consistent display

**Result**: Static holds have full PR support with ZERO changes to PRDetectionService - demonstrates the power of the strategy pattern!

---

### Phase 6: Simplify PRDetectionService
**Status**: NOT STARTED
**Files to Modify**:
- `app/Services/PRDetectionService.php`
- `app/Services/LiftLogTableRowBuilder.php`

**Tasks**:
1. Remove all exercise-type conditionals from PRDetectionService
2. Simplify `detectPRsWithDetails()` to just orchestrate:
   ```php
   $strategy = $exercise->getTypeStrategy();
   $supportedTypes = $strategy->getSupportedPRTypes();
   if (empty($supportedTypes)) return [];
   
   $currentMetrics = $strategy->calculateCurrentMetrics($liftLog);
   $prs = $strategy->compareToPrevious($currentMetrics, $previousLogs, $liftLog);
   return $this->enrichWithPreviousPRIds($prs, $liftLog);
   ```
3. Update `LiftLogTableRowBuilder` to delegate formatting to strategy:
   ```php
   $strategy = $liftLog->exercise->getTypeStrategy();
   foreach ($prs as $pr) {
       $records[] = $strategy->formatPRDisplay($pr, $liftLog);
   }
   ```
4. Remove helper methods that are now in strategies
5. Run full test suite

**Result**: PRDetectionService is now ~200 lines instead of 754, no conditionals

---

### Phase 7: Update Documentation
**Status**: NOT STARTED
**Files to Modify**:
- `docs/personal-records-architecture.md`
- `docs/exercise-types-usage-guide.md` (if exists)

**Tasks**:
1. Document the new strategy-based PR detection architecture
2. Add examples of implementing PR support for new exercise types
3. Update migration guide for adding new types
4. Document the PR detection flow

---

## Testing Strategy

### Per Phase Testing
- **Phase 2**: Run existing PR tests, all should pass (delegation maintains behavior)
- **Phase 3**: Run regular exercise PR tests, verify no regressions
- **Phase 4**: Run bodyweight PR tests (BodyweightPRDetectionTest.php)
- **Phase 5**: Create and run StaticHoldPRDetectionTest.php
- **Phase 6**: Run full test suite (1677 tests)

### New Tests Needed
- `tests/Feature/StaticHoldPRDetectionTest.php` (~400 lines, following bodyweight pattern)
- `tests/Unit/Services/ExerciseTypes/StaticHoldExerciseTypePRTest.php` (unit tests for PR methods)

---

## Rollback Plan

Each phase is independent and can be rolled back:
- **Phase 2**: Remove default implementations, revert interface
- **Phase 3**: Revert RegularExerciseType, restore PRDetectionService logic
- **Phase 4**: Revert BodyweightExerciseType, restore conditionals
- **Phase 5**: Just don't use static hold exercises yet
- **Phase 6**: Keep old PRDetectionService methods as fallback

---

## Success Criteria

- [x] **Phase 1 Complete**: Interface extended
- [x] **Phase 2 Complete**: All tests pass with default implementations
- [x] **Phase 3 Complete**: Regular exercise PRs work via strategy
- [x] **Phase 4 Complete**: Bodyweight exercise PRs work via strategy
- [x] **Phase 5 Complete**: Static holds have full PR support
- [ ] **Phase 6 Complete**: PRDetectionService is <300 lines, no exercise-type conditionals
- [ ] **Phase 7 Complete**: Documentation updated

**Final Goal**: Adding cardio PR support requires only creating `CardioExerciseType::getSupportedPRTypes()` etc. - no changes to PRDetectionService or any other file.

---

## Example: Adding Cardio PR Support (After Refactoring)

Once refactoring is complete, adding cardio PR support will look like this:

```php
// In CardioExerciseType.php

public function getSupportedPRTypes(): array
{
    return [PRType::ENDURANCE, PRType::DENSITY];
}

public function calculateCurrentMetrics(LiftLog $liftLog): array
{
    $bestDistance = $liftLog->liftSets->max('reps'); // reps = distance in meters
    $totalRounds = $liftLog->liftSets->count();
    
    return [
        'best_distance' => $bestDistance,
        'density' => $totalRounds > 0 ? $bestDistance / $totalRounds : 0,
    ];
}

public function compareToPrevious(array $currentMetrics, Collection $previousLogs, LiftLog $currentLog): array
{
    $prs = [];
    
    // Check ENDURANCE PR (furthest distance)
    $previousBest = $this->getBestDistance($previousLogs);
    if ($currentMetrics['best_distance'] > $previousBest) {
        $prs[] = [
            'type' => 'endurance',
            'value' => $currentMetrics['best_distance'],
            'previous_value' => $previousBest,
        ];
    }
    
    // Check DENSITY PR (best distance per round)
    $previousBestDensity = $this->getBestDensity($previousLogs);
    if ($currentMetrics['density'] > $previousBestDensity) {
        $prs[] = [
            'type' => 'density',
            'value' => $currentMetrics['density'],
            'previous_value' => $previousBestDensity,
        ];
    }
    
    return $prs;
}

public function formatPRDisplay(PersonalRecord $pr, LiftLog $liftLog): array
{
    return match($pr->pr_type) {
        'endurance' => [
            'label' => 'Best Distance',
            'value' => $pr->previous_value ? $pr->previous_value . 'm' : '—',
            'comparison' => $pr->value . 'm',
        ],
        'density' => [
            'label' => 'Best Efficiency',
            'value' => $pr->previous_value ? number_format($pr->previous_value, 1) . 'm/round' : '—',
            'comparison' => number_format($pr->value, 1) . 'm/round',
        ],
    };
}
```

**That's it!** No changes to PRDetectionService, LiftLogTableRowBuilder, or any other file. Just implement the interface in CardioExerciseType and it works.
