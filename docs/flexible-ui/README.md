# Flexible UI Documentation

Documentation for the flexible component-based mobile entry system.

## Status: ✅ PRODUCTION READY

The flexible UI system is now in production use across all interfaces with a mature, stable component architecture.

## Quick Links

- **[Reference](reference.md)** - Complete API reference for all components
- **[CHANGELOG v1.5](CHANGELOG-v1.5.md)** - Latest incremental improvements and Quick Actions component
- **[CHANGELOG v1.4](CHANGELOG-v1.4.md)** - Major expansion with 4 new components
- **[Testing Guide](testing.md)** - How to test flexible UI components
- **[Table Component](component-table.md)** - Complete table component documentation
- **[Chart Component](component-chart.md)** - Chart.js integration with native API
- **[Item List Component](component-item-list.md)** - Searchable lists with create functionality

## Overview

The flexible UI system provides a component-based architecture for building mobile entry interfaces. Instead of hardcoded sections, the system uses reusable components that can be arranged, reordered, and customized.

### Key Benefits

1. **Flexibility** - Components can be reordered, added, or removed easily
2. **Consistency** - All interfaces use the same component structure
3. **Maintainability** - ComponentBuilder provides type-safe, fluent API
4. **Extensibility** - New component types can be added without changing views
5. **Domain-agnostic** - Generic naming allows reuse beyond fitness domain

## Architecture

### Old System (Hardcoded)
```php
$data = [
    'navigation' => [...],
    'summary' => [...],
    'forms' => [...],
    'loggedItems' => [...]
];
```

### New System (Component-Based)
```php
$data = [
    'components' => [
        ['type' => 'navigation', 'data' => [...]],
        ['type' => 'title', 'data' => [...]],
        ['type' => 'summary', 'data' => [...]],
        ['type' => 'form', 'data' => [...]],
        ['type' => 'items', 'data' => [...]]
    ]
];
```

## Available Components

| Component | Type | Description |
|-----------|------|-------------|
| Navigation | `navigation` | Date navigation with prev/today/next buttons |
| Title | `title` | Page title with optional subtitle and back button |
| Messages | `messages` | Success, error, warning, info, tip messages |
| Summary | `summary` | Key metrics display (calories, entries, etc.) |
| Button | `button` | Action button with enhanced mobile UX |
| Item List | `item-list` | Searchable list with filter and create form |
| Form | `form` | Data entry form with enhanced mobile inputs |
| Items | `items` | Logged items with edit/delete actions |
| Table | `table` | Tabular CRUD list with badges and bulk actions |
| Quick Actions | `quick-actions` | Standardized action button grid for page operations |
| PR Cards | `pr-cards` | Personal record tracking with visual highlights |
| Calculator Grid | `calculator-grid` | Interactive calculation display |
| Code Editor | `code-editor` | IDE-like syntax editor with highlighting |
| Markdown | `markdown` | Rich text rendering with custom styling |
| Chart | `chart` | Chart.js integration with enhanced styling |

## Quick Start

### 1. Import ComponentBuilder
```php
use App\Services\ComponentBuilder as C;
```

### 2. Build Components
```php
$components = [];

// Navigation
$components[] = C::navigation()
    ->prev('← Prev', $prevUrl)
    ->center('Today', $todayUrl)
    ->next('Next →', $nextUrl)
    ->build();

// Title
$components[] = C::title('Today')->build();

// Form
$components[] = C::form('workout-1', 'Bench Press')
    ->type('primary')
    ->formAction(route('lift-logs.store'))
    ->numericField('weight', 'Weight:', 135, 5, 45)
    ->numericField('reps', 'Reps:', 10, 1, 1)
    ->submitButton('Log Exercise')
    ->build();
```

### 3. Return View
```php
$data = ['components' => $components];
return view('mobile-entry.flexible', compact('data'));
```

## Form Types

| Type | Color | Usage |
|------|-------|-------|
| `primary` | Blue | Main/important forms (exercises) |
| `success` | Green | Positive/completion forms (food) |
| `warning` | Yellow | Attention forms (measurements) |
| `secondary` | Gray | General/default forms |
| `danger` | Red | Error/critical forms |
| `info` | Light Blue | Informational forms |

## Production Status

### ✅ Completed
- LiftLogService (exercise forms)
- FoodLogService (food/meal forms)
- BodyLogService (measurement forms)
- MobileEntryController (lifts, foods, measurements methods)
- All 92 MobileEntry tests updated and passing

### 🗑️ Removed (Cleanup Complete)
- ✅ MobileEntryController::index() method - deleted
- ✅ Route mobile-entry.index - removed
- ✅ Old view: mobile-entry/index.blade.php - deleted

