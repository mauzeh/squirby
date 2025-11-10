# Migration Guide: Old Mobile Entry → Flexible Component-Based UI

## Overview

This document outlines all the changes needed to migrate existing implementations (MobileEntryController, services, tests) from the old hardcoded structure to the new flexible component-based architecture.

## Architecture Changes

### Old Architecture (Hardcoded)
- Single view with hardcoded sections
- Fixed data structure with specific array keys
- Tightly coupled to fitness domain (exercise, food, measurement)
- All sections always present

### New Architecture (Flexible)
- Component-based with dynamic rendering
- Flexible data structure (components array)
- Generic, domain-agnostic naming
- All sections optional and reorderable

## View Changes

### Old View
- **File**: `resources/views/mobile-entry/index.blade.php`
- **Structure**: Hardcoded sections with specific array keys
- **Usage**: `return view('mobile-entry.index', compact('data'));`

### New View
- **File**: `resources/views/mobile-entry/flexible.blade.php`
- **Structure**: Loops through components array
- **Usage**: `return view('mobile-entry.flexible', compact('data'));`

## Data Structure Changes

### Old Structure
```php
$data = [
    'selectedDate' => '2025-11-10',
    'navigation' => [...],
    'summary' => [...],
    'addItemButton' => [...],
    'itemSelectionList' => [...],
    'forms' => [...],
    'loggedItems' => [...],
    'interfaceMessages' => [...]
];
```

### New Structure
```php
$data = [
    'components' => [
        ['type' => 'navigation', 'data' => [...]],
        ['type' => 'title', 'data' => [...]],
        ['type' => 'messages', 'data' => [...]],
        ['type' => 'summary', 'data' => [...]],
        ['type' => 'button', 'data' => [...]],
        ['type' => 'item-list', 'data' => [...]],
        ['type' => 'form', 'data' => [...]],
        ['type' => 'items', 'data' => [...]]
    ]
];
```

## Component Builder Usage

### Old Way (Manual Array Building)
```php
$data = [
    'forms' => [
        [
            'id' => 'workout-1',
            'type' => 'exercise',
            'title' => 'Bench Press',
            'formAction' => route('lift-logs.store'),
            'numericFields' => [
                [
                    'id' => 'workout-1-weight',
                    'name' => 'weight',
                    'label' => 'Weight:',
                    'defaultValue' => 135,
                    // ... more config
                ]
            ],
            // ... more config
        ]
    ]
];
```

### New Way (ComponentBuilder)
```php
use App\Services\ComponentBuilder as C;

$data = [
    'components' => [
        C::form('workout-1', 'Bench Press')
            ->type('primary')
            ->formAction(route('lift-logs.store'))
            ->numericField('weight', 'Weight:', 135, 5, 45)
            ->numericField('reps', 'Reps:', 10, 1, 1)
            ->submitButton('Log')
            ->build()
    ]
];
```

## CSS Class Name Changes

### Navigation
| Old | New |
|-----|-----|
| `.date-navigation` | `.component-navigation` |
| `.date-title-container` | `.component-title-container` |
| `.date-title` | `.component-title` |
| `.date-subtitle` | `.component-subtitle` |

### Forms
| Old | New |
|-----|-----|
| `.item-logging-section` | `.component-form-section` |
| `.item-header` | `.component-header` |
| `.item-title` | `.component-heading` |
| `.item-messages` | `.component-messages` |
| `.item-message` | `.component-message` |
| `.item-form` | `.component-form-element` |

### Item List
| Old | New |
|-----|-----|
| `.item-selection-section` | `.component-list-section` |
| `.item-selection-list` | `.component-list` |
| `.item-selection-card` | `.component-list-item` |
| `.item-filter-container` | `.component-filter-container` |
| `.item-filter-group` | `.component-filter-group` |
| `.item-filter-input` | `.component-filter-input` |
| `.item-name` | `.component-list-item-name` |
| `.item-type` | `.component-list-item-type` |
| `.create-item-form` | `.component-create-form` |
| `.create-item-input` | `.component-create-input` |

### Items (Previously Logged Items)
| Old | New |
|-----|-----|
| `.logged-items-section` | `.component-items-section` |
| `.logged-item` | `.component-item` |
| `.item-value` | `.component-value` |
| `.item-actions` | `.component-actions` |
| `.item-freeform-text` | `.component-freeform-text` |

### Buttons
| Old | New |
|-----|-----|
| `.add-item-section` | `.component-button-section` |

## Form Type Changes

