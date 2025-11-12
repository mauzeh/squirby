# Title Component - Back Button Feature

> **New in v1.2** - Added November 11, 2025

## Overview

The title component now supports an optional back button that appears on the left side of the title. The title and subtitle remain centered, providing a clean navigation pattern for detail pages.

## Basic Usage

```php
C::title('Exercise Details', 'View and edit exercise information')
    ->backButton('fa-arrow-left', route('exercises.index'), 'Back to exercises')
    ->build()
```

## Parameters

The `backButton()` method accepts three parameters:

1. **icon** (required) - FontAwesome icon class (e.g., 'fa-arrow-left', 'fa-times', 'fa-chevron-left')
2. **url** (required) - Destination URL (use `route()` helper)
3. **ariaLabel** (optional) - Accessibility label (defaults to 'Go back')

## Visual Layout

```
[←]        Page Title        
           Subtitle Text
```

- Back button: 44px × 44px touch target on the left
- Title/subtitle: Centered with padding to account for button
- Responsive: Works on all screen sizes

## Examples

### Simple Back Button
```php
C::title('Edit Template')
    ->backButton('fa-arrow-left', route('workout-templates.index'))
    ->build()
```

### With Custom Icon and Label
```php
C::title('Exercise Details', 'Bench Press')
    ->backButton('fa-times', route('exercises.index'), 'Close and return to list')
    ->build()
```

### With Subtitle
```php
C::title('Workout Template', 'Edit exercises and details')
    ->subtitle('Last modified: 2 days ago')
    ->backButton('fa-chevron-left', route('workout-templates.index'), 'Back to templates')
    ->build()
```

## Use Cases

### Detail Pages
Perfect for pages that show details of a single item:
- Exercise details
- Template editor
- Food item details
- Measurement history

### Edit Pages
Provides clear navigation back to list view:
- Edit workout template
- Edit exercise
- Edit meal

### Nested Navigation
When you have multiple levels of navigation:
- Main list → Detail view → Edit form
- Each level can have a back button to previous level

## Styling

The back button automatically includes:
- **Background:** Secondary background color (#3a3a3a)
- **Hover:** Lighter background with primary color text
- **Active:** Darker background for touch feedback
- **Border radius:** 5px for rounded corners
- **Transition:** Smooth 0.2s ease animation

## Accessibility

- **Touch target:** 44px minimum for mobile accessibility
- **ARIA label:** Descriptive label for screen readers
- **Keyboard navigation:** Fully keyboard accessible
- **Focus indicator:** Visual focus state for keyboard users

## CSS Classes

The back button uses these classes:
- `.component-title-back-button` - Main button styling
- `.component-title-row` - Flex container for button + title
- `.component-title-content` - Centered title/subtitle wrapper

## Best Practices

1. **Use consistent icons** - Stick to `fa-arrow-left` or `fa-chevron-left` throughout your app
2. **Descriptive labels** - Provide clear aria labels like "Back to exercises" not just "Back"
3. **Logical navigation** - Back button should go to the logical parent page
4. **Don't overuse** - Not every page needs a back button (main pages don't need them)

## Common Patterns

### Template Editor Pattern
```php
C::title($template->name, 'Edit template')
    ->backButton('fa-arrow-left', route('workout-templates.index'), 'Back to templates')
    ->build()
```

### Detail View Pattern
```php
C::title($exercise->title, 'Exercise details')
    ->backButton('fa-arrow-left', route('exercises.index'), 'Back to exercises')
    ->build()
```

### Close Pattern (Modal-like)
```php
C::title('Quick Add', 'Add exercise to today')
    ->backButton('fa-times', route('mobile-entry.lifts'), 'Close')
    ->build()
```

## Live Example

Visit `/flexible/title-back-button` to see a working example with:
- Back button linking to main flexible page
- Centered title and subtitle
- Form content below

## Migration from Separate Button

If you previously used a separate button component for back navigation:

### Before
```php
$components[] = C::button('← Back')->asLink(route('index'))->build();
$components[] = C::title('Page Title')->build();
```

### After
```php
$components[] = C::title('Page Title')
    ->backButton('fa-arrow-left', route('index'), 'Back')
    ->build();
```

**Benefits:**
- Cleaner layout (button integrated with title)
- Better visual hierarchy
- Consistent positioning
- Less code

## Technical Implementation

The back button is rendered as part of the title component:
- Positioned absolutely on the left
- Title content has padding to maintain centering
- Uses flexbox for responsive layout
- No JavaScript required (pure CSS)

## Browser Support

Works in all modern browsers:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Related Documentation

- [ComponentBuilder Quick Reference](component-builder-quick-reference.md)
- [Mobile Entry Documentation](mobile-entry.md)
- [Initial State Configuration](initial-state.md)
