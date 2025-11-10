# Design Document

## Overview

The Workout Templates MVP provides a CRUD interface for users to create, view, edit, and delete collections of exercises as reusable templates. This feature follows Laravel MVC patterns and integrates with the existing exercise selection system used in mobile lift forms. The design focuses on simplicity and consistency with existing codebase patterns.

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                      User Interface Layer                    │
├─────────────────────────────────────────────────────────────┤
│  - Template Index View (list all templates)                 │
│  - Template Create View (new template form)                 │
│  - Template Edit View (modify existing template)            │
│  - Template Show View (view template details)               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     Controller Layer                         │
├─────────────────────────────────────────────────────────────┤
│  WorkoutTemplateController                                   │
│  - index()    : Display all user templates                  │
│  - create()   : Show template creation form                 │
│  - store()    : Save new template                           │
│  - show()     : Display single template                     │
│  - edit()     : Show template edit form                     │
│  - update()   : Update existing template                    │
│  - destroy()  : Delete template                             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                       Model Layer                            │
├─────────────────────────────────────────────────────────────┤
│  WorkoutTemplate Model                                       │
│  - Relationships: user, exercises (through pivot)           │
│  - Scopes: forUser()                                        │
│                                                              │
│  WorkoutTemplateExercise Model (Pivot)                      │
│  - Stores exercise_id and order                             │
│  - Relationships: template, exercise                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      Database Layer                          │
├─────────────────────────────────────────────────────────────┤
│  workout_templates table                                     │
│  workout_template_exercises table (pivot)                   │
└─────────────────────────────────────────────────────────────┘
```

### Integration Points

1. **Exercise Selection System**: Reuses the existing `LiftLogService::generateItemSelectionList()` for consistent exercise selection UI
2. **Exercise Model**: Templates reference exercises via foreign keys, respecting exercise visibility rules
3. **User Model**: Templates belong to users, ensuring proper ownership and access control

## Components and Interfaces

### Database Schema

#### workout_templates Table

```sql
CREATE TABLE workout_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);
```

#### workout_template_exercises Table (Pivot)

```sql
CREATE TABLE workout_template_exercises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_template_id BIGINT UNSIGNED NOT NULL,
    exercise_id BIGINT UNSIGNED NOT NULL,
    order INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (workout_template_id) REFERENCES workout_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    UNIQUE KEY unique_template_exercise (workout_template_id, exercise_id),
    INDEX idx_template_id (workout_template_id),
    INDEX idx_exercise_id (exercise_id),
    INDEX idx_order (workout_template_id, order)
);
```

### Models

#### WorkoutTemplate Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkoutTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description'
    ];
    
    /**
     * Get the user that owns the template
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the exercises in this template with order
     */
    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'workout_template_exercises')
                    ->withPivot('order')
                    ->withTimestamps()
                    ->orderBy('workout_template_exercises.order');
    }
    
    /**
     * Scope to get templates for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Check if the template can be edited by the user
     */
    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
    
    /**
     * Check if the template can be deleted by the user
     */
    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
```

