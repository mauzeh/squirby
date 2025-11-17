# Migration Plan: Refactor `lift-logs/edit` to Generic Flexible Component System

**Goal:**  
Unify the lift logs editing interface (`lift-logs/id/edit`) with your generic component-based flexible layout, as used for MobileEntry. This migration will simplify your codebase, reduce duplication, and make maintenance easier.

---

## Overview

- Replace legacy Blade logic and component with a service-driven, flexible component system for editing lift logs.
- Render the edit form using the generic `components.flexible` layout.
- Move all form building logic to the service layer for consistency.
- Remove/deprecate old Blade files and PHP component classes.
- Update tests and documentation.

---

## Migration Steps

### 1. Controller: `LiftLogController.php`

**Before:**
- `edit()` method loads `$liftLog`, `$exercises`, then returns a Blade view with `<x-lift-log-form-component ... />`.

**After:**
- Refactor `edit()` to use a service (`MobileEntry\LiftLogService`) to build an edit form component.
- Pass `data['components']` to the generic flexible view for rendering.
- Example:
    ```php
    public function edit(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $user = auth()->user();
        $exercises = Exercise::with('aliases')->get();
        $formComponent = app(MobileEntry\LiftLogService::class)->generateEditFormComponent($liftLog, $exercises, $user->id);
        $data = [
            'components' => [$formComponent],
            'autoscroll' => true
        ];
        return view('components.flexible', compact('data'));
    }
    ```
---

### 2. Service: `MobileEntry\LiftLogService.php`

**Add a method:**
- `generateEditFormComponent($liftLog, $exercises, $userId)`  
  - Composes a form using the same component & field structure as MobileEntry.
  - All form logic, fields, validations, and UI configuration live here.

---

### 2.5. Factory: `LiftLogFormFactory.php`

**Role:**
- This new factory is responsible for building the specific form fields required for lift log entry, abstracting the logic from `MobileEntry\LiftLogService`.
- It encapsulates the creation of numeric fields, select fields, and other input types based on exercise type strategies and default values.

---

### 3. View: `resources/views/lift-logs/edit.blade.php`

**Before:**
- Contains legacy Blade logic and component usage.

**After:**
- Replace with a minimal wrapper that uses the generic flexible view:
    ```blade
    @extends('app')
    @section('content')
        <div class="container">
            @include('components.flexible', ['data' => $data])
        </div>
    @endsection
    ```
- Or, simply delete and rely on the generic layout.

---

### 4. View: `resources/views/components/flexible.blade.php`

- Make sure this view supports all needed component types for lift log editing.
- If necessary, adapt/copy from `mobile-entry.flexible.blade.php`.

---

### 5. Blade Component: `components/lift-log-form.blade.php`  
### 6. PHP Component: `app/View/Components/LiftLogFormComponent.php`

- Remove these files and associated logic once the flexible system fully powers edit/add views.

---

### 7. Other UI Fragments / Helpers

- Audit for UI partials used only in legacy edit forms; migrate or delete as appropriate.

---

### 8. Tests

- Update feature/integration tests so they validate flexible component rendering and logic.

---

### 9. Documentation

- Update docs to reflect that lift logs edit now uses the generic, flexible, service-driven system.

---

## Summary Table

| File                                       | Action                                  |
|---------------------------------------------|-----------------------------------------|
| `LiftLogController.php`                     | Refactor edit() to use flexible system  |
| `MobileEntry/LiftLogService.php`                        | Add edit form builder method            |
| `Services/Factories/LiftLogFormFactory.php` | New factory for building form fields    |
| `lift-logs/edit.blade.php`                  | Replace with flexible layout            |
| `components/flexible.blade.php`             | Ensure supports all needed types        |
| `components/lift-log-form.blade.php`        | Remove/deprecate                        |
| `View/Components/LiftLogFormComponent.php`  | Remove/deprecate                        |
| Other UI partials/helpers                   | Remove/migrate as needed                |
| Tests                                      | Update for new flexible rendering       |
| Docs                                       | Update migration and usage instructions |

---

## Example References

- MobileEntry migration: `mobile-entry.flexible.blade.php`
- Flexible component builder: `MobileEntry\LiftLogService::generateForms`
- Docs: See `docs/flexible-ui/migration-guide.md`

---

**After this migration, all lift log editing will use a maintainable, unified, component-based flexible layout.**