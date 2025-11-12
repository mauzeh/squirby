# Redirect Parameters Flow

## Problem
When clicking "Log Now" from workout templates, the redirect parameters (`redirect_to` and `template_id`) were being lost because the flow goes through an intermediate route (`add-lift-form`) before reaching the final form page (`mobile-entry.lifts`).

## Solution
Pass redirect parameters through the entire chain using URL parameters and hidden form fields.

## Flow

### 1. Initial Click (Workout Templates)
```
User clicks "Log Now" on workout template
↓
URL: /mobile-entry/add-lift-form/{exercise}?date=2025-11-12&redirect_to=workout-templates&template_id=5
```

### 2. Add Lift Form (Intermediate Route)
**Controller:** `MobileEntryController::addLiftForm()`
- Receives redirect params from URL
- Creates the mobile lift form in database
- **Passes redirect params forward** in redirect URL

```php
$redirectParams = ['date' => $selectedDate->toDateString()];

if ($request->has('redirect_to')) {
    $redirectParams['redirect_to'] = $request->input('redirect_to');
}

if ($request->has('template_id')) {
    $redirectParams['template_id'] = $request->input('template_id');
}

return redirect()->route('mobile-entry.lifts', $redirectParams);
```

### 3. Mobile Entry Lifts Page
**Controller:** `MobileEntryController::lifts()`
- Receives redirect params from URL
- Captures them and passes to service

```php
$redirectParams = [];
if ($request->has('redirect_to')) {
    $redirectParams['redirect_to'] = $request->input('redirect_to');
}
if ($request->has('template_id')) {
    $redirectParams['template_id'] = $request->input('template_id');
}

$forms = $formService->generateForms(Auth::id(), $selectedDate, $redirectParams);
```

### 4. Form Generation
**Service:** `LiftLogService::generateForms()`
- Receives redirect params as method parameter
- **Embeds them as hidden fields** in the form

```php
$hiddenFields = [
    'exercise_id' => $exercise->id,
    'date' => $selectedDate->toDateString(),
    'mobile_lift_form_id' => $form->id
];

if (!empty($redirectParams['redirect_to'])) {
    $hiddenFields['redirect_to'] = $redirectParams['redirect_to'];
    
    if (!empty($redirectParams['template_id'])) {
        $hiddenFields['template_id'] = $redirectParams['template_id'];
    }
} else {
    $hiddenFields['redirect_to'] = 'mobile-entry-lifts';
}
```

### 5. Form Submission
When user submits the lift log form:
- Hidden fields are submitted with the form data
- `LiftLogController::store()` receives them via `$request->input()`
- `RedirectService` uses them to determine where to redirect

### 6. Final Redirect
**Service:** `RedirectService::getRedirect()`
- Reads `redirect_to` and `template_id` from request
- Redirects back to workout templates with the template expanded

```
Redirect to: /workout-templates?id=5
```

## Key Points

1. **No database storage needed** - params flow through URL and form fields
2. **Backward compatible** - if no redirect params provided, defaults to `mobile-entry-lifts`
3. **Clean separation** - each layer only knows about its immediate next step
4. **Works with validation errors** - hidden fields persist through form resubmission

## Files Modified

- `app/Http/Controllers/MobileEntryController.php` - Pass params through intermediate route
- `app/Services/MobileEntry/LiftLogService.php` - Accept params and embed in hidden fields