#### WorkoutTemplateExercise Model (Pivot)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutTemplateExercise extends Model
{
    protected $fillable = [
        'workout_template_id',
        'exercise_id',
        'order'
    ];
    
    protected $casts = [
        'order' => 'integer'
    ];
    
    /**
     * Get the template this exercise belongs to
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id');
    }
    
    /**
     * Get the exercise
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

### Controller

#### WorkoutTemplateController

```php
<?php

namespace App\Http\Controllers;

use App\Models\WorkoutTemplate;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkoutTemplateController extends Controller
{
    /**
     * Display a listing of the user's templates
     */
    public function index()
    {
        $templates = WorkoutTemplate::forUser(Auth::id())
            ->withCount('exercises')
            ->orderBy('name')
            ->get();
            
        return view('workout-templates.index', compact('templates'));
    }
    
    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        // Get available exercises for the user
        $exercises = Exercise::availableToUser(Auth::id())
            ->orderBy('title')
            ->get();
            
        return view('workout-templates.create', compact('exercises'));
    }
    
    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exercises' => 'required|array|min:1',
            'exercises.*' => 'required|exists:exercises,id'
        ]);
        
        DB::transaction(function () use ($validated) {
            $template = WorkoutTemplate::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null
            ]);
            
            // Attach exercises with order
            foreach ($validated['exercises'] as $order => $exerciseId) {
                $template->exercises()->attach($exerciseId, [
                    'order' => $order + 1
                ]);
            }
        });
        
        return redirect()->route('workout-templates.index')
            ->with('success', 'Template created successfully');
    }
    
    /**
     * Display the specified template
     */
    public function show(WorkoutTemplate $template)
    {
        $this->authorize('view', $template);
        
        $template->load('exercises');
        
        return view('workout-templates.show', compact('template'));
    }
    
    /**
     * Show the form for editing the specified template
     */
    public function edit(WorkoutTemplate $template)
    {
        $this->authorize('update', $template);
        
        $template->load('exercises');
        
        $exercises = Exercise::availableToUser(Auth::id())
            ->orderBy('title')
            ->get();
            
        return view('workout-templates.edit', compact('template', 'exercises'));
    }
    
    /**
     * Update the specified template
     */
    public function update(Request $request, WorkoutTemplate $template)
    {
        $this->authorize('update', $template);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exercises' => 'required|array|min:1',
            'exercises.*' => 'required|exists:exercises,id'
        ]);
        
        DB::transaction(function () use ($template, $validated) {
            $template->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null
            ]);
            
            // Detach all existing exercises
            $template->exercises()->detach();
            
            // Attach new exercises with order
            foreach ($validated['exercises'] as $order => $exerciseId) {
                $template->exercises()->attach($exerciseId, [
                    'order' => $order + 1
                ]);
            }
        });
        
        return redirect()->route('workout-templates.index')
            ->with('success', 'Template updated successfully');
    }
    
    /**
     * Remove the specified template
     */
    public function destroy(WorkoutTemplate $template)
    {
        $this->authorize('delete', $template);
        
        $template->delete();
        
        return redirect()->route('workout-templates.index')
            ->with('success', 'Template deleted successfully');
    }
}
```

### Policy

#### WorkoutTemplatePolicy

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutTemplate;

class WorkoutTemplatePolicy
{
    /**
     * Determine if the user can view the template
     */
    public function view(User $user, WorkoutTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
    
    /**
     * Determine if the user can create templates
     */
    public function create(User $user): bool
    {
        return true;
    }
    
    /**
     * Determine if the user can update the template
     */
    public function update(User $user, WorkoutTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
    
    /**
     * Determine if the user can delete the template
     */
    public function delete(User $user, WorkoutTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
```

### Routes

```php
// routes/web.php

Route::middleware(['auth'])->group(function () {
    Route::resource('workout-templates', WorkoutTemplateController::class);
});
```

### Views Structure

```
resources/views/workout-templates/
├── index.blade.php      # List all templates
├── create.blade.php     # Create new template
├── edit.blade.php       # Edit existing template
├── show.blade.php       # View template details
└── _form.blade.php      # Shared form partial
```

## Data Models

### WorkoutTemplate

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Foreign key to users table |
| name | varchar(255) | Template name |
| description | text | Optional description |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### WorkoutTemplateExercise (Pivot)

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| workout_template_id | bigint | Foreign key to workout_templates |
| exercise_id | bigint | Foreign key to exercises |
| order | int | Exercise order in template |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### Relationships

- **WorkoutTemplate** belongs to **User** (one-to-many)
- **WorkoutTemplate** has many **Exercise** through **WorkoutTemplateExercise** (many-to-many with pivot)
- **WorkoutTemplateExercise** belongs to **WorkoutTemplate** (one-to-many)
- **WorkoutTemplateExercise** belongs to **Exercise** (one-to-many)

## Error Handling

### Validation Errors

1. **Template Name Required**: Display error if name is empty
2. **No Exercises Selected**: Display error if exercises array is empty
3. **Invalid Exercise ID**: Display error if exercise doesn't exist or user doesn't have access
4. **Duplicate Exercise**: Prevent adding same exercise twice to a template

### Authorization Errors

1. **Unauthorized Access**: Return 403 if user tries to view/edit/delete another user's template
2. **Template Not Found**: Return 404 if template doesn't exist

### Database Errors

1. **Foreign Key Constraint**: Handle gracefully if exercise is deleted while in use
2. **Transaction Rollback**: Ensure all-or-nothing saves for template + exercises

