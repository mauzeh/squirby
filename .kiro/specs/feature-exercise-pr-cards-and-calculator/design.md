# Design Document

## Overview

This feature adds PR (Personal Record) cards and a 1RM percentage calculator grid to the exercise logs page, transforming it from a purely historical view into an actionable training tool. Users can quickly see their best lifts and reference recommended weights for their next workout.

## Architecture

### Core Components

1. **ExercisePRService**: Calculates and formats PR data and calculator grids
2. **ExerciseController**: Extended to provide PR data to the view
3. **ComponentBuilder**: New component types for PR cards and calculator grid
4. **Exercise Type Strategies**: Determine if exercise supports PR/calculator features

### Data Flow

```
User visits /exercises/{id}/logs → ExerciseController::showLogs() →
ExercisePRService::getPRData() → Calculate PRs for 1, 2, 3 reps →
ExercisePRService::getCalculatorGrid() → Generate percentage grid →
ComponentBuilder creates PR and calculator components →
Render above existing chart and log list
```

## Components and Interfaces

### ExercisePRService

**Purpose**: Encapsulates all PR calculation and calculator grid generation logic

**Key Methods**:

```php
/**
 * Get PR data for an exercise
 * 
 * @param Exercise $exercise
 * @param User $user
 * @return array|null Returns null if exercise doesn't support PRs
 */
public function getPRData(Exercise $exercise, User $user): ?array
{
    // Returns:
    // [
    //     'rep_1' => ['weight' => 242, 'lift_log_id' => 123, 'date' => '2024-01-15'],
    //     'rep_2' => ['weight' => 235, 'lift_log_id' => 124, 'date' => '2024-01-10'],
    //     'rep_3' => ['weight' => 235, 'lift_log_id' => 125, 'date' => '2024-01-08'],
    // ]
}

/**
 * Get calculator grid data based on PRs
 * 
 * @param array $prData PR data from getPRData()
 * @return array|null Returns null if no valid PR data
 */
public function getCalculatorGrid(array $prData): ?array
{
    // Returns:
    // [
    //     'columns' => [
    //         ['label' => '1x1', 'one_rep_max' => 242],
    //         ['label' => '1x2', 'one_rep_max' => 235],
    //         ['label' => '1x3', 'one_rep_max' => 235],
    //     ],
    //     'rows' => [
    //         ['percentage' => 100, 'weights' => [242, 235, 235]],
    //         ['percentage' => 95, 'weights' => [230, 223, 223]],
    //         // ... more rows
    //     ]
    // ]
}

/**
 * Check if exercise supports PR tracking
 * Only barbell exercises are supported
 * 
 * @param Exercise $exercise
 * @return bool
 */
public function supportsPRTracking(Exercise $exercise): bool
{
    // Only barbell exercises
    return $exercise->exercise_type === 'barbell';
}
```

**Implementation Details**:
- Calculate 1RM using Brzycki formula: 1RM = weight × (36 / (37 - reps))
- Query lift logs with eager loading to avoid N+1 queries
- Round calculated weights to nearest whole number
- Handle edge cases (no data, incomplete data)

### ComponentBuilder Extensions

**New Component Types**:

1. **PR Cards Component** (`pr-cards`)
```php
ComponentBuilder::prCards('Heaviest Lifts')
    ->card('1x1', 242, 'lbs')
    ->card('1x2', 235, 'lbs')
    ->card('1x3', 235, 'lbs')
    ->build();
```

2. **Calculator Grid Component** (`calculator-grid`)
```php
ComponentBuilder::calculatorGrid('1-Rep Max Percentages')
    ->columns([
        ['label' => '1x1', 'one_rep_max' => 242],
        ['label' => '1x2', 'one_rep_max' => 235],
        ['label' => '1x3', 'one_rep_max' => 235],
    ])
    ->percentages([100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45])
    ->build();
```

### Controller Extensions

**ExerciseController::showLogs() Updates**:

