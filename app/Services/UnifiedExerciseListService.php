<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unified service for generating exercise selection lists across all contexts
 * 
 * Replaces:
 * - ExerciseSelectionService::generateItemSelectionList (mobile-entry)
 * - ExerciseListService::generateMetricsExerciseList (lift-logs/index)
 * - WorkoutExerciseListService::generateExerciseSelectionList* (workout builder)
 * 
 * All differences are handled through configuration options.
 */
class UnifiedExerciseListService
{
    protected ExerciseAliasService $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Generate exercise list for any context
     * 
     * @param int $userId
     * @param array $config Configuration options:
     *   - 'context' => string: 'mobile-entry' | 'metrics' | 'workout-builder' (for documentation)
     *   - 'date' => Carbon|null: Selected date (for mobile-entry, affects recency calculation)
     *   - 'filter_exercises' => string: 'all' | 'logged-only' (default: 'all')
     *   - 'show_popular' => bool: Show popular exercises for new users (default: false)
     *   - 'url_generator' => callable: Function to generate URL for each exercise (required)
     *   - 'create_form' => array|null: Create form configuration
     *   - 'initial_state' => string: 'expanded' | 'collapsed' (default: 'expanded')
     *   - 'show_cancel_button' => bool (default: false)
     *   - 'restrict_height' => bool (default: false)
     *   - 'filter_placeholder' => string (default: 'Tap to search...')
     *   - 'no_results_message' => string (default: 'No exercises found.')
     *   - 'recent_days' => int: Days to consider for "recent" (default: 28)
     *   - 'aria_labels' => array: Custom aria labels
     * @return array Raw array format for item-list component
     */
    public function generate(int $userId, array $config = []): array
    {
        $config = $this->mergeDefaults($config);
        
        // 1. Get exercises (filtered or all)
        $exercises = $this->getExercises($userId, $config);
        
        // 2. Apply aliases
        $user = User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);
        
        // 3. Get recency data
        $recentIds = $this->getRecentExerciseIds($userId, $config);
        $lastPerformed = $this->getLastPerformedDates($userId, $exercises, $config);
        
        // 4. Handle new user popular exercises (if enabled)
        $popularMap = [];
        if ($config['show_popular'] && $this->isNewUser($userId)) {
            $popularMap = $this->getPopularExercises($exercises);
        }
        
        // 5. Build items with categorization
        $items = $this->buildItems($exercises, $recentIds, $lastPerformed, $popularMap, $config);
        
        // 6. Sort items
        $items = $this->sortItems($items);
        
