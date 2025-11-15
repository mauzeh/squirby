# Table Component - Bulk Selection Example

## Overview

This example demonstrates how to implement checkbox-based bulk selection for the Table Component. While the Table Component doesn't have built-in bulk selection support, this pattern shows how to add it using custom HTML and JavaScript.

## Demo

Visit `/labs/table-bulk-selection` to see it in action!

## Implementation Pattern

### 1. Add Checkbox Support and Badges to Rows

Use the `->checkbox(true)` method on table rows, and optionally add badges for metadata:

```php
C::table()
    ->row(1, 'Item Name', 'Description', null)
        ->checkbox(true)  // Enable checkbox for this row
        ->badge('Today', 'success')  // Green badge
        ->badge('5 x 8', 'neutral')  // Gray badge for sets/reps
        ->badge('225 lbs', 'dark', true)  // Large, bold badge for weight
        ->linkAction('fa-edit', route('items.edit', 1), 'Edit')
        ->formAction('fa-trash', route('items.destroy', 1), 'DELETE', [], 'Delete', 'btn-danger', true)
        ->add()
    ->build()
```

**Badge Colors:**
- `success` - Green (#28a745) - for "Today", "Active", "Complete"
- `info` - Blue (#007bff) - for dates within 7 days, general info
- `warning` - Orange (#e67e22) - for "Yesterday", warnings, attention items
- `danger` - Red (#dc3545) - for errors, urgent items
- `neutral` - Gray (#4a5568) - for counts, neutral info
- `dark` - Dark gray (#2d3748) - for weights, measurements
- Or use any custom hex color like `#ff5733`

**Badge Sizes:**
- Regular: `->badge('Text', 'color')` - Standard size (0.85em, 4px/8px padding)
- Large: `->badge('Text', 'color', true)` - Prominent size (1.1em, 8px/12px padding, bold)
  - Use for important values like weights, distances, or key metrics

### 2. Add "Select All" Control

Use a raw HTML component before the table:

```php
[
    'type' => 'raw_html',
    'html' => '
        <div class="container" style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="select-all-templates" style="width: 20px; height: 20px;">
                <span>Select All</span>
            </label>
        </div>
    '
]
```

### 3. Add Bulk Action Form

The JavaScript file (`public/js/table-bulk-selection.js`) is **automatically included** when the Table Component detects that any row has a checkbox enabled. You don't need to manually reference it in your controller.

Use a raw HTML component after the table for the bulk action form:

```php
[
    'type' => 'raw_html',
    'data' => [
        'html' => '
            <div class="container" style="margin-top: 20px;">
                <form action="' . route('items.bulk-delete') . '" method="POST" id="bulk-delete-form" onsubmit="return confirmBulkDelete();">
                    ' . csrf_field() . '
                    <button type="submit" class="button delete" style="width: 100%;">
                        <i class="fa-solid fa-trash"></i> Delete Selected
                    </button>
                </form>
            </div>
        '
    ]
]
```

**JavaScript file** (`public/js/table-bulk-selection.js`):

```javascript
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all-templates');
    const checkboxes = document.querySelectorAll('.template-checkbox');
    const bulkForm = document.getElementById('bulk-delete-form');

    // Select all functionality
    if (selectAll) {
        selectAll.addEventListener('change', function (e) {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
    }

    // Update select-all state when individual checkboxes change
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const allChecked = Array.from(checkboxes).every((cb) => cb.checked);
            const someChecked = Array.from(checkboxes).some((cb) => cb.checked);
            if (selectAll) {
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        });
    });

    // Make rows clickable to toggle checkbox
    const rows = document.querySelectorAll('.component-table-row.has-checkbox');
    rows.forEach(function (row) {
        row.style.cursor = 'pointer';

        row.addEventListener('click', function (e) {
            // Don't toggle if clicking on interactive elements
            if (e.target.closest('a, button, form, input, .table-expand-icon')) {
                return;
            }

            // Find the checkbox in this row
            const checkbox = row.querySelector('.template-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            }
        });
    });

    // Bulk delete form submission
    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const checkedBoxes = document.querySelectorAll('.template-checkbox:checked');

            if (checkedBoxes.length === 0) {
                alert('Please select at least one template to delete.');
                return false;
            }

            checkedBoxes.forEach(function (checkbox) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = checkbox.value;
                bulkForm.appendChild(input);
            });

            bulkForm.submit();
        });
    }
});

function confirmBulkDelete() {
    const count = document.querySelectorAll('.template-checkbox:checked').length;
    if (count === 0) {
        alert('Please select at least one template to delete.');
        return false;
    }
    return confirm('Are you sure you want to delete ' + count + ' template(s)?');
}
```

### 4. Handle Bulk Action in Controller

```php
public function bulkDelete(Request $request)
{
    if ($request->isMethod('post') && $request->has('selected_ids')) {
        $selectedIds = $request->input('selected_ids', []);
        $count = count($selectedIds);
        
        // Delete items
        WorkoutTemplate::whereIn('id', $selectedIds)
            ->where('user_id', auth()->id())
            ->delete();
        
        $message = $count === 1 
            ? 'Successfully deleted 1 item.'
            : "Successfully deleted {$count} items.";
        
        return redirect()->back()->with('success', $message);
    }
    
    // Show the page
    // ...
}
```

## Key Features

- **Select All checkbox** with indeterminate state support
- **Individual row checkboxes** that update the Select All state
- **Clickable rows** - Click anywhere on a row to toggle its checkbox
- **Smart click detection** - Buttons, links, and expand icons still work normally
- **Mobile-friendly badges** - Display metadata like dates, counts, and status
- **Color-coded badges** - Visual indicators for different types of information
- **Bulk delete confirmation** before submission
- **Dynamic form submission** that collects selected IDs
- **Success messages** showing count of deleted items

## Badge Usage Examples

Badges are perfect for displaying metadata in a mobile-friendly way:

```php
// Date-based badges (like lift-logs)
->badge('Today', 'success')
->badge('Yesterday', 'warning')
->badge('3 days ago', 'info')
->badge('11/10', 'neutral')

// Count/quantity badges
->badge('5 exercises', 'neutral')
->badge('3x per week', 'info')
->badge('4 x 8', 'neutral')  // Sets x reps

// Status badges
->badge('Active', 'success')
->badge('Pending', 'warning')
->badge('Archived', 'neutral')

// Weight/measurement badges (larger, darker, bold)
->badge('225 lbs', 'dark', true)  // Large badge
->badge('Bodyweight', 'dark', true)  // Large badge
->badge('3.5 miles', 'dark', true)  // Large badge for distance

// Custom color
->badge('Custom', '#9b59b6')  // Purple
```

## CSS Classes

The checkbox implementation uses these CSS classes:
- `.template-checkbox` - Individual row checkboxes (you can customize this class name)
- `.has-checkbox` - Added to rows with checkboxes for styling

Badges use CSS classes from `public/css/mobile-entry/table-badges.css` which is automatically included when badges are detected.

## Notes

1. **Automatic script loading**: The `table-bulk-selection.js` file is automatically included when the Table Component detects checkboxes. No manual script references needed.

2. **Pattern-based**: While checkboxes are supported via the `->checkbox()` method, the bulk action form and "Select All" control are custom HTML that you add as needed.

3. **Customizable**: Change the checkbox class name, form ID, and JavaScript selectors to match your needs.

3. **Works with sub-items**: Checkboxes work alongside expandable sub-items.

4. **Mobile-friendly**: Checkboxes are sized appropriately (20px) for touch targets.

5. **Accessibility**: Uses proper labels and ARIA attributes.

## How It Works

The Table Component automatically detects component features and includes required assets:

**Checkboxes:**
1. Detects when any row has `->checkbox(true)`
2. Sets a `requiresScript` flag on the component
3. Flexible view automatically includes `public/js/table-bulk-selection.js`

**Badges:**
1. Detects when any row has `->badge()`
2. Sets a `requiresStyle` flag on the component
3. Flexible view automatically includes `public/css/mobile-entry/table-badges.css`

This means you never have to manually manage asset includes - just use the methods and the rest happens automatically.

## When to Use

Use this pattern when you need:
- Bulk delete functionality
- Bulk status updates
- Bulk export/download
- Any multi-item operations

## Alternative Approaches

If you need more complex bulk operations, consider:
- Creating a dedicated bulk selection component
- Using a JavaScript framework (Vue, React) for state management
- Implementing server-side selection tracking with sessions
