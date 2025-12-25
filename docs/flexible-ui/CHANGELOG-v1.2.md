# Changelog - Version 1.2

**Release Date:** November 11, 2025

## Overview

Version 1.2 adds several enhancements to improve navigation, support multiple item lists, and provide better user experience with auto-scroll and context-aware behaviors.

## New Features

### 1. Title Component Back Button

Add navigation buttons directly to page titles for cleaner layouts.

**API:**
```php
C::title('Page Title', 'Subtitle')
    ->backButton('fa-arrow-left', route('index'), 'Back')
    ->build()
```

**Features:**
- Icon-only button on the left
- Title/subtitle remain centered
- 44px touch target for mobile
- Customizable icon and aria label

**Documentation:** [title-back-button.md](title-back-button.md)

**Example:** `/flexible/title-back-button`

### 2. Multiple Independent Item Lists

Support multiple item selection lists on the same page, each with independent state.

**Features:**
- Each button/list pair operates independently
- Automatic linking by proximity in component array
- Each can have different initial states
- No conflicts or interference

**Example:**
```php
// First list - collapsed
C::button('Add Exercise')->addClass('btn-add-item')->build(),
C::itemList()->items(...)->build(),

// Second list - expanded
C::button('Add Meal')->addClass('btn-add-item')->initialState('hidden')->build(),
C::itemList()->items(...)->initialState('expanded')->build(),
```

**Documentation:** [initial-state.md](initial-state.md#multiple-independent-lists)

**Example:** `/flexible/multiple-lists`

### 3. Auto-Scroll and Focus for Expanded Lists

When item lists start expanded, they automatically scroll into view and focus the filter input.

**Features:**
- Scrolls to position filter input 5% from top of viewport
- Focuses input field for immediate typing
- Opens mobile keyboard on mobile devices
- Identical behavior whether expanded manually or via initial state

**Technical:**
- Uses double `requestAnimationFrame` for reliable DOM rendering
- 300ms delay for smooth animation
- Works on page load and button click

**Documentation:** [initial-state.md](initial-state.md#auto-scroll-and-focus)

### 4. Context-Aware Initial States

Conditionally set initial state based on URL parameters or other context.

**Example:**
```php
public function edit(Request $request, $id)
{
    $shouldExpand = $request->query('expand') === 'true';
    
    $buttonBuilder = C::button('Add Exercise')->addClass('btn-add-item');
    if ($shouldExpand) {
        $buttonBuilder->initialState('hidden');
    }
    
    $listBuilder = C::itemList()->items(...);
    if ($shouldExpand) {
        $listBuilder->initialState('expanded');
    }
    
    // ...
}
```

**Use Case:** Link with `?expand=true` to open page with list pre-expanded

**Documentation:** [initial-state.md](initial-state.md#context-aware-initial-states)

**Example:** `/workout-templates/{id}/edit?expand=true`

### 5. Submenu Wrapping

Submenus now wrap to multiple lines when there are too many items.

**Change:**
- Changed `.sub-navbar` from `overflow: hidden` to `overflow: visible`
- Added `display: flex` and `flex-wrap: wrap`
- Prevents menu items from overflowing off screen

**Benefit:** Flexible workflow submenu can display all examples without horizontal scrolling

## Bug Fixes

### Fixed: Incorrect CSS Class Reference

**Issue:** Auto-scroll wasn't working because JavaScript referenced `.item-filter-container` instead of `.component-filter-container`

**Fix:** Updated all references to use correct class name

**Files:** `public/js/mobile-entry.js`

### Fixed: Inconsistent Behavior Between Manual and Auto-Expanded Lists

**Issue:** Lists expanded via `initialState('expanded')` didn't focus input or scroll, while manually clicked lists did

**Fix:** Unified code paths to use same `showItemSelection()` function for both scenarios

**Files:** `public/js/mobile-entry.js`

## Examples Added

New examples in `FlexibleWorkflowController`:

1. **Multiple Lists** (`/flexible/multiple-lists`)
   - Two independent lists (exercises and meals)
   - Mixed initial states (one collapsed, one expanded)
   - Demonstrates independent operation

2. **Title Back Button** (`/flexible/title-back-button`)
   - Shows back button with centered title
   - Links back to main flexible page
   - Includes form content

3. **Expanded List** (`/flexible/expanded-list`)
   - Single list starting expanded
   - Demonstrates auto-scroll and focus
   - Button hidden initially

## Production Usage

### Workout Templates

The workout template edit page now uses:
- **Back button** in title for navigation
- **Template details form** for editing name/description
- **Context-aware expansion** via `?expand=true` parameter
- **Edit button** on template rows for regular editing
- **Add Exercise button** on sub-items for quick adding

**Routes:**
- `/workout-templates/{id}/edit` - Regular edit (collapsed list)
- `/workout-templates/{id}/edit?expand=true` - Quick add (expanded list)

## Breaking Changes

None - all changes are backward compatible.

## Performance

- No performance impact
- JavaScript uses efficient DOM queries
- CSS uses hardware-accelerated transforms
- Scroll animations use `requestAnimationFrame`

## Browser Compatibility

All features work in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Testing

All existing tests continue to pass. No new test failures introduced.

## Documentation Updates

- Updated [README.md](README.md) with v1.2 changes
- Updated [component-builder-quick-reference.md](component-builder-quick-reference.md) with back button
- Updated [initial-state.md](initial-state.md) with advanced features
- Created [title-back-button.md](title-back-button.md) for new feature
- Created this changelog

## Contributors

- Enhanced by Kiro AI Assistant
- Tested in production environment
- Feedback incorporated from real-world usage

## Next Steps

Potential future enhancements:
- Additional title button positions (right side)
- Multiple buttons in title
- Animated transitions for list expansion
- Configurable scroll position (not just 5%)
- Persistent expanded state (localStorage)

## Support

For questions or issues:
1. Check the [Quick Reference](component-builder-quick-reference.md)
2. Review [Initial State docs](initial-state.md)
3. Look at working examples in `/flexible/*` routes
4. Check [Testing Guide](testing.md) for test patterns

---

**Version:** 1.2  
**Status:** Production Ready ✅  
**Release Date:** November 11, 2025
