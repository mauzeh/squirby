# PR Read Tracking System

## Overview

The PR read tracking system allows individual tracking of which personal records (PRs) each user has viewed in their feed. This provides more granular control over the "unread" badge display compared to the previous global timestamp approach.

## Architecture

### Database Schema

**Table: `personal_record_reads`**
- `id` - Primary key
- `user_id` - Foreign key to users table
- `personal_record_id` - Foreign key to personal_records table
- `read_at` - Timestamp when the PR was marked as read
- Unique constraint on `(user_id, personal_record_id)` to prevent duplicates
- Index on `(user_id, read_at)` for fast queries

### Models

**PersonalRecordRead**
- Pivot model representing the read status of a PR for a specific user
- No timestamps (uses custom `read_at` field)
- Relationships:
  - `user()` - The user who read the PR
  - `personalRecord()` - The PR that was read

**PersonalRecord** (updated)
- Added relationships:
  - `reads()` - HasMany relationship to PersonalRecordRead
  - `readBy()` - BelongsToMany relationship to User through personal_record_reads
- Added helper methods:
  - `isReadBy(User $user): bool` - Check if a specific user has read this PR
  - `markAsReadBy(User $user): void` - Mark this PR as read by a specific user

**User** (updated)
- Added relationship:
  - `readPersonalRecords()` - BelongsToMany relationship to PersonalRecord through personal_record_reads

## How It Works

### Marking PRs as Read

When a user views the feed (`FeedController::index()`):

1. All PRs in the last 7 days from followed users are fetched
2. The feed is rendered with the current read status
3. After the response is sent to the browser (using `app()->terminating()`), all visible PRs are marked as read
4. This is done by creating `PersonalRecordRead` records for each PR the user hasn't already read

```php
app()->terminating(function () use ($userId, $prIds) {
    foreach ($prIds as $prId) {
        \App\Models\PersonalRecordRead::firstOrCreate([
            'user_id' => $userId,
            'personal_record_id' => $prId,
        ], [
            'read_at' => now(),
        ]);
    }
});
```

### Badge Display

The feed badge shows when there are unread PRs:

1. Fetch all PRs from the last 7 days from followed users
2. Get IDs of PRs the current user has already read
3. Check if any PRs exist that are not in the read list
4. Display badge if unread PRs exist

```php
$readPRIds = $currentUser->readPersonalRecords()->pluck('personal_records.id')->toArray();
$hasNewPRs = $prs->contains(function ($pr) use ($readPRIds) {
    return !in_array($pr->id, $readPRIds);
});
```

## Benefits Over Previous System

### Previous System (Global Timestamp)
- Used `last_feed_viewed_at` timestamp on users table
- All PRs after this timestamp were considered "new"
- Issues:
  - Too restrictive - viewing feed once marked everything as read
  - No way to track individual PR read status
  - New connections required complex logic to show their PRs as unread

### Current System (Individual Tracking)
- Tracks each PR read status per user
- Benefits:
  - More granular control over what's considered "read"
  - Can implement features like "mark as unread" in the future
  - Easier to understand and debug
  - Better user experience - only truly viewed PRs are marked as read
  - Simpler logic for new connections - their PRs are simply not in the read list

## Performance Considerations

### Query Optimization
- Unique constraint prevents duplicate read records
- Index on `(user_id, read_at)` speeds up read status queries
- Uses `firstOrCreate()` to avoid duplicate inserts
- Read marking happens after response is sent (non-blocking)

### Scalability
- Read records only created for PRs in the last 7 days
- Old read records can be cleaned up periodically (future enhancement)
- Efficient bulk checking using `pluck()` and `in_array()`

## Future Enhancements

Possible improvements to the system:

1. **Cleanup Job**: Periodically delete read records older than 30 days
2. **Mark as Unread**: Allow users to manually mark PRs as unread
3. **Read Receipts**: Show PR owners who has viewed their PRs
4. **Selective Marking**: Only mark PRs as read when user scrolls past them (requires JavaScript)
5. **Read Statistics**: Track which types of PRs users engage with most

## Migration Path

The system was migrated from the global timestamp approach:

1. Created `personal_record_reads` table
2. Updated models with new relationships
3. Refactored `FeedController` to use individual read tracking
4. Removed dependency on `last_feed_viewed_at` for badge logic
5. Updated tests to verify new behavior

Note: The `last_feed_viewed_at` field still exists on the users table but is no longer used for feed badge logic. It can be removed in a future migration if no other features depend on it.

## Testing

The system includes comprehensive test coverage:

- `test_new_connection_prs_show_as_unread_in_feed()` - Verifies PRs from new connections appear as unread
- Tests verify that:
  - PRs are not marked as read before viewing
  - PRs are marked as read after viewing feed
  - Badge logic correctly identifies unread PRs

## Related Documentation

- [QR Connection Feature](qr-connection.md) - Uses this system to show new connection PRs as unread
- [Notifications System](notifications.md) - Similar read tracking for notifications
