# Tabs Component Example

This example demonstrates how to create a tabbed interface with a lift logging form in one tab and a historical graph in another tab.

## Implementation

The tabbed interface is implemented using the new `TabsComponentBuilder` which allows you to create multiple tabs, each containing their own set of components.

### Key Features

- **Accessible**: Full keyboard navigation support (arrow keys, home/end)
- **Responsive**: Mobile-optimized with scrollable tab navigation
- **Flexible**: Each tab can contain any combination of components
- **Animated**: Smooth transitions between tabs

### Usage Example

```php
// Create components for each tab
$logLiftComponents = [
    C::form('bench-press-log', 'Bench Press')
        ->numericField('weight', 'Weight (lbs):', 185, 5, 45, 500)
        ->numericField('reps', 'Reps:', 8, 1, 1, 50)
        ->submitButton('Log Workout')
        ->build(),
    
    C::summary()
        ->item('streak', '12 days', 'Current Streak')
        ->item('pr', '185 lbs', 'Current PR')
        ->build(),
];

$historyComponents = [
    C::chart('progress-chart', 'Progress Chart')
        ->type('line')
        ->datasets($chartData)
        ->build(),
    
    C::table()
        ->row(1, 'Recent Workout', 'Details')
        ->build(),
];

// Create the tabbed interface
$components = [
    C::title('Bench Press Tracker')->build(),
    
    C::tabs('lift-tracker-tabs')
        ->tab('log', 'Log Lift', $logLiftComponents, 'fa-plus', true)
        ->tab('history', 'History', $historyComponents, 'fa-chart-line')
        ->build(),
];
```

### Tab Configuration

- **ID**: Unique identifier for the tab
- **Label**: Display text for the tab button
- **Components**: Array of component data to render in the tab
- **Icon**: Optional FontAwesome icon class
- **Active**: Whether this tab should be active by default

### Accessibility Features

- ARIA labels and roles for screen readers
- Keyboard navigation (arrow keys, home, end)
- Focus management
- Proper tab/tabpanel relationships

### Route

Visit `/labs/tabbed-lift-logger` to see the example in action.

## Files Created

- `app/Services/Components/Interactive/TabsComponentBuilder.php` - Builder class
- `resources/views/mobile-entry/components/tabs.blade.php` - Blade template
- `public/css/mobile-entry/components/tabs.css` - Styling
- `public/js/mobile-entry/tabs.js` - JavaScript functionality
- Added method to `app/Http/Controllers/LabsController.php` - Example implementation