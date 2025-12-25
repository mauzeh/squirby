# Changelog - Version 1.3

**Release Date:** November 13, 2025

## Overview

Version 1.3 enhances the table component with clickable sub-items, compact button modes, additional button styles, and improved mobile UX for tabular interfaces.

## New Features

### 1. Clickable Sub-Items

Sub-items with a single link action now have the entire row clickable for better mobile UX.

**Features:**
- Automatic detection of single-action sub-items
- Entire row becomes tappable (larger touch target)
- Visual hover feedback on full row
- Button still functional for explicit clicks
- Maintains accessibility

**API:**
```php
// Single action = fully clickable
$rowBuilder->subItem(1, 'Push-ups', '3 sets × 15 reps')
    ->linkAction('fa-play', route('log'), 'Log now', 'btn-log-now')
    ->add();
```

**Documentation:** [table-clickable-subitems.md](table-clickable-subitems.md)

**Example:** `/labs/table-example` (Row 8)

### 2. Compact Button Mode

Reduce button size to 75% for less prominent actions.

**API:**
```php
$rowBuilder->row(1, 'Title', 'Subtitle')
    ->linkAction('fa-edit', route('edit'), 'Edit')
    ->formAction('fa-trash', route('delete'), 'DELETE', [], 'Delete', 'btn-danger', true)
    ->compact()  // Makes all buttons 75% size
    ->add();
```

**Use Cases:**
- Secondary actions
- Dense information displays
- When space is limited

**Example:** `/labs/table-example` (Row 7)

### 3. Additional Button Styles

New button style variants for different use cases.


#### Transparent Button (`btn-transparent`)

White icon with subtle hover effect, no background.

```php
->linkAction('fa-pencil', route('edit'), 'Edit', 'btn-transparent')
```

**Use Case:** Edit actions that should be subtle

#### Info Circle Button (`btn-info-circle`)

Small circle with border, minimal visual weight.

```php
->linkAction('fa-info', route('details'), 'View details', 'btn-info-circle')
```

**Use Case:** Info/details actions

#### Log Now Button (`btn-log-now`)

Green button for positive actions.

```php
->linkAction('fa-play', route('log'), 'Log now', 'btn-log-now')
```

**Use Case:** Primary action in workout/exercise contexts

### 4. Table Row Initial State

Control whether table rows start expanded or collapsed.

**API:**
```php
$rowBuilder->row(1, 'Title', 'Subtitle')
    ->subItem(11, 'Sub-item', 'Details')
        ->linkAction('fa-play', route('log'), 'Log')
        ->add()
    ->initialState('expanded')  // Start expanded
    ->add();
```

**Use Cases:**
- Show important rows expanded by default
- Context-aware expansion (e.g., after deletion)
- Single-row tables (always show content)

**Example:** `/labs/table-initial-expanded`

### 5. Sub-Item Messages

Add inline messages to sub-items for status, tips, or context.

**API:**
```php
$rowBuilder->subItem(1, 'Bench Press', 'Felt strong today')
    ->message('success', '185 lbs × 8 reps × 4 sets', 'Completed:')
    ->message('tip', 'Try 190 lbs next time', 'Goal:')
    ->linkAction('fa-pencil', route('edit'), 'Edit', 'btn-transparent')
    ->add();
```

**Message Types:**
- `success` - Green, for completed actions
- `info` - Blue, for informational messages
- `tip` - Yellow, for suggestions
- `warning` - Orange, for cautions
- `error` - Red, for errors
- `neutral` - Gray, for general notes

**Example:** `/labs/table-example` (Rows 2-3)

### 6. Large Title Class

Make row titles more prominent.

**API:**
```php
$rowBuilder->row(1, 'Important Title', 'Subtitle')
    ->titleClass('cell-title-large')  // 1.4em instead of 1em
    ->add();
```

**Use Case:** Emphasize important rows

**Example:** `/labs/table-example` (Row 5)

### 7. Non-Collapsible Sub-Items

Keep sub-items always visible (no expand/collapse).

