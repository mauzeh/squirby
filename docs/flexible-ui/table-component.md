# Table Component

## Overview

The table component provides a tabular CRUD list optimized for narrow mobile screens. Each row displays up to 3 lines of text with edit and delete action buttons.

## Features

- **Mobile-optimized**: Designed for narrow screens with touch-friendly buttons
- **Multi-line rows**: Support for up to 3 lines of text per row
- **CRUD actions**: Built-in edit and delete buttons for each row
- **Responsive**: Adapts to different screen sizes
- **Accessible**: Proper ARIA labels and keyboard navigation
- **Confirmation dialogs**: Optional delete confirmations

## Visual Design

Each row consists of:
- **Line 1**: Bold, primary text (e.g., item name)
- **Line 2**: Secondary text (e.g., description, metadata)
- **Line 3**: Muted, italic text (e.g., additional details)
- **Action buttons**: Edit (orange) and Delete (red) icons

## Usage

### Basic Example

```php
use App\Services\ComponentBuilder as C;

$components[] = C::table()
    ->row(1, 'Item Name', 'Description', 'Details', '/edit/1', '/delete/1')
        ->add()
    ->row(2, 'Another Item', 'More info', null, '/edit/2', '/delete/2')
        ->add()
    ->emptyMessage('No items yet.')
    ->build();
```

### With Delete Confirmation

```php
$components[] = C::table()
    ->row(1, 'Workout', '30 min', 'Last: 2 days ago', '/edit/1', '/delete/1')
        ->deleteParams(['date' => '2025-11-10'])
        ->add()
    ->confirmMessage('deleteItem', 'Are you sure you want to delete this workout?')
    ->build();
```

### Complete Controller Example

```php
public function workouts(Request $request)
{
    $workouts = Workout::where('user_id', Auth::id())->get();
    
    $tableBuilder = C::table()
        ->emptyMessage('No workouts yet. Create your first routine!')
        ->confirmMessage('deleteItem', 'Are you sure you want to delete this workout?')
        ->ariaLabel('Workout routines');
    
    foreach ($workouts as $workout) {
        $tableBuilder->row(
            $workout->id,
            $workout->name,
            $workout->duration . ' minutes • ' . $workout->frequency,
            'Last completed: ' . $workout->last_completed_at?->diffForHumans(),
            route('workouts.edit', $workout->id),
            route('workouts.destroy', $workout->id)
        )
        ->deleteParams(['redirect' => 'workouts'])
        ->add();
    }
    
    $data = [
        'components' => [
            C::title('My Workouts')->build(),
            $tableBuilder->build(),
            C::button('Add New Workout')
                ->ariaLabel('Create a new workout routine')
                ->build(),
        ]
    ];
    
    return view('mobile-entry.flexible', compact('data'));
}
```

## API Reference

### TableComponentBuilder Methods

#### `row($id, $line1, $line2, $line3, $editAction, $deleteAction)`
Add a row to the table.

**Parameters:**
- `int $id` - Row identifier
- `string $line1` - First line (bold, primary text) - **Required**
- `string|null $line2` - Second line (secondary text) - Optional
- `string|null $line3` - Third line (muted, italic text) - Optional
- `string $editAction` - Edit URL - **Required**
- `string $deleteAction` - Delete URL - **Required**

**Returns:** `TableRowBuilder` for chaining row-specific methods

#### `emptyMessage($message)`
Set the message displayed when no rows exist.

**Parameters:**
- `string $message` - Empty state message

**Returns:** `self`

#### `confirmMessage($key, $message)`
Add a confirmation dialog for delete actions.

**Parameters:**
- `string $key` - Message key (typically 'deleteItem')
- `string $message` - Confirmation message text

**Returns:** `self`

#### `ariaLabel($label)`
Set the ARIA label for the table section.

**Parameters:**
- `string $label` - Accessibility label

**Returns:** `self`

#### `build()`
Build the component and return the array structure.

**Returns:** `array` with keys `type` and `data`

### TableRowBuilder Methods

#### `deleteParams($params)`
Add hidden parameters to the delete form (e.g., redirect URL, date).

