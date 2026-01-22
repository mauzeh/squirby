# Personal Records (PRs) as First-Class Data

## Overview

This document outlines the architectural approach for making Personal Records (PRs) first-class citizens in the application's data layer, moving from expensive on-the-fly calculations to event-driven, queryable data.

## Current Problem

**Performance Issues:**
- O(n²) calculation on every page load
- For 18 historical logs: ~153 comparisons, taking 28ms
- For 50 historical logs: ~1,225 comparisons, taking 200-500ms+
- Scales poorly with user activity

**Functional Limitations:**
- No historical record of when PRs were achieved
- Can't query "show me all my PRs from last month"
- Can't show PR trends over time
- Can't analyze PR patterns
- Recalculation needed if algorithm changes

## Proposed Solution: Event-Driven PR Records

### Database Schema

Create a new `personal_records` table to store PR achievements:

```php
Schema::create('personal_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
    $table->foreignId('lift_log_id')->constrained()->cascadeOnDelete();
    $table->enum('pr_type', ['one_rm', 'volume', 'rep_specific', 'hypertrophy']);
    $table->integer('rep_count')->nullable(); // For rep-specific PRs (e.g., "5 reps")
    $table->decimal('weight', 8, 2)->nullable(); // For hypertrophy PRs (e.g., "best @ 200 lbs")
    $table->decimal('value', 10, 2); // The actual PR value (1RM weight, volume, etc.)
    $table->foreignId('previous_pr_id')->nullable()->constrained('personal_records');
    $table->decimal('previous_value', 10, 2)->nullable();
    $table->timestamp('achieved_at');
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes for fast queries
    $table->index(['user_id', 'exercise_id', 'pr_type']);
    $table->index(['user_id', 'achieved_at']);
    $table->index(['lift_log_id']);
});
```

Add lightweight flags to `lift_logs` table for fast filtering:

```php
Schema::table('lift_logs', function (Blueprint $table) {
    $table->boolean('is_pr')->default(false)->index();
    $table->integer('pr_count')->default(0); // How many PR types achieved
});
```

### Data Model

**PersonalRecord Model:**

```php
class PersonalRecord extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'user_id',
        'exercise_id',
        'lift_log_id',
        'pr_type',
        'rep_count',
        'weight',
        'value',
        'previous_pr_id',
        'previous_value',
        'achieved_at',
    ];
    
    protected $casts = [
        'value' => 'decimal:2',
        'previous_value' => 'decimal:2',
        'weight' => 'decimal:2',
        'achieved_at' => 'datetime',
    ];
    
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
    
    public function liftLog()
    {
        return $this->belongsTo(LiftLog::class);
    }
    
    public function previousPR()
    {
        return $this->belongsTo(PersonalRecord::class, 'previous_pr_id');
    }
    
    public function supersededBy()
    {
        return $this->hasOne(PersonalRecord::class, 'previous_pr_id');
    }
    
    // Scopes
    public function scopeCurrent($query)
    {
        return $query->whereDoesntHave('supersededBy');
    }
    
    public function scopeForExercise($query, $exerciseId)
    {
        return $query->where('exercise_id', $exerciseId);
    }
    
    public function scopeOfType($query, $type)
    {
        return $query->where('pr_type', $type);
    }
}
```

**Update LiftLog Model:**

```php
class LiftLog extends Model
{
    // ... existing code ...
    
    public function personalRecords()
    {
        return $this->hasMany(PersonalRecord::class);
    }
    
    public function isPR(): bool
    {
        return $this->is_pr;
    }
    
    public function getPRCount(): int
    {
        return $this->pr_count;
    }
}
```

## Event-Driven Architecture

### Write Path

When a lift log is created or updated:

```
User submits form
  ↓
Create/Update LiftLog + LiftSets (in transaction)
  ↓
Dispatch LiftLogged event
  ↓
DetectAndRecordPRs listener
  ↓
Detect PRs (compare against previous logs)
  ↓
Create PersonalRecord entries
  ↓
Update lift_logs.is_pr and pr_count flags
  ↓
Return response
```

### Event Implementation

**Event:**

```php
namespace App\Events;

use App\Models\LiftLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiftLogged
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public LiftLog $liftLog,
        public bool $isUpdate = false
    ) {}
}
```

**Listener:**

