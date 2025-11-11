# Flexible Mobile Entry Architecture

## Overview

The mobile entry UI has been refactored into a component-based architecture that is completely flexible and loosely coupled. Every section is optional, components can repeat, and there are no hardcoded array keys.

## Architecture

### Component-Based Structure

Instead of a fixed view with hardcoded sections, the UI now renders a list of components:

```php
$data = [
    'components' => [
        ['type' => 'navigation', 'data' => [...]],
        ['type' => 'title', 'data' => [...]],
        ['type' => 'form', 'data' => [...]],
        ['type' => 'form', 'data' => [...]],  // Components can repeat!
        ['type' => 'logged-items', 'data' => [...]],
    ]
];
```

### Available Components

1. **navigation** - Date navigation (prev/today/next) or custom navigation
2. **title** - Page title with optional subtitle
3. **messages** - Interface messages (success, error, warning, info, tip)
4. **summary** - Summary statistics grid
5. **button** - Action button (like "Add Exercise")
6. **item-list** - Filterable item selection list
7. **form** - Data entry form with numeric fields, selects, and comments
8. **logged-items** - Display of previously logged items

## Component Builder Service

Use the fluent `ComponentBuilder` service to construct components:

```php
use App\Services\ComponentBuilder as C;

// Navigation
C::navigation()
    ->prev('← Prev', '/previous-url')
    ->center('Today', '/today-url')
    ->next('Next →', '/next-url')
    ->build();

// Title
C::title('Today', 'Monday, November 10, 2025')->build();

// Messages
C::messages()
    ->success('Workout logged!')
    ->info('2 exercises remaining')
    ->build();

// Summary
C::summary()
    ->item('total', 1250, 'Total Calories')
    ->item('completed', 3, 'Exercises Done')
    ->build();

// Button
C::button('Add Exercise')
    ->ariaLabel('Add new exercise')
    ->build();

// Item List
C::itemList()
    ->item('ex-1', 'Bench Press', '/add/bench', 'In Program', 'in-program', 4)
    ->item('ex-2', 'Squats', '/add/squats', 'Recent', 'recent', 1)
    ->filterPlaceholder('Search exercises...')
    ->createForm('/create', 'exercise_name', ['date' => '2025-11-10'])
    ->build();

// Form
C::form('workout-1', 'Bench Press')
    ->type('exercise')
    ->formAction('/lift-logs')
    ->deleteAction('/remove/workout-1')
    ->message('info', '3 sets of 10 reps', 'Last time:')
    ->numericField('weight', 'Weight (lbs):', 135, 5, 45, 500)
    ->numericField('reps', 'Reps:', 10, 1, 1, 50)
    ->numericField('sets', 'Sets:', 3, 1, 1, 10)
    ->commentField('Notes:', 'How did it feel?', 'Felt strong!')
    ->hiddenField('date', '2025-11-10')
    ->submitButton('Log Bench Press')
    ->build();

// Logged Items
C::loggedItems()
    ->item(1, 'Morning Workout', 25, '/edit/1', '/delete/1')
        ->message('neutral', 'Great session!', 'Comment:')
        ->freeformText('Felt great today!')
        ->add()
    ->item(2, 'Afternoon Session', 15, '/edit/2', '/delete/2')
        ->add()
    ->emptyMessage('No workouts logged yet.')
    ->build();
```

## Usage Examples

### Example 1: With Date Navigation (Full Featured)

```php
public function withDateNavigation(Request $request)
{
    $selectedDate = $request->input('date') 
        ? \Carbon\Carbon::parse($request->input('date')) 
        : \Carbon\Carbon::today();
    
    $data = [
        'components' => [
            C::navigation()
                ->prev('← Prev', route('page', ['date' => $prevDay]))
                ->center('Today', route('page', ['date' => $today]))
                ->next('Next →', route('page', ['date' => $nextDay]))
                ->build(),
            
            C::title($selectedDate->format('M j, Y'))->build(),
            
            C::summary()
                ->item('total', 1250, 'Calories')
                ->item('completed', 3, 'Done')
                ->build(),
            
            C::form('workout', 'Bench Press')
                ->formAction(route('lift-logs.store'))
                ->numericField('weight', 'Weight:', 135, 5, 45)
                ->submitButton('Log')
                ->build(),
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

### Example 2: Without Navigation (Standalone Form)

```php
public function withoutNavigation()
{
    $data = [
        'components' => [
            C::title('Quick Workout Log')->build(),
            
            C::form('quick-log', 'Log Your Workout')
                ->formAction('#')
                ->numericField('sets', 'Sets:', 3, 1, 1)
                ->numericField('reps', 'Reps:', 10, 1, 1)
                ->submitButton('Save')
                ->build(),
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

### Example 3: Multiple Forms

```php
public function multipleForms()
{
    $data = [
        'components' => [
            C::title('Today\'s Workout')->build(),
            
            C::form('ex-1', 'Bench Press')
                ->formAction('#')
                ->numericField('weight', 'Weight:', 135, 5, 45)
                ->submitButton('Log')
                ->build(),
            
            C::form('ex-2', 'Squats')
                ->formAction('#')
                ->numericField('weight', 'Weight:', 185, 5, 45)
                ->submitButton('Log')
                ->build(),
            
            C::form('ex-3', 'Deadlift')
                ->formAction('#')
                ->numericField('weight', 'Weight:', 225, 5, 45)
                ->submitButton('Log')
                ->build(),
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

### Example 4: Custom Component Order

Components can appear in any order:

```php
$data = [
    'components' => [
        C::messages()->warning('Complete your profile')->build(),
        C::title('Profile Setup')->build(),
        C::form('profile', 'Your Info')->build(),
        C::summary()->item('completion', '25%', 'Complete')->build(),
    ]
];
```

## Key Benefits

1. **Fully Optional** - Include only the components you need
2. **No Hardcoded Keys** - View loops through components dynamically
3. **Repeatable** - Use multiple forms, messages, etc.
4. **Reorderable** - Components render in the order you define them
5. **Type-Safe** - Builder classes provide IDE autocomplete
6. **Backward Compatible** - Old MobileEntryController still works (will be migrated later)

## Migration Path

All mobile entry methods now use the flexible UI system:

1. ✅ `lifts()` - Uses ComponentBuilder and flexible view
2. ✅ `foods()` - Uses ComponentBuilder and flexible view
3. ✅ `measurements()` - Uses ComponentBuilder and flexible view
4. ✅ Old `index()` method - Removed (was demo only)

The old `mobile-entry.index` view has been completely removed.

## Files

- **View**: `resources/views/mobile-entry/flexible.blade.php`
- **Components**: `resources/views/mobile-entry/components/*.blade.php`
- **Builder**: `app/Services/ComponentBuilder.php`
- **Example Controller**: `app/Http/Controllers/FlexibleWorkflowController.php`
- **CSS/JS**: Same as before (`public/css/mobile-entry.css`, `public/js/mobile-entry.js`)

## Next Steps

1. Test the new flexible view with example controller
2. Migrate `MobileEntryController` methods one at a time
3. Update services (LiftLogService, FoodLogService, etc.) to use ComponentBuilder
4. Remove old view once migration is complete
