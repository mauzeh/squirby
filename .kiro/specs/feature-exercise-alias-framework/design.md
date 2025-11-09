# Design Document

## Overview

The Exercise Alias Framework provides a transparent layer that allows users to see personalized exercise names throughout the application. When an administrator merges a user's custom exercise (e.g., "BP") into a global exercise (e.g., "Bench Press"), an alias can be automatically created so the user continues to see "BP" in all their workout logs, exercise lists, and programs. This preserves user familiarity while consolidating duplicate exercises.

The system is designed to be completely transparent - users see their aliases without any indication that they're using a personalized name. Aliases are user-scoped, meaning each user can have their own personalized names for exercises without affecting other users.

## Architecture

### Core Components

1. **ExerciseAlias Model**: Stores user-specific exercise name mappings
2. **ExerciseAliasService**: Handles alias resolution and application logic
3. **ExerciseMergeService Enhancement**: Extended to support alias creation during merges
4. **View Composers/Presenters**: Apply aliases transparently across all views
5. **Query Scopes**: Efficiently load and apply aliases in database queries

### Data Flow

```
Merge Operation:
Admin initiates merge → Merge form shows "Create alias" checkbox (checked by default) →
Admin confirms → ExerciseMergeService creates alias (if enabled) →
Alias stored in database → Source exercise deleted

Display Operation:
User requests page → Controller loads exercises with aliases (eager loading) →
ExerciseAliasService resolves display names → View renders with aliases →
User sees personalized names (or exercise title if no alias)
```

### Integration Points

- **Exercise Merge Interface**: Add checkbox for alias creation
- **Exercise Lists**: Apply aliases in index, dropdowns, autocomplete
- **Lift Log Views**: Apply aliases in tables, charts, exports
- **Program Views**: Apply aliases in program entries and templates
- **Exercise Model**: Add relationship and helper methods

## Components and Interfaces

### Database Schema

**New Table: `exercise_aliases`**

```sql
CREATE TABLE exercise_aliases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    exercise_id BIGINT UNSIGNED NOT NULL,
    alias_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_exercise (user_id, exercise_id),
    INDEX idx_user_id (user_id),
    INDEX idx_exercise_id (exercise_id)
);
```

**Key Design Decisions:**
- `user_id` + `exercise_id` unique constraint prevents duplicate aliases
- Cascade deletes ensure data integrity when users or exercises are deleted
- Indexes on both foreign keys for efficient lookups
- `alias_name` uses same VARCHAR(255) as exercise title for consistency

### ExerciseAlias Model

**Location**: `app/Models/ExerciseAlias.php`

**Relationships:**
```php
public function user(): BelongsTo
public function exercise(): BelongsTo
```

**Validation Rules:**
- `user_id`: required, exists in users table
- `exercise_id`: required, exists in exercises table
- `alias_name`: required (inherited from source exercise title)
- Unique constraint on (user_id, exercise_id)

**Scopes:**
```php
public function scopeForUser($query, $userId)
public function scopeForExercise($query, $exerciseId)
```

### ExerciseAliasService

**Location**: `app/Services/ExerciseAliasService.php`

**Key Methods:**

```php
/**
 * Create an alias for a user and exercise
 */
public function createAlias(User $user, Exercise $exercise, string $aliasName): ExerciseAlias

/**
 * Get all aliases for a user (returns collection keyed by exercise_id)
 */
public function getUserAliases(User $user): Collection

/**
 * Apply aliases to a collection of exercises for a user
 */
public function applyAliasesToExercises(Collection $exercises, User $user): Collection

/**
 * Get display name for an exercise (alias if exists, otherwise title)
 */
public function getDisplayName(Exercise $exercise, User $user): string

/**
 * Check if an alias exists for a user and exercise
 */
public function hasAlias(User $user, Exercise $exercise): bool

/**
 * Delete an alias (admin operation)
 */
public function deleteAlias(ExerciseAlias $alias): bool
```

**Caching Strategy:**
- Cache user aliases in memory during request lifecycle
- Use Laravel's collection caching for repeated lookups within the same request

### Exercise Model Enhancement

**New Relationship:**
```php
public function aliases(): HasMany
{
    return $this->hasMany(ExerciseAlias::class);
}
```

