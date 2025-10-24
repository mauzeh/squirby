# Design Document

## Overview

This design outlines the comprehensive refactoring of the MeasurementLog system to BodyLog throughout the Laravel application. The refactor involves renaming classes, database tables, routes, views, and all related references while maintaining complete functionality and data integrity. The system currently tracks various body measurements (weight, body fat, etc.) and this refactor will make the naming more intuitive and user-friendly.

## Architecture

The refactor follows Laravel's MVC architecture and maintains the existing system structure:

- **Model Layer**: `MeasurementLog` → `BodyLog`, relationships with `MeasurementType` (keeping this name as it's still accurate)
- **Controller Layer**: `MeasurementLogController` → `BodyLogController`
- **View Layer**: All measurement-logs views → body-logs views
- **Database Layer**: `measurement_logs` table → `body_logs` table
- **Route Layer**: measurement-logs routes → body-logs routes
- **Service Layer**: Update `TsvImporterService` to use BodyLog references

## Components and Interfaces

### Database Schema Changes

#### Primary Table Rename
- **Current**: `measurement_logs` table
- **New**: `body_logs` table
- **Migration Strategy**: Create new migration to rename table and update foreign key references

#### Foreign Key Updates
- Update any foreign key references from `measurement_log_id` to `body_log_id` (if any exist)
- Maintain existing relationships with `measurement_types` table (name remains unchanged)

#### Migration Files
- Create new migration: `rename_measurement_logs_table_to_body_logs.php`
- Handle foreign key constraint updates
- Ensure rollback capability

### Model Changes

#### BodyLog Model (formerly MeasurementLog)
```php
class BodyLog extends Model
{
    protected $table = 'body_logs';
    protected $fillable = [
        'measurement_type_id',
        'value', 
        'logged_at',
        'comments',
        'user_id',
    ];
    
    // Relationships remain the same structure
    public function measurementType() // Keep this name as MeasurementType is still accurate
    public function user()
}
```

#### Related Model Updates
- **User Model**: Update relationship method from `measurementLogs()` to `bodyLogs()`
- **MeasurementType Model**: Update relationship method from `measurementLogs()` to `bodyLogs()`

### Controller Changes

#### BodyLogController (formerly MeasurementLogController)
- Rename class and file
- Update all internal references to use BodyLog model
- Update route redirects to use new route names
- Update view references to use new view paths
- Maintain all existing functionality (CRUD, import, filtering)

### Route Changes

#### Route Definitions
- **Current**: `measurement-logs.*` routes
- **New**: `body-logs.*` routes
- **Specific Changes**:
  - `measurement-logs.index` → `body-logs.index`
  - `measurement-logs.create` → `body-logs.create`
  - `measurement-logs.store` → `body-logs.store`
  - `measurement-logs.edit` → `body-logs.edit`
  - `measurement-logs.update` → `body-logs.update`
  - `measurement-logs.destroy` → `body-logs.destroy`
  - `measurement-logs.destroy-selected` → `body-logs.destroy-selected`
  - `measurement-logs.import-tsv` → `body-logs.import-tsv`
  - `measurement-logs.show-by-type` → `body-logs.show-by-type`

### View Changes

#### Directory Structure
- **Current**: `resources/views/measurement-logs/`
- **New**: `resources/views/body-logs/`

#### View Files to Rename
- `measurement-logs/index.blade.php` → `body-logs/index.blade.php`
- `measurement-logs/create.blade.php` → `body-logs/create.blade.php`
- `measurement-logs/edit.blade.php` → `body-logs/edit.blade.php`
- `measurement-logs/show-by-type.blade.php` → `body-logs/show-by-type.blade.php`

#### Content Updates
- Update all route references to use new route names
- Update page titles and headings to use "Body Log" terminology
- Update form actions and button labels
- Update navigation links and breadcrumbs

### Service Layer Changes

#### TsvImporterService Updates
- Update `importMeasurements()` method to use `BodyLog` model
- Maintain existing functionality and return structure
- Update any internal variable names for consistency

## Data Models

### BodyLog Model Structure
```php
// Database Schema
body_logs:
- id (primary key)
- measurement_type_id (foreign key to measurement_types)
- user_id (foreign key to users)
- value (decimal)
- logged_at (timestamp)
- comments (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)

// Relationships
- belongsTo(MeasurementType::class)
- belongsTo(User::class)
```

### Relationship Updates
```php
// User Model
public function bodyLogs()
{
    return $this->hasMany(BodyLog::class);
}

// MeasurementType Model  
public function bodyLogs()
{
    return $this->hasMany(BodyLog::class);
}
```

## Error Handling

### Migration Error Handling
- Check for table existence before renaming
- Handle foreign key constraint conflicts
- Provide rollback mechanism for failed migrations
- Log migration progress and errors

### Application Error Handling
- Update error messages to use "body log" terminology
- Maintain existing validation rules and error responses
- Ensure 404 errors use correct model binding

### Data Integrity
- Verify all foreign key relationships remain intact
- Ensure no orphaned records during migration
- Validate data consistency after refactor

## Testing Strategy

### Database Testing
- Test migration up and down methods
- Verify table rename and foreign key updates
- Test data preservation during migration
- Validate relationship integrity

### Model Testing
- Update existing model tests to use BodyLog
- Test all model relationships and methods
- Verify factory and seeder compatibility
- Test model validation rules

### Feature Testing
- Update all existing feature tests
- Test CRUD operations with new routes and controllers
- Test TSV import functionality
- Test multi-user isolation
- Test filtering and chart functionality

### Integration Testing
- Test complete user workflows
- Verify navigation and UI consistency
- Test form submissions and redirects
- Validate error handling and success messages

### Test File Updates Required
- `MeasurementLogManagementTest.php` → `BodyLogManagementTest.php`
- `MeasurementLogImportTest.php` → `BodyLogImportTest.php`
- Update all test method names and assertions
- Update route references in tests
- Update model factory references

## Implementation Phases

### Phase 1: Database Migration
1. Create migration to rename table
2. Update foreign key references
3. Test migration rollback capability

### Phase 2: Model and Relationship Updates
1. Rename MeasurementLog to BodyLog
2. Update model relationships
3. Update factory and seeder references

### Phase 3: Controller and Route Updates
1. Rename controller class and file
2. Update route definitions
3. Update controller method implementations

### Phase 4: View and UI Updates
1. Rename view directory and files
2. Update view content and references
3. Update navigation and UI elements

### Phase 5: Service and Helper Updates
1. Update TsvImporterService
2. Update any helper classes or utilities
3. Update configuration references

### Phase 6: Testing Updates
1. Rename and update test files
2. Update test assertions and expectations
3. Verify all tests pass with new structure

## Rollback Strategy

### Database Rollback
- Migration down method to rename table back
- Restore original foreign key constraints
- Verify data integrity after rollback

### Code Rollback
- Git branch strategy for safe rollback
- Maintain backward compatibility during transition
- Document rollback procedures for each phase