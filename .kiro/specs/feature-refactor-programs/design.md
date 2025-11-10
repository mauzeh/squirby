# Design Document

## Overview

This design document outlines the complete refactoring of the Programs system to use a lightweight Mobile Lift Forms approach, mirroring the architecture of Mobile Food Forms. The refactoring eliminates the Programs table, model, controller, views, and all related code, replacing it with a minimal mobile_lift_forms table that stores only selection state (user_id, date, exercise_id).

All workout parameters (sets, reps, weight) will be calculated dynamically by the TrainingProgressionService, eliminating data duplication and ensuring consistency across the application.

## Architecture

### Current Architecture (Programs)

```
┌─────────────────────────────────────────────────────────────┐
│                     Desktop Interface                        │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  ProgramController (Full CRUD)                       │  │
│  │  - index, create, store, edit, update, destroy      │  │
│  │  - quickAdd, quickCreate, moveUp, moveDown          │  │
│  │  - destroySelected                                   │  │
│  └──────────────────────────────────────────────────────┘  │
│                           │                                  │
│                           ▼                                  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Program Model                                       │  │
│  │  - exercise_id, date, sets, reps, priority          │  │
│  │  - comments, weight (removed)                        │  │
│  │  - isCompleted(), getLiftLogs()                     │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    Mobile Interface                          │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  LiftLogService                                      │  │
│  │  - generateProgramForms(from programs table)        │  │
│  │  - Uses program.sets, program.reps                  │  │
│  │  - Checks program.isCompleted()                     │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### New Architecture (Mobile Lift Forms)

```
┌─────────────────────────────────────────────────────────────┐
│                    Mobile Interface                          │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  MobileEntryController                               │  │
│  │  - addLiftForm(exercise_id, date)                   │  │
│  │  - removeForm(form_id)                              │  │
│  │  - createExercise(name, date)                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                           │                                  │
│                           ▼                                  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  LiftLogService                                      │  │
│  │  - generateForms(from mobile_lift_forms)            │  │
│  │  - addExerciseForm(), removeForm()                  │  │
│  │  - generateItemSelectionList()                      │  │
│  └──────────────────────────────────────────────────────┘  │
│                           │                                  │
│                           ▼                                  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  MobileLiftForm Model                                │  │
│  │  - user_id, date, exercise_id (only)                │  │
│  │  - forUserAndDate() scope                           │  │
│  └──────────────────────────────────────────────────────┘  │
│                           │                                  │
│                           ▼                                  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  TrainingProgressionService                          │  │
│  │  - getSuggestionDetails() → sets, reps, weight      │  │
│  │  - Calculates all parameters dynamically            │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. MobileLiftForm Model

**Location:** `app/Models/MobileLiftForm.php`

**Purpose:** Lightweight model for storing exercise selection state

**Schema:**
```php
Schema::create('mobile_lift_forms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->date('date');
    $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
    $table->timestamps();
    
    $table->unique(['user_id', 'date', 'exercise_id']);
    $table->index(['user_id', 'date']);
});
```

**Relationships:**
- `belongsTo(User::class)`
- `belongsTo(Exercise::class)`

**Scopes:**
- `forUserAndDate($userId, Carbon $date)` - Filter by user and date

**Methods:**
- No business logic methods needed (pure data model)

### 2. LiftLogService Updates

**Location:** `app/Services/MobileEntry/LiftLogService.php`

**Updated Methods:**

#### `generateForms($userId, Carbon $selectedDate)`
```php
// OLD: Query programs table
$programs = Program::with(['exercise'])
    ->where('user_id', $userId)
    ->whereDate('date', $selectedDate)
    ->orderBy('priority')
    ->get();

// NEW: Query mobile_lift_forms table
$mobileForms = MobileLiftForm::with(['exercise'])
    ->forUserAndDate($userId, $selectedDate)
    ->get();

// Calculate sets/reps dynamically for each exercise
foreach ($mobileForms as $form) {
    $suggestion = $this->trainingProgressionService
        ->getSuggestionDetails($userId, $form->exercise_id, $selectedDate);
    
    $defaultSets = $suggestion->sets ?? 3;
    $defaultReps = $suggestion->reps ?? 10;
    $defaultWeight = $suggestion->suggestedWeight ?? 0;
}
```

