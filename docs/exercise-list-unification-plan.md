# Exercise List Services Unification Analysis

## Current State (Post-Alignment)

All three services now return **raw arrays** with identical structure. Controllers wrap them with ComponentBuilder when needed.

### Services Overview

| Service | Method | Context | Lines of Code |
|---------|--------|---------|---------------|
| **ExerciseSelectionService** | `generateItemSelectionList()` | mobile-entry/lifts | ~240 |
| **ExerciseListService** | `generateMetricsExerciseList()` | lift-logs/index | ~80 |
| **WorkoutExerciseListService** | `generateExerciseSelectionList()` | workouts/edit | ~110 |
| **WorkoutExerciseListService** | `generateExerciseSelectionListForNew()` | workouts/create | ~110 |

**Total duplicated logic: ~540 lines**

---

## Core Logic Comparison

### Identical Logic (Can be unified immediately)

1. **Exercise Fetching**
   ```php
   Exercise::availableToUser($userId)
       ->with(['aliases' => ...])
       ->orderBy('title', 'asc')
       ->get()
   ```

2. **Alias Application**
   ```php
   $this->aliasService->applyAliasesToExercises($exercises, $user)
   ```

3. **Recent Exercise Detection** (all use 28 days now)
   ```php
   LiftLog::where('user_id', $userId)
       ->where('logged_at', '>=', $date->subDays(28))
       ->pluck('exercise_id')
   ```

4. **Last Performed Dates**
   ```php
   LiftLog::where('user_id', $userId)
       ->select('exercise_id', DB::raw('MAX(logged_at) as last_logged_at'))
       ->groupBy('exercise_id')
       ->pluck('last_logged_at', 'exercise_id')
   ```

5. **Time Label Generation**
   ```php
   $lastPerformed->diffForHumans(['short' => true])
   ```

6. **Sorting Logic**
   - Recent exercises: alphabetically
   - Older exercises: by recency (most recent first)

7. **Item Structure**
   ```php
   [
       'id' => 'exercise-' . $exercise->id,
       'name' => $exercise->title,
       'href' => $url,
       'type' => [
           'label' => $timeLabel,
           'cssClass' => 'recent' | 'exercise-history',
           'priority' => 1 | 2,
       ]
   ]
   ```

---

## Key Differences (Need configuration)

### 1. **Exercise Filtering**
- **mobile-entry/lifts**: All accessible exercises
- **lift-logs/index**: Only logged exercises
- **workouts**: All accessible exercises

**Solution**: Add `filter_mode` config option

### 2. **New User Detection & Popular Exercises**
- **mobile-entry/lifts**: Shows "Popular" exercises for users with < 5 logs
- **Others**: No new user detection

**Solution**: Add `show_popular` config option

### 3. **Date Parameter**
- **mobile-entry/lifts**: Accepts `$selectedDate` parameter
- **Others**: Always use current date

**Solution**: Add optional `date` parameter

### 4. **URL Generation**
- **mobile-entry/lifts**: `route('lift-logs.create', [...])` with date handling
- **lift-logs/index**: `route('exercises.show-logs', [...])`
- **workouts**: `route('simple-workouts.add-exercise', [...])`

**Solution**: Pass URL generator callback

### 5. **Create Form**
- **mobile-entry/lifts**: Has create form with date handling
- **lift-logs/index**: No create form (null)
- **workouts**: Has create form

**Solution**: Pass create form config or null

### 6. **UI Configuration**
- **initialState**: 'expanded' vs 'collapsed'
- **showCancelButton**: true vs false
- **restrictHeight**: true vs false

**Solution**: Pass as config options

---

## Unification Options

### **Option 1: Single Unified Service (Recommended)**

Create one service that handles all contexts through configuration.

**Pros:**
- Single source of truth (~150 lines vs ~540 lines)
- Easier to maintain and test
- Clear configuration-based approach
- Eliminates all duplication

**Cons:**
- Requires updating all callers
- More complex configuration object
- Need comprehensive testing

**Implementation:**
```php
class UnifiedExerciseListService
{
    public function generate(int $userId, array $config = []): array
    {
        $config = $this->mergeDefaults($config);
        
        // 1. Get exercises (filtered or all)
        $exercises = $this->getExercises($userId, $config);
        
        // 2. Apply aliases
        $exercises = $this->applyAliases($exercises, $userId);
        
        // 3. Get recency data
        $recentIds = $this->getRecentExerciseIds($userId, $config);
        $lastPerformed = $this->getLastPerformedDates($userId, $exercises, $config);
        
        // 4. Handle new user popular exercises (if enabled)
        $popularMap = $config['show_popular'] && $this->isNewUser($userId)
            ? $this->getPopularExercises($exercises)
            : [];
        
        // 5. Build items
        $items = $this->buildItems($exercises, $recentIds, $lastPerformed, $popularMap, $config);
        
        // 6. Sort items
        $items = $this->sortItems($items);
        
        // 7. Return raw array
        return $this->formatOutput($items, $config);
    }
}
```

