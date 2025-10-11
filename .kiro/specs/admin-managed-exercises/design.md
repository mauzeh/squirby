# Design Document

## Overview

This design implements a two-tier exercise management system where administrators can create and manage global exercises available to all users, while users can still create their own personal exercises. The system leverages the existing role-based access control and extends the current Exercise model to support both global and user-specific exercises.

## Architecture

### Database Schema Changes

The current `exercises` table will be modified to support the new functionality:

```sql
-- Migration: make_user_id_nullable_in_exercises_table
ALTER TABLE exercises MODIFY COLUMN user_id BIGINT UNSIGNED NULL;
```

**Key Changes:**
- `user_id`: Made nullable to allow global exercises (where user_id = NULL)

### Data Model Rules

1. **Global Exercises**: `user_id = NULL`
2. **User Exercises**: `user_id = [specific_user_id]`
3. **Constraint**: Exercise names must be unique within the combination of global exercises and per-user exercises

## Components and Interfaces

### Model Updates

#### Exercise Model Enhancements

```php
class Exercise extends Model
{
    protected $fillable = [
        'title',
        'description', 
        'is_bodyweight',
        'user_id'
    ];

    protected $casts = [
        'is_bodyweight' => 'boolean',
    ];

    // Scopes for querying
    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeUserSpecific($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAvailableToUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')        // Global exercises (available to all users)
              ->orWhere('user_id', $userId); // User's own exercises
        });
    }

    // Helper methods
    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($this->isGlobal()) {
            return $user->hasRole('Admin');
        }
        return $this->user_id === $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        if ($this->liftLogs()->exists()) {
            return false; // Cannot delete if has lift logs
        }
        return $this->canBeEditedBy($user);
    }
}
```

### Controller Updates

#### ExerciseController Modifications

The existing `ExerciseController` will be updated to handle both global and user-specific exercises:

```php
class ExerciseController extends Controller
{
    public function index(Request $request)
    {
        $exercises = Exercise::availableToUser(auth()->id())
            ->orderBy('is_global', 'desc') // Global exercises first
            ->orderBy('title')
            ->get();

        return view('exercises.index', compact('exercises'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_bodyweight' => 'boolean',
            'is_global' => 'boolean'
        ]);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            $this->authorize('createGlobalExercise');
        }

        // Check for name conflicts
        $this->validateExerciseName($validated['title'], $validated['is_global'] ?? false);

        $exercise = new Exercise($validated);
        
        if ($validated['is_global'] ?? false) {
            $exercise->user_id = null;
        } else {
            $exercise->user_id = auth()->id();
        }

        $exercise->save();

        return redirect()->route('exercises.index')
            ->with('success', 'Exercise created successfully.');
    }

    private function validateExerciseName(string $title, bool $isGlobal): void
    {
        if ($isGlobal) {
            // Check if global exercise with same name exists
            if (Exercise::global()->where('title', $title)->exists()) {
                throw ValidationException::withMessages([
                    'title' => 'A global exercise with this name already exists.'
                ]);
            }
        } else {
            // Check if user has exercise with same name OR global exercise exists
            $userId = auth()->id();
            $conflicts = Exercise::where('title', $title)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->exists();

            if ($conflicts) {
                throw ValidationException::withMessages([
                    'title' => 'An exercise with this name already exists.'
                ]);
            }
        }
    }
}
```

#### Enhanced ExerciseController

The existing controller will be enhanced to handle admin functionality:

```php
// Additional methods added to existing ExerciseController

public function create(Request $request)
{
    $canCreateGlobal = auth()->user()->hasRole('Admin');
    return view('exercises.create', compact('canCreateGlobal'));
}

public function edit(Exercise $exercise)
{
    $this->authorize('update', $exercise);
    $canCreateGlobal = auth()->user()->hasRole('Admin');
    return view('exercises.edit', compact('exercise', 'canCreateGlobal'));
}

public function update(Request $request, Exercise $exercise)
{
    $this->authorize('update', $exercise);
    
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'is_bodyweight' => 'boolean',
        'is_global' => 'boolean'
    ]);

    // Check admin permission for global exercises
    if ($validated['is_global'] ?? false) {
        $this->authorize('createGlobalExercise');
    }

    // Check for name conflicts (excluding current exercise)
    $this->validateExerciseNameForUpdate($exercise, $validated['title'], $validated['is_global'] ?? false);

    $exercise->update([
        'title' => $validated['title'],
        'description' => $validated['description'],
        'is_bodyweight' => $validated['is_bodyweight'] ?? false,
        'user_id' => ($validated['is_global'] ?? false) ? null : auth()->id()
    ]);

    return redirect()->route('exercises.index')
        ->with('success', 'Exercise updated successfully.');
}

public function destroy(Exercise $exercise)
{
    $this->authorize('delete', $exercise);
    
    if ($exercise->liftLogs()->exists()) {
        return back()->withErrors(['error' => 'Cannot delete exercise: it has associated lift logs.']);
    }
    
    $exercise->delete();
    
    return redirect()->route('exercises.index')
        ->with('success', 'Exercise deleted successfully.');
}
```

### Authorization Policy

```php
class ExercisePolicy
{
    public function createGlobalExercise(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $exercise->canBeEditedBy($user);
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $exercise->canBeDeletedBy($user);
    }
}
```

## Data Models

### Exercise Model Schema

```sql
CREATE TABLE exercises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_bodyweight BOOLEAN DEFAULT FALSE,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Unique constraint for exercise names within scope
    UNIQUE KEY unique_exercise_name_per_scope (title, user_id)
);
```

### Data Migration Strategy

1. **Make user_id nullable** in existing exercises table
3. **Seed global exercises**: Create a set of common exercises as global
4. **Remove duplicate user exercises**: Clean up exercises that match new global ones

## Error Handling

### Validation Errors

1. **Name Conflicts**: Clear error messages when exercise names conflict
2. **Permission Errors**: Appropriate 403 responses for unauthorized actions
3. **Deletion Constraints**: Informative messages when exercises cannot be deleted due to lift logs

### Exception Handling

```php
class ExerciseService
{
    public function deleteExercise(Exercise $exercise, User $user): bool
    {
        if (!$exercise->canBeDeletedBy($user)) {
            if ($exercise->liftLogs()->exists()) {
                throw new \Exception('Cannot delete exercise: it has associated lift logs.');
            }
            throw new \Exception('You do not have permission to delete this exercise.');
        }

        return $exercise->delete();
    }
}
```

## Testing Strategy

### Unit Tests

1. **Model Scopes**: Test global, userSpecific, and availableToUser scopes
2. **Model Methods**: Test isGlobal(), canBeEditedBy(), canBeDeletedBy()
3. **Validation Logic**: Test exercise name conflict detection

### Feature Tests

1. **Admin Exercise Management**: Test CRUD operations for global exercises
2. **User Exercise Management**: Test user exercise creation with conflict detection
3. **Permission Tests**: Verify admin-only access to global exercise management
4. **Integration Tests**: Test exercise listing shows both global and user exercises

### Database Tests

1. **Migration Tests**: Verify schema changes apply correctly
2. **Constraint Tests**: Test database-level constraints work as expected
3. **Data Integrity**: Verify existing data is preserved during migration