**Parameters:**
- `array $params` - Key-value pairs for hidden inputs

**Returns:** `self`

#### `add()`
Finish configuring the row and return to the parent table builder.

**Returns:** `TableComponentBuilder`

## Styling

The table component uses these CSS classes:

- `.component-table-section` - Container section
- `.component-table` - Table wrapper
- `.component-table-row` - Individual row
- `.component-table-cell` - Cell containing text lines
- `.cell-line` - Base class for text lines
- `.cell-line-1` - First line (bold)
- `.cell-line-2` - Second line (secondary)
- `.cell-line-3` - Third line (muted, italic)
- `.component-table-actions` - Action buttons container
- `.btn-table-edit` - Edit button
- `.btn-table-delete` - Delete button
- `.component-table-empty` - Empty state message

## Responsive Behavior

### Desktop (> 768px)
- Full padding and spacing
- Larger touch targets
- Hover effects enabled

### Tablet (480px - 768px)
- Reduced padding
- Adjusted font sizes
- Maintained touch targets

### Mobile (< 480px)
- Minimal padding for maximum content
- Smaller but still accessible buttons
- Optimized for one-handed use

## Accessibility

- Proper ARIA labels for screen readers
- Keyboard navigation support
- Focus indicators on all interactive elements
- Sufficient color contrast
- Touch targets meet minimum size requirements (44x44px)

## JavaScript Integration

The table component integrates with the mobile-entry.js delete confirmation system:

```javascript
// Automatically handles delete confirmations
// Reads messages from data-table-confirm-messages attribute
// Shows browser confirm() dialog before submitting delete form
```

## Best Practices

1. **Keep line 1 concise** - This is the primary identifier, keep it short
2. **Use line 2 for metadata** - Duration, frequency, counts, etc.
3. **Line 3 is optional** - Only use if you have additional context
4. **Provide empty messages** - Always set a helpful empty state message
5. **Use confirmation dialogs** - Prevent accidental deletions
6. **Add delete params** - Include redirect URLs or context data
7. **Set ARIA labels** - Improve accessibility for screen readers

## Examples

### Simple List
```php
C::table()
    ->row(1, 'Task 1', null, null, '/edit/1', '/delete/1')->add()
    ->row(2, 'Task 2', null, null, '/edit/2', '/delete/2')->add()
    ->emptyMessage('No tasks.')
    ->build()
```

### Rich Data
```php
C::table()
    ->row(
        $exercise->id,
        $exercise->name,
        $exercise->sets . ' sets × ' . $exercise->reps . ' reps',
        'Last: ' . $exercise->last_performed_at->diffForHumans(),
        route('exercises.edit', $exercise),
        route('exercises.destroy', $exercise)
    )
    ->deleteParams(['date' => $date->toDateString()])
    ->add()
```

### With All Features
```php
C::table()
    ->row(1, 'Item', 'Details', 'More info', '/edit/1', '/delete/1')
        ->deleteParams(['redirect' => 'items', 'date' => '2025-11-10'])
        ->add()
    ->emptyMessage('No items found. Add your first item!')
    ->confirmMessage('deleteItem', 'Delete this item permanently?')
    ->ariaLabel('Items list')
    ->build()
```

## Testing

When testing table components:

```php
// Access table component
$tableComponent = collect($data['components'])
    ->where('type', 'table')
    ->first();

// Check rows
$this->assertCount(3, $tableComponent['data']['rows']);

// Check first row
$firstRow = $tableComponent['data']['rows'][0];
$this->assertEquals('Item Name', $firstRow['line1']);
$this->assertEquals('Description', $firstRow['line2']);
$this->assertEquals('Details', $firstRow['line3']);

// Check empty message
$this->assertEquals('No items yet.', $tableComponent['data']['emptyMessage']);
```

## Demo

Visit `/flexible/table-example` to see a working demo of the table component.

---

**Component Type:** `table`  
**View File:** `resources/views/mobile-entry/components/table.blade.php`  
**Builder Class:** `TableComponentBuilder`  
**CSS Classes:** `.component-table-*`
