# Design Document

## Overview

The mobile food entry system will be implemented as a new controller method and view that mirrors the existing lift logs mobile entry architecture. It will provide a streamlined, touch-optimized interface for logging food consumption with real-time entry, unified search, and unit-specific quantity controls.

## Architecture

### Route Structure
```php
Route::get('food-logs/mobile-entry', [FoodLogController::class, 'mobileEntry'])
    ->name('food-logs.mobile-entry');
```

### Controller Architecture
The feature will extend the existing `FoodLogController` with a new `mobileEntry()` method that:
- Handles date navigation via query parameters
- Fetches user ingredients and meals for autocomplete
- Retrieves existing food logs for the selected date
- Calculates daily nutrition totals
- Returns the mobile-optimized view

### View Architecture
- **Primary View**: `resources/views/food_logs/mobile-entry.blade.php`
- **Styling**: Reuse existing mobile entry CSS classes from lift logs
- **JavaScript**: Custom autocomplete and increment/decrement functionality
- **Layout**: Single-page interface with form at top, logged entries below

## Components and Interfaces

### 1. Mobile Entry Controller Method

```php
public function mobileEntry(Request $request)
{
    $selectedDate = $request->input('date') ? 
        Carbon::parse($request->input('date')) : Carbon::today();
    
    // Get user ingredients and meals for autocomplete
    $ingredients = Ingredient::where('user_id', auth()->id())
        ->with('baseUnit')
        ->orderBy('name')
        ->get();
    
    $meals = Meal::where('user_id', auth()->id())
        ->orderBy('name')
        ->get();
    
    // Get existing food logs for the date
    $foodLogs = FoodLog::with(['ingredient', 'unit'])
        ->where('user_id', auth()->id())
        ->whereDate('logged_at', $selectedDate->toDateString())
        ->orderBy('logged_at', 'desc')
        ->get();
    
    // Calculate daily totals
    $dailyTotals = $this->nutritionService->calculateFoodLogTotals($foodLogs);
    
    return view('food_logs.mobile-entry', compact(
        'selectedDate', 'ingredients', 'meals', 'foodLogs', 'dailyTotals'
    ));
}
```

### 2. Unified Search Interface Component

The interface will display ingredients and meals in a single expandable list, similar to the exercise selection in lift logs mobile entry:

```html
<div class="add-food-container">
    <button type="button" id="add-food-button" class="button-large button-green">Add Food</button>
</div>

<div id="food-list-container" class="hidden">
    <div class="food-list">
        @foreach ($ingredients as $ingredient)
            <a href="#" class="food-list-item ingredient-item" 
               data-type="ingredient" 
               data-id="{{ $ingredient->id }}"
               data-name="{{ $ingredient->name }}"
               data-unit="{{ $ingredient->baseUnit->name }}">
                <span class="food-name">{{ $ingredient->name }}</span>
                <span class="food-label"><em>Ingredient</em></span>
            </a>
        @endforeach
        
        @foreach ($meals as $meal)
            <a href="#" class="food-list-item meal-item"
               data-type="meal"
               data-id="{{ $meal->id }}"
               data-name="{{ $meal->name }}">
                <span class="food-name">{{ $meal->name }}</span>
                <span class="food-label"><em>Meal</em></span>
            </a>
        @endforeach
    </div>
</div>
```

### 3. Dynamic Form Fields Component

Based on selection type, different form fields will be shown:

**For Ingredients:**
```html
<div id="ingredient-fields" class="form-fields hidden">
    <div class="form-group">
        <label>Quantity:</label>
        <div class="input-group">
            <button type="button" class="decrement-button">-</button>
            <input type="number" name="quantity" class="large-input" step="0.01">
            <button type="button" class="increment-button">+</button>
        </div>
        <span class="unit-display"></span>
    </div>
    <div class="form-group">
        <label>Notes:</label>
        <textarea name="notes" class="large-textarea"></textarea>
    </div>
</div>
```

