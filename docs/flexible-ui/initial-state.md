# Initial State Configuration

> **New in v1.1** - Added November 11, 2025

## Overview

The "Add Item" button and item list components support configurable initial states, allowing you to control whether the item selection list starts collapsed (hidden) or expanded (visible) when the page loads.

This feature is **fully backward compatible** - existing code without `initialState()` calls will continue to work with default behavior (collapsed list, visible button).

## Use Cases

### Collapsed (Default)
Use when you want a clean, minimal interface where users explicitly choose to add items:
- Workout logging pages where forms are the primary focus
- Pages with multiple action buttons
- When screen space is limited

### Expanded
Use when item selection is the primary action:
- Dedicated "add exercise" pages
- Quick-add workflows
- When you want to minimize clicks for the user

## Configuration

### Item List Component

```php
C::itemList()
    ->item('ex-1', 'Bench Press', '#', 'In Program', 'in-program', 4)
    ->item('ex-2', 'Squats', '#', 'Recent', 'recent', 1)
    ->filterPlaceholder('Search exercises...')
    ->createForm('#', 'exercise_name', ['date' => $date])
    ->initialState('expanded')  // or 'collapsed' (default)
    ->build()
```

### Button Component

```php
C::button('Add Exercise')
    ->ariaLabel('Add new exercise')
    ->addClass('btn-add-item')
    ->initialState('hidden')  // or 'visible' (default)
    ->build()
```

## Coordinated States

When using both components together, coordinate their initial states:

### Collapsed List (Default Behavior)
```php
// Button visible, list hidden
C::button('Add Exercise')
    ->addClass('btn-add-item')
    ->initialState('visible')  // default
    ->build(),

C::itemList()
    ->items(...)
    ->initialState('collapsed')  // default
    ->build()
```

### Expanded List
```php
// Button hidden, list visible
C::button('Add Exercise')
    ->addClass('btn-add-item')
    ->initialState('hidden')
    ->build(),

C::itemList()
    ->items(...)
    ->initialState('expanded')
    ->build()
```

## Examples