#### `addExerciseForm($userId, $exerciseId, Carbon $date)`
```php
// Check if exercise exists
$exercise = Exercise::find($exerciseId);
if (!$exercise) {
    return [
        'success' => false, 
        'message' => config('mobile_entry_messages.error.exercise_not_found')
    ];
}

// Check if already added
$exists = MobileLiftForm::where('user_id', $userId)
    ->where('date', $date)
    ->where('exercise_id', $exerciseId)
    ->exists();

if ($exists) {
    return [
        'success' => false, 
        'message' => str_replace(':exercise', $exercise->title, 
            config('mobile_entry_messages.error.exercise_already_in_program'))
    ];
}

// Create form
MobileLiftForm::create([
    'user_id' => $userId,
    'date' => $date,
    'exercise_id' => $exerciseId
]);

return [
    'success' => true, 
    'message' => str_replace(':exercise', $exercise->title, 
        config('mobile_entry_messages.success.exercise_added'))
];
```

#### `removeForm($userId, $formId)`
```php
// Parse form ID (format: "lift-{id}")
if (preg_match('/^lift-(\d+)$/', $formId, $matches)) {
    $id = $matches[1];
    
    $form = MobileLiftForm::with('exercise')
        ->where('id', $id)
        ->where('user_id', $userId)
        ->first();
    
    if ($form) {
        $exerciseName = $form->exercise->title;
        $form->delete();
        return [
            'success' => true, 
            'message' => str_replace(':exercise', $exerciseName, 
                config('mobile_entry_messages.success.form_removed'))
        ];
    }
}

return [
    'success' => false, 
    'message' => config('mobile_entry_messages.error.form_not_found')
];
```

#### `createExercise($userId, $exerciseName, Carbon $date)`
```php
// Check for duplicates
$exists = Exercise::where('title', $exerciseName)
    ->where('user_id', $userId)
    ->exists();

if ($exists) {
    return [
        'success' => false, 
        'message' => str_replace(':exercise', $exerciseName, 
            config('mobile_entry_messages.error.exercise_already_exists'))
    ];
}

// Create exercise
$exercise = Exercise::create([
    'title' => $exerciseName,
    'user_id' => $userId
]);

// Add to mobile lift forms
MobileLiftForm::create([
    'user_id' => $userId,
    'date' => $date,
    'exercise_id' => $exercise->id
]);

return [
    'success' => true, 
    'message' => str_replace(':exercise', $exerciseName, 
        config('mobile_entry_messages.success.exercise_created'))
];
```

#### `generateItemSelectionList($userId, Carbon $selectedDate)`
```php
// Get recommended exercises from RecommendationEngine
$recommendedExercises = $this->recommendationEngine
    ->getRecommendations($userId, $selectedDate);

// Get all available exercises with recent usage data
$exercises = Exercise::availableToUser()
    ->with(['liftLogs' => function ($query) use ($userId, $selectedDate) {
        $query->where('user_id', $userId)
            ->where('logged_at', '>=', $selectedDate->copy()->subDays(30))
            ->orderBy('logged_at', 'desc')
            ->limit(1);
    }])
    ->orderBy('title')
    ->get();

$items = [];
foreach ($exercises as $exercise) {
    // Determine type: recommended > recent > custom > regular
    $type = $this->determineExerciseType($exercise, $userId, $recommendedExercises);
    
    $items[] = [
        'id' => 'exercise-' . $exercise->id,
        'name' => $exercise->title,
        'type' => $type,
        'href' => route('mobile-entry.add-lift-form', [
            'exercise' => $exercise->id,
            'date' => $selectedDate->toDateString()
        ])
    ];
}

// Sort by priority (recommended=1, recent=2, custom=3, regular=4), then alphabetically
usort($items, function ($a, $b) {
    $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
    return $priorityComparison !== 0 ? $priorityComparison : strcmp($a['name'], $b['name']);
});

return [
    'items' => $items,
    'createForm' => [
        'action' => route('mobile-entry.create-exercise'),
        'method' => 'POST',
        'inputName' => 'exercise_name',
        'submitText' => '+',
        'hiddenFields' => ['date' => $selectedDate->toDateString()]
    ],
    // ... other fields
];
```

