# Design Document

## Overview

This design outlines the comprehensive refactoring of the Workout system to LiftLog throughout the Laravel application. The refactor involves renaming classes, database tables, routes, views, and all related references while maintaining complete functionality and data integrity. The system will continue to track weightlifting sessions with exercises, sets, reps, and weights, but with more specific and intuitive naming.

## Architecture

The refactor follows Laravel's MVC architecture and maintains the existing system structure:

- **Model Layer**: `Workout` → `LiftLog`, `WorkoutSet` → `LiftSet`, relationships with `Exercise` and `User`
- **Controller Layer**: `WorkoutController` → `LiftLogController`
- **View Layer**: All workouts views → lift-logs views
- **Database Layer**: `workouts` table → `lift_logs` table, `workout_sets` table → `lift_sets` table
- **Route Layer**: workouts routes → lift-logs routes
- **Service Layer**: Update `TsvImporterService` and `OneRepMaxCalculatorService` to use LiftLog references

## Components and Interfaces

### Model Changes

#### LiftLog Model (formerly Workout)

```php
class LiftLog extends Model
{
    protected $fillable = [
        'exercise_id',
        'comments', 
        'logged_at',
        'user_id',
    ];

    public function exercise() { return $this->belongsTo(Exercise::class); }
    public function liftSets() { return $this->hasMany(LiftSet::class); }
    public function user() { return $this->belongsTo(User::class); }
    
    // Computed attributes for display
    public function getOneRepMaxAttribute() { /* ... */ }
    public function getBestOneRepMaxAttribute() { /* ... */ }
    public function getDisplayRepsAttribute() { /* ... */ }
    public function getDisplayRoundsAttribute() { /* ... */ }
    public function getDisplayWeightAttribute() { /* ... */ }
}
```

#### LiftSet Model (formerly WorkoutSet)

```php
class LiftSet extends Model
{
    protected $fillable = [
        'lift_log_id',
        'weight',
        'reps', 
        'notes',
    ];

    public function liftLog() { return $this->belongsTo(LiftLog::class); }
    public function getOneRepMaxAttribute() { /* ... */ }
}
```

#### Related Model Updates
- **User Model**: Update relationship method from `workouts()` to `liftLogs()`
- **Exercise Model**: Update relationship method from `workouts()` to `liftLogs()`

### Controller Changes

#### LiftLogController (formerly WorkoutController)

- Rename class and file
- Update all internal references to use LiftLog model
- Update route redirects and success messages
- Maintain all existing functionality:
  - CRUD operations
  - TSV import/export
  - Bulk delete operations
  - Chart data generation

### Database Migration Strategy

#### Phase 1: Table Renaming
```sql
-- Rename tables
ALTER TABLE workouts RENAME TO lift_logs;
ALTER TABLE workout_sets RENAME TO lift_sets;

-- Update foreign key column name
ALTER TABLE lift_sets RENAME COLUMN workout_id TO lift_log_id;
```

#### Phase 2: Index and Constraint Updates
- Update foreign key constraints
- Rename indexes to match new table names
- Update any database triggers or procedures

### Route Changes

#### Route Definitions
- **Current**: `workouts.*` routes
- **New**: `lift-logs.*` routes
- **Specific Changes**:
  - `workouts.index` → `lift-logs.index`
  - `workouts.create` → `lift-logs.create`
  - `workouts.store` → `lift-logs.store`
  - `workouts.edit` → `lift-logs.edit`
  - `workouts.update` → `lift-logs.update`
  - `workouts.destroy` → `lift-logs.destroy`
  - `workouts.destroy-selected` → `lift-logs.destroy-selected`
  - `workouts.import-tsv` → `lift-logs.import-tsv`

### View Changes

#### Directory Structure
- **Current**: `resources/views/workouts/`
- **New**: `resources/views/lift-logs/`

#### View Files to Rename
- `workouts/index.blade.php` → `lift-logs/index.blade.php`
- `workouts/edit.blade.php` → `lift-logs/edit.blade.php`

