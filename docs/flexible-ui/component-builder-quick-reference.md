# ComponentBuilder Quick Reference

Quick reference guide for using the ComponentBuilder API in mobile entry interfaces.

## Import

```php
use App\Services\ComponentBuilder as C;
```

## Navigation Component

```php
C::navigation()
    ->prev('← Prev', route('mobile-entry.lifts', ['date' => $prevDay]))
    ->center('Today', route('mobile-entry.lifts', ['date' => $today]))
    ->next('Next →', route('mobile-entry.lifts', ['date' => $nextDay]))
    ->ariaLabel('Date navigation')
    ->build()
```

## Title Component

### Basic Title
```php
C::title('Main Title', 'Optional Subtitle')->build()
```

### Title with Back Button
```php
C::title('Exercise Details', 'View and edit information')
    ->backButton('fa-arrow-left', route('exercises.index'), 'Back to exercises')
    ->build()
```

**Back Button Features:**
- Icon-only button positioned on the left
- Title and subtitle remain centered
- 44px touch target for mobile
- Optional aria label for accessibility

## Messages Component

```php
C::messages()
    ->success('Success message', 'Prefix:')
    ->error('Error message')
    ->warning('Warning message')
    ->info('Info message')
    ->tip('Tip message')
    ->build()
```

## Summary Component

```php
C::summary()
    ->item('total', 1250, 'Calories')
    ->item('completed', 3, 'Entries')
    ->item('average', 85, '7-Day Avg')
    ->ariaLabel('Daily summary')
    ->build()
```

## Button Component

```php
C::button('Add Exercise')
    ->ariaLabel('Add new exercise')
    ->cssClass('btn-primary btn-success')
    ->initialState('visible')  // 'visible' (default) or 'hidden'
    ->build()
```

**Initial State Options:**
- `visible` (default) - Button shown on page load
- `hidden` - Button hidden on page load (useful when item list starts expanded)

## Item List Component

```php
C::itemList()
    ->item('exercise-1', 'Bench Press', '/add/1', 'In Program', 'in-program', 1)
    ->item('exercise-2', 'Squats', '/add/2', 'Recent', 'recent', 2)
    ->filterPlaceholder('Search exercises...')
    ->noResultsMessage('No exercises found.')
    ->createForm(route('create'), 'exercise_name', ['date' => $date])
    ->initialState('collapsed')  // 'collapsed' (default) or 'expanded'
    ->build()
```

**Initial State Options:**
- `collapsed` (default) - List hidden on page load, shown when "Add" button clicked
- `expanded` - List visible on page load (useful for dedicated add pages)

**Coordinated States Example:**
```php
// Collapsed list (default behavior)
C::button('Add Exercise')->build(),
C::itemList()->items(...)->build()

// Expanded list (quick-add workflow)
C::button('Add Exercise')->initialState('hidden')->build(),
C::itemList()->items(...)->initialState('expanded')->build()
```

## Form Component

### Basic Form
```php
C::form('form-id', 'Form Title')
    ->type('primary')  // primary, success, warning, secondary, danger, info
    ->formAction(route('lift-logs.store'))
    ->deleteAction(route('mobile-entry.remove-form', ['id' => 'form-id']))
    ->submitButton('Log Exercise')
    ->build()
```

### Form with Messages
```php
C::form('workout-1', 'Bench Press')
    ->type('primary')
    ->formAction(route('lift-logs.store'))
    ->message('info', '135 lbs × 10 reps × 3 sets', 'Last time:')
    ->message('tip', 'Try 140 lbs × 10 reps × 3 sets', 'Suggestion:')
    ->build()
```

### Form with Numeric Fields
```php
C::form('workout-1', 'Bench Press')
    ->type('primary')
    ->formAction(route('lift-logs.store'))
    ->numericField('weight', 'Weight:', 135, 5, 45, 500)  // name, label, default, increment, min, max
    ->numericField('reps', 'Reps:', 10, 1, 1, 100)
    ->numericField('sets', 'Sets:', 3, 1, 1, 10)
    ->build()
```

### Form with Select Field
```php
C::form('band-exercise-1', 'Band Pull-aparts')
    ->type('primary')
    ->formAction(route('lift-logs.store'))
    ->selectField('band_color', 'Band Color:', [
        ['value' => 'red', 'label' => 'Red'],
        ['value' => 'blue', 'label' => 'Blue'],
        ['value' => 'green', 'label' => 'Green']
    ], 'red')  // default value
    ->numericField('reps', 'Reps:', 12, 1, 1, 50)
    ->build()
```

