# Implementation Plan

## Overview

This document outlines the step-by-step implementation of the PR cards and 1RM calculator grid feature for the exercise logs page.

## Implementation Phases

### Phase 1: Service Layer (ExercisePRService)

**Files to Create**:
- `app/Services/ExercisePRService.php`

**Implementation Steps**:

1. Create the service class with dependency injection
2. Implement `supportsPRTracking()` method
   - Return true only for barbell exercises
   - Check: `$exercise->exercise_type === 'barbell'`
   - Return false for all other types
3. Implement `getPRData()` method
   - Query lift logs for user and exercise
   - Eager load lift sets
   - Loop through rep ranges [1, 2, 3]
   - Find highest weight for each rep range
   - Return structured array with weight, lift_log_id, date
4. Implement `getCalculatorGrid()` method
   - Accept PR data array
   - Calculate 1RM for each rep range using Brzycki formula: 1RM = weight × (36 / (37 - reps))
   - Generate rows for percentages [100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45]
   - Calculate weight for each percentage: weight = 1RM × (percentage / 100)
   - Round to nearest whole number
   - Return structured array with columns and rows

**Code Structure**:
```php
<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\LiftLog;
use Illuminate\Support\Collection;

class ExercisePRService
{
    /**
     * Calculate 1RM using Brzycki formula
     * Formula: 1RM = weight × (36 / (37 - reps))
     */
    protected function calculate1RM(float $weight, int $reps): float
    {
        if ($reps === 1) {
            return $weight;
        }
        
        if ($reps >= 37) {
            // Brzycki formula breaks down at 37+ reps
            return $weight;
        }
        
        return $weight * (36 / (37 - $reps));
    }
    
    public function supportsPRTracking(Exercise $exercise): bool
    {
        // Only barbell exercises
        return $exercise->exercise_type === 'barbell';
    }
    
    public function getPRData(Exercise $exercise, User $user): ?array
    {
        if (!$this->supportsPRTracking($exercise)) {
            return null;
        }
        
        // Implementation here
    }
    
    public function getCalculatorGrid(array $prData): ?array
    {
        // Implementation here
    }
}
```

### Phase 2: Unit Tests for Service

**Files to Create**:
- `tests/Unit/ExercisePRServiceTest.php`

**Test Cases**:
1. `test_supports_pr_tracking_returns_true_for_barbell_exercise()`
2. `test_supports_pr_tracking_returns_false_for_dumbbell_exercise()`
3. `test_supports_pr_tracking_returns_false_for_cardio_exercise()`
3. `test_get_pr_data_returns_null_for_unsupported_exercise()`
4. `test_get_pr_data_returns_correct_prs_for_each_rep_range()`
5. `test_get_pr_data_handles_no_lift_logs()`
6. `test_get_pr_data_handles_partial_data_missing_rep_ranges()`
7. `test_get_pr_data_selects_highest_weight_when_multiple_logs_exist()`
8. `test_get_calculator_grid_generates_correct_percentages()`
9. `test_get_calculator_grid_rounds_weights_correctly()`
10. `test_get_calculator_grid_handles_three_columns()`
11. `test_get_calculator_grid_returns_null_when_no_pr_data()`

**Test Data Setup**:
- Create test user
- Create test exercises (barbell, dumbbell, cardio)
- Create lift logs with various rep ranges and weights
- Use factories for consistent test data

### Phase 3: ComponentBuilder Extensions

**Files to Modify**:
- `app/Services/ComponentBuilder.php`

**New Methods to Add**:

```php
/**
 * Create a PR cards component
 */
public static function prCards(string $title): self
{
    $instance = new self();
    $instance->componentType = 'pr-cards';
    $instance->data['title'] = $title;
    $instance->data['cards'] = [];
    return $instance;
}

/**
 * Add a card to PR cards component
 */
public function card(string $label, ?float $value, string $unit = 'lbs'): self
{
    $this->data['cards'][] = [
        'label' => $label,
        'value' => $value,
        'unit' => $unit,
    ];
    return $this;
}

/**
 * Create a calculator grid component
 */
public static function calculatorGrid(string $title): self
{
    $instance = new self();
    $instance->componentType = 'calculator-grid';
    $instance->data['title'] = $title;
    return $instance;
}

/**
 * Set columns for calculator grid
 */
public function columns(array $columns): self
{
    $this->data['columns'] = $columns;
    return $this;
}

/**
 * Set percentages for calculator grid
 */
public function percentages(array $percentages): self
{
    $this->data['percentages'] = $percentages;
    return $this;
}
```

