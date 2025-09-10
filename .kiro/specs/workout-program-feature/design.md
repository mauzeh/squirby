# Design Document

## Overview

The workout program feature will add a new "Program" section to the fitness app that allows users to create, manage, and follow structured workout programs. The feature follows the same UI/UX patterns as the existing food-logs functionality, providing a familiar date-based interface where users can configure daily workout programs using exercises from the existing database.

The system will support complex programming methodologies like high-frequency training by allowing users to specify different exercise parameters (sets, reps, intensity) for each day while maintaining the exercise movement patterns across multiple sessions.

## Architecture

### Database Schema

**WorkoutProgram Model:**
- `id` (primary key)
- `user_id` (foreign key to users table)
- `date` (date for the program)
- `name` (optional program name/title)
- `notes` (optional program notes)
- `created_at`, `updated_at`

**ProgramExercise Model (pivot table with additional data):**
- `id` (primary key)
- `workout_program_id` (foreign key to workout_programs table)
- `exercise_id` (foreign key to exercises table)
- `sets` (integer - number of sets)
- `reps` (integer - number of reps per set)
- `notes` (string - notes like "heavy", "light", "speed work", "75-80% of Day 1 weight")
- `exercise_order` (integer - order within the program)
- `exercise_type` (enum: 'main', 'accessory')
- `created_at`, `updated_at`

### Model Relationships

**User Model:**
- `hasMany(WorkoutProgram::class)`

**WorkoutProgram Model:**
- `belongsTo(User::class)`
- `belongsToMany(Exercise::class)->withPivot(['sets', 'reps', 'notes', 'exercise_order', 'exercise_type'])`

**Exercise Model:**
- `belongsToMany(WorkoutProgram::class)->withPivot(['sets', 'reps', 'notes', 'exercise_order', 'exercise_type'])`

## Components and Interfaces

### Controllers

**WorkoutProgramController:**
- `index()` - Display programs for selected date with date navigation
- `create()` - Show form to create new program
- `store()` - Save new program with exercises
- `edit($id)` - Show form to edit existing program
- `update($id)` - Update existing program
- `destroy($id)` - Delete program

### Views Structure

**resources/views/workout_programs/**
- `index.blade.php` - Main program view with date navigation (similar to food_logs/index.blade.php)
- `create.blade.php` - Create new program form
- `edit.blade.php` - Edit existing program form
- `_program_display.blade.php` - Partial for displaying program details

### Navigation Integration

Update `resources/views/app.blade.php`:
- Add "Program" navigation item between "Lifts" and "Body"
- Add sub-navigation for program-related routes
- Update route checking logic to include program routes

## Data Models

### WorkoutProgram Model Fields

```php
protected $fillable = [
    'user_id',
    'date', 
    'name',
    'notes'
];

protected $casts = [
    'date' => 'date'
];
```

### ProgramExercise Pivot Model Fields

```php
protected $fillable = [
    'workout_program_id',
    'exercise_id',
    'sets',
    'reps', 
    'notes',
    'exercise_order',
    'exercise_type'
];

protected $casts = [
    'exercise_order' => 'integer',
    'sets' => 'integer',
    'reps' => 'integer'
];
```

### Exercise Type Enumeration

- `main` - Primary and secondary compound movements (e.g., Heavy Back Squat, Bench Press, Overhead Press, Deadlift)
- `accessory` - Accessory/assistance work (e.g., Romanian Deadlifts, Face Pulls, Zombie Squats)

## Error Handling

### Validation Rules

**WorkoutProgram:**
- `date` - required, date format
- `name` - optional, string, max 255 characters
- `notes` - optional, string, max 1000 characters

**ProgramExercise:**
- `exercise_id` - required, exists in exercises table for current user
- `sets` - required, integer, min 1, max 20
- `reps` - required, integer, min 1, max 100
- `notes` - optional, string, max 255 characters
- `exercise_type` - required, in ['main', 'accessory']

### Error Messages

- Provide clear validation messages for invalid exercise selections
- Handle cases where exercises don't exist for the user
- Validate date ranges and prevent invalid date entries
- Ensure at least one exercise is added to a program

## Testing Strategy

### Unit Tests

**WorkoutProgramTest:**
- Test model relationships and data integrity
- Test date filtering and user scoping
- Test program creation with exercises
- Test program updates and deletions

**ProgramExerciseTest:**
- Test pivot table relationships
- Test exercise ordering functionality
- Test different exercise types
- Test weight percentage calculations

### Feature Tests

**WorkoutProgramControllerTest:**
- Test index view with date navigation
- Test program creation workflow
- Test program editing and updates
- Test program deletion
- Test user isolation (users can only see their own programs)

**WorkoutProgramIntegrationTest:**
- Test complete program creation workflow
- Test high-frequency program setup
- Test exercise selection and configuration
- Test date-based program retrieval

### Sample Program Data

The system will include the high-frequency squatting program for September 15-19, 2025:

**Day 1 (Sept 15) - Heavy Squat & Bench:**
- Back Squat: 3x5 (main, heavy)
- Bench Press: 3x5 (main)
- Zombie Squats: 3x8-12 (accessory)
- Pendlay Rows: 3x8 (accessory)
- Romanian Deadlifts: 3x8-10 (accessory)
- Plank: 3x45-60s (accessory)

**Day 2 (Sept 16) - Light Squat & Overhead Press:**
- Back Squat: 2x5 at 75-80% (main, light/speed)
- Overhead Press: 3x5 (main)
- Zombie Squats: 3x8-12 (accessory)
- Lat Pulldowns: 3x8-12 (accessory)
- Dumbbell Incline Press: 3x8-12 (accessory)
- Face Pulls: 3x15-20 (accessory)
- Bicep Curls: 3x10-12 (accessory)

**Day 3 (Sept 17) - Volume Squat & Deadlift:**
- Back Squat: 5x5 at 85-90% (main, volume)
- Conventional Deadlift: 1x5 (main)
- Zombie Squats: 3x8-12 (accessory)
- Glute-Ham Raises: 3x10-15 (accessory)
- Dumbbell Rows: 3x10-12 (accessory)
- Hanging Leg Raises: 3x to failure (accessory)

### Missing Exercises to Add

The following exercises need to be added to the User::boot() method:
- **Zombie Squats** - Front-loaded squat variation focusing on core stability and upright posture
- **Pendlay Rows** - Barbell row variation starting from the floor with strict form
- **Romanian Deadlifts** - Hip-hinge movement targeting hamstrings and glutes
- **Plank** - Isometric core exercise for stability and strength
- **Overhead Press** - Standing shoulder press with barbell
- **Lat Pulldowns** - Cable exercise targeting the latissimus dorsi
- **Dumbbell Incline Press** - Upper chest focused pressing movement
- **Face Pulls** - Cable exercise for rear deltoids and upper back health
- **Bicep Curls** - Isolation exercise for bicep development
- **Conventional Deadlift** - Hip-hinge movement lifting from the floor
- **Glute-Ham Raises** - Posterior chain exercise targeting hamstrings and glutes
- **Dumbbell Rows** - Unilateral rowing movement for back development
- **Hanging Leg Raises** - Core exercise performed hanging from a bar