### Form with Text Field
```php
C::form('custom-1', 'Custom Entry')
    ->type('secondary')
    ->formAction(route('custom.store'))
    ->textField('name', 'Name:', 'Default', 'Placeholder text')
    ->build()
```

### Form with Comment Field
```php
C::form('workout-1', 'Bench Press')
    ->type('primary')
    ->formAction(route('lift-logs.store'))
    ->commentField('Notes:', 'How did it feel?', 'Default text')
    ->build()
```

### Form with Hidden Fields
```php
C::form('workout-1', 'Bench Press')
    ->type('primary')
    ->formAction(route('lift-logs.store'))
    ->hiddenField('exercise_id', 123)
    ->hiddenField('date', '2025-11-10')
    ->hiddenField('redirect_to', 'mobile-entry-lifts')
    ->build()
```

## Items Component (Logged Items)

### Basic Items
```php
C::items()
    ->item(1, 'Morning Workout', 25, route('edit', 1), route('delete', 1))
        ->message('success', '135 lbs × 10 reps × 3 sets', 'Completed!')
        ->freeformText('Felt great today!')
        ->deleteParams(['date' => '2025-11-10'])
        ->add()
    ->emptyMessage('No workouts logged yet!')
    ->confirmMessage('deleteItem', 'Are you sure?')
    ->build()
```

### Multiple Items
```php
$itemsBuilder = C::items()
    ->confirmMessage('deleteItem', 'Are you sure you want to delete?')
    ->confirmMessage('removeForm', 'Are you sure you want to remove?');

foreach ($logs as $log) {
    $itemsBuilder->item(
        $log->id,
        $log->title,
        null,
        route('edit', $log->id),
        route('delete', $log->id)
    )
    ->message('success', $log->details, 'Completed!')
    ->freeformText($log->comments ?? '')
    ->deleteParams(['redirect_to' => 'mobile-entry-lifts'])
    ->add();
}

if (empty($logs)) {
    $itemsBuilder->emptyMessage('No items yet.');
}

return $itemsBuilder->build()['data'];
```

## Form Types and Colors

| Type | Color | Border | Usage |
|------|-------|--------|-------|
| `primary` | Blue | `#007bff` | Main/important forms (exercises) |
| `success` | Green | `#28a745` | Positive/completion forms (food) |
| `warning` | Yellow | `#ffc107` | Attention forms (measurements) |
| `secondary` | Gray | `#6c757d` | General/default forms |
| `danger` | Red | `#dc3545` | Error/critical forms |
| `info` | Light Blue | `#17a2b8` | Informational forms |

## Complete Controller Example