### 3. MobileEntryController Updates

**Location:** `app/Http/Controllers/MobileEntryController.php`

**Updated Methods:**

#### `lifts(Request $request, DateTitleService $dateTitleService, LiftLogService $formService)`
```php
// Remove cleanupOldForms call (no auto-cleanup needed)
// Change: $forms = $formService->generateProgramForms(...)
// To:     $forms = $formService->generateForms(...)

$forms = $formService->generateForms(Auth::id(), $selectedDate);
```

**Existing Methods (no changes needed):**
- `addLiftForm()` - Already exists
- `removeForm()` - Already exists
- `createExercise()` - Already exists

### 4. Routes

**Location:** `routes/web.php`

**Remove:**
```php
// Remove all program routes
Route::resource('programs', ProgramController::class);
Route::post('programs/destroy-selected', [ProgramController::class, 'destroySelected']);
Route::get('programs/quick-add/{exercise}/{date}', [ProgramController::class, 'quickAdd']);
Route::post('programs/quick-create/{date}', [ProgramController::class, 'quickCreate']);
Route::get('programs/{program}/move-down', [ProgramController::class, 'moveDown']);
Route::get('programs/{program}/move-up', [ProgramController::class, 'moveUp']);
```

**Keep (already exist):**
```php
// Mobile entry routes (already defined)
Route::get('mobile-entry/lifts', [MobileEntryController::class, 'lifts'])
    ->name('mobile-entry.lifts');
Route::post('mobile-entry/add-lift-form/{exercise}', [MobileEntryController::class, 'addLiftForm'])
    ->name('mobile-entry.add-lift-form');
Route::delete('mobile-entry/remove-form/{id}', [MobileEntryController::class, 'removeForm'])
    ->name('mobile-entry.remove-form');
Route::post('mobile-entry/create-exercise', [MobileEntryController::class, 'createExercise'])
    ->name('mobile-entry.create-exercise');
```

## Data Models

