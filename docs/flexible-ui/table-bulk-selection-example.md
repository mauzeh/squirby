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
        ->badge('225 lbs', 'dark', true)  // Emphasized badge for weight (bold, darker)
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

**Badge Emphasis:**
- Regular: `->badge('Text', 'color')` - Standard badge
- Emphasized: `->badge('Text', 'color', true)` - Bold text with darker background (#2d3748)
  - Use for important values like weights, distances, or key metrics
  - Same size as regular badges but stands out more

### 2. Add "Select All" Control

Use the select all control component before the table:

```php
C::selectAllControl('select-all-templates', 'Select All')->build()
```

### 3. Add Bulk Action Form

Use the bulk action form component after the table:

```php
C::bulkActionForm('bulk-delete-form', route('items.bulk-delete'), 'Delete Selected')
    ->confirmMessage('Are you sure you want to delete :count item(s)?')
    ->ariaLabel('Delete selected items')
    ->build()
```

**Optional configuration:**
```php
C::bulkActionForm('bulk-update-form', route('items.bulk-update'), 'Update Selected')
    ->method('PATCH')  // Change HTTP method
    ->buttonClass('btn-primary')  // Change button style
    ->icon('fa-check')  // Change icon
    ->inputName('item_ids')  // Change input name
    ->checkboxSelector('.item-checkbox')  // Change checkbox selector
    ->emptyMessage('Please select items first.')  // Custom empty message
    ->build()
```

The JavaScript file (`public/js/table-bulk-selection.js`) is **automatically included** when components require it. No manual setup needed.



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

// Weight/measurement badges (emphasized - bold, darker)
->badge('225 lbs', 'dark', true)  // Emphasized badge
->badge('Bodyweight', 'dark', true)  // Emphasized badge
->badge('3.5 miles', 'dark', true)  // Emphasized badge for distance

// Custom color
->badge('Custom', '#9b59b6')  // Purple
```

## CSS Classes

The checkbox implementation uses these CSS classes:
- `.template-checkbox` - Individual row checkboxes (you can customize this class name)
- `.has-checkbox` - Added to rows with checkboxes for styling

Badges use CSS classes from `public/css/mobile-entry/table-badges.css` which is automatically included when badges are detected.

## Notes

1. **Automatic script loading**: The `table-bulk-selection.js` file is automatically included when components require it (checkboxes, select all control, or bulk action forms).

2. **Component-based**: All bulk selection features use proper components - no raw HTML needed.

3. **Customizable**: All components have fluent configuration methods for customization.

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