```php
public function showLogs(Request $request, Exercise $exercise)
{
    // ... existing code ...
    
    // Get PR data if exercise supports it
    $prService = app(ExercisePRService::class);
    $prData = null;
    $calculatorGrid = null;
    
    if ($prService->supportsPRTracking($exercise)) {
        $prData = $prService->getPRData($exercise, auth()->user());
        if ($prData) {
            $calculatorGrid = $prService->getCalculatorGrid($prData);
        }
    }
    
    // Build components
    $components = [];
    
    // Title
    $components[] = ComponentBuilder::title($displayName)->build();
    
    // Messages
    if ($sessionMessages = ComponentBuilder::messagesFromSession()) {
        $components[] = $sessionMessages;
    }
    
    // PR Cards (if available)
    if ($prData) {
        $components[] = $this->buildPRCardsComponent($prData);
    }
    
    // Calculator Grid (if available)
    if ($calculatorGrid) {
        $components[] = $this->buildCalculatorGridComponent($calculatorGrid);
    }
    
    // Chart (existing)
    // Table (existing)
    
    // ... rest of existing code ...
}
```

## Data Models

### No Model Changes Required

This feature uses existing models:
- `Exercise`: Provides exercise type information
- `LiftLog`: Contains workout history
- `LiftSet`: Contains individual set data (weight, reps)
- `User`: Current user context

### Query Optimization

**Efficient PR Calculation Query**:
```php
// Get all lift logs with sets for this exercise and user
$liftLogs = LiftLog::where('exercise_id', $exercise->id)
    ->where('user_id', $user->id)
    ->with('liftSets')
    ->get();

// Process in memory to find PRs for each rep range
foreach ([1, 2, 3] as $targetReps) {
    $bestForReps = null;
    $maxWeight = 0;
    
    foreach ($liftLogs as $log) {
        foreach ($log->liftSets as $set) {
            if ($set->reps == $targetReps && $set->weight > $maxWeight) {
                $maxWeight = $set->weight;
                $bestForReps = [
                    'weight' => $set->weight,
                    'lift_log_id' => $log->id,
                    'date' => $log->logged_at,
                ];
            }
        }
    }
    
    $prData["rep_{$targetReps}"] = $bestForReps;
}
```

## User Interface Design

### Component Layout

```
┌─────────────────────────────────────┐
│ Exercise Name (with alias)          │
├─────────────────────────────────────┤
│ Session Messages (if any)           │
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ Heaviest Lifts                  │ │
│ ├───────────┬───────────┬─────────┤ │
│ │   1x1     │   1x2     │   1x3   │ │
│ │   242     │   235     │   235   │ │
│ └───────────┴───────────┴─────────┘ │
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ 1-Rep Max Percentages           │ │
│ ├─────┬───────┬───────┬───────────┤ │
│ │     │  1x1  │  1x2  │   1x3     │ │
│ ├─────┼───────┼───────┼───────────┤ │
│ │100% │  243  │  230  │   218     │ │
│ │ 95% │  230  │  218  │   207     │ │
│ │ 90% │  218  │  207  │   196     │ │
│ │ ... │  ...  │  ...  │   ...     │ │
│ └─────┴───────┴───────┴───────────┘ │
├─────────────────────────────────────┤
│ Progress Chart (existing)           │
├─────────────────────────────────────┤
│ Lift Log Table (existing)           │
└─────────────────────────────────────┘
```

### CSS Styling

**PR Cards Component** (`resources/views/mobile-entry/components/pr-cards.blade.php`):
- Clean card layout with centered text
- Large, bold numbers for weights
- Subtle labels for rep ranges
- Responsive grid (3 columns on desktop, stack on mobile)

**Calculator Grid Component** (`resources/views/mobile-entry/components/calculator-grid.blade.php`):
- Table layout with fixed header
- Alternating row colors for readability
- Bold text for percentage labels
- Monospace font for weight values (alignment)
- Scrollable on mobile if needed