        // 7. Format output
        return $this->formatOutput($items, $config);
    }

    /**
     * Merge user config with defaults
     */
    protected function mergeDefaults(array $config): array
    {
        return array_merge([
            'context' => 'generic',
            'date' => null,
            'filter_exercises' => 'all',
            'show_popular' => false,
            'url_generator' => null,
            'create_form' => null,
            'initial_state' => 'expanded',
            'show_cancel_button' => false,
            'restrict_height' => false,
            'filter_placeholder' => 'Tap to search...',
            'no_results_message' => 'No exercises found.',
            'recent_days' => 28,
            'aria_labels' => [
                'section' => 'Exercise selection list',
                'selectItem' => 'Select exercise',
            ],
        ], $config);
    }

    /**
     * Get exercises based on filter mode
     */
    protected function getExercises(int $userId, array $config): Collection
    {
        $query = Exercise::query();
        
        if ($config['filter_exercises'] === 'logged-only') {
            // Only exercises that have been logged by this user
            $query->whereHas('liftLogs', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        } else {
            // All accessible exercises (global + user's own)
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $userId);
            });
        }
        
        return $query->with([
            'aliases' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            },
            'user' // For ownership display
        ])
        ->orderBy('title', 'asc')
        ->get();
    }

    /**
     * Get IDs of recently logged exercises
     */
    protected function getRecentExerciseIds(int $userId, array $config): array
    {
        $referenceDate = $config['date'] ?? Carbon::now();
        
        return LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', $referenceDate->copy()->subDays($config['recent_days']))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();
    }

    /**
     * Get last performed dates for exercises
     */
    protected function getLastPerformedDates(int $userId, Collection $exercises, array $config): Collection
    {
        $query = LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'));
        
        // If a date is provided, only consider logs up to that date
        if ($config['date']) {
            $query->where('logged_at', '<=', $config['date']);
        }
        
        return $query->select('exercise_id', DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');
    }

    /**
     * Check if user is new (< 5 total lift logs)
     */
    protected function isNewUser(int $userId): bool
    {
        return LiftLog::where('user_id', $userId)->count() < 5;
    }

    /**
     * Get popular exercises for new users
     * Returns map of exercise_id => priority_rank
     */
    protected function getPopularExercises(Collection $exercises): array
    {
        $availableExerciseIds = $exercises->pluck('id')->toArray();
        
        if (empty($availableExerciseIds)) {
            return [];
        }
        
        // Find top 10 most logged exercises by non-admin users
        $topExercises = LiftLog::select('exercise_id', DB::raw('COUNT(*) as log_count'))
            ->whereIn('exercise_id', $availableExerciseIds)
            ->whereHas('user', function ($query) {
                $query->whereDoesntHave('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'Admin');
                });
            })
            ->groupBy('exercise_id')
            ->orderBy('log_count', 'desc')
            ->limit(10)
            ->pluck('exercise_id')
            ->toArray();
        
        // Create priority map (1 = highest priority)
        $priorityMap = [];
        foreach ($topExercises as $index => $exerciseId) {
            $priorityMap[$exerciseId] = $index + 1;
        }
        
        return $priorityMap;
    }

    /**
     * Build items array with categorization
     */
    protected function buildItems(
        Collection $exercises,
        array $recentIds,
        Collection $lastPerformed,
        array $popularMap,
        array $config
    ): array {
        $items = [];
        $isNewUser = !empty($popularMap);
        
        foreach ($exercises as $exercise) {
            // Calculate "X ago" label
            $timeLabel = '';
            if (isset($lastPerformed[$exercise->id])) {
                $lastPerformedDate = Carbon::parse($lastPerformed[$exercise->id]);
                $timeLabel = $lastPerformedDate->diffForHumans(['short' => true]);
            }
            
            // Categorize exercise
            $itemType = $this->categorizeExercise(
                $exercise,
                $recentIds,
                $popularMap,
                $timeLabel,
                $isNewUser
            );
            
            // Generate URL
            if (!$config['url_generator']) {
                throw new \InvalidArgumentException('url_generator is required in config');
            }
            
            $href = $config['url_generator']($exercise, $config);
            
            $items[] = [
                'id' => 'exercise-' . $exercise->id,
                'name' => $exercise->title,
                'type' => $itemType,
                'href' => $href,
            ];
        }
        
        return $items;
    }

    /**
     * Categorize a single exercise
     */
    protected function categorizeExercise(
        Exercise $exercise,
        array $recentIds,
        array $popularMap,
        string $timeLabel,
        bool $isNewUser
    ): array {
        $isRecent = in_array($exercise->id, $recentIds);
        
        // Priority 1: Recent exercises (for all users)
        if ($isRecent) {
            return [
                'label' => $timeLabel,
                'cssClass' => 'recent',
                'priority' => 1,
                'subPriority' => 0,
            ];
        }
        
        // Priority 2: Popular exercises (for new users only)
        if ($isNewUser && isset($popularMap[$exercise->id])) {
            return [
                'label' => 'Popular',
                'cssClass' => 'in-program',
                'priority' => 2,
                'subPriority' => $popularMap[$exercise->id],
            ];
        }
        
        // Priority 3: Previously logged exercises
        if (!empty($timeLabel)) {
            return [
                'label' => $timeLabel,
                'cssClass' => 'exercise-history',
                'priority' => 2,
                'subPriority' => 0,
            ];
        }
        
        // Priority 4: Never logged exercises
        return [
            'label' => '',
            'cssClass' => 'exercise-history',
            'priority' => 3,
            'subPriority' => 0,
        ];
    }

    /**
     * Sort items by priority, subPriority, then alphabetically
     */
    protected function sortItems(array $items): array
    {
        usort($items, function ($a, $b) {
            // First sort by priority (lower number = higher priority)
            $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            
            // If same priority, sort by subPriority
            $subPriorityA = $a['type']['subPriority'] ?? 0;
            $subPriorityB = $b['type']['subPriority'] ?? 0;
            $subPriorityComparison = $subPriorityA <=> $subPriorityB;
            if ($subPriorityComparison !== 0) {
                return $subPriorityComparison;
            }
            
            // If same priority and subPriority, sort alphabetically by name
            return strcmp($a['name'], $b['name']);
        });
        
        return $items;
    }

    /**
     * Format output as raw array
     */
    protected function formatOutput(array $items, array $config): array
    {
        return [
            'items' => $items,
            'filterPlaceholder' => $config['filter_placeholder'],
            'noResultsMessage' => $config['no_results_message'],
            'initialState' => $config['initial_state'],
            'showCancelButton' => $config['show_cancel_button'],
            'restrictHeight' => $config['restrict_height'],
            'createForm' => $config['create_form'],
            'ariaLabels' => $config['aria_labels'],
        ];
    }
}