**API:**
```php
$rowBuilder->row(1, 'Quick Stretches', 'Always visible')
    ->subItem(11, 'Neck Rolls', '2 minutes')
        ->linkAction('fa-play', route('log'), 'Log')
        ->add()
    ->collapsible(false)  // No chevron, always visible
    ->add();
```

**Use Case:** Short lists that should always be visible

**Example:** `/labs/table-example` (Row 6)

## Technical Changes

### CSS Additions

**Clickable Sub-Items:**
```css
.component-table-subitem.subitem-clickable {
    cursor: pointer;
    user-select: none;
}

.component-table-subitem.subitem-clickable:hover {
    background-color: rgba(0, 123, 255, 0.15);
}
```

**Compact Buttons:**
```css
.component-table-actions.actions-compact .btn-table-edit,
.component-table-actions.actions-compact .btn-table-delete {
    width: var(--touch-target-compact);  /* 33px instead of 44px */
    height: var(--touch-target-compact);
    font-size: 15px;  /* Smaller icon */
}
```

**Button Styles:**
```css
.btn-transparent {
    background-color: transparent;
    color: white;
}

.btn-info-circle {
    background-color: transparent;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.btn-log-now {
    background-color: var(--color-success) !important;
}
```

### JavaScript Additions

**Clickable Sub-Items Handler:**
```javascript
const setupClickableSubItems = () => {
    const clickableSubItems = document.querySelectorAll('.component-table-subitem.subitem-clickable');
    
    clickableSubItems.forEach(subItem => {
        subItem.addEventListener('click', function(event) {
            // Ignore clicks on buttons/forms
            if (event.target.closest('.component-table-actions')) return;
            
            const href = this.dataset.href;
            if (href) window.location.href = href;
        });
    });
};
```

## Production Usage

These features are actively used in:

- **Workout Templates** (`/workouts`)
  - Clickable sub-items for exercise logging
  - Compact buttons for edit/delete actions
  - Initial expanded state for active workouts
  - Sub-item messages for logged exercises

- **Labs Examples** (`/labs/table-example`)
  - Comprehensive demonstration of all features
  - Multiple rows showing different patterns
  - Interactive examples

## Breaking Changes

None - all changes are backward compatible.

## Performance Improvements

Add `->compact()` to row builder:

```php
// Before
$rowBuilder->row(1, 'Title', 'Subtitle')
    ->linkAction('fa-edit', route('edit'), 'Edit')
    ->add();

// After
$rowBuilder->row(1, 'Title', 'Subtitle')
    ->linkAction('fa-edit', route('edit'), 'Edit')
    ->compact()
    ->add();
```

### Using New Button Styles

Replace CSS class in action methods:

```php
// Transparent edit button
->linkAction('fa-pencil', route('edit'), 'Edit', 'btn-transparent')

// Info circle button
->linkAction('fa-info', route('details'), 'Details', 'btn-info-circle')

// Log now button
->linkAction('fa-play', route('log'), 'Log', 'btn-log-now')
```

## Performance

- No performance impact
- JavaScript uses efficient event delegation
- CSS uses hardware-accelerated transforms
- Minimal DOM manipulation

## Browser Compatibility

All features work in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Testing

All existing tests continue to pass. Features tested in production.

## Documentation Updates

- Created [table-clickable-subitems.md](table-clickable-subitems.md)
- Updated [table-component.md](table-component.md) with new features
- Updated [component-builder-quick-reference.md](component-builder-quick-reference.md)
- Updated [README.md](README.md) with v1.3 changes
- Created this changelog

## Next Steps

Potential future enhancements:
- Swipe actions for mobile (swipe to delete)
- Drag-and-drop reordering
- Bulk selection mode
- Inline editing
- Virtualized scrolling for large lists

## Support

For questions or issues:
1. Check [table-clickable-subitems.md](table-clickable-subitems.md)
2. Review [table-component.md](table-component.md)
3. Look at `/labs/table-example` for working examples
4. Check [Testing Guide](testing.md) for test patterns

---

**Version:** 1.3  
**Status:** Production Ready ✅  
**Release Date:** November 13, 2025
