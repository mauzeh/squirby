# WOD Syntax Autocomplete

## Overview

Minimal autocomplete implementation for exercise names in the WOD syntax editor.

## How It Works

### 1. API Endpoint
- **Route**: `GET /api/exercises/autocomplete`
- **Controller**: `ExerciseAutocompleteController`
- **Returns**: JSON array of exercise names available to the user

### 2. JavaScript Implementation
- Fetches exercise list on page load
- Triggers when typing inside brackets `[` or `[[`
- Shows dropdown with matching exercises (max 10)
- Filters by substring match (case-insensitive)

### 3. User Experience
- Type `[[Back` → shows "Back Squat", "Back Extension", etc.
- Arrow keys to navigate
- Enter to select
- Escape to close
- Click to select
- Auto-hides when typing outside brackets

### 4. Styling
- Dark theme matching editor
- Positioned below cursor
- Scrollable list
- Hover highlighting

## Files Modified

1. **app/Http/Controllers/ApiController.php** (new)
   - Centralized API controller for future API endpoints
   - `exerciseAutocomplete()` method fetches user's available exercises
   - Returns as JSON array

2. **tests/Feature/ApiControllerTest.php** (new)
   - Comprehensive test coverage for exercise autocomplete
   - Tests user filtering, global exercises, sorting, authentication
   - 8 test cases with 28 assertions

3. **routes/web.php**
   - Added autocomplete API route

3. **public/js/mobile-entry/components/code-editor-autocomplete.js** (new)
   - Standalone autocomplete module
   - Exposes `window.CodeEditorAutocomplete.init()`
   - All autocomplete logic in one file

4. **public/js/mobile-entry/components/code-editor.js**
   - Calls autocomplete init if available
   - Removed inline autocomplete code

5. **app/Http/Controllers/WorkoutController.php**
   - Changed `requiresScript` to array format for WOD components
   - Includes both code-editor and autocomplete scripts

6. **resources/views/mobile-entry/flexible.blade.php**
   - Updated to support both string and array format for `requiresScript`
   - Maintains backward compatibility with existing components

## Complexity

**LOW** - Minimal implementation with ~150 lines of JavaScript:
- No external dependencies
- Simple substring matching
- Basic dropdown UI
- Works with existing editor architecture

## Future Enhancements

- Fuzzy matching (e.g., "bsq" → "Back Squat")
- Show exercise type icons
- Recently used exercises at top
- Cache exercise list in localStorage
- Debounce API calls if list is large
