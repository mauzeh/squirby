# Workout Templates → Workouts Refactor

## Summary

Successfully refactored "Workout Templates" to simply "Workouts" throughout the entire codebase. This makes the terminology cleaner and more intuitive - they're just saved workouts that you can reuse.

## Changes Made

### Database
- **Migration**: `2025_11_12_120738_rename_workout_templates_to_workouts.php`
  - Renamed `workout_templates` table → `workouts`
  - Renamed `workout_template_exercises` table → `workout_exercises`
  - Renamed `workout_template_id` column → `workout_id`

### Models
- `WorkoutTemplate` → `Workout`
- `WorkoutTemplateExercise` → `WorkoutExercise`
- Updated all relationships and method references

### Controllers
- `WorkoutTemplateController` → `WorkoutController`
- Updated all variable names (`$template` → `$workout`, `$templates` → `workouts`)
- Updated all user-facing text

### Policies
- `WorkoutTemplatePolicy` → `WorkoutPolicy`

### Factories
- `WorkoutTemplateFactory` → `WorkoutFactory`
- `WorkoutTemplateExerciseFactory` → `WorkoutExerciseFactory`

### Seeders
- `WorkoutTemplateSeeder` → `WorkoutSeeder`

### Routes
- All routes changed from `workout-templates.*` → `workouts.*`
- Route parameter changed from `{workoutTemplate}` → `{workout}`

### Configuration
- Updated `config/redirects.php`:
  - `'workout-templates'` → `'workouts'`
  - `'template_id'` → `'workout_id'`

### Other Controllers
- Updated `LiftLogController` redirect context check
- Updated `MobileEntryController` parameter handling

### Tests
- `WorkoutTemplateTest` → `WorkoutTest`
- All test assertions updated
- All 16 tests passing

### User-Facing Text Changes
- "Workout Templates" → "Workouts"
- "Template" → "Workout" (in buttons, titles, messages)
- "Create New Template" → "Create New Workout"
- "Apply Template" → "Apply Workout"
- "Template Details" → "Workout Details"
- "Template Name" → "Workout Name"

## Migration Instructions

1. Run the migration:
   ```bash
   php artisan migrate
   ```

2. The migration is reversible with:
   ```bash
   php artisan migrate:rollback
   ```

## Testing

All tests pass:
```bash
php artisan test --filter=WorkoutTest
# Tests: 16 passed (52 assertions)
```

## Notes

- The refactor maintains full backward compatibility with existing data
- All functionality remains the same - only naming has changed
- The concept is simpler: users create "workouts" (collections of exercises) and can apply them to any date
