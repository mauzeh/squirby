# Redirect Service Documentation

## Overview

The `RedirectService` centralizes all conditional redirect logic that was previously scattered across multiple controllers. This makes redirect behavior more maintainable, testable, and configurable.

## Configuration

All redirect configurations are stored in `config/redirects.php`. The structure is:

```php
'controller_name' => [
    'action_name' => [
        'redirect_target' => [
            'route' => 'route.name',
            'params' => ['param1', 'param2'],
        ],
        'default' => [
            'route' => 'default.route.name',
            'params' => ['param1'],
        ],
    ],
],
```

### Example Configuration

```php
'lift_logs' => [
    'store' => [
        'mobile-entry' => [
            'route' => 'mobile-entry.lifts',
            'params' => ['date', 'submitted_lift_log_id'],
        ],
        'default' => [
            'route' => 'exercises.show-logs',
            'params' => ['exercise_id'],
        ],
    ],
],
```

## Usage in Controllers

### Basic Usage

```php
use App\Services\RedirectService;

class MyController extends Controller
{
    protected $redirectService;

    public function __construct(RedirectService $redirectService)
    {
        $this->redirectService = $redirectService;
    }

    public function store(Request $request)
    {
        // ... your logic ...

        return $this->redirectService->getRedirect(
            'controller_name',  // Config key for this controller
            'store',            // Action name
            $request,           // Request object
            [                   // Context data for route parameters
                'id' => $model->id,
                'other_param' => $value,
            ],
            'Success message'   // Flash message (optional)
        );
    }
}
```

### How Parameters Are Resolved

The service resolves route parameters in this order:

1. **Context array** - Values passed in the `$context` parameter
2. **Request input** - Values from `$request->input()`
3. **Special mappings** - Automatic conversions:
   - `exercise_id` → `exercise` (for exercise routes)
   - `template_id` → `id` (legacy support)
   - `workout_id` → `id` (for workout routes)

### Dynamic Redirects

For cases where the redirect route is passed directly (like `food_logs.destroy`):

```php
'food_logs' => [
    'destroy' => [
        'dynamic' => true,  // Accepts any route name
        'default' => [
            'route' => 'food-logs.index',
            'params' => ['date'],
        ],
    ],
],
```

## Benefits

1. **Centralized Configuration** - All redirect logic in one place
2. **Easy to Modify** - Change redirect behavior without touching controller code
3. **Testable** - Service can be unit tested independently
4. **Consistent** - Same pattern across all controllers
5. **Discoverable** - Easy to see all redirect paths in the config file

## Migration Guide

### Before (Old Pattern)

```php
if ($request->input('redirect_to') === 'mobile-entry') {
    return redirect()->route('mobile-entry.lifts', [
        'date' => $request->input('date'),
        'id' => $model->id,
    ])->with('success', 'Success!');
} else {
    return redirect()->route('default.route')->with('success', 'Success!');
}
```

### After (New Pattern)

```php
return $this->redirectService->getRedirect(
    'controller_name',
    'action',
    $request,
    ['id' => $model->id],
    'Success!'
);
```

## Adding New Redirect Configurations

1. Open `config/redirects.php`
2. Add your controller and action configuration
3. Inject `RedirectService` into your controller
4. Replace redirect logic with `$this->redirectService->getRedirect()`

## Special Parameter Mappings

The service includes special handling for parameters that need to be renamed for route compatibility:

### `exercise_id` → `exercise`
Exercise routes expect the parameter to be named `exercise`, but forms and configs use `exercise_id`.

```php
// Config uses 'exercise_id'
'params' => ['exercise_id']

// Service maps to 'exercise' for the route
route('exercises.show-logs', ['exercise' => 123])
```

### `workout_id` → `id`
Workout routes expect the parameter to be named `id`, but forms and configs use `workout_id` for clarity.

```php
// Config uses 'workout_id'
'params' => ['workout_id']

// Service maps to 'id' for the route
route('workouts.index', ['id' => 5])
```

### `template_id` → `id` (Legacy)
Legacy support for the old template naming convention.

```php
// Config uses 'template_id'
'params' => ['template_id']

// Service maps to 'id' for the route
route('workouts.index', ['id' => 5])
```

## Testing

The service includes a validation method to check if a redirect target is valid:

```php
$isValid = $this->redirectService->isValidRedirectTarget(
    'lift_logs',
    'store',
    'mobile-entry'
);
```

This can be used in tests or validation logic.