**New Helper Methods:**
```php
/**
 * Get the display name for this exercise for a specific user
 */
public function getDisplayNameForUser(User $user): string
{
    return app(ExerciseAliasService::class)->getDisplayName($this, $user);
}

/**
 * Check if this exercise has an alias for a specific user
 */
public function hasAliasForUser(User $user): bool
{
    return app(ExerciseAliasService::class)->hasAlias($user, $this);
}
```

**Eager Loading Support:**
```php
// In queries, eager load aliases for current user
Exercise::with(['aliases' => function ($query) {
    $query->where('user_id', auth()->id());
}])->get();
```

### ExerciseMergeService Enhancement

**Updated Method Signature:**
```php
public function mergeExercises(
    Exercise $source, 
    Exercise $target, 
    User $admin,
    bool $createAlias = true
): bool
```

**New Private Method:**
```php
/**
 * Create alias for the source exercise owner if requested
 */
private function createAliasForOwner(
    Exercise $source, 
    Exercise $target, 
    bool $createAlias
): void {
    if (!$createAlias || !$source->user) {
        return;
    }
    
    $aliasService = app(ExerciseAliasService::class);
    $aliasService->createAlias(
        $source->user,
        $target,
        $source->title
    );
}
```

**Integration Point:**
- Call `createAliasForOwner()` after transferring data but before deleting source
- Wrap in same transaction as merge operation
- Log alias creation in merge audit log

### Merge View Enhancement

**Location**: `resources/views/exercises/merge.blade.php`

**New Form Element:**
```html
<div style="margin: 20px 0; padding: 15px; background-color: #2a2a2a; border-radius: 5px;">
    <label style="display: flex; align-items: center; cursor: pointer;">
        <input type="checkbox" name="create_alias" value="1" checked style="margin-right: 10px; transform: scale(1.2);">
        <div>
            <strong style="color: #f2f2f2;">Create alias for exercise owner</strong>
            <p style="color: #aaa; margin: 5px 0 0 0; font-size: 0.9em;">
                The owner will continue to see "{{ $exercise->title }}" instead of the target exercise name
            </p>
        </div>
    </label>
</div>
```

**Controller Update:**
```php
public function merge(Request $request, Exercise $exercise)
{
    // ... existing validation ...
    
    $createAlias = $request->boolean('create_alias', true);
    
    $this->exerciseMergeService->mergeExercises(
        $exercise, 
        $targetExercise, 
        auth()->user(),
        $createAlias
    );
    
    // ... existing redirect ...
}
```

## Data Models

### ExerciseAlias Entity

```
ExerciseAlias {
    id: bigint (PK)
    user_id: bigint (FK → users.id)
    exercise_id: bigint (FK → exercises.id)
    alias_name: string(255)
    created_at: timestamp
    updated_at: timestamp
}

Relationships:
- belongs to User
- belongs to Exercise

Constraints:
- UNIQUE(user_id, exercise_id)
- CASCADE DELETE on user_id
- CASCADE DELETE on exercise_id
```

### Relationship Diagram

```
User (1) ──────< (N) ExerciseAlias (N) >────── (1) Exercise
         has many              belongs to
```

## Display Logic Implementation

### View Composer Approach

**Location**: `app/Http/View/Composers/ExerciseAliasComposer.php`

```php
class ExerciseAliasComposer
{
    protected $aliasService;
    
    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }
    
    public function compose(View $view)
    {
        if (!auth()->check()) {
            return;
        }
        
        // Get exercises from view data
        $exercises = $view->getData()['exercises'] ?? null;
        
        if ($exercises instanceof Collection) {
            $view->with('exercises', 
                $this->aliasService->applyAliasesToExercises($exercises, auth()->user())
            );
        }
    }
}
```

**Registration** in `AppServiceProvider`:
```php
View::composer([
    'exercises.index',
    'exercises.merge',
    'lift-logs.*',
    'programs.*'
], ExerciseAliasComposer::class);
```

### Alternative: Accessor Pattern

Add to Exercise model:
```php
protected $appends = ['display_name'];

public function getDisplayNameAttribute(): string
{
    if (!auth()->check()) {
        return $this->title;
    }
    
    // Check if alias is already loaded
    if ($this->relationLoaded('aliases') && $this->aliases->isNotEmpty()) {
        return $this->aliases->first()->alias_name;
    }
    
    return $this->title;
}
```

**Recommendation**: Use View Composer approach for better separation of concerns and performance control.

## Performance Optimization

### Query Optimization

**Eager Loading Pattern:**
```php
// In controllers
$exercises = Exercise::availableToUser()
    ->with(['aliases' => function ($query) {
        $query->where('user_id', auth()->id());
    }])
    ->get();
```

**Benefits:**
- Single additional query for all aliases (N+1 prevention)
- Filtered at database level for current user only
- Minimal memory overhead

### Caching Strategy

**Request-Level Cache:**
```php
class ExerciseAliasService
{
    protected $userAliasesCache = [];
    
    public function getUserAliases(User $user): Collection
    {
        if (!isset($this->userAliasesCache[$user->id])) {
            $this->userAliasesCache[$user->id] = ExerciseAlias::forUser($user->id)
                ->get()
                ->keyBy('exercise_id');
        }
        
        return $this->userAliasesCache[$user->id];
    }
}
```

### Performance Targets

- **Additional Query Overhead**: Maximum 1 additional query per page load
- **Memory Overhead**: < 1KB per 100 aliases
- **Response Time Impact**: < 10ms additional processing time

## Error Handling

### Alias Creation Errors

**Duplicate Alias:**
```php
try {
    $aliasService->createAlias($user, $exercise, $aliasName);
} catch (QueryException $e) {
    if ($e->getCode() === '23000') { // Duplicate entry
        Log::warning('Duplicate alias creation attempted', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        // Silently skip - alias already exists
        return;
    }
    throw $e;
}
```

### Merge Operation Errors

**Transaction Rollback:**
```php
DB::beginTransaction();
try {
    // Transfer data
    $this->transferLiftLogs($source, $target);
    $this->transferProgramEntries($source, $target);
    
    // Create alias
    if ($createAlias) {
        $this->createAliasForOwner($source, $target, true);
    }
    
    // Delete source
    $source->delete();
    
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    Log::error('Merge with alias creation failed', [
        'source_id' => $source->id,
        'target_id' => $target->id,
        'error' => $e->getMessage()
    ]);
    throw $e;
}
```

### Display Fallback

**Graceful Degradation:**
```php
public function getDisplayName(Exercise $exercise, User $user): string
{
    try {
        $alias = ExerciseAlias::forUser($user->id)
            ->forExercise($exercise->id)
            ->first();
            
        return $alias ? $alias->alias_name : $exercise->title;
    } catch (Exception $e) {
        Log::error('Alias lookup failed', [
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'error' => $e->getMessage()
        ]);
        
        // Fallback to exercise title
        return $exercise->title;
    }
}
```

## Testing Strategy

### Unit Tests

**ExerciseAliasServiceTest:**
- Test alias creation with source exercise title
- Test duplicate alias prevention
- Test getUserAliases returns correct collection
- Test applyAliasesToExercises modifies exercise titles
- Test getDisplayName returns alias when exists
- Test getDisplayName returns title when no alias
- Test caching behavior

**ExerciseAliasModelTest:**
- Test relationships (user, exercise)
- Test unique constraint enforcement
- Test cascade delete behavior

### Integration Tests

**ExerciseMergeWithAliasTest:**
- Test merge with alias creation enabled
- Test merge with alias creation disabled
- Test alias is created before source deletion
- Test alias creation within transaction
- Test rollback on alias creation failure
- Test merge with existing alias (should not duplicate)

**ExerciseDisplayTest:**
- Test exercise list shows aliases for user
- Test exercise list shows titles for admin
- Test lift log table shows aliases
- Test program view shows aliases
- Test export includes alias names
- Test search works with both alias and title

### Feature Tests

**End-to-End Merge Flow:**
1. Admin navigates to merge page
2. Sees "Create alias" checkbox (checked)
3. Selects target exercise
4. Confirms merge
5. Alias is created
6. User sees their original name in all views

**Alias Display Across Views:**
1. Create alias for user
2. User logs in
3. Check exercise index shows alias
4. Check lift logs show alias
5. Check programs show alias
6. Check charts use alias

### Performance Tests

**Query Count Test:**
```php
public function test_exercise_list_with_aliases_uses_single_query()
{
    $user = User::factory()->create();
    Exercise::factory()->count(10)->create();
    ExerciseAlias::factory()->count(5)->create(['user_id' => $user->id]);
    
    $this->actingAs($user);
    
    DB::enableQueryLog();
    $response = $this->get(route('exercises.index'));
    $queries = DB::getQueryLog();
    
    // Should be: 1 for exercises, 1 for aliases, 1 for user roles
    $this->assertLessThanOrEqual(3, count($queries));
}
```

## Migration Strategy

### Database Migration

**File**: `database/migrations/YYYY_MM_DD_create_exercise_aliases_table.php`

```php
public function up(): void
{
    Schema::create('exercise_aliases', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
        $table->string('alias_name');
        $table->timestamps();
        
        $table->unique(['user_id', 'exercise_id']);
        $table->index('user_id');
        $table->index('exercise_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('exercise_aliases');
}
```

### Deployment Steps

1. **Deploy database migration** - Create exercise_aliases table
2. **Deploy model and service** - Add ExerciseAlias model and service
3. **Deploy merge enhancement** - Update ExerciseMergeService
4. **Deploy view updates** - Add checkbox to merge form
5. **Deploy display logic** - Add view composers/accessors
6. **Test in production** - Verify aliases work correctly

### Rollback Plan

If issues arise:
1. Remove view composers from AppServiceProvider
2. Revert ExerciseMergeService changes
3. Revert merge view changes
4. Keep database table (aliases won't be displayed but data preserved)
5. Fix issues and redeploy

## Admin Operations

### Viewing Aliases (Future Enhancement)

**Admin Interface** (optional):
- View all aliases for a user
- View all aliases for an exercise
- Delete specific aliases
- Bulk delete aliases

**Implementation** (if needed):
```php
// In admin controller
public function showUserAliases(User $user)
{
    $aliases = ExerciseAlias::with('exercise')
        ->where('user_id', $user->id)
        ->orderBy('alias_name')
        ->get();
        
    return view('admin.user-aliases', compact('user', 'aliases'));
}
```

### Audit Logging

**Log Alias Creation:**
```php
Log::info('Exercise alias created', [
    'user_id' => $user->id,
    'user_email' => $user->email,
    'exercise_id' => $exercise->id,
    'exercise_title' => $exercise->title,
    'alias_name' => $aliasName,
    'created_via' => 'merge_operation',
    'admin_id' => $admin->id
]);
```

**Log Alias Deletion:**
```php
Log::info('Exercise alias deleted', [
    'alias_id' => $alias->id,
    'user_id' => $alias->user_id,
    'exercise_id' => $alias->exercise_id,
    'alias_name' => $alias->alias_name,
    'deleted_by' => auth()->id()
]);
```

## Security Considerations

### Authorization

**Alias Visibility:**
- Users see their own aliases throughout the application
- Aliases are user-scoped and automatically applied

**Alias Creation:**
- Only created through merge operation by admins
- No user-facing creation interface
- Validated and sanitized before storage

### Data Validation

**SQL Injection Prevention:**
- Use Eloquent ORM (parameterized queries)
- Validate foreign keys exist
- Use unique constraints

### Privacy

**User Data Protection:**
- Aliases are user-scoped (each user has their own)
- Cascade delete on user deletion
- Aliases included in user's own data exports

## Future Enhancements

### Potential Features (Not in Current Scope)

1. **User Alias Management**
   - Allow users to view their aliases
   - Allow users to edit/delete aliases
   - Allow users to create aliases manually

2. **Alias Suggestions**
   - Suggest aliases based on common abbreviations
   - Machine learning to detect duplicate exercises

3. **Alias Import/Export**
   - Bulk import aliases from CSV
   - Export aliases for backup

4. **Alias Analytics**
   - Track most common aliases
   - Identify exercises that need better naming

5. **Alias History**
   - Track alias changes over time
   - Allow reverting to previous aliases

These features can be added incrementally without changing the core architecture.