```php
namespace App\Listeners;

use App\Events\LiftLogged;
use App\Services\PRDetectionService;
use App\Models\PersonalRecord;
use Illuminate\Support\Facades\DB;

class DetectAndRecordPRs
{
    public function __construct(
        protected PRDetectionService $prDetectionService
    ) {}
    
    public function handle(LiftLogged $event): void
    {
        DB::transaction(function () use ($event) {
            $liftLog = $event->liftLog;
            
            // If this is an update, delete old PR records
            if ($event->isUpdate) {
                PersonalRecord::where('lift_log_id', $liftLog->id)->delete();
            }
            
            // Detect PRs for this lift log
            $prs = $this->prDetectionService->detectPRsWithDetails($liftLog);
            
            // Create PR records
            foreach ($prs as $pr) {
                PersonalRecord::create([
                    'user_id' => $liftLog->user_id,
                    'exercise_id' => $liftLog->exercise_id,
                    'lift_log_id' => $liftLog->id,
                    'pr_type' => $pr['type'],
                    'rep_count' => $pr['rep_count'] ?? null,
                    'weight' => $pr['weight'] ?? null,
                    'value' => $pr['value'],
                    'previous_pr_id' => $pr['previous_pr_id'] ?? null,
                    'previous_value' => $pr['previous_value'] ?? null,
                    'achieved_at' => $liftLog->logged_at,
                ]);
            }
            
            // Update lift log flags
            $liftLog->update([
                'is_pr' => count($prs) > 0,
                'pr_count' => count($prs),
            ]);
        });
    }
}
```

**Register in EventServiceProvider:**

```php
protected $listen = [
    LiftLogged::class => [
        DetectAndRecordPRs::class,
        // Future: SendPRNotification::class,
    ],
];
```

### Read Path

**Fast PR lookup (replaces O(n²) calculation):**

```php
// Get all PRs for a lift log (for display)
$prs = PersonalRecord::where('lift_log_id', $liftLog->id)
    ->with('previousPR')
    ->get();

// Transform to display format
$prRecords = $prs->map(function ($pr) {
    return [
        'label' => $this->formatPRLabel($pr),
        'value' => $this->formatPRValue($pr),
    ];
});
```

**Performance improvement:**
- Current: O(n²) calculation (28ms for 18 logs, 200-500ms for 50+ logs)
- New: O(1) lookup (1-2ms regardless of history size)
- **14-250x faster!**

## Data Consistency

### Edge Cases to Handle

#### 1. User Edits a Lift Log

**Scenario:** User changes 315 lbs → 300 lbs (was a PR, no longer is)

**Solution:**
```php
// In LiftLogController@update
DB::transaction(function () use ($liftLog, $validated) {
    $liftLog->update($validated);
    
    // Re-trigger PR detection
    event(new LiftLogged($liftLog, isUpdate: true));
    
    // May need to recalculate subsequent PRs
    $this->recalculateSubsequentPRs($liftLog);
});
```

#### 2. User Deletes a Lift Log

**Scenario:** User deletes a lift log that was a PR

**Solution:**
```php
// In LiftLogController@destroy
DB::transaction(function () use ($liftLog) {
    // Soft delete cascades to personal_records (via foreign key)
    $liftLog->delete();
    
    // Recalculate PRs for this exercise (in case deleted log was a PR)
    $this->recalculatePRsForExercise(
        $liftLog->user_id,
        $liftLog->exercise_id,
        $liftLog->logged_at
    );
});
```

#### 3. User Logs Out of Order (Backdating)

**Scenario:** 
- Logs 2025-01-15: 300 lbs
- Logs 2025-01-10: 315 lbs (backdated)
- The 315 lbs should be the PR, not 300 lbs

**Solution:**
```php
// In DetectAndRecordPRs listener
public function handle(LiftLogged $event): void
{
    $liftLog = $event->liftLog;
    
    // Check if there are any logs after this one for the same exercise
    $hasSubsequentLogs = LiftLog::where('user_id', $liftLog->user_id)
        ->where('exercise_id', $liftLog->exercise_id)
        ->where('logged_at', '>', $liftLog->logged_at)
        ->exists();
    
    if ($hasSubsequentLogs) {
        // Recalculate all PRs for this exercise from this date forward
        $this->recalculatePRsFromDate(
            $liftLog->user_id,
            $liftLog->exercise_id,
            $liftLog->logged_at
        );
    } else {
        // Normal case: just detect PRs for this log
        $this->detectAndRecordPRs($liftLog);
    }
}
```

### Recalculation Service

```php
namespace App\Services;

class PRRecalculationService
{
    public function recalculatePRsForExercise(
        int $userId,
        int $exerciseId,
        ?Carbon $fromDate = null
    ): void {
        DB::transaction(function () use ($userId, $exerciseId, $fromDate) {
            // Delete existing PR records for this exercise (from date forward)
            $query = PersonalRecord::where('user_id', $userId)
                ->where('exercise_id', $exerciseId);
            
            if ($fromDate) {
                $query->where('achieved_at', '>=', $fromDate);
            }
            
            $query->delete();
            
            // Get all lift logs for this exercise (from date forward)
            $logs = LiftLog::where('user_id', $userId)
                ->where('exercise_id', $exerciseId)
                ->when($fromDate, fn($q) => $q->where('logged_at', '>=', $fromDate))
                ->with(['exercise', 'liftSets'])
                ->orderBy('logged_at', 'asc')
                ->get();
            
            // Recalculate PRs for each log
            foreach ($logs as $log) {
                event(new LiftLogged($log, isUpdate: true));
            }
        });
    }
}
```