**For Meals:**
```html
<div id="meal-fields" class="form-fields hidden">
    <div class="form-group">
        <label>Portion:</label>
        <div class="input-group">
            <button type="button" class="decrement-button">-</button>
            <input type="number" name="portion" class="large-input" step="0.01">
            <button type="button" class="increment-button">+</button>
        </div>
    </div>
    <div class="form-group">
        <label>Notes:</label>
        <textarea name="notes" class="large-textarea"></textarea>
    </div>
</div>
```

### 4. Unit-Specific Increment Logic

```javascript
function getIncrementAmount(unit) {
    const unitName = unit.toLowerCase();
    
    if (unitName.includes('g') || unitName.includes('ml')) {
        return 10;
    } else if (unitName.includes('kg') || unitName.includes('lb') || 
               unitName.includes('liter') || unitName.includes('pound')) {
        return 0.1;
    } else if (unitName.includes('pc') || unitName.includes('serving') || 
               unitName.includes('piece')) {
        return 0.25;
    }
    
    return 1; // Default increment
}
```

## Data Models

### No Model Changes Required
The design leverages existing models without modifications:

- **FoodLog**: Existing model handles ingredient_id, quantity, logged_at, notes, user_id
- **Ingredient**: Existing model with base_unit relationship
- **Meal**: Existing model with ingredients relationship
- **Unit**: Existing model for measurement units

### Data Flow for Logging

**Ingredient Logging:**
1. User selects ingredient from autocomplete
2. Form shows quantity field with unit-specific increments
3. On submit, creates single FoodLog entry
4. Redirects back to mobile entry with success message

**Meal Logging:**
1. User selects meal from autocomplete
2. Form shows portion field (decimal multiplier)
3. On submit, creates multiple FoodLog entries (one per meal ingredient)
4. Each entry includes meal name and portion in notes field
5. Redirects back to mobile entry with success message

## Error Handling

### Form Validation
- Reuse existing FoodLogController validation rules
- Add client-side validation for positive quantities
- Handle food selection validation

### Network Error Handling
- Show loading states during form submission
- Display error messages for failed submissions
- Handle form validation errors gracefully

### Data Consistency
- Prevent duplicate rapid submissions with form disabling
- Validate ingredient/meal ownership before logging
- Handle edge cases for deleted ingredients/meals

## Testing Strategy

### Unit Tests
- Test mobileEntry controller method with various date parameters
- Test increment amount calculation for different unit types
- Test form submission for both ingredients and meals

### Integration Tests
- Test complete logging workflow from selection to database storage
- Test date navigation functionality

### Mobile-Specific Tests
- Test touch interactions on increment/decrement buttons

## Performance Considerations

### Data Loading
- Limit autocomplete results to user's ingredients/meals only
- Use eager loading for ingredient->baseUnit relationships

### JavaScript Performance
- Use event delegation for dynamic form elements
- Minimize DOM manipulation during interactions

### Mobile Optimization
- Minimize JavaScript bundle size
- Optimize touch target sizes (minimum 44px)

## Security Considerations

### Authorization
- Ensure user can only access their own ingredients/meals
- Validate ownership before creating food log entries
- Use existing middleware for authentication

### Input Validation
- Validate quantity/portion numeric ranges
- Prevent XSS in notes fields

### CSRF Protection
- Use existing Laravel CSRF token validation for form submissions

## Implementation Phases

### Phase 1: Basic Mobile Entry
- Create mobileEntry controller method
- Build basic mobile view with date navigation
- Implement simple ingredient selection and logging

### Phase 2: Unified Food Selection Interface
- Add expandable food list (ingredients and meals)
- Implement dynamic form field switching
- Add unit-specific increment controls

### Phase 3: Meal Support
- Add meal selection to autocomplete
- Implement meal portion logging
- Handle multiple food log creation for meals

### Phase 4: Polish and Optimization
- Add error handling

## Dependencies

### Existing Services
- `NutritionService`: For calculating daily totals
- `DateNavigationService`: For date navigation (if needed)

### Frontend Dependencies
- No new JavaScript libraries required
- Reuse existing CSS framework and mobile styles
- Leverage existing form validation patterns

### Backend Dependencies
- No new packages required
- Leverage existing Laravel validation and routing
- Use existing Eloquent relationships and queries