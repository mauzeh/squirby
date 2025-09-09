# Design Document

## Overview

This design outlines the comprehensive refactoring of the DailyLog system to FoodLog throughout the Laravel application. The refactoring involves renaming models, controllers, migrations, views, routes, tests, and all related functionality while maintaining exact behavioral compatibility and data integrity.

**Key Discovery**: The codebase already contains a `FoodLog.php` model that appears to be identical to `DailyLog.php`, suggesting this refactoring may have been partially started or planned previously.

## Architecture

### Current State Analysis

**Models:**
- `DailyLog.php` - Primary model (active)
- `FoodLog.php` - Duplicate model (inactive)
- Both models have identical structure and relationships

**Database:**
- Table: `daily_logs`
- Foreign keys: `ingredient_id`, `unit_id`, `user_id`
- Additional fields: `quantity`, `logged_at`, `notes`

**Controllers:**
- `DailyLogController.php` - Main controller handling CRUD operations

**Routes:**
- Resource routes: `daily-logs.*`
- Custom routes: `daily-logs/add-meal`, `daily-logs/destroy-selected`, etc.

**Views:**
- Directory: `resources/views/daily_logs/`
- Files: `index.blade.php`, `edit.blade.php`

**Tests:**
- `DailyLogExportTest.php`
- `DailyLogImportTest.php` 
- `DailyLogMultiUserTest.php`

## Components and Interfaces

### 1. Database Layer

**Migration Strategy:**
- Create new migration to rename `daily_logs` table to `food_logs`
- Update all foreign key references in related tables
- Ensure migration is reversible for rollback capability

**Table Rename:**
```sql
RENAME TABLE daily_logs TO food_logs;
```

### 2. Model Layer

**Primary Changes:**
- Remove existing `FoodLog.php` model (duplicate)
- Rename `DailyLog.php` to `FoodLog.php`
- Update class name from `DailyLog` to `FoodLog`
- Update table name property to `food_logs`
- Update factory references

**Model Relationships:**
- Maintain all existing relationships (User, Ingredient, Unit)
- Update relationship method names in related models

### 3. Controller Layer

**Controller Refactoring:**
- Rename `DailyLogController.php` to `FoodLogController.php`
- Update class name and all method implementations
- Update model references from `DailyLog` to `FoodLog`
- Update variable names from `$dailyLog` to `$foodLog`
- Update route redirects and responses

### 4. Route Layer

**Route Updates:**
- Change resource route from `daily-logs` to `food-logs`
- Update all custom route paths:
  - `daily-logs/add-meal` → `food-logs/add-meal`
  - `daily-logs/destroy-selected` → `food-logs/destroy-selected`
  - `daily-logs/import-tsv` → `food-logs/import-tsv`
  - `daily-logs/export` → `food-logs/export`
  - `daily-logs/export-all` → `food-logs/export-all`
- Update route names to use `food-logs.*` pattern
- Update default redirect from `daily-logs.index` to `food-logs.index`

### 5. View Layer

**Directory and File Changes:**
- Rename `resources/views/daily_logs/` to `resources/views/food_logs/`
- Update all route references in view files
- Update form action URLs
- Update navigation menu items
- Update page titles and headings
- Update button text and labels

**UI Text Updates:**
- "Daily Logs" → "Food Logs"
- "Daily Log" → "Food Log"
- "Add Daily Log" → "Add Food Log"
- "Edit Daily Log" → "Edit Food Log"

### 6. Service Layer

**Service Updates:**
- Update `TsvImporterService.php` to reference `FoodLog` model
- Update `NutritionService.php` if it references daily logs
- Update any other services that interact with the model

### 7. Factory and Seeder Layer

**Factory Updates:**
- Rename `DailyLogFactory.php` to `FoodLogFactory.php`
- Update factory class name and model reference
- Update any seeders that use the factory

## Data Models

### FoodLog Model Structure
```php
class FoodLog extends Model
{
    protected $table = 'food_logs';
    
    protected $fillable = [
        'ingredient_id',
        'unit_id', 
        'quantity',
        'logged_at',
        'notes',
        'user_id',
    ];
    
    protected $casts = [
        'logged_at' => 'datetime',
    ];
    
    // Relationships remain the same
    public function ingredient() { ... }
    public function unit() { ... }
    public function user() { ... }
}
```

### Related Model Updates

**User Model:**
```php
public function foodLogs()
{
    return $this->hasMany(FoodLog::class);
}
```

**Ingredient Model:**
```php
public function foodLogs()
{
    return $this->hasMany(FoodLog::class);
}
```

## Error Handling

### Migration Error Handling
- Wrap table rename in transaction
- Check for table existence before rename
- Provide clear error messages for constraint violations
- Implement rollback mechanism

### Application Error Handling
- Update error messages to reference "Food Log"
- Ensure validation messages use new terminology
- Update exception handling in controllers

### Testing Error Scenarios
- Test migration rollback functionality
- Test constraint violations during rename
- Test application behavior with missing routes

## Testing Strategy

### Test File Refactoring
1. **Rename Test Files:**
   - `DailyLogExportTest.php` → `FoodLogExportTest.php`
   - `DailyLogImportTest.php` → `FoodLogImportTest.php`
   - `DailyLogMultiUserTest.php` → `FoodLogMultiUserTest.php`

2. **Update Test Content:**
   - Change class names
   - Update model references
   - Update route references
   - Update factory references
   - Update assertion messages

3. **Factory Updates:**
   - Rename `DailyLogFactory` to `FoodLogFactory`
   - Update model reference in factory

### Test Execution Strategy
- Run tests after each major component change
- Full test suite execution after each phase
- Regression testing to ensure no functionality breaks
- Performance testing to ensure no degradation

### Test Coverage Areas
- Model CRUD operations
- Controller endpoints
- Route accessibility
- View rendering
- Import/Export functionality
- Multi-user isolation
- Database constraints
- Migration reversibility

## Implementation Phases

### Phase 1: Database Migration
- Create and test table rename migration
- Verify data integrity after migration
- Test migration rollback

### Phase 2: Model Layer
- Remove duplicate FoodLog model
- Rename DailyLog to FoodLog
- Update related model relationships
- Run model tests

### Phase 3: Controller Layer
- Rename and update controller
- Update all method implementations
- Update variable naming
- Run controller tests

### Phase 4: Route Layer
- Update route definitions
- Update route names
- Update default redirects
- Test all endpoints

### Phase 5: View Layer
- Rename view directory
- Update all view files
- Update navigation and UI text
- Test UI functionality

### Phase 6: Service and Factory Layer
- Update service references
- Rename and update factory
- Update seeders if needed
- Run service tests

### Phase 7: Test Layer
- Rename all test files
- Update test implementations
- Run full test suite
- Verify all tests pass

## Rollback Strategy

### Database Rollback
- Migration down method to rename table back
- Restore original foreign key constraints
- Verify data integrity after rollback

### Code Rollback
- Git branch strategy for safe rollback
- Component-by-component rollback capability
- Automated rollback scripts for critical components

## Risk Mitigation

### Data Loss Prevention
- Full database backup before migration
- Transaction-wrapped migration operations
- Data integrity verification at each step

### Functionality Preservation
- Comprehensive test coverage
- Incremental testing approach
- Feature flag capability for gradual rollout

### Performance Considerations
- Index preservation during table rename
- Query performance verification
- Database connection pool considerations