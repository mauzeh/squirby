# Redirect Logic Refactoring Summary

## Overview
Centralized all conditional redirect logic from controllers into a configurable service.

## Files Created

### 1. `config/redirects.php`
- Centralized configuration for all redirect logic
- Defines redirect targets for each controller action
- Easy to modify without touching controller code

### 2. `app/Services/RedirectService.php`
- Service class that handles redirect logic
- Resolves route parameters from request and context
- Supports dynamic redirects (e.g., food_logs.destroy)
- Provides validation for redirect targets

### 3. `docs/redirect-service.md`
- Complete documentation for the redirect service
- Usage examples and migration guide
- Benefits and testing information

## Files Modified

### 1. `app/Http/Controllers/LiftLogController.php`
**Changes:**
- Added `RedirectService` dependency injection
- Replaced all conditional redirect logic in:
  - `store()` method
  - `update()` method
  - `destroy()` method
- Reduced code duplication significantly

**Before (store method):**
```php
if ($request->input('redirect_to') === 'mobile-entry') {
    $redirectParams = [
        'date' => $request->input('date'),
        'submitted_lift_log_id' => $liftLog->id,
    ];
    return redirect()->route('mobile-entry.lifts', $redirectParams)->with('success', $successMessage);
} elseif ($request->input('redirect_to') === 'mobile-entry-lifts') {
    // ... more conditions
} else {
    return redirect()->route('exercises.show-logs', ['exercise' => $liftLog->exercise_id])->with('success', $successMessage);
}
```

**After:**
```php
return $this->redirectService->getRedirect(
    'lift_logs',
    'store',
    $request,
    [
        'submitted_lift_log_id' => $liftLog->id,
        'exercise' => $liftLog->exercise_id,
    ],
    $successMessage
);
```

### 2. `app/Http/Controllers/BodyLogController.php`
**Changes:**
- Added `RedirectService` dependency injection
- Replaced conditional redirect logic in:
  - `store()` method
  - `update()` method
  - `destroy()` method

### 3. `app/Http/Controllers/FoodLogController.php`
**Changes:**
- Added `RedirectService` dependency injection
- Replaced conditional redirect logic in:
  - `store()` method
  - `destroy()` method (including dynamic redirect support)
  - `addMealToLog()` method

## Configuration Structure

The redirect configuration supports:

1. **Multiple redirect targets per action**
   - `mobile-entry`, `mobile-entry-lifts`, `workout-templates`, etc.
   - `default` fallback for each action

2. **Flexible parameter resolution**
   - From request input
   - From context data
   - Automatic mappings (e.g., `exercise_id` → `exercise`)

3. **Dynamic redirects**
   - Special case for accepting any route name
   - Used in `food_logs.destroy`

## Benefits

1. **Maintainability**: All redirect logic in one config file
2. **Consistency**: Same pattern across all controllers
3. **Testability**: Service can be unit tested independently
4. **Flexibility**: Easy to add new redirect targets
5. **Discoverability**: Clear overview of all redirect paths
6. **Reduced Duplication**: Eliminated repetitive if/else chains

## Backward Compatibility

✅ All existing redirect behavior is preserved
✅ No changes to request parameters or route names
✅ No changes to flash message behavior

## Next Steps (Optional)

1. Add unit tests for `RedirectService`
2. Consider adding redirect logging/analytics
3. Extend to other controllers if needed
4. Add validation for redirect configurations on boot