## Migration Strategy

### Historical Data Migration

Create a command to calculate PRs for all existing lift logs:

```php
namespace App\Console\Commands;

use App\Models\User;
use App\Models\LiftLog;
use App\Events\LiftLogged;
use Illuminate\Console\Command;

class CalculateHistoricalPRs extends Command
{
    protected $signature = 'prs:calculate-historical 
                          {--user= : Specific user ID to process}
                          {--exercise= : Specific exercise ID to process}
                          {--dry-run : Show what would be done without making changes}';
    
    protected $description = 'Calculate and store PR records for all historical lift logs';
    
    public function handle(): int
    {
        $this->info('Starting historical PR calculation...');
        
        $query = User::query();
        
        if ($userId = $this->option('user')) {
            $query->where('id', $userId);
        }
        
        $users = $query->get();
        $totalUsers = $users->count();
        
        $this->info("Processing {$totalUsers} users...");
        
        $progressBar = $this->output->createProgressBar($totalUsers);
        
        foreach ($users as $user) {
            $this->processUser($user);
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info('Historical PR calculation complete!');
        
        return Command::SUCCESS;
    }
    
    protected function processUser(User $user): void
    {
        $exerciseQuery = $user->exercises();
        
        if ($exerciseId = $this->option('exercise')) {
            $exerciseQuery->where('id', $exerciseId);
        }
        
        $exercises = $exerciseQuery->get();
        
        foreach ($exercises as $exercise) {
            $logs = LiftLog::where('user_id', $user->id)
                ->where('exercise_id', $exercise->id)
                ->with(['exercise', 'liftSets'])
                ->orderBy('logged_at', 'asc')
                ->get();
            
            if ($this->option('dry-run')) {
                $this->line("Would process {$logs->count()} logs for {$exercise->title}");
                continue;
            }
            
            foreach ($logs as $log) {
                event(new LiftLogged($log));
            }
        }
    }
}
```

**Run the migration:**

```bash
# Dry run first to see what would happen
php artisan prs:calculate-historical --dry-run

# Process all users
php artisan prs:calculate-historical

# Process specific user
php artisan prs:calculate-historical --user=26

# Process specific exercise for all users
php artisan prs:calculate-historical --exercise=1
```

**Considerations:**
- Run during off-peak hours
- Consider queuing for large datasets
- Monitor memory usage
- Add progress tracking
- Make idempotent (can run multiple times safely)

## Common Query Patterns

### Display PRs for a Lift Log

```php
// In LiftLogTableRowBuilder
$prs = PersonalRecord::where('lift_log_id', $liftLog->id)
    ->with('previousPR')
    ->get();

$prRecords = $prs->map(function ($pr) {
    $label = match($pr->pr_type) {
        'one_rm' => '1RM',
        'volume' => 'Volume',
        'rep_specific' => $pr->rep_count . ' Rep' . ($pr->rep_count > 1 ? 's' : ''),
        'hypertrophy' => 'Best @ ' . $pr->weight . ' lbs',
    };
    
    $value = $pr->previous_value 
        ? sprintf('%s → %s', $pr->previous_value, $pr->value)
        : sprintf('%s (First time!)', $pr->value);
    
    return [
        'label' => $label,
        'value' => $value,
    ];
});
```

### Get Current PRs for an Exercise

```php
// Get the current (unbeaten) PRs for an exercise
$currentPRs = PersonalRecord::where('user_id', $userId)
    ->where('exercise_id', $exerciseId)
    ->current() // Uses scope: whereDoesntHave('supersededBy')
    ->get();
```

### Get PR History

```php
// Get all 1RM PRs for an exercise over time
$prHistory = PersonalRecord::where('user_id', $userId)
    ->where('exercise_id', $exerciseId)
    ->where('pr_type', 'one_rm')
    ->orderBy('achieved_at', 'desc')
    ->with('liftLog')
    ->get();
```

### Get Recent PRs

```php
// Get all PRs in the last 30 days
$recentPRs = PersonalRecord::where('user_id', $userId)
    ->where('achieved_at', '>=', now()->subDays(30))
    ->with(['exercise', 'liftLog'])
    ->orderBy('achieved_at', 'desc')
    ->get();
```

### PR Statistics