### Phase 4: Blade Templates

**Files to Create**:
- `resources/views/mobile-entry/components/pr-cards.blade.php`
- `resources/views/mobile-entry/components/calculator-grid.blade.php`

**PR Cards Template**:
```blade
<div class="component pr-cards-component">
    @if(isset($data['title']))
        <h3 class="pr-cards-title">{{ $data['title'] }}</h3>
    @endif
    
    <div class="pr-cards-container">
        @foreach($data['cards'] as $card)
            <div class="pr-card">
                <div class="pr-card-label">{{ $card['label'] }}</div>
                <div class="pr-card-value">
                    @if($card['value'] !== null)
                        {{ number_format($card['value'], 0) }}
                    @else
                        —
                    @endif
                </div>
                @if($card['value'] !== null && isset($card['unit']))
                    <div class="pr-card-unit">{{ $card['unit'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
</div>
```

**Calculator Grid Template**:
```blade
<div class="component calculator-grid-component">
    @if(isset($data['title']))
        <h3 class="calculator-grid-title">{{ $data['title'] }}</h3>
    @endif
    
    <div class="calculator-grid">
        <table class="calculator-table">
            <thead>
                <tr>
                    <th></th>
                    @foreach($data['columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data['rows'] as $row)
                    <tr>
                        <td class="percentage-label">{{ $row['percentage'] }}%</td>
                        @foreach($row['weights'] as $weight)
                            <td class="weight-value">
                                @if($weight !== null)
                                    {{ number_format($weight, 0) }}
                                @else
                                    —
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

### Phase 5: CSS Styling

**Files to Create**:
- `public/css/mobile-entry/components/pr-cards.css`
- `public/css/mobile-entry/components/calculator-grid.css`

**PR Cards CSS**:
```css
.pr-cards-component {
    margin: 1.5rem 0;
}

.pr-cards-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--text-primary, #1a1a1a);
}

