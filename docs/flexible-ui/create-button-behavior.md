# Create Button Behavior

## Overview

The create button in item selection lists now uses a contextual, user-friendly approach that only appears when it makes sense and clearly shows what will be created.

## Behavior

The create button:
- **Hidden by default** - Only appears when needed
- **Shows on no results** - Appears when user has typed a search term but no items match
- **Dynamic text** - Displays exactly what will be created: `Create "Bulgarian Split Squat"`
- **Configurable** - Button text template can be customized per implementation

## User Flow

```
User clicks "Add Exercise" or "Add Food"
↓
Item list appears with search filter
↓
User types "Bulgarian Split Squat"
↓
Has matching items? → Show items (no create button)
↓
No matching items? → Show helpful message:
  "No matches found for 'Bulgarian Split Squat'"
  "Click above to create"
  [Create "Bulgarian Split Squat"] button
↓
User clicks create button → New item created and form appears
```

## Configuration

### Backend (Services)

In your service's `generateItemSelectionList` method:

```php
return [
    'createForm' => [
        'action' => route('mobile-entry.create-exercise'),
        'inputName' => 'exercise_name',
        'buttonTextTemplate' => 'Create "{term}"',  // Configurable text
        'ariaLabel' => 'Create new exercise',
        'hiddenFields' => [
            'date' => $selectedDate->toDateString()
        ]
    ],
    // ... other config
];
```

### Template Placeholders

The `buttonTextTemplate` supports the `{term}` placeholder which gets replaced with the user's search term:

- `'Create "{term}"'` → "Create 'Bulgarian Split Squat'"
- `'Add new: {term}'` → "Add new: Bulgarian Split Squat"
- `'+ {term}'` → "+ Bulgarian Split Squat"

### Controller Usage

```php
if (isset($itemSelectionList['createForm'])) {
    $itemListBuilder->createForm(
        $itemSelectionList['createForm']['action'],
        $itemSelectionList['createForm']['inputName'],
        $itemSelectionList['createForm']['hiddenFields'],
        $itemSelectionList['createForm']['buttonTextTemplate'] ?? 'Create "{term}"'
    );
}
```

## No-Results Message

When no items match the search, the message dynamically updates to be more helpful:

**Before (static):**
```
No exercises found
No matches
```

**After (dynamic):**
```
No matches found for "Bulgarian Split Squat"
Click above to create
```

The message:
- Shows the exact search term the user typed
- Guides them to use the create button below
- Only appears when there's a search term with no results

## Technical Implementation

### JavaScript Logic

The button visibility is controlled in `public/js/mobile-entry.js`:

```javascript
// Show button only when: has search term AND no results
if (normalizedSearch !== '' && !hasResults) {
    const displayTerm = normalizedSearch.length > 30 
        ? normalizedSearch.substring(0, 30) + '...' 
        : normalizedSearch;
    
    const buttonText = buttonTextTemplate.replace('{term}', displayTerm);
    createButtonText.textContent = buttonText;
    createForm.style.display = '';
} else {
    createForm.style.display = 'none';
}
```

### Features

- **Truncation**: Long search terms are truncated to 30 characters with "..."
- **Accessibility**: ARIA label updates dynamically with the full search term
- **Real-time**: Updates as user types in the search filter
- **Contextual help**: No-results message updates to show the search term and guide users to create

## Benefits

1. **Clarity**: Users immediately understand what clicking the button will do
2. **Context**: Button only appears when it's relevant (no matches found)
3. **Discoverability**: Users naturally discover the create feature when they can't find what they're looking for
4. **Prevents accidents**: No accidental creates when browsing existing items
5. **Flexibility**: Text template can be customized per use case

## Current Implementations

- **mobile-entry/lifts**: `'Create "{term}"'` for exercises
- **mobile-entry/foods**: `'Create "{term}"'` for ingredients
- **workouts/edit**: `'Create "{term}"'` for exercises in workout templates
