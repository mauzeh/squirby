# Table Component - Quick Start

## 30-Second Setup

```php
use App\Services\ComponentBuilder as C;

// In your controller
$data = [
    'components' => [
        C::table()
            ->row(1, 'Name', 'Description', 'Details', '/edit/1', '/delete/1')
                ->add()
            ->emptyMessage('No items yet.')
            ->build()
    ]
];

return view('mobile-entry.flexible', compact('data'));
```

## Row Structure

```
┌─────────────────────────────────────────┬──────┬──────┐
│ Line 1 (bold, primary)                  │ Edit │ Del  │
│ Line 2 (secondary)                      │  🖊   │  🗑   │
│ Line 3 (muted, italic)                  │      │      │
└─────────────────────────────────────────┴──────┴──────┘
```

## Common Patterns

### Simple List
```php
C::table()
    ->row(1, 'Task Name', null, null, '/edit/1', '/delete/1')->add()
    ->build()
```

### With All Lines
```php
C::table()
    ->row(
        $item->id,
        $item->name,                    // Line 1: Bold title
        $item->description,             // Line 2: Secondary info
        'Updated: ' . $item->updated_at, // Line 3: Metadata
        route('items.edit', $item),
        route('items.destroy', $item)
    )
    ->add()
    ->build()
```

### With Delete Confirmation
```php
C::table()
    ->row(1, 'Item', 'Info', null, '/edit/1', '/delete/1')
        ->deleteParams(['date' => '2025-11-10'])
        ->add()
    ->confirmMessage('deleteItem', 'Delete this item?')
    ->build()
```

### Loop Through Data
```php
$tableBuilder = C::table()
    ->emptyMessage('No items found.')
    ->confirmMessage('deleteItem', 'Are you sure?');

foreach ($items as $item) {
    $tableBuilder->row(
        $item->id,
        $item->name,
        $item->description,
        null,
        route('items.edit', $item),
        route('items.destroy', $item)
    )->add();
}

$components[] = $tableBuilder->build();
```

## Method Cheat Sheet

| Method | What It Does |
|--------|-------------|
| `row()` | Add a row (id, line1, line2, line3, edit, delete) |
| `deleteParams()` | Add hidden params to delete form |
| `add()` | Finish row, return to table |
| `emptyMessage()` | Message when no rows |
| `confirmMessage()` | Delete confirmation text |
| `ariaLabel()` | Accessibility label |
| `build()` | Build the component |

## Tips

1. **Line 2 and 3 are optional** - Pass `null` if not needed
2. **Always call `add()`** - After configuring each row
3. **Set empty message** - Users appreciate helpful empty states
4. **Use confirmations** - Prevent accidental deletions
5. **Add delete params** - Include redirect URLs or context

## Demo

Visit `/flexible/table-example` to see it in action!

## Full Documentation

See `docs/flexible-ui/table-component.md` for complete details.