### Error Messages

```php
// config/messages.php or inline in controller

return [
    'template_created' => 'Template created successfully',
    'template_updated' => 'Template updated successfully',
    'template_deleted' => 'Template deleted successfully',
    'template_not_found' => 'Template not found',
    'unauthorized' => 'You are not authorized to perform this action',
    'validation_failed' => 'Please correct the errors below',
    'name_required' => 'Template name is required',
    'exercises_required' => 'Please add at least one exercise',
    'exercise_invalid' => 'One or more exercises are invalid'
];
```

## Testing Strategy

### Unit Tests

1. **WorkoutTemplate Model Tests**
   - Test relationships (user, exercises)
   - Test scopes (forUser)
   - Test authorization methods (canBeEditedBy, canBeDeletedBy)

2. **WorkoutTemplateExercise Model Tests**
   - Test relationships (template, exercise)
   - Test ordering

### Feature Tests

1. **Template CRUD Operations**
   - Test creating a template with exercises
   - Test viewing template list
   - Test viewing single template
   - Test updating template (name, description, exercises)
   - Test deleting template
   - Test reordering exercises

2. **Authorization Tests**
   - Test users can only see their own templates
   - Test users cannot edit other users' templates
   - Test users cannot delete other users' templates

3. **Validation Tests**
   - Test template name is required
   - Test at least one exercise is required
   - Test exercise IDs must exist
   - Test exercise IDs must be accessible to user

4. **Integration Tests**
   - Test template creation with exercise selection
   - Test template editing with exercise reordering
   - Test cascade delete when user is deleted
   - Test handling of deleted exercises

### Test Data

```php
// database/factories/WorkoutTemplateFactory.php

public function definition()
{
    return [
        'user_id' => User::factory(),
        'name' => $this->faker->words(3, true),
        'description' => $this->faker->optional()->sentence(),
        'times_used' => 0
    ];
}
```

## UI/UX Considerations

### Template List View (index)

- Display templates in a card or table layout
- Show template name, description (truncated), exercise count, and creation date
- Provide actions: View, Edit, Delete
- Include "Create New Template" button
- Empty state message when no templates exist

### Template Create/Edit View

- Form with name and description fields
- Exercise selection using existing exercise picker UI
- Up/down buttons for reordering exercises (server-side form submission)
- List of selected exercises with order numbers
- Save and Cancel buttons
- Validation error display
- Pure server-side rendering, no JavaScript required

### Template Show View

- Display template name and description
- List all exercises in order
- Show exercise names (respecting aliases)
- Provide Edit and Delete actions
- Back to list button

### Mobile Considerations

- Responsive design for all views
- Large tap targets for up/down reorder buttons
- Touch-friendly button sizes
- Simplified layout on small screens
- Server-side form submissions for all interactions

## Future Enhancements

The following features are explicitly out of scope for this MVP but documented for future reference:

1. **Apply Template to Date**: Create mobile lift forms from template
2. **Template Duplication**: Copy existing template to create variations
3. **Public Templates**: Share templates with other users
4. **Template Tags**: Categorize templates (push, pull, legs, etc.)
5. **Template Analytics**: Track usage statistics and effectiveness
6. **Template Variations**: Heavy/light day versions
7. **Smart Scheduling**: Auto-apply templates on specific days
8. **Multi-week Programs**: Structured programs with progression

## Migration Strategy

### Database Migrations

1. Create `workout_templates` table
2. Create `workout_template_exercises` pivot table
3. Add indexes for performance

### Rollback Plan

- Migrations include `down()` methods to drop tables
- No data migration needed (new feature)
- No impact on existing features

## Performance Considerations

1. **Eager Loading**: Load exercises with templates to avoid N+1 queries
2. **Indexes**: Add indexes on user_id and template_id for fast lookups
3. **Pagination**: Implement pagination if user has many templates
4. **Caching**: Consider caching template list for frequent access (future)

## Security Considerations

1. **Authorization**: Use Laravel policies to ensure users can only access their own templates
2. **Validation**: Validate all input to prevent SQL injection and XSS
3. **CSRF Protection**: Use Laravel's CSRF token for all forms
4. **Mass Assignment**: Use `$fillable` to prevent mass assignment vulnerabilities
5. **Exercise Access**: Ensure users can only add exercises they have access to