.pr-cards-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.pr-card {
    background: var(--card-bg, #f8f9fa);
    border: 1px solid var(--border-color, #e0e0e0);
    border-radius: 8px;
    padding: 1.25rem 1rem;
    text-align: center;
}

.pr-card-label {
    font-size: 0.875rem;
    color: var(--text-muted, #6c757d);
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.pr-card-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary, #1a1a1a);
    line-height: 1.2;
}

.pr-card-unit {
    font-size: 0.875rem;
    color: var(--text-muted, #6c757d);
    margin-top: 0.25rem;
}

/* Mobile responsive */
@media (max-width: 640px) {
    .pr-cards-container {
        gap: 0.75rem;
    }
    
    .pr-card {
        padding: 1rem 0.75rem;
    }
    
    .pr-card-value {
        font-size: 1.5rem;
    }
}
```

**Calculator Grid CSS**:
```css
.calculator-grid-component {
    margin: 1.5rem 0;
}

.calculator-grid-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--text-primary, #1a1a1a);
}

.calculator-grid {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
    border: 1px solid var(--border-color, #e0e0e0);
}

.calculator-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.calculator-table th {
    background: var(--header-bg, #f1f3f5);
    padding: 0.75rem;
    text-align: center;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-primary, #1a1a1a);
    border-bottom: 2px solid var(--border-color, #e0e0e0);
}

.calculator-table td {
    padding: 0.625rem 0.75rem;
    text-align: center;
    border-bottom: 1px solid var(--border-light, #f1f3f5);
}

.calculator-table tbody tr:hover {
    background: var(--row-hover-bg, #f8f9fa);
}

.calculator-table tbody tr:nth-child(even) {
    background: var(--row-alt-bg, #fafbfc);
}

.percentage-label {
    font-weight: 600;
    color: var(--text-muted, #6c757d);
    font-size: 0.875rem;
    text-align: left;
    padding-left: 1rem;
}

.weight-value {
    font-family: 'SF Mono', 'Monaco', 'Courier New', monospace;
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-primary, #1a1a1a);
}

/* Mobile responsive */
@media (max-width: 640px) {
    .calculator-table th,
    .calculator-table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .percentage-label {
        padding-left: 0.5rem;
    }
    
    .weight-value {
        font-size: 0.875rem;
    }
}
```

### Phase 6: Controller Integration

**Files to Modify**:
- `app/Http/Controllers/ExerciseController.php`

**Changes to `showLogs()` method**:

```php
public function showLogs(Request $request, Exercise $exercise)
{
    // ... existing authorization and data loading ...
    
    // Get PR data
    $prService = app(\App\Services\ExercisePRService::class);
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
    $components[] = \App\Services\ComponentBuilder::title($displayName)->build();
    
    // Messages from session
    if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
        $components[] = $sessionMessages;
    }
    
    // PR Cards (NEW)
    if ($prData) {
        $prCardsBuilder = \App\Services\ComponentBuilder::prCards('Heaviest Lifts');
        
        foreach ([1, 2, 3] as $reps) {
            $key = "rep_{$reps}";
            $value = $prData[$key]['weight'] ?? null;
            $prCardsBuilder->card("1x{$reps}", $value, 'lbs');
        }
        
        $components[] = $prCardsBuilder->build();
    }
    
    // Calculator Grid (NEW)
    if ($calculatorGrid) {
        $components[] = \App\Services\ComponentBuilder::calculatorGrid('1-Rep Max Percentages')
            ->columns($calculatorGrid['columns'])
            ->percentages([100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45])
            ->build();
    }
    
    // Chart (existing)
    if (!empty($chartData['datasets'])) {
        // ... existing chart code ...
    }
    
    // Table (existing)
    if ($liftLogs->isNotEmpty()) {
        // ... existing table code ...
    }
    
    // ... rest of existing code ...
}
```

### Phase 7: Update Flexible View

**Files to Modify**:
- `resources/views/mobile-entry/flexible.blade.php`

**Add CSS imports** in the `@section('styles')`:
```blade
<link rel="stylesheet" href="{{ asset('css/mobile-entry/components/pr-cards.css') }}">
<link rel="stylesheet" href="{{ asset('css/mobile-entry/components/calculator-grid.css') }}">
```

### Phase 8: Integration Tests

**Files to Create**:
- `tests/Feature/ExercisePRCardsIntegrationTest.php`

**Test Cases**:
1. `test_exercise_logs_page_shows_pr_cards_for_barbell_exercise()`
2. `test_exercise_logs_page_shows_calculator_grid()`
3. `test_exercise_logs_page_does_not_show_pr_features_for_cardio()`
4. `test_pr_cards_display_correct_weights_from_database()`
5. `test_calculator_grid_shows_accurate_percentages()`
6. `test_pr_cards_handle_missing_rep_ranges_gracefully()`
7. `test_page_works_normally_when_no_lift_logs_exist()`

### Phase 9: Manual Testing

**Testing Checklist**:
- [ ] Create test exercise (barbell squat)
- [ ] Add lift logs with 1, 2, and 3 rep sets
- [ ] Visit exercise logs page
- [ ] Verify PR cards show correct weights
- [ ] Verify calculator grid shows correct percentages
- [ ] Test with dumbbell exercise (should not show PR features)
- [ ] Test with cardio exercise (should not show PR features)
- [ ] Test with no lift logs (should not show PR features)
- [ ] Test mobile responsive design
- [ ] Test with partial data (only 1 rep range)
- [ ] Verify existing chart and table still work

## Implementation Order

1. **Day 1**: Service layer and unit tests
   - Create ExercisePRService
   - Write and pass all unit tests
   
2. **Day 2**: Components and styling
   - Extend ComponentBuilder
   - Create Blade templates
   - Write CSS
   
3. **Day 3**: Integration and testing
   - Update ExerciseController
   - Write integration tests
   - Manual testing and bug fixes
   
4. **Day 4**: Polish and refinement
   - Responsive design tweaks
   - Performance testing
   - Code review and cleanup

## Rollback Plan

If issues arise:
1. Remove PR cards and calculator grid components from controller
2. Keep service layer for future use
3. Remove CSS imports from flexible view
4. Feature can be re-enabled after fixes

## Success Metrics

- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Manual testing checklist complete
- [ ] No performance degradation on exercise logs page
- [ ] Code review approved
- [ ] Feature works on mobile and desktop

## Dependencies

- Exercise model and type system
- ComponentBuilder service
- Mobile entry flexible view system
- Brzycki formula for 1RM calculation

## Notes

- Keep existing functionality intact
- Follow existing code patterns and conventions
- Ensure backward compatibility
- Document any edge cases discovered during implementation
