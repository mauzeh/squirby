# Flexible UI Documentation

Documentation for the flexible component-based mobile entry system.

## Status: ✅ PRODUCTION READY

The flexible UI system has been successfully migrated and is now in production use for all three main mobile entry interfaces (lifts, foods, measurements).

## Quick Links

- **[Migration Guide](migration-guide.md)** - Complete guide for migrating from old to new system
- **[Migration Completed Report](migration-completed.md)** - Summary of completed migration
- **[ComponentBuilder Quick Reference](component-builder-quick-reference.md)** - Quick reference for using ComponentBuilder API
- **[Mobile Entry Documentation](mobile-entry.md)** - Detailed API documentation
- **[Testing Guide](testing.md)** - How to test flexible UI components
- **[Admin Menu Documentation](admin-menu.md)** - Admin interface documentation
- **[Initial State Configuration](initial-state.md)** - Configure collapsed/expanded state for item lists

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
| Title | `title` | Page title with optional subtitle |
| Messages | `messages` | Success, error, warning, info, tip messages |
| Summary | `summary` | Key metrics display (calories, entries, etc.) |
| Button | `button` | Action button (e.g., "Add Exercise") |
| Item List | `item-list` | Searchable list with filter and create form |
| Form | `form` | Data entry form with fields and validation |
| Items | `items` | Logged items with edit/delete actions |
| Table | `table` | Tabular CRUD list optimized for narrow screens |

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

## Migration Status

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
1. Check the [Quick Reference](component-builder-quick-reference.md) for API usage
2. Review the [Migration Guide](migration-guide.md) for detailed examples
3. Look at `FlexibleWorkflowController.php` for working examples
4. Check the [Testing Guide](testing.md) for test patterns

## Contributing

When adding new features:
1. Use ComponentBuilder for consistency
2. Follow the established patterns
3. Update tests to use component structure
4. Document new component types
5. Add examples to the quick reference

## Version History

- **v1.1** (November 11, 2025) - Initial state configuration added
  - Added `initialState()` method to Button and ItemList components
  - Supports collapsed/expanded initial states for item lists
  - Backward compatible with existing code
  - Documentation and examples added

- **v1.0** (November 10, 2025) - Initial migration completed
  - All three main interfaces migrated
  - 92 tests updated and passing
  - Critical bug fixes applied
  - Documentation created

---

**Status:** Production Ready ✅  
**Last Updated:** November 11, 2025
