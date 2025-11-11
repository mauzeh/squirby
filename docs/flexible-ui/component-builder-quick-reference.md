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

```php
C::title('Main Title', 'Optional Subtitle')->build()
```

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
    ->build()
```

## Item List Component

```php
C::itemList()
    ->item('exercise-1', 'Bench Press', '/add/1', 'In Program', 'in-program', 1)
    ->item('exercise-2', 'Squats', '/add/2', 'Recent', 'recent', 2)
    ->filterPlaceholder('Search exercises...')
    ->noResultsMessage('No exercises found.')
    ->createForm(route('create'), 'exercise_name', ['date' => $date])
    ->build()
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

### Complete Table Example
```php
$components[] = C::table()
    ->row(
        1,
        'Morning Cardio',
        '30 minutes • 3x per week',
        'Last completed: 2 days ago',
        route('workouts.edit', 1),
        route('workouts.destroy', 1)
    )
    ->deleteParams(['redirect' => 'workouts'])
    ->add()
    ->row(
        2,
        'Upper Body Strength',
        'Bench Press, Rows, Shoulder Press',
        '45 minutes • Mon, Wed, Fri',
        route('workouts.edit', 2),
        route('workouts.destroy', 2)
    )
    ->deleteParams(['redirect' => 'workouts'])
    ->add()
    ->emptyMessage('No workouts yet. Create your first routine!')
    ->confirmMessage('deleteItem', 'Are you sure you want to delete this workout?')
    ->ariaLabel('Workout routines')
    ->build();
```