**Configuration:**
```php
// mobile-entry/lifts
$config = [
    'context' => 'mobile-entry',
    'date' => $selectedDate,
    'filter_exercises' => 'all',
    'show_popular' => true,
    'url_generator' => fn($ex, $cfg) => route('lift-logs.create', [...]),
    'create_form' => [...],
    'initial_state' => 'collapsed',
    'show_cancel_button' => true,
];

// lift-logs/index
$config = [
    'context' => 'metrics',
    'filter_exercises' => 'logged-only',
    'show_popular' => false,
    'url_generator' => fn($ex) => route('exercises.show-logs', [...]),
    'create_form' => null,
    'initial_state' => 'expanded',
    'show_cancel_button' => false,
];

// workouts
$config = [
    'context' => 'workout-builder',
    'filter_exercises' => 'all',
    'show_popular' => false,
    'url_generator' => fn($ex, $cfg) => route('simple-workouts.add-exercise', [...]),
    'create_form' => [...],
    'initial_state' => 'collapsed',
    'show_cancel_button' => true,
];
```

---

### **Option 2: Base Service + Context-Specific Wrappers**

Create a base service with shared logic, keep thin wrappers for each context.

**Pros:**
- Gradual migration path
- Existing callers don't change
- Lower risk
- Clear separation of concerns

**Cons:**
- Still maintains three services
- Some duplication remains
- More files to manage

**Implementation:**
```php
class BaseExerciseListService
{
    protected function getExercises(...) { }
    protected function getRecentExerciseIds(...) { }
    protected function buildItems(...) { }
    // ... all shared logic
}

class ExerciseSelectionService extends BaseExerciseListService
{
    public function generateItemSelectionList($userId, Carbon $date)
    {
        $config = $this->buildMobileEntryConfig($date);
        return $this->generate($userId, $config);
    }
}

class ExerciseListService extends BaseExerciseListService
{
    public function generateMetricsExerciseList($userId)
    {
        $config = $this->buildMetricsConfig();
        return $this->generate($userId, $config);
    }
}
```

---

### **Option 3: Trait-Based Composition**

Extract common logic into traits.

**Pros:**
- Minimal refactoring
- Keeps existing boundaries
- Easy to understand

**Cons:**
- Traits are harder to test
- Still maintains duplication
- Not a true single code path

**Not Recommended** - Doesn't achieve the goal of single code path.

---

## Recommendation: Option 1

**Why Option 1 is best:**

1. **Maximum Code Reduction**: ~540 lines → ~200 lines (63% reduction)
2. **Single Source of Truth**: All logic in one place
3. **Easier Testing**: Test once with different configs
4. **Future-Proof**: Easy to add new contexts
5. **Clean Architecture**: Configuration-driven design

**Migration Strategy:**

1. ✅ **Phase 1: Align output format** (DONE)
   - All services return raw arrays
   - Controllers wrap with ComponentBuilder

2. **Phase 2: Create unified service**
   - Build `UnifiedExerciseListService`
   - Comprehensive unit tests
   - Verify all contexts work

3. **Phase 3: Migrate callers one by one**
   - Start with lift-logs/index (simplest)
   - Then workouts (medium complexity)
   - Finally mobile-entry/lifts (most complex)
   - Run tests after each migration

4. **Phase 4: Deprecate old services**
   - Mark old services as deprecated
   - Remove after all callers migrated
   - Clean up unused code

---

## Estimated Effort

| Phase | Effort | Risk |
|-------|--------|------|
| Create unified service | 2-3 hours | Low |
| Write comprehensive tests | 1-2 hours | Low |
| Migrate lift-logs/index | 30 min | Low |
| Migrate workouts | 1 hour | Medium |
| Migrate mobile-entry | 1-2 hours | Medium |
| Remove old services | 30 min | Low |
| **Total** | **6-9 hours** | **Low-Medium** |

---

## Benefits Summary

### Code Quality
- ✅ 63% reduction in duplicated code
- ✅ Single source of truth
- ✅ Easier to maintain
- ✅ Easier to test

### Developer Experience
- ✅ Clear configuration-based API
- ✅ Consistent behavior across contexts
- ✅ Easy to add new contexts
- ✅ Self-documenting through config

### Performance
- ✅ No performance impact (same queries)
- ✅ Potential for future optimization in one place

### Risk Mitigation
- ✅ Gradual migration (one caller at a time)
- ✅ Comprehensive test coverage
- ✅ Easy rollback (keep old services until done)
- ✅ All tests passing before each step

---

## Next Steps

1. Review this analysis
2. Approve Option 1 approach
3. Begin Phase 2: Create unified service
4. Implement with comprehensive tests
5. Migrate callers one by one
