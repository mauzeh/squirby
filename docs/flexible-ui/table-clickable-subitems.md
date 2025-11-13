# Table Component: Clickable Sub-Items

**Feature:** Sub-items with a single action become fully clickable for better mobile UX  
**Added:** November 13, 2025  
**Status:** ✅ Production Ready

## Overview

When a table sub-item has only one link action, the entire row becomes clickable instead of requiring users to tap a small button. This significantly improves mobile usability by providing a larger touch target.

## How It Works

### Automatic Detection

The system automatically detects sub-items with a single link action and applies the `subitem-clickable` CSS class. No special configuration needed.

```php
// Single action = fully clickable row
$rowBuilder->subItem(1, 'Push-ups', '3 sets × 15 reps')
    ->linkAction('fa-play', route('log'), 'Log now', 'btn-log-now')
    ->add();

// Multiple actions = normal behavior (buttons only)
$rowBuilder->subItem(2, 'Pull-ups', '3 sets × 8 reps')
    ->linkAction('fa-play', route('log'), 'Log now', 'btn-log-now')
    ->formAction('fa-trash', route('delete'), 'DELETE', [], 'Delete', 'btn-danger', true)
    ->add();
```

### Visual Feedback

Clickable sub-items show hover effects on the entire row:
- Background color changes on hover
- Cursor changes to pointer
- Entire row is tappable on mobile

### Button Behavior

The action button is still rendered and functional:
- Clicking the button works normally
- Clicking anywhere else on the row also triggers the action
- Provides visual indication of what will happen

## Use Cases

### 1. Quick Workout Logging

Perfect for workout templates where users tap exercises to log them:

```php
C::table()
    ->row(1, 'Morning Workout', '3 exercises')
        ->linkAction('fa-edit', route('edit'), 'Edit')
        ->subItem(11, 'Push-ups', '3 sets × 15 reps', 'Tap to log')
            ->linkAction('fa-play', route('log'), 'Log now', 'btn-log-now')
            ->add()
        ->subItem(12, 'Pull-ups', '3 sets × 8 reps', 'Tap to log')
            ->linkAction('fa-play', route('log'), 'Log now', 'btn-log-now')
            ->add()
        ->add()
    ->build();
```

### 2. Navigation Lists

When sub-items are primarily for navigation:

```php
C::table()
    ->row(1, 'Settings', 'Configure your preferences')
        ->subItem(11, 'Profile Settings', 'Update your profile')
            ->linkAction('fa-chevron-right', route('profile'), 'View')
            ->add()
        ->subItem(12, 'Privacy Settings', 'Manage privacy')
            ->linkAction('fa-chevron-right', route('privacy'), 'View')
            ->add()
        ->add()
    ->build();
```

### 3. Selection Lists

When users need to select from a list of options:

```php
C::table()
    ->row(1, 'Choose Exercise', 'Select an exercise to add')
        ->subItem(11, 'Bench Press', 'Chest exercise')
            ->linkAction('fa-plus', route('add', ['exercise' => 1]), 'Add')
            ->add()
        ->subItem(12, 'Squats', 'Leg exercise')
            ->linkAction('fa-plus', route('add', ['exercise' => 2]), 'Add')
            ->add()
        ->add()
    ->build();
```

## Technical Details

### JavaScript Implementation

Located in `public/js/mobile-entry.js`:

```javascript
const setupClickableSubItems = () => {
    const clickableSubItems = document.querySelectorAll('.component-table-subitem.subitem-clickable');
    
    clickableSubItems.forEach(subItem => {
        subItem.addEventListener('click', function(event) {
            // Ignore clicks on action buttons
            if (event.target.closest('.component-table-actions') || 
                event.target.closest('a') || 
                event.target.closest('button') || 
                event.target.closest('form')) {
                return;
            }
            
            // Navigate to the URL
            const href = this.dataset.href;
            if (href) {
                window.location.href = href;
            }
        });
    });
};
```

### CSS Styling

Located in `public/css/mobile-entry.css`:

```css
.component-table-subitem.subitem-clickable {
    cursor: pointer;
    user-select: none;
}

@media (hover: hover) {
    .component-table-subitem.subitem-clickable:hover {
        background-color: rgba(0, 123, 255, 0.15);
    }
}

.component-table-subitem.subitem-clickable:active {
    background-color: rgba(0, 123, 255, 0.25);
}
```

### Blade Template Logic

The view automatically detects single-action sub-items:

```blade
@php
    $isSingleAction = count($subItem['actions']) === 1 && 
                      $subItem['actions'][0]['type'] === 'link';
    $clickableClass = $isSingleAction ? 'subitem-clickable' : '';
    $dataHref = $isSingleAction ? $subItem['actions'][0]['url'] : '';
@endphp

<div class="component-table-subitem {{ $clickableClass }}" 
     @if($dataHref) data-href="{{ $dataHref }}" @endif>
    <!-- content -->
</div>
```

## Best Practices

### Do ✅

- Use for navigation-heavy interfaces
- Use when the primary action is obvious
- Combine with visual hints (e.g., "Tap to log")
- Use consistent button styles (e.g., all `btn-log-now`)

### Don't ❌

- Don't use when multiple actions are needed
- Don't use for destructive actions (delete, remove)
- Don't mix clickable and non-clickable sub-items in the same list
- Don't rely solely on button icon to indicate action

## Accessibility

The feature maintains full accessibility:

- Button still has proper aria-label
- Keyboard navigation works (tab to button, enter to activate)
- Screen readers announce the button action
- Focus indicators remain visible

## Browser Compatibility

Works in all modern browsers:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Examples

See working examples in:
- `/labs/table-example` - Row 8 demonstrates clickable sub-items
- `/workouts` - Workout templates use this for exercise logging

## Related Features

- [Table Component](table-component.md) - Main table documentation
- [Table Initial Expanded](table-component.md#initial-state) - Auto-expand rows
- [Compact Buttons](table-component.md#compact-mode) - Smaller button sizes

---

**Status:** Production Ready ✅  
**Last Updated:** November 13, 2025
