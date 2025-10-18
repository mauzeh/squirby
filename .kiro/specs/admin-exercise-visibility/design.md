# Design Document

## Overview

This feature modifies the exercise list functionality to provide role-based visibility. The current system uses the `Exercise::availableToUser()` scope which shows both global exercises and the user's own exercises. For admin users, we need to expand this to show all exercises from all users while maintaining the existing behavior for regular users.

## Architecture

The solution will be implemented by enhancing the existing `Exercise::availableToUser()` scope to handle admin users differently. This approach maintains consistency across the entire system and prevents authorization issues in other controller methods that rely on this scope.

### Current System Analysis

- **Exercise Model**: Uses `availableToUser()` scope to filter exercises - this needs enhancement
- **User Model**: Has role-based authentication with `hasRole('Admin')` method
- **Controller**: Currently calls `Exercise::availableToUser(auth()->id())` in index method
- **View**: Already displays user badges and handles both global and user-specific exercises

## Components and Interfaces

### Modified Components

#### Exercise Model - availableToUser() Scope
- **Current Behavior**: Shows global exercises + user's own exercises
- **New Behavior**: 
  - Admin users: Show all exercises from all users
  - Regular users: Maintain current behavior (global + own exercises)
- **Implementation**: Accept User model or user ID, check admin role within scope

#### No Controller Changes Required
- All existing controller methods will automatically benefit from the enhanced scope
- This includes index, showLogs, and any other methods using availableToUser()

### Unchanged Components

- **Exercise Policy**: Existing authorization logic remains intact
- **View Template**: Current template already handles user identification via badges
- **User Role System**: No changes to role management
- **Database Schema**: No schema modifications required

## Data Models

### Exercise Model Relationships
```php
// Existing relationships remain unchanged
public function user() // belongsTo User
public function liftLogs() // hasMany LiftLog
```

### Enhanced Scope Implementation

#### Updated availableToUser() Scope
```php
public function scopeAvailableToUser($query, $userId)
{
    // Get the user model to check role
    $user = User::find($userId);
    
    if ($user && $user->hasRole('Admin')) {
        // Admin users see all exercises
        return $query->orderByRaw('user_id IS NULL ASC');
    }
    
    // Regular users see global + own exercises (current behavior)
    return $query->where(function ($q) use ($userId) {
        $q->whereNull('user_id')        // Global exercises
          ->orWhere('user_id', $userId); // User's own exercises
    })->orderByRaw('user_id IS NULL ASC');
}
```

#### All Existing Controller Calls Remain Unchanged
```php
// This call now automatically handles admin vs regular user logic
Exercise::availableToUser(auth()->id())
    ->with('user')
    ->orderBy('user_id')
    ->orderBy('title', 'asc')
    ->get()
```

## Error Handling

### Security Considerations
- **Role Verification**: Ensure admin role is properly verified before expanding visibility
- **Authorization**: Maintain existing exercise-level permissions for edit/delete operations
- **Data Integrity**: Preserve existing user scoping for all other exercise operations

### Error Scenarios
- **Invalid Role**: If role check fails, default to regular user behavior
- **Missing User Relationship**: Handle exercises with null user_id (global exercises)
- **Performance**: Monitor query performance with larger datasets

## Testing Strategy

### Unit Tests
- Test controller method with admin user returns all exercises
- Test controller method with regular user returns scoped exercises
- Verify role-based conditional logic

### Integration Tests
- Test complete exercise list workflow for both user types
- Verify existing functionality remains intact for regular users
- Test admin-specific visibility features

### Security Tests
- Verify non-admin users cannot access system-wide exercise data
- Test role verification edge cases
- Ensure existing authorization policies are preserved

## Implementation Approach

### Phase 1: Model Scope Enhancement
1. Modify `Exercise::scopeAvailableToUser()` to accept user ID and check admin role
2. Implement conditional query logic within the scope
3. Maintain existing ordering patterns

### Phase 2: Testing and Validation
1. Create comprehensive test coverage for the enhanced scope
2. Verify all existing controller methods work correctly
3. Test with various user roles and exercise datasets

### Phase 3: System-wide Verification
1. Verify all controller methods using the scope work correctly
2. Test authorization flows for edit/delete operations
3. Ensure no breaking changes to existing functionality

## Design Decisions

### Decision 1: Model Scope vs Controller Implementation
**Chosen**: Enhance existing model scope
**Rationale**: System-wide consistency, prevents authorization issues, single point of change

### Decision 2: Query Strategy
**Chosen**: Conditional query based on role check
**Rationale**: Leverages existing patterns, maintains performance, preserves current functionality

### Decision 3: View Modifications
**Chosen**: No view changes required
**Rationale**: Current template already handles user identification and mixed exercise types

## Performance Considerations

- **Query Efficiency**: Admin queries will return more data but use existing indexes
- **Memory Usage**: Larger result sets for admin users, but within reasonable limits
- **Caching**: No immediate caching requirements, existing patterns sufficient