### Example 1: Default Collapsed State
```php
public function workoutLog(Request $request)
{
    $data = [
        'components' => [
            C::title('Today\'s Workout')->build(),
            
            // Button starts visible
            C::button('Add Exercise')
                ->addClass('btn-add-item')
                ->build(),
            
            // List starts collapsed (hidden)
            C::itemList()
                ->item('ex-1', 'Bench Press', '#', 'In Program', 'in-program', 4)
                ->item('ex-2', 'Squats', '#', 'Recent', 'recent', 1)
                ->filterPlaceholder('Search exercises...')
                ->createForm('#', 'exercise_name')
                ->build(),
            
            // Forms are immediately visible
            C::form('workout-1', 'Bench Press')
                ->formAction('#')
                ->numericField('weight', 'Weight:', 135, 5)
                ->build(),
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

### Example 2: Expanded State for Quick Add
```php
public function quickAddExercise(Request $request)
{
    $data = [
        'components' => [
            C::title('Add Exercise')->build(),
            
            C::messages()
                ->info('Select an exercise or create a new one')
                ->build(),
            
            // Button starts hidden
            C::button('Add Exercise')
                ->addClass('btn-add-item')
                ->initialState('hidden')
                ->build(),
            
            // List starts expanded (visible)
            C::itemList()
                ->item('ex-1', 'Bench Press', '#', 'In Program', 'in-program', 4)
                ->item('ex-2', 'Squats', '#', 'Recent', 'recent', 1)
                ->item('ex-3', 'Deadlift', '#', 'Available', 'regular', 3)
                ->filterPlaceholder('Search exercises...')
                ->createForm('#', 'exercise_name')
                ->initialState('expanded')
                ->build(),
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

## Technical Details

### How It Works

1. **Controller Configuration**: Set initial state via fluent methods
2. **Blade Templates**: Add data attributes and CSS classes based on state
3. **JavaScript**: Read data attributes and apply appropriate visibility on page load

### CSS Classes

- `.active` - Applied to `.component-list-section` when expanded
- `.hidden` - Applied to `.component-button-section` when hidden

### Data Attributes

- `data-initial-state="collapsed|expanded"` - On item list container
- `data-initial-state="visible|hidden"` - On button container

### JavaScript Behavior

The `setupItemListToggle()` function in `mobile-entry.js` reads these data attributes and applies the appropriate initial state before setting up the toggle handlers.

## Best Practices

1. **Be Consistent**: Use the same pattern throughout your app for similar workflows
2. **Consider Context**: Use expanded state when adding is the primary action
3. **Mobile First**: Expanded state can reduce taps on mobile devices
4. **User Expectations**: Match the initial state to what users expect based on the page title/context
5. **Coordinate States**: Always set both button and list states together to avoid confusion

## Migration Guide

Existing code without `initialState()` calls will continue to work with default behavior (collapsed list, visible button). To change behavior, simply add the method calls:

```php
// Before (default behavior)
C::itemList()
    ->items(...)
    ->build()

// After (explicit expanded state)
C::itemList()
    ->items(...)
    ->initialState('expanded')
    ->build()
```


## Advanced Features

### Multiple Independent Lists

You can have multiple item lists on the same page, each with its own initial state:

```php
public function multipleCategories(Request $request)
{
    $data = [
        'components' => [
            C::title('Add Items')->build(),
            
            // First list - collapsed
            C::button('Add Exercise')->addClass('btn-add-item')->build(),
            C::itemList()
                ->item('ex-1', 'Bench Press', '#', 'Available', 'regular', 3)
                ->filterPlaceholder('Search exercises...')
                ->build(),
            
            // Second list - expanded
            C::button('Add Meal')->addClass('btn-add-item')->initialState('hidden')->build(),
            C::itemList()
                ->item('meal-1', 'Chicken & Rice', '#', 'Favorite', 'in-program', 4)
                ->filterPlaceholder('Search meals...')
                ->initialState('expanded')
                ->build(),
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

**Key Points:**
- Each button/list pair operates independently
- Automatically linked by proximity in component array
- Each can have different initial states
- See `/flexible/multiple-lists` for live example

### Auto-Scroll and Focus

When a list starts expanded, it automatically:
1. Scrolls to position the filter input near the top of viewport (5% from top)
2. Focuses the input field
3. Opens mobile keyboard if on mobile device

This behavior is identical whether the list is:
- Expanded via `initialState('expanded')`
- Expanded by clicking the "Add" button
- Expanded via URL parameter (e.g., `?expand=true`)

### Context-Aware Initial States

You can conditionally set initial state based on URL parameters:

```php
public function edit(Request $request, WorkoutTemplate $template)
{
    $shouldExpand = $request->query('expand') === 'true';
    
    $components = [];
    
    // Button
    $buttonBuilder = C::button('Add Exercise')->addClass('btn-add-item');
    if ($shouldExpand) {
        $buttonBuilder->initialState('hidden');
    }
    $components[] = $buttonBuilder->build();
    
    // List
    $listBuilder = C::itemList()->items(...);
    if ($shouldExpand) {
        $listBuilder->initialState('expanded');
    }
    $components[] = $listBuilder->build();
    
    return view('mobile-entry.flexible', compact('data'));
}
```

**Use Case:** Link from one page to another with the list pre-expanded:
```php
// In your view or controller
route('workout-templates.edit', ['template' => $id]) . '?expand=true'
```

This provides context-aware UX where the interface adapts based on user intent.

## Technical Details

### JavaScript Implementation

The `setupItemListToggle()` function in `mobile-entry.js`:
- Finds all button/list pairs on page load
- Assigns unique IDs to link them together
- Reads `data-initial-state` attribute from components
- Applies appropriate visibility and scroll behavior
- Uses `requestAnimationFrame` for reliable DOM rendering

### CSS Classes

- `.active` - Applied to `.component-list-section` when visible
- `.hidden` - Applied to `.component-button-section` when hidden

### Scroll Behavior

- **Target Position:** 5% from top of viewport
- **Timing:** 300ms delay after focus for smooth animation
- **Mobile:** Triggers keyboard opening on mobile devices
- **Reliability:** Double `requestAnimationFrame` ensures DOM is ready

## Examples

All examples are available in the flexible workflow submenu:

- `/flexible/with-nav` - Default collapsed state
- `/flexible/expanded-list` - Single expanded list
- `/flexible/multiple-lists` - Multiple independent lists with mixed states
- `/flexible/title-back-button` - Title with back button example

## Troubleshooting

### List doesn't scroll on page load
- Ensure browser cache is cleared
- Check console for JavaScript errors
- Verify `data-initial-state="expanded"` is in HTML

### Multiple lists interfere with each other
- Ensure button and list are adjacent in components array
- Check that each has unique positioning
- Verify no custom JavaScript is interfering

### Mobile keyboard doesn't open
- This is intentional on page load to avoid disruption
- Keyboard opens when user clicks "Add" button manually
- Focus is still applied for accessibility