**CSS File** (`public/css/mobile-entry/components/pr-cards.css` and `calculator-grid.css`):
```css
/* PR Cards */
.pr-cards-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin: 1rem 0;
}

.pr-card {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}

.pr-card-label {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.pr-card-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary);
}

/* Calculator Grid */
.calculator-grid {
    overflow-x: auto;
    margin: 1rem 0;
}

.calculator-table {
    width: 100%;
    border-collapse: collapse;
}

.calculator-table th {
    background: var(--header-bg);
    padding: 0.75rem;
    text-align: center;
    font-weight: 600;
}

.calculator-table td {
    padding: 0.5rem;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
}

.calculator-table tr:nth-child(even) {
    background: var(--row-alt-bg);
}

.percentage-label {
    font-weight: 600;
    color: var(--text-muted);
}

.weight-value {
    font-family: 'Courier New', monospace;
    font-size: 1.125rem;
}
```

## Error Handling

### No Data Scenarios

**No PRs for any rep range**:
- Don't show PR cards component
- Don't show calculator grid component
- Show existing chart and log list as normal

**Partial PR data** (e.g., only 1x1 exists):
- Show PR cards with available data
- Show "—" for missing rep ranges
- Calculator grid shows only columns with data

**Exercise doesn't support PRs**:
- Don't show PR cards or calculator
- Page functions normally with chart and log list

### Calculation Errors

**Invalid data** (negative weights, zero reps):
- Skip invalid sets during PR calculation
- Log warning to application log
- Continue with valid data

**1RM calculation failure**:
- Handle division by zero (if reps >= 37 in Brzycki formula)
- Fall back to showing just the raw weight
- Log error for debugging

## Testing Strategy

### Unit Tests

**ExercisePRServiceTest**:
- `test_get_pr_data_returns_correct_prs_for_each_rep_range()`
- `test_get_pr_data_returns_null_for_unsupported_exercise_type()`
- `test_get_pr_data_handles_no_lift_logs()`
- `test_get_pr_data_handles_partial_data()`
- `test_get_calculator_grid_generates_correct_percentages()`
- `test_get_calculator_grid_rounds_weights_correctly()`
- `test_supports_pr_tracking_returns_true_for_weight_exercises()`
- `test_supports_pr_tracking_returns_false_for_cardio()`

**ComponentBuilderTest**:
- `test_pr_cards_component_builds_correctly()`
- `test_calculator_grid_component_builds_correctly()`

### Integration Tests

**ExerciseLogsPageTest**:
- `test_exercise_logs_page_shows_pr_cards_for_weight_exercise()`
- `test_exercise_logs_page_shows_calculator_grid()`
- `test_exercise_logs_page_hides_pr_features_for_cardio()`
- `test_pr_cards_show_correct_data_from_database()`
- `test_calculator_grid_calculations_are_accurate()`

### Manual Testing Checklist

- [ ] PR cards display correctly with real data
- [ ] Calculator grid shows accurate percentages
- [ ] Components don't appear for cardio exercises
- [ ] Page handles no data gracefully
- [ ] Mobile responsive design works
- [ ] Performance is acceptable with large datasets

## Performance Considerations

### Query Optimization
- Single query to fetch all lift logs with sets (eager loading)
- In-memory processing for PR calculation
- No N+1 query issues

### Data Volume
- Typical user has 50-200 lift logs per exercise
- Processing 200 logs with 3-5 sets each is negligible
- No pagination needed for PR calculation

## Security Considerations

### Authorization
- Existing authorization checks in ExerciseController::showLogs()
- Only show user's own lift logs
- No new security concerns introduced

### Data Validation
- Use existing validated data from database
- No user input in PR calculation
- No SQL injection risk (using Eloquent ORM)

## Migration Path

### Phase 1: Service and Logic
1. Create ExercisePRService
2. Add unit tests
3. Verify calculations are correct

### Phase 2: Components
1. Extend ComponentBuilder with new component types
2. Create Blade templates for PR cards and calculator grid
3. Add CSS styling

### Phase 3: Integration
1. Update ExerciseController::showLogs()
2. Add integration tests
3. Manual testing

### Phase 4: Polish
1. Responsive design refinements
2. Performance testing
3. User feedback incorporation

## Future Enhancements

### Potential Additions (Out of Scope for V1)
- More rep ranges (4, 5, 8, 10)
- Custom percentage selection
- Rep scheme filtering
- Historical PR comparison
- PR achievement notifications
- Export calculator to PDF/image
- Unit conversion (lbs/kg)
