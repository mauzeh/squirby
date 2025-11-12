# Table Component - Quick Start

## 30-Second Setup

```php
use App\Services\ComponentBuilder as C;

// In your controller
$data = [
    'components' => [
        C::table()
            ->row(1, 'Name', 'Description', 'Details')
                ->linkAction('fa-edit', '/edit/1', 'Edit')
                ->formAction('fa-trash', '/delete/1', 'DELETE', [], 'Delete', 'btn-danger', true)
                ->add()
            ->emptyMessage('No items yet.')
            ->build()
    ]
];

return view('mobile-entry.flexible', compact('data'));
```

## Row Structure

```
┌─┬─────────────────────────────────────────┬──────┬──────┬──────┐
│►│ Line 1 (bold, primary)                  │ Act1 │ Act2 │ Del  │
│ │ Line 2 (secondary)                      │  ⚡  │  🖊   │  🗑   │
│ │ Line 3 (muted, italic)                  │      │      │      │
└─┴─────────────────────────────────────────┴──────┴──────┴──────┘
  ▼ Sub-items (expandable)
  ├─ Sub-item 1 (darker background)         │ Act1 │ Del  │
  └─ Sub-item 2                             │ Act1 │ Del  │
```

## Common Patterns

### Simple List
```php
C::table()
    ->row(1, 'Task Name', null, null)
        ->linkAction('fa-edit', '/edit/1', 'Edit')
        ->formAction('fa-trash', '/delete/1', 'DELETE', [], 'Delete', 'btn-danger', true)
        ->add()
    ->build()
```

### With Custom Action Colors
```php
C::table()
    ->row(1, 'Exercise', '3 sets × 10 reps', '135 lbs')
        ->linkAction('fa-play', '/log/1', 'Log now', 'btn-log-now') // Green
        ->linkAction('fa-edit', '/edit/1', 'Edit') // Orange
        ->formAction('fa-trash', '/delete/1', 'DELETE', [], 'Delete', 'btn-danger', true) // Red
        ->add()
    ->build()
```

### With Sub-Items (Expandable)
```php
C::table()
    ->row(1, 'Workout', '3 exercises', null)
        ->linkAction('fa-edit', '/edit/1', 'Edit')
        ->formAction('fa-trash', '/delete/1', 'DELETE', [], 'Delete', 'btn-danger', true)
        ->subItem(11, 'Bench Press', '4 sets × 8 reps', null)
            ->linkAction('fa-play', '/log/11', 'Log now', 'btn-log-now')
            ->formAction('fa-trash', '/remove/11', 'DELETE', [], 'Remove', 'btn-danger', true)
            ->add()
        ->subItem(12, 'Rows', '4 sets × 10 reps', null)
            ->linkAction('fa-play', '/log/12', 'Log now', 'btn-log-now')
            ->formAction('fa-trash', '/remove/12', 'DELETE', [], 'Remove', 'btn-danger', true)
            ->add()
        ->add()
    ->confirmMessage('deleteItem', 'Delete this item?')
    ->build()
```

### With Non-Collapsible Sub-Items
```php
C::table()
    ->row(1, 'Quick Stretches', 'Always visible', null)
        ->linkAction('fa-edit', '/edit/1', 'Edit')
        ->subItem(11, 'Neck Rolls', '2 minutes', null)
            ->linkAction('fa-play', '/log/11', 'Log now', 'btn-log-now')
            ->add()
        ->collapsible(false) // No expand button, always visible
        ->add()
    ->build()
```

### Loop Through Data
```php
$tableBuilder = C::table()
    ->emptyMessage('No items found.')
    ->confirmMessage('deleteItem', 'Are you sure?');

foreach ($items as $item) {
    $rowBuilder = $tableBuilder->row(
        $item->id,
        $item->name,
        $item->description,
        null
    )
    ->linkAction('fa-edit', route('items.edit', $item), 'Edit')
    ->formAction('fa-trash', route('items.destroy', $item), 'DELETE', 
        ['redirect' => 'items'], 'Delete', 'btn-danger', true);
    
    // Add sub-items if any
    foreach ($item->children as $child) {
        $rowBuilder->subItem($child->id, $child->name, null, null)
            ->linkAction('fa-play', route('children.log', $child), 'Log', 'btn-log-now')
            ->add();
    }
    
    $rowBuilder->add();
}

$components[] = $tableBuilder->build();
```

## Method Cheat Sheet

| Method | What It Does |
|--------|-------------|
| `row()` | Add a row (id, line1, line2, line3) |
| `linkAction()` | Add link button (icon, url, label, cssClass) |
| `formAction()` | Add form button (icon, url, method, params, label, cssClass, confirm) |
| `subItem()` | Add sub-item to row (id, line1, line2, line3) |
| `collapsible()` | Set if sub-items are collapsible (default: true) |
| `add()` | Finish row/sub-item, return to parent |
| `emptyMessage()` | Message when no rows |
| `confirmMessage()` | Delete confirmation text |
| `ariaLabel()` | Accessibility label |
| `build()` | Build the component |

## Action Button CSS Classes

| Class | Color | Use For |
|-------|-------|---------|
| (default) | Orange | Edit, view, general actions |
| `btn-log-now` | Green | Positive actions (log, start, apply) |
| `btn-danger` | Red | Destructive actions (delete, remove) |
| `btn-disabled` | Gray | Disabled state (e.g., move up at top) |

## Tips

1. **Line 2 and 3 are optional** - Pass `null` if not needed
2. **Always call `add()`** - After configuring each row and sub-item
3. **Set empty message** - Users appreciate helpful empty states
4. **Use confirmations** - Set `requiresConfirm: true` for destructive actions
5. **Add form params** - Include redirect URLs in formAction params array
6. **Limit actions** - 2-3 actions per row is ideal for mobile
7. **Use appropriate icons** - fa-edit, fa-trash, fa-play, fa-arrow-up, etc.
8. **Sub-items are one level** - Don't nest sub-items within sub-items
9. **Collapsible by default** - Sub-items are hidden until expanded
10. **Fat-finger friendly** - Expand button has 44px touch target

## Demo

Visit `/flexible/table-example` to see it in action!

## Full Documentation

See `docs/flexible-ui/table-component.md` for complete details.