### MobileLiftForm

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MobileLiftForm extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'exercise_id'
    ];
    
    protected $casts = [
        'date' => 'date'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
    
    public function scopeForUserAndDate($query, $userId, Carbon $date)
    {
        return $query->where('user_id', $userId)
                    ->whereDate('date', $date->toDateString());
    }
}
```

## Error Handling

The refactoring uses existing message templates from `config/mobile_entry_messages.php`:

1. **Exercise Not Found:** `config('mobile_entry_messages.error.exercise_not_found')`
2. **Duplicate Form:** `config('mobile_entry_messages.error.exercise_already_in_program')` with `:exercise` placeholder
3. **Form Not Found:** `config('mobile_entry_messages.error.form_not_found')`
4. **Duplicate Exercise:** `config('mobile_entry_messages.error.exercise_already_exists')` with `:exercise` placeholder
5. **Database Errors:** Log errors and return generic user-facing messages

Success messages also use config templates:
- **Exercise Added:** `config('mobile_entry_messages.success.exercise_added')` with `:exercise` placeholder
- **Form Removed:** `config('mobile_entry_messages.success.form_removed')` with `:exercise` placeholder
- **Exercise Created:** `config('mobile_entry_messages.success.exercise_created')` with `:exercise` placeholder

All messages are displayed via session flash messages in the MobileEntryController.

## Testing Strategy

### Unit Tests

**MobileLiftFormTest.php**
- Test model relationships (user, exercise)
- Test forUserAndDate scope
- Test fillable fields and casts

**LiftLogServiceTest.php**
- Test generateForms() with mobile_lift_forms
- Test addExerciseForm() success and error cases
- Test removeForm() success and error cases
- Test createExercise() success and error cases
- Test generateItemSelectionList() without program logic
- Test dynamic calculation of sets/reps/weight

### Integration Tests

**MobileEntryControllerTest.php**
- Test lifts() page loads with mobile lift forms
- Test addLiftForm() creates mobile_lift_forms record
- Test removeForm() deletes mobile_lift_forms record
- Test createExercise() creates exercise and mobile_lift_forms record
- Test error handling for all operations

### Migration Tests

**Verify:**
- Programs table is dropped
- Mobile_lift_forms table is created with correct schema
- Foreign keys and indexes are properly set
- Unique constraint works as expected

## Migration Strategy

### Step 1: Create mobile_lift_forms table

```php
Schema::create('mobile_lift_forms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->date('date');
    $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
    $table->timestamps();
    
    $table->unique(['user_id', 'date', 'exercise_id']);
    $table->index(['user_id', 'date']);
});
```

### Step 2: Drop programs table

```php
Schema::dropIfExists('programs');
```

**Note:** Data migration from programs to mobile_lift_forms will be handled manually outside this project.

## Files to Delete

### Models
- `app/Models/Program.php`

### Controllers
- `app/Http/Controllers/ProgramController.php`

### Requests
- `app/Http/Requests/StoreProgramRequest.php`
- `app/Http/Requests/UpdateProgramRequest.php`

### Views
- `resources/views/programs/index.blade.php`
- `resources/views/programs/create.blade.php`
- `resources/views/programs/edit.blade.php`
- `resources/views/programs/_form.blade.php`
- `resources/views/programs/_form_create.blade.php`
- Any other program-related view files

### Tests
- `tests/Feature/ProgramTest.php` (if exists)
- `tests/Unit/ProgramTest.php` (if exists)
- Any other program-related test files

### Migrations (keep for history)
- Keep existing program migrations for database history
- Add new migration to drop programs table
- Add new migration to create mobile_lift_forms table

## Files to Update

### Services
- `app/Services/MobileEntry/LiftLogService.php`
  - Update generateForms() method
  - Update generateItemSelectionList() method
  - Ensure addExerciseForm(), removeForm(), createExercise() work with mobile_lift_forms

### Controllers
- `app/Http/Controllers/MobileEntryController.php`
  - Update lifts() method to use new generateForms()
  - Verify existing methods work with mobile_lift_forms

### Routes
- `routes/web.php`
  - Remove all program routes
  - Verify mobile-entry routes exist

### Other Services
- `app/Services/RecommendationEngine.php` - Remove program references or delete if no longer needed
- `app/Services/MobileEntry/LiftDataCacheService.php` - Remove program-related caching logic

### Navigation/Views
- Remove any links to program routes from navigation
- Remove any program-related UI elements

## Backward Compatibility

This refactoring is a breaking change that completely removes the Programs system. There is no backward compatibility:

- Programs table will be dropped
- All program routes will return 404
- All program views will be deleted
- Users must manually migrate their program data if needed (outside this project)

## Performance Considerations

### Improvements
1. **Simpler queries:** Mobile lift forms table has fewer columns and no complex completion logic
2. **No N+1 queries:** Removed completion status checks that required joining with lift_logs
3. **Dynamic calculation:** Sets/reps/weight calculated once per form generation instead of stored redundantly

### Potential Concerns
1. **TrainingProgressionService calls:** Now called for every form generation
   - **Mitigation:** Already cached in LiftDataCacheService
2. **No priority ordering:** Forms will be ordered by creation time or alphabetically
   - **Mitigation:** Users can remove/re-add forms to change order if needed

## Security Considerations

1. **Authorization:** All mobile lift form operations check user_id to prevent unauthorized access
2. **Validation:** Exercise IDs are validated before creating mobile lift forms
3. **Cascade deletes:** Foreign keys ensure orphaned records are cleaned up
4. **Unique constraint:** Prevents duplicate forms for same user/date/exercise

## Rollback Plan

If issues arise:

1. **Restore programs table:** Use database backup to restore programs table
2. **Restore code:** Revert commits that removed Program model, controller, views
3. **Restore routes:** Re-add program routes to web.php
4. **Drop mobile_lift_forms:** Remove the new table if not needed

**Note:** Since data migration is manual, users can keep their program data backed up separately.