### Old Types (Domain-Specific)
- `exercise` → Blue border
- `food` → Green border
- `measurement` → Yellow border
- `general` → Gray border

### New Types (Generic, Color-Based)
- `primary` → Blue border (main/important)
- `success` → Green border (positive/completion)
- `warning` → Yellow border (attention)
- `secondary` → Gray border (general/default)
- `danger` → Red border (error/critical)
- `info` → Light blue border (informational)

**Breaking Change**: Old type names no longer supported. Must update all form type references.

## JavaScript Changes

### Filter-Related
- All class names updated to use `component-` prefix
- Filter code now wrapped in conditional (only runs if elements exist)

### Autoscroll
- Updated to use `.component-form-section` instead of `.item-logging-section`
- Now runs even when filter elements don't exist

## Files That Need Migration

### Controllers
- [ ] `app/Http/Controllers/MobileEntryController.php`
  - `index()` method
  - `lifts()` method
  - `foods()` method
  - `measurements()` method

### Services
- [ ] `app/Services/MobileEntry/LiftLogService.php`
  - `generateForms()` method
  - `generateLoggedItems()` method
  - Update form type from `exercise` to `primary`
  
- [ ] `app/Services/MobileEntry/FoodLogService.php`
  - `generateForms()` method
  - `generateLoggedItems()` method
  - Update form type from `food` to `success`
  
- [ ] `app/Services/MobileEntry/BodyLogService.php`
  - `generateForms()` method
  - `generateLoggedItems()` method
  - Update form type from `measurement` to `warning`

### Tests
- [ ] `tests/Feature/MobileEntry/MeasurementsTest.php`
  - Update assertions for new data structure
  - Change `$data['loggedItems']` to component-based access
  
- [ ] `tests/Unit/Services/MobileEntry/LiftLogServiceTest.php`
  - Update form structure assertions
  - Update `numericFields` access patterns
  
- [ ] `tests/Unit/Services/MobileEntry/FoodLogServiceTest.php`
  - Update form structure assertions
  - Update `numericFields` access patterns
  
- [ ] `tests/Feature/ShowExtraWeightPreferenceTest.php`
  - Update form field access patterns

### Views
- [ ] `resources/views/mobile-entry/index.blade.php`
  - Can be deprecated once migration complete
  - Or update to use new class names if keeping for backward compat

## Migration Steps

### Phase 1: Update Services
1. Update `LiftLogService::generateForms()` to use ComponentBuilder
2. Update `LiftLogService::generateLoggedItems()` to use ComponentBuilder
3. Change form type from `exercise` to `primary`
4. Repeat for `FoodLogService` (type: `success`) and `BodyLogService` (type: `warning`)

### Phase 2: Update Controllers
1. Update `MobileEntryController::lifts()` to use new structure
2. Update `MobileEntryController::foods()` to use new structure
3. Update `MobileEntryController::measurements()` to use new structure
4. Change view from `mobile-entry.index` to `mobile-entry.flexible`

### Phase 3: Update Tests
1. Update all test assertions to use component-based structure
2. Update form type assertions (exercise→primary, food→success, measurement→warning)
3. Update CSS class name assertions if any exist

### Phase 4: Cleanup
1. Remove or deprecate old `mobile-entry/index.blade.php` view
2. Remove old CSS classes if no longer needed
3. Update documentation

## ComponentBuilder API Reference

### Navigation
```php
C::navigation()
    ->prev('← Prev', $url)
    ->center('Today', $url)
    ->next('Next →', $url)
    ->ariaLabel('Date navigation')
    ->build()
```

### Title
```php
C::title('Main Title', 'Optional Subtitle')->build()
```

### Messages
```php
C::messages()
    ->success('Success message')
    ->error('Error message')
    ->warning('Warning message')
    ->info('Info message')
    ->tip('Tip message')
    ->build()
```

### Summary
```php
C::summary()
    ->item('key', 1250, 'Label')
    ->item('completed', 3, 'Done')
    ->ariaLabel('Summary')
    ->build()
```

### Button
```php
C::button('Add Exercise')
    ->ariaLabel('Add new exercise')
    ->cssClass('btn-primary btn-success')
    ->build()
```

### Item List
```php
C::itemList()
    ->item('id', 'Name', '/url', 'Type Label', 'css-class', 4)
    ->filterPlaceholder('Search...')
    ->createForm('/create', 'field_name', ['date' => '2025-11-10'])
    ->build()
```

### Form
```php
C::form('form-id', 'Form Title')
    ->type('primary')  // primary, success, warning, secondary, danger, info
    ->formAction('/submit')
    ->deleteAction('/delete')
    ->message('info', 'Message text', 'Prefix:')
    ->numericField('weight', 'Weight:', 135, 5, 45, 500)
    ->selectField('band', 'Band:', [
        ['value' => 'red', 'label' => 'Red'],
        ['value' => 'blue', 'label' => 'Blue']
    ], 'red')
    ->textField('name', 'Name:', 'Default', 'Placeholder')
    ->commentField('Notes:', 'Add notes...', 'Default text')
    ->hiddenField('date', '2025-11-10')
    ->submitButton('Submit')
    ->build()
```

### Items (Previously Logged Items)
```php
C::items()
    ->item(1, 'Title', 25, '/edit/1', '/delete/1')
        ->message('neutral', 'Comment text', 'Comment:')
        ->freeformText('Additional details...')
        ->deleteParams(['date' => '2025-11-10'])
        ->add()
    ->emptyMessage('No items yet.')
    ->confirmMessage('deleteItem', 'Are you sure?')
    ->build()
```

## Important Notes

### What NOT to Change
- **`numericFields` array name**: Keep as-is even though it now contains text and select fields
  - Changing this would require massive refactor across all services and tests
  - The name is misleading but functional
  - Consider it a "fields" array that happens to be called `numericFields`

### Backward Compatibility
- Old form types (exercise, food, measurement, general) are **NOT** supported
- Old CSS class names are **NOT** supported
- Old view structure is **NOT** supported
- This is a **breaking change** - full migration required

### Testing Strategy
1. Migrate one method at a time (e.g., start with `lifts()`)
2. Run tests after each migration
3. Manually test UI after each migration
4. Keep old and new implementations side-by-side during transition
5. Remove old implementation only after all tests pass

## Example Migration

### Before (Old Structure)
```php
public function lifts(Request $request)
{
    $forms = [
        [
            'id' => 'workout-1',
            'type' => 'exercise',
            'title' => 'Bench Press',
            'formAction' => route('lift-logs.store'),
            'numericFields' => [
                [
                    'id' => 'workout-1-weight',
                    'name' => 'weight',
                    'label' => 'Weight:',
                    'defaultValue' => 135,
                    'increment' => 5,
                    'min' => 45
                ]
            ]
        ]
    ];
    
    $data = [
        'navigation' => [...],
        'forms' => $forms,
        'loggedItems' => [...]
    ];
    
    return view('mobile-entry.index', compact('data'));
}
```

### After (New Structure)
```php
use App\Services\ComponentBuilder as C;

public function lifts(Request $request)
{
    $data = [
        'components' => [
            C::navigation()
                ->prev('← Prev', $prevUrl)
                ->center('Today', $todayUrl)
                ->next('Next →', $nextUrl)
                ->build(),
            
            C::title('Today')->build(),
            
            C::form('workout-1', 'Bench Press')
                ->type('primary')
                ->formAction(route('lift-logs.store'))
                ->numericField('weight', 'Weight:', 135, 5, 45)
                ->build(),
            
            C::items()
                ->item(1, 'Morning Workout', 25, '/edit/1', '/delete/1')
                    ->add()
                ->build()
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

## Questions & Answers

**Q: Can I mix old and new structures?**
A: No. The new view only works with the component-based structure.

**Q: Do I need to update all methods at once?**
A: No. You can migrate one method at a time. Just change the view path for migrated methods.

**Q: What about the old view?**
A: Keep it until all methods are migrated, then deprecate or remove it.

**Q: Can I add custom components?**
A: Yes! Just create a new blade file in `resources/views/mobile-entry/components/` and add a builder method to `ComponentBuilder`.

**Q: Why keep `numericFields` as the array name?**
A: Changing it would require updating dozens of files. It's not worth the effort for a naming issue.

**Q: Are there any performance implications?**
A: No. The new structure is just as performant as the old one.

## Checklist for Complete Migration

- [ ] All controller methods updated
- [ ] All service methods updated
- [ ] All tests passing
- [ ] Manual testing complete
- [ ] Old view removed or deprecated
- [ ] Documentation updated
- [ ] Team notified of breaking changes

## Support

For questions or issues during migration:
1. Check `FlexibleWorkflowController.php` for working examples
2. Review `docs/FLEXIBLE_MOBILE_ENTRY.md` for API documentation
3. Check `docs/TESTING_FLEXIBLE_UI.md` for testing examples