```php
use App\Services\ComponentBuilder as C;

public function lifts(Request $request, LiftLogService $formService)
{
    $selectedDate = $request->input('date') 
        ? Carbon::parse($request->input('date')) 
        : Carbon::today();
    
    $prevDay = $selectedDate->copy()->subDay();
    $nextDay = $selectedDate->copy()->addDay();
    $today = Carbon::today();
    
    // Get data from services
    $forms = $formService->generateForms(Auth::id(), $selectedDate);
    $loggedItems = $formService->generateLoggedItems(Auth::id(), $selectedDate);
    $itemSelectionList = $formService->generateItemSelectionList(Auth::id(), $selectedDate);
    
    // Build components array
    $components = [];
    
    // Navigation
    $components[] = C::navigation()
        ->prev('← Prev', route('mobile-entry.lifts', ['date' => $prevDay->toDateString()]))
        ->center('Today', route('mobile-entry.lifts', ['date' => $today->toDateString()]))
        ->next('Next →', route('mobile-entry.lifts', ['date' => $nextDay->toDateString()]))
        ->build();
    
    // Title
    $components[] = C::title('Today')->build();
    
    // Messages (if any)
    if ($interfaceMessages['hasMessages']) {
        $messagesBuilder = C::messages();
        foreach ($interfaceMessages['messages'] as $message) {
            $messagesBuilder->add($message['type'], $message['text'], $message['prefix'] ?? null);
        }
        $components[] = $messagesBuilder->build();
    }
    
    // Add Exercise button
    $components[] = C::button('Add Exercise')
        ->ariaLabel('Add new exercise')
        ->build();
    
    // Item selection list
    $itemListBuilder = C::itemList()
        ->filterPlaceholder($itemSelectionList['filterPlaceholder'])
        ->noResultsMessage($itemSelectionList['noResultsMessage']);
    
    foreach ($itemSelectionList['items'] as $item) {
        $itemListBuilder->item(
            $item['id'],
            $item['name'],
            $item['href'],
            $item['type']['label'],
            $item['type']['cssClass'],
            $item['type']['priority']
        );
    }
    
    if (isset($itemSelectionList['createForm'])) {
        $itemListBuilder->createForm(
            $itemSelectionList['createForm']['action'],
            $itemSelectionList['createForm']['inputName'],
            $itemSelectionList['createForm']['hiddenFields']
        );
    }
    
    $components[] = $itemListBuilder->build();
    
    // Forms
    foreach ($forms as $form) {
        $components[] = ['type' => 'form', 'data' => $form];
    }
    
    // Logged items
    $components[] = ['type' => 'items', 'data' => $loggedItems];
    
    $data = ['components' => $components];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

## Tips

1. **Always use `build()`** - Don't forget to call `build()` at the end of the chain
2. **Extract data for forms/items** - Services return `build()['data']` directly
3. **Check for null** - Summary can be null, check before adding to components
4. **Use type-safe methods** - ComponentBuilder provides type hints for all methods
5. **Chain methods** - All builder methods return `$this` for fluent chaining
6. **Empty messages** - Always set `emptyMessage` for items component (even if empty string)

## Common Patterns

### Conditional Components
```php
// Only add summary if it exists
$summary = $formService->generateSummary(Auth::id(), $selectedDate);
if ($summary) {
    $summaryBuilder = C::summary();
    foreach ($summary['values'] as $key => $value) {
        $summaryBuilder->item($key, $value, $summary['labels'][$key] ?? null);
    }
    $components[] = $summaryBuilder->build();
}
```

### Dynamic Messages
```php
$messagesBuilder = C::messages();
if ($sessionMessages['success']) {
    $messagesBuilder->success($sessionMessages['success']);
}
if ($sessionMessages['error']) {
    $messagesBuilder->error($sessionMessages['error']);
}
if ($messagesBuilder->hasMessages()) {
    $components[] = $messagesBuilder->build();
}
```

### Reusing Service Data
```php
// Services return data in the correct format
$forms = $formService->generateForms($userId, $date);
foreach ($forms as $form) {
    $components[] = ['type' => 'form', 'data' => $form];
}

$loggedItems = $formService->generateLoggedItems($userId, $date);
$components[] = ['type' => 'items', 'data' => $loggedItems];
```

## Table Component

### Basic Table
```php
C::table()
    ->row(1, 'Line 1', 'Line 2', 'Line 3', '/edit/1', '/delete/1')
        ->add()
    ->row(2, 'Another row', 'Secondary text', null, '/edit/2', '/delete/2')
        ->add()
    ->emptyMessage('No items yet.')
    ->confirmMessage('deleteItem', 'Are you sure?')
    ->build()
```

### Table with Delete Parameters
```php
C::table()
    ->row(1, 'Workout Name', '30 minutes', 'Last: 2 days ago', '/edit/1', '/delete/1')
        ->deleteParams(['date' => '2025-11-10'])
        ->add()
    ->build()