## Testing

All tests have been updated to work with the new component structure:

```php
// Old way
$this->assertCount(1, $data['forms']);

// New way
$formComponents = collect($data['components'])->where('type', 'form')->values();
$this->assertCount(1, $formComponents);
```

See [Testing Guide](testing.md) for more examples.

## Examples

### Complete Controller Method
```php
public function lifts(Request $request, LiftLogService $formService)
{
    $selectedDate = $request->input('date') 
        ? Carbon::parse($request->input('date')) 
        : Carbon::today();
    
    $components = [];
    
    // Navigation
    $components[] = C::navigation()
        ->prev('← Prev', route('mobile-entry.lifts', ['date' => $prevDay]))
        ->center('Today', route('mobile-entry.lifts', ['date' => $today]))
        ->next('Next →', route('mobile-entry.lifts', ['date' => $nextDay]))
        ->build();
    
    // Title
    $components[] = C::title('Today')->build();
    
    // Forms from service
    $forms = $formService->generateForms(Auth::id(), $selectedDate);
    foreach ($forms as $form) {
        $components[] = ['type' => 'form', 'data' => $form];
    }
    
    // Logged items from service
    $loggedItems = $formService->generateLoggedItems(Auth::id(), $selectedDate);
    $components[] = ['type' => 'items', 'data' => $loggedItems];
    
    $data = ['components' => $components];
    return view('mobile-entry.flexible', compact('data'));
}
```

### Service Method
```php
public function generateLoggedItems($userId, Carbon $selectedDate)
{
    $logs = LiftLog::where('user_id', $userId)
        ->whereDate('logged_at', $selectedDate)
        ->get();
    
    $itemsBuilder = C::items()
        ->confirmMessage('deleteItem', 'Are you sure?');
    
    foreach ($logs as $log) {
        $itemsBuilder->item(
            $log->id,
            $log->exercise->title,
            null,
            route('lift-logs.edit', $log->id),
            route('lift-logs.destroy', $log->id)
        )
        ->message('success', $log->details, 'Completed!')
        ->freeformText($log->comments ?? '')
        ->deleteParams(['date' => $selectedDate->toDateString()])
        ->add();
    }
    
    if ($logs->isEmpty()) {
        $itemsBuilder->emptyMessage('No workouts logged yet!');
    }
    
    return $itemsBuilder->build()['data'];
}
```

## Support

For questions or issues:
1. Check the [Reference](reference.md) for complete API documentation
2. Look at `FlexibleWorkflowController.php` for working examples
3. Check the [Testing Guide](testing.md) for test patterns
4. Review component-specific guides for detailed usage

## Contributing

When adding new features:
1. Use ComponentBuilder for consistency
2. Follow the established patterns
3. Update tests to use component structure
4. Document new component types
5. Add examples to the quick reference

## Version History

- **v1.5** (December 24, 2025) - Incremental improvements with Quick Actions component
  - New component: Quick Actions for standardized action button grids
  - Enhanced FormComponentBuilder with checkbox array support
  - Enhanced ButtonComponentBuilder with style mappings and url() method
  - Continued adoption of component-based architecture patterns
  - 59 commits with focused improvements

- **v1.4** (December 13, 2025) - Major expansion with 4 new components and enhanced UX
  - New components: PR Cards, Calculator Grid, Code Editor, Markdown
  - Enhanced existing components with better mobile UX
  - Component-based CSS architecture
  - Automatic script loading system
  - 328 commits with significant improvements

- **v1.3** (November 13, 2025) - Table component enhancements
  - Clickable sub-items (entire row tappable for single actions)
  - Compact button mode (75% size for secondary actions)
  - Additional button styles (transparent, info-circle, log-now)
  - Table row initial state (expanded/collapsed)
  - Sub-item inline messages (success, info, tip, warning, error)
  - Large title class for prominent rows
  - Non-collapsible sub-items option

- **v1.2** (November 11, 2025) - Enhanced features and improvements
  - Added back button support to title component
  - Multiple independent item lists on same page
  - Auto-scroll and focus for initially expanded lists
  - Context-aware initial states (e.g., expand parameter)
  - Submenu wrapping for better navigation
  - Additional examples in FlexibleWorkflowController

- **v1.1** (November 11, 2025) - Initial state configuration added
  - Added `initialState()` method to Button and ItemList components
  - Supports collapsed/expanded initial states for item lists
  - Backward compatible with existing code
  - Documentation and examples added

- **v1.0** (November 10, 2025) - Initial production release
  - All three main interfaces implemented
  - 92 tests updated and passing
  - Critical bug fixes applied
  - Documentation created