```php
// Count PRs by exercise
$prCounts = PersonalRecord::where('user_id', $userId)
    ->groupBy('exercise_id')
    ->selectRaw('exercise_id, COUNT(*) as pr_count')
    ->with('exercise')
    ->get();

// Count PRs by type
$prsByType = PersonalRecord::where('user_id', $userId)
    ->groupBy('pr_type')
    ->selectRaw('pr_type, COUNT(*) as count')
    ->get();

// PRs per month
$prsPerMonth = PersonalRecord::where('user_id', $userId)
    ->selectRaw('DATE_FORMAT(achieved_at, "%Y-%m") as month, COUNT(*) as count')
    ->groupBy('month')
    ->orderBy('month', 'desc')
    ->get();
```

## Performance Implications

### Storage

**Per PR lift log:**
- Average: 2-3 PR records
- Storage per record: ~100 bytes
- 1000 lift logs → ~200 PRs → ~600 records → ~60KB

**Minimal storage impact**

### Write Performance

**Additional overhead per lift log creation:**
- PR detection: 5-10ms
- INSERT queries: 2-5ms
- Total: 7-15ms additional

**Acceptable overhead for dramatic read performance improvement**

### Read Performance

**Before (calculated on-the-fly):**
- 18 logs: 28ms
- 50 logs: 200-500ms
- 100 logs: 1000-2000ms

**After (database lookup):**
- Any number of logs: 1-2ms
- **14-1000x faster!**

## Feature Unlocks

Once PRs are first-class data, new features become possible:

### PR Dashboard
- "You've set 47 PRs this year!"
- PR frequency chart
- Longest PR drought
- PR streak tracking

### PR Notifications
- Email: "Congrats on your new PR!"
- Push notifications
- Weekly PR summary

### PR Analytics
- "You set most PRs on Mondays"
- "Average time between PRs: 12 days"
- PR prediction: "You're on track for a PR next week"
- Correlation with other metrics (sleep, nutrition, etc.)

### PR History Timeline
- Visual timeline of all PRs
- See progression over time
- Compare PR rates across exercises

### Social Features
- Share PR achievements
- Compare with friends
- Gym leaderboards
- PR challenges

### Training Insights
- Which exercises you PR most frequently
- Optimal training frequency for PRs
- PR patterns by time of day, day of week
- Deload effectiveness (PR rate before/after)

## Implementation Plan

### Phase 1: Foundation (Week 1)
- [ ] Create migration for `personal_records` table
- [ ] Add `is_pr` and `pr_count` columns to `lift_logs`
- [ ] Create `PersonalRecord` model with relationships
- [ ] Update `LiftLog` model with PR relationships
- [ ] Write unit tests for models

### Phase 2: Event System (Week 1-2)
- [ ] Create `LiftLogged` event
- [ ] Create `DetectAndRecordPRs` listener
- [ ] Update `PRDetectionService` with `detectPRsWithDetails()` method
- [ ] Register event listener
- [ ] Write integration tests

### Phase 3: Edge Cases (Week 2)
- [ ] Handle lift log updates
- [ ] Handle lift log deletions
- [ ] Handle backdated logs
- [ ] Create `PRRecalculationService`
- [ ] Write tests for edge cases

### Phase 4: Update Read Path (Week 3)
- [ ] Update `LiftLogTableRowBuilder` to use PR records
- [ ] Remove old O(n²) calculation code
- [ ] Update tests
- [ ] Performance testing
- [ ] Deploy to production

### Phase 5: Historical Migration (Week 3-4)
- [ ] Create `prs:calculate-historical` command
- [ ] Test on development data
- [ ] Run on staging environment
- [ ] Verify data integrity
- [ ] Run on production (off-peak hours)

## Testing Strategy

### Unit Tests
- PersonalRecord model relationships
- PR detection logic
- PR formatting methods

### Integration Tests
- Lift log creation triggers PR detection
- Lift log update recalculates PRs
- Lift log deletion handles PRs correctly
- Backdated logs recalculate correctly

### Performance Tests
- Benchmark PR lookup vs calculation
- Load test with 1000+ historical logs
- Memory usage monitoring

### Data Integrity Tests
- Verify all historical PRs are correct
- Check for orphaned PR records
- Validate previous_pr_id references

## Rollback Plan

If issues arise:

1. **Disable event listener** (quick rollback)
   ```php
   // In EventServiceProvider
   protected $listen = [
       // LiftLogged::class => [
       //     DetectAndRecordPRs::class,
       // ],
   ];
   ```

2. **Revert to old calculation** (fallback)
   - Keep old `calculatePRLogIds()` method
   - Add feature flag to switch between old/new

3. **Data cleanup** (if needed)
   ```bash
   php artisan prs:cleanup
   ```

## Conclusion

Making PRs first-class data transforms them from expensive calculations into queryable, analyzable records. This architectural change:

- **Improves performance** by 14-1000x
- **Enables new features** (dashboard, analytics, notifications)
- **Provides historical record** of all PR achievements
- **Scales better** with user activity
- **Unlocks insights** into training patterns

The event-driven approach ensures data consistency while maintaining flexibility for future enhancements.