#### Content Updates
- Update all page titles to use "Lift Log" terminology
- Update form labels and button text
- Update success/error message displays
- Update navigation breadcrumbs
- Update chart titles and labels

#### Navigation Updates
- Main navigation: "Lifts" instead of "Workouts"
- Sub-navigation: "Lift Logs" instead of "Workouts"
- Update active state detection for new routes

## Data Models

### Database Schema Changes

#### lift_logs Table (formerly workouts)
```sql
CREATE TABLE lift_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id BIGINT UNSIGNED NOT NULL,
    comments TEXT NULL,
    logged_at TIMESTAMP NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### lift_sets Table (formerly workout_sets)
```sql
CREATE TABLE lift_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lift_log_id BIGINT UNSIGNED NOT NULL,
    weight DECIMAL(8,2) NOT NULL,
    reps INTEGER NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (lift_log_id) REFERENCES lift_logs(id) ON DELETE CASCADE
);
```

### Model Relationships

#### LiftLog Relationships
- `belongsTo(Exercise::class)` - Each lift log belongs to one exercise
- `hasMany(LiftSet::class)` - Each lift log has multiple sets
- `belongsTo(User::class)` - Each lift log belongs to one user

#### LiftSet Relationships
- `belongsTo(LiftLog::class)` - Each set belongs to one lift log

#### User Relationships
- `hasMany(LiftLog::class)` - Users have many lift logs

#### Exercise Relationships  
- `hasMany(LiftLog::class)` - Exercises have many lift logs

## Error Handling

### Migration Error Handling
- Backup existing data before migration
- Rollback capability if migration fails
- Validation of data integrity after migration
- Graceful handling of foreign key constraint updates

### Application Error Handling
- Maintain existing validation rules
- Update error messages to use new terminology
- Ensure proper authorization checks remain in place
- Handle edge cases in TSV import/export

### User Experience Error Handling
- Clear error messages using "lift log" terminology
- Proper form validation feedback
- Graceful handling of missing or invalid data

## Testing Strategy

### Unit Tests
- Test LiftLog model methods and relationships
- Test LiftSet model methods and relationships
- Test computed attributes (one rep max calculations)
- Test model factories and seeders

### Feature Tests
- Test LiftLogController CRUD operations
- Test TSV import/export functionality
- Test bulk delete operations
- Test user authorization and data isolation
- Test chart data generation

### Integration Tests
- Test complete user workflows (create, edit, delete lift logs)
- Test navigation and UI consistency
- Test responsive design maintenance
- Test relationship integrity

### Migration Tests
- Test database migration success
- Test data preservation during migration
- Test rollback functionality
- Test foreign key constraint updates

### Test File Updates Required
- `WorkoutLoggingTest.php` → `LiftLogLoggingTest.php`
- `WorkoutImportTest.php` → `LiftLogImportTest.php`
- `WorkoutExerciseFilteringTest.php` → `LiftLogExerciseFilteringTest.php`
- Update all test method names and assertions
- Update route references in tests
- Update model factory references

## Implementation Phases

### Phase 1: Database Migration
1. Create migration to rename tables
2. Update foreign key relationships
3. Test data integrity

### Phase 2: Model and Relationship Updates
1. Rename Workout to LiftLog
2. Rename WorkoutSet to LiftSet
3. Update model relationships
4. Update factory and seeder references

### Phase 3: Controller and Route Updates
1. Rename WorkoutController to LiftLogController
2. Update route definitions
3. Update controller method implementations
4. Update success/error messages

### Phase 4: View and UI Updates
1. Rename view directories and files
2. Update view content and terminology
3. Update navigation elements
4. Update form labels and buttons

### Phase 5: Service Layer Updates
1. Update TsvImporterService references
2. Update OneRepMaxCalculatorService references
3. Update any other service dependencies

### Phase 6: Test Updates
1. Rename test files
2. Update test method names
3. Update assertions and expectations
4. Update route and model references

### Phase 7: Final Integration Testing
1. Run comprehensive test suite
2. Test complete user workflows
3. Verify UI consistency
4. Test TSV import/export
5. Verify chart functionality