```

### Table Methods

| Method | Parameters | Description |
|--------|-----------|-------------|
| `row()` | `$id, $line1, $line2, $line3, $editAction, $deleteAction` | Add a row (line2 and line3 optional) |
| `deleteParams()` | `array $params` | Add hidden params to delete form |
| `add()` | - | Finish row and return to table builder |
| `emptyMessage()` | `string $message` | Message when no rows |
| `confirmMessage()` | `string $key, string $message` | Delete confirmation message |
| `ariaLabel()` | `string $label` | Accessibility label |
| `build()` | - | Build the component |

### Complete Table Example (v1.3 Features)
```php
$components[] = C::table()
    ->row(1, 'Morning Workout', '3 exercises', 'Last completed: 2 days ago')
        ->linkAction('fa-edit', route('edit', 1), 'Edit')
        ->formAction('fa-trash', route('delete', 1), 'DELETE', [], 'Delete', 'btn-danger', true)
        // Sub-items with messages and actions
        ->subItem(11, 'Push-ups', '3 sets × 15 reps', 'Tap to log')
            ->message('info', '45 total reps last time', 'History:')
            ->linkAction('fa-play', route('log', 11), 'Log now', 'btn-log-now')
            ->add()
        ->subItem(12, 'Pull-ups', '3 sets × 8 reps', 'Tap to log')
            ->message('tip', 'Try to beat your record!', 'Goal:')
            ->linkAction('fa-play', route('log', 12), 'Log now', 'btn-log-now')
            ->add()
        ->initialState('expanded')  // Start expanded
        ->add()
    ->row(2, 'Evening Workout', '2 exercises')
        ->linkAction('fa-info', route('details', 2), 'Details', 'btn-info-circle')
        ->formAction('fa-trash', route('delete', 2), 'DELETE', [], 'Delete', 'btn-danger', true)
        ->compact()  // 75% button size
        ->subItem(21, 'Squats', 'Felt strong!', null)
            ->message('success', '225 lbs × 5 reps × 5 sets', 'Completed:')
            ->linkAction('fa-pencil', route('edit', 21), 'Edit', 'btn-transparent')
            ->formAction('fa-trash', route('delete', 21), 'DELETE', [], 'Delete', 'btn-danger', true)
            ->add()
        ->add()
    ->row(3, 'Quick Stretches', 'Always visible')
        ->linkAction('fa-edit', route('edit', 3), 'Edit')
        ->subItem(31, 'Neck Rolls', '2 minutes')
            ->linkAction('fa-play', route('log', 31), 'Log')
            ->add()
        ->collapsible(false)  // No expand/collapse
        ->add()
    ->emptyMessage('No workouts yet!')
    ->confirmMessage('deleteItem', 'Are you sure?')
    ->ariaLabel('Workout routines')
    ->build();
```

### Table Row Methods (v1.3)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `linkAction()` | `$icon, $url, $ariaLabel, $cssClass` | Add link button (GET) |
| `formAction()` | `$icon, $url, $method, $params, $ariaLabel, $cssClass, $confirm` | Add form button (POST/DELETE) |
| `subItem()` | `$id, $line1, $line2, $line3` | Add expandable sub-item |
| `titleClass()` | `string $class` | CSS class for title ('cell-title-large' for 1.4em) |
| `compact()` | `bool $compact` | Use 75% button size |
| `collapsible()` | `bool $collapsible` | Enable/disable expand/collapse (default: true) |
| `initialState()` | `string $state` | 'expanded' or 'collapsed' (default: 'collapsed') |

### Table Sub-Item Methods (v1.3)

| Method | Parameters | Description |
|--------|-----------|-------------|
| `linkAction()` | `$icon, $url, $ariaLabel, $cssClass` | Add link button |
| `formAction()` | `$icon, $url, $method, $params, $ariaLabel, $cssClass, $confirm` | Add form button |
| `message()` | `$type, $text, $prefix` | Add inline message |
| `compact()` | `bool $compact` | Use 75% button size |

### Button Style Classes (v1.3)

| Class | Appearance | Use Case |
|-------|-----------|----------|
| `btn-transparent` | White icon, no background | Subtle edit actions |
| `btn-info-circle` | Circle with border | Info/details actions |
| `btn-log-now` | Green background | Primary workout actions |
| `btn-danger` | Red background | Delete actions |

### Message Types for Sub-Items

| Type | Color | Use Case |
|------|-------|----------|
| `success` | Green | Completed actions |
| `info` | Blue | Informational messages |
| `tip` | Yellow | Suggestions/goals |
| `warning` | Orange | Cautions |
| `error` | Red | Errors/issues |
| `neutral` | Gray | General notes |

### Clickable Sub-Items (v1.3)

Sub-items with a single link action automatically become fully clickable:

```php
// Entire row is clickable (not just the button)
->subItem(1, 'Push-ups', '3 sets × 15 reps', 'Tap anywhere to log')
    ->linkAction('fa-play', route('log'), 'Log now', 'btn-log-now')
    ->add()
```

**Features:**
- Larger touch target for mobile
- Visual hover feedback on entire row
- Button still functional
- Automatic detection (no config needed)
