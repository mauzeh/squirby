# Migration Plan: Refactor `lift-logs/edit` to Generic Flexible Component System

**Goal:**  
Unify the lift logs editing interface (`lift-logs/id/edit`) with your generic component-based flexible layout, as used for MobileEntry. This migration will simplify your codebase, reduce duplication, and make maintenance easier.

---

## Progress Summary

**Completed:**
- ✅ Created `LiftLogFormFactory` to encapsulate form building logic
- ✅ Refactored factory to build complete form components (not just fields)
- ✅ Updated `LiftLogService::generateForms()` to use factory pattern
- ✅ Updated unit tests for the refactored service
- ✅ All tests passing (14/14)

**Remaining:**
- ⏳ Add `generateEditFormComponent()` method to `LiftLogService`
- ⏳ Refactor `LiftLogController::edit()` to use flexible system
- ⏳ Update `lift-logs/edit.blade.php` view
- ⏳ Remove legacy `LiftLogFormComponent` class and Blade component
- ⏳ Update feature tests for edit functionality
- ⏳ Final documentation update

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

### 2.5. Factory: `LiftLogFormFactory.php` ✅ COMPLETED

**Status:** ✅ **COMPLETED**

**What was done:**
- Created `LiftLogFormFactory` class in `app/Services/Factories/`
- Implemented `buildForm()` method that creates complete form components
- Factory handles all ComponentBuilder logic, hidden fields, buttons, aria labels
- Moved form construction logic out of service layer
- Made `buildFields()` private as internal implementation detail
- Updated `LiftLogService::generateForms()` to use factory
- Updated all unit tests to mock `buildForm()` instead of `buildFields()`
- All 14 tests passing

**Benefits achieved:**
- Service layer now focuses on business logic (data, defaults, messages)
- Factory handles all form construction details
- Reduced `generateForms()` method from ~120 lines to ~20 lines
- Better separation of concerns and single responsibility principle

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

| File                                       | Status | Action                                  |
|---------------------------------------------|--------|----------------------------------------|
| `Services/Factories/LiftLogFormFactory.php` | ✅ DONE | Created factory for building complete forms |
| `MobileEntry/LiftLogService.php`            | ✅ DONE | Refactored generateForms() to use factory |
| `tests/Unit/.../LiftLogServiceTest.php`     | ✅ DONE | Updated tests to mock buildForm() |
| `MobileEntry/LiftLogService.php`            | ⏳ TODO | Add generateEditFormComponent() method |
| `LiftLogController.php`                     | ⏳ TODO | Refactor edit() to use flexible system |
| `lift-logs/edit.blade.php`                  | ⏳ TODO | Replace with flexible layout |
| `components/flexible.blade.php`             | ⏳ TODO | Ensure supports all needed types |
| `components/lift-log-form.blade.php`        | ⏳ TODO | Remove/deprecate |
| `View/Components/LiftLogFormComponent.php`  | ⏳ TODO | Remove/deprecate |
| Other UI partials/helpers                   | ⏳ TODO | Remove/migrate as needed |
| Feature tests                               | ⏳ TODO | Update for new flexible rendering |
| Docs                                        | ⏳ TODO | Final update after completion |

---

## Example References

- MobileEntry migration: `mobile-entry.flexible.blade.php`
- Flexible component builder: `MobileEntry\LiftLogService::generateForms`
- Docs: See `docs/flexible-ui/migration-guide.md`

---

## Remaining Tasks (In Order)

### Task 1: Add Edit Form Method to Service
**File:** `app/Services/MobileEntry/LiftLogService.php`

Add a new method `generateEditFormComponent()` that:
- Takes a `LiftLog` model, exercises collection, and user ID
- Extracts existing lift log data (exercise, sets, reps, weight, comments)
- Uses the existing `LiftLogFormFactory` to build the form
- Returns a form component pre-populated with the lift log's data
- Form should submit to `lift-logs.update` route with PUT method

### Task 2: Update Controller
**File:** `app/Http/Controllers/LiftLogController.php`

Refactor the `edit()` method to:
- Keep authorization check
- Call `LiftLogService::generateEditFormComponent()`
- Pass form component to flexible view
- Return `view('components.flexible', compact('data'))`

### Task 3: Update Edit View
**File:** `resources/views/lift-logs/edit.blade.php`

Replace current implementation with:
- Simple wrapper that extends 'app' layout
- Uses `@include('components.flexible', ['data' => $data])`
- Or delete entirely if controller returns flexible view directly

### Task 4: Verify Flexible View Support
**File:** `resources/views/components/flexible.blade.php`

Ensure the flexible view:
- Supports all form field types needed for lift log editing
- Handles PUT method forms correctly
- Renders validation errors appropriately

### Task 5: Remove Legacy Components
**Files to remove:**
- `resources/views/components/lift-log-form.blade.php`
- `app/View/Components/LiftLogFormComponent.php`

After confirming edit functionality works:
- Delete these legacy files
- Search codebase for any remaining references
- Remove any related imports or dependencies

### Task 6: Update Feature Tests
**Files:** `tests/Feature/*LiftLog*.php`

Update tests that cover edit functionality:
- Test that edit page loads with flexible layout
- Test that form is pre-populated with existing data
- Test that updates work correctly
- Test authorization (users can only edit their own logs)
- Test validation errors display properly

### Task 7: Final Documentation
**File:** `docs/migrations/lift-logs-edit-to-flexible.md`

- Mark all tasks as completed
- Document any gotchas or lessons learned
- Update example code if needed
- Add "Migration Complete" status

---

**After this migration, all lift log editing will use a maintainable, unified, component-based flexible layout.**