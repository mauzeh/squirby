# Design Document

## Overview

This feature adds a user preference setting to control whether global exercises are visible in the lift-logs mobile entry interface. The setting will be integrated into the existing profile settings page and will modify the exercise filtering behavior in the mobile entry interface.

## Architecture

The solution follows Laravel's MVC pattern and integrates with the existing user profile system:

1. **Database Layer**: Add a new boolean column to the users table
2. **Model Layer**: Update the User model to include the new preference
3. **Controller Layer**: Modify ProfileController and LiftLogController to handle the setting
4. **View Layer**: Add UI controls to the profile settings and modify exercise filtering logic

## Components and Interfaces

### Database Schema Changes

**Migration**: Add `show_global_exercises` column to users table
- Column: `show_global_exercises` (boolean, default: true)
- This ensures new users have global exercises enabled by default

### Model Updates

**User Model** (`app/Models/User.php`)
- Add `show_global_exercises` to fillable attributes
- Add cast to boolean type
- Add helper method `shouldShowGlobalExercises(): bool`

**Exercise Model** (`app/Models/Exercise.php`)
- Modify `scopeAvailableToUser` method to accept optional parameter for global exercise visibility
- Create new scope `scopeAvailableToUserWithPreference` that respects user preference

### Controller Changes

**ProfileController** (`app/Http/Controllers/ProfileController.php`)
- Update `update` method to handle the new `show_global_exercises` field
- Add validation for the boolean field

**LiftLogController** (`app/Http/Controllers/LiftLogController.php`)
- Modify `mobileEntry` method to pass user's global exercise preference to exercise query
- Update exercise filtering to respect user preference

### View Updates

**Profile Settings** (`resources/views/profile/partials/update-profile-information-form.blade.php`)
- Add checkbox input for global exercise visibility setting
- Include proper labeling and help text
- Ensure form submission includes the new field

**Mobile Entry Interface** (`resources/views/lift-logs/mobile-entry.blade.php`)
- No direct changes needed - filtering happens at controller level
- Exercise list will automatically reflect user preference

### Component Integration

**Exercise List Component** (`resources/views/components/lift-logs/mobile-entry/exercise-list.blade.php`)
- Receives filtered exercise list from controller
- No changes needed to component itself

## Data Models

### User Model Extension
```php
// Additional fillable attribute
'show_global_exercises'

// Additional cast
'show_global_exercises' => 'boolean'

// Helper method
public function shouldShowGlobalExercises(): bool
{
    return $this->show_global_exercises ?? true;
}
```

### Exercise Query Modification
```php
// New scope method
public function scopeAvailableToUserWithPreference($query, $userId, $showGlobal = true)
{
    $user = User::find($userId);
    
    if ($user && $user->hasRole('Admin')) {
        return $query->orderByRaw('user_id IS NULL ASC');
    }
    
    if ($showGlobal) {
        // Current behavior - show global + user exercises
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $userId);
        })->orderByRaw('user_id IS NULL ASC');
    } else {
        // New behavior - show only user exercises
        return $query->where('user_id', $userId)
                    ->orderBy('title', 'asc');
    }
}
```

## Error Handling

### Validation
- Profile update form validates `show_global_exercises` as boolean
- Default to `true` if not provided to maintain backward compatibility

### Migration Safety
- Use nullable boolean with default value to handle existing users
- Backfill existing users with `true` value to maintain current behavior

### Fallback Behavior
- If user preference is null/undefined, default to showing global exercises
- Admin users always see all exercises regardless of preference

## Testing Strategy

### Unit Tests
- Test User model helper method `shouldShowGlobalExercises()`
- Test Exercise scope `availableToUserWithPreference` with different parameters
- Test ProfileController update method with new field

### Feature Tests
- Test profile settings form submission with global exercise preference
- Test mobile entry interface respects user preference for exercise visibility
- Test that admin users always see all exercises regardless of preference
- Test default behavior for new users (global exercises enabled)

### Integration Tests
- Test complete workflow: user changes preference → mobile entry reflects change
- Test that existing functionality remains unchanged when preference is enabled
- Test that only user exercises show when preference is disabled

## Implementation Notes

### Backward Compatibility
- Existing users will have `show_global_exercises` set to `true` by default
- Current behavior is preserved unless user explicitly changes setting
- Admin users maintain full exercise visibility regardless of setting

### Performance Considerations
- Exercise filtering happens at query level, not in PHP
- No additional database queries required
- Existing indexes on `user_id` column support efficient filtering

### User Experience
- Setting is clearly labeled in profile settings
- Immediate effect when changed (no cache clearing required)
- Intuitive default behavior (global exercises enabled)

### Security Considerations
- User can only modify their own preference
- Admin users maintain elevated permissions
- No exposure of other users' exercises regardless of setting