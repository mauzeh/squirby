# Menu Service

The `MenuService` centralizes all navigation menu logic, removing hardcoded conditionals from the `app.blade.php` layout.

## Purpose

- Removes complex menu logic from Blade templates
- Centralizes menu configuration in one place
- Makes it easy to add/modify menu items
- Handles role-based menu visibility
- Manages active state detection

## How It Works

The service is automatically injected into the `app.blade.php` view via a View Composer in `AppServiceProvider`:

```php
View::composer('app', function ($view) {
    if (Auth::check()) {
        $menuService = app(\App\Services\MenuService::class);
        $view->with('menuService', $menuService);
    }
});
```

## Usage in Blade

### Main Navigation

```blade
@foreach($menuService->getMainMenu() as $item)
    <a @if(isset($item['id']))id="{{ $item['id'] }}"@endif 
       href="{{ route($item['route']) }}" 
       class="top-level-nav-item {{ $item['active'] ? 'active' : '' }}">
        <i class="fas {{ $item['icon'] }}"></i> {{ $item['label'] }}
    </a>
@endforeach
```

### Utility Menu (Right Side)

```blade
@foreach($menuService->getUtilityMenu() as $item)
    @if(isset($item['type']) && $item['type'] === 'logout')
        {{-- Special handling for logout button --}}
    @else
        <a href="{{ route($item['route']) }}" class="{{ $item['active'] ? 'active' : '' }}">
            <i class="fas {{ $item['icon'] }}"></i>
        </a>
    @endif
@endforeach
```

### Sub-Navigation

```blade
@if ($menuService->shouldShowSubMenu())
    <div class="navbar sub-navbar">
        @foreach($menuService->getSubMenu() as $item)
            <a href="{{ isset($item['routeParams']) ? route($item['route'], $item['routeParams']) : route($item['route']) }}" 
               class="{{ $item['active'] ? 'active' : '' }}">
                @if(isset($item['icon']))<i class="fas {{ $item['icon'] }}"></i>@endif
                @if(isset($item['label'])){{ $item['label'] }}@endif
            </a>
        @endforeach
    </div>
@endif
```

## Menu Item Structure

Each menu item is an array with these possible keys:

```php
[
    'id' => 'optional-element-id',           // Optional HTML id attribute
    'label' => 'Menu Text',                  // Text to display (null for icon-only)
    'icon' => 'fa-icon-name',                // FontAwesome icon class
    'route' => 'route.name',                 // Laravel route name
    'routeParams' => [$param1, $param2],     // Optional route parameters
    'active' => true/false,                  // Whether this item is currently active
    'style' => 'padding: 14px 8px',          // Optional inline styles
    'title' => 'Tooltip text',               // Optional title attribute
    'type' => 'logout',                      // Special type for logout button
]
```

## Adding a New Main Menu Item

Edit `getMainMenu()` in `MenuService.php`:

```php
public function getMainMenu(): array
{
    return [
        // ... existing items ...
        [
            'label' => 'Sleep',
            'icon' => 'fa-bed',
            'route' => 'mobile-entry.sleep',
            'active' => Request::routeIs(['sleep.*', 'mobile-entry.sleep']),
        ],
    ];
}
```

## Adding a New Sub-Menu Section

1. Add route detection in `getSubMenu()`:

```php
if (Request::routeIs(['sleep.*', 'mobile-entry.sleep'])) {
    return $this->getSleepSubMenu();
}
```

2. Create the sub-menu method:

```php
protected function getSleepSubMenu(): array
{
    return [
        [
            'label' => null,
            'icon' => 'fa-mobile-alt',
            'route' => 'mobile-entry.sleep',
            'active' => Request::routeIs(['mobile-entry.sleep']),
        ],
        [
            'label' => 'History',
            'route' => 'sleep-logs.index',
            'active' => Request::routeIs('sleep-logs.*'),
        ],
    ];
}
```

3. Update `shouldShowSubMenu()` to include the new routes:

```php
public function shouldShowSubMenu(): bool
{
    return Request::routeIs([
        // ... existing routes ...
        'sleep.*', 'mobile-entry.sleep',
    ]);
}
```

## Role-Based Menu Items

The service automatically handles role-based visibility:

```php
if (Auth::user()->hasRole('Admin')) {
    $items[] = [
        'label' => 'Admin Panel',
        'icon' => 'fa-cog',
        'route' => 'admin.index',
        'active' => Request::routeIs('admin.*'),
    ];
}
```

## Dynamic Menu Items

For menus that need database data (like measurement types):

```php
$measurementTypes = \App\Models\MeasurementType::where('user_id', Auth::id())
    ->orderBy('name')
    ->get();

foreach ($measurementTypes as $measurementType) {
    $items[] = [
        'label' => $measurementType->name,
        'route' => 'body-logs.show-by-type',
        'routeParams' => [$measurementType],
        'active' => Request::is('body-logs/type/' . $measurementType->id),
    ];
}
```

## Benefits

- **Cleaner Views**: Blade template is now simple loops instead of nested conditionals
- **Centralized Logic**: All menu configuration in one service
- **Testable**: Menu logic can be unit tested
- **Maintainable**: Easy to add/modify menu items
- **Consistent**: Same structure for all menu types
- **Type-Safe**: IDE autocomplete works better with structured arrays
