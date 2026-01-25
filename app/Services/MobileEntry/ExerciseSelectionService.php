<?php

namespace App\Services\MobileEntry;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseAliasService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExerciseSelectionService
{
    protected ExerciseAliasService $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Generate item selection list based on user's accessible exercises
     * 
     * Adaptive system that prioritizes exercises based on user experience:
     * 
     * For New Users (< 5 total lift logs):
     * 1. Recent (Last 4 Weeks)
     *    - Label: Recent
     *    - Style: 'recent' (green, lighter)
     *    - Priority: 1
     *    - Exercises performed in the last 4 weeks (excluding today)
     * 
     * 2. Popular Exercises (Essential beginner-friendly exercises)
     *    - Label: Popular
     *    - Style: 'in-program' (green, prominent)
     *    - Priority: 2
     *    - Curated list of most common beginner exercises
     * 
     * 3. All Others
     *    - No label or last performed date
     *    - Style: 'regular' (gray)
     *    - Priority: 3
     *    - All remaining exercises, ordered alphabetically
     * 
     * For Experienced Users (≥ 5 total lift logs):
     * 1. Recent (Last 4 Weeks)
     *    - Label: Recent
     *    - Style: 'recent' (green, lighter)
     *    - Priority: 1
     *    - Exercises performed in the last 4 weeks (excluding today)
     * 
     * 2. Previously Logged Exercises
     *    - Label: Shows last performed date (e.g., "2 days ago")
     *    - Style: 'in-program' (green, prominent)
     *    - Priority: 2
     *    - Other exercises with workout history for this user
     * 
     * 3. Never Logged Exercises
     *    - Label: Empty
     *    - Style: 'regular' (gray)
     *    - Priority: 3
     *    - Exercises never performed by this user, ordered alphabetically
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateItemSelectionList($userId, Carbon $selectedDate)
    {
        // Get user's accessible exercises with aliases
        $exercises = Exercise::availableToUser($userId)
            ->with(['aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get exercises already logged today (to exclude from recent list)
        $loggedTodayExerciseIds = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get recent exercises (last 4 weeks, excluding today) for the "Recent" category
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', $selectedDate->copy()->subDays(28))
            ->where('logged_at', '<', $selectedDate->startOfDay())
            ->whereNotIn('exercise_id', $loggedTodayExerciseIds)
            ->pluck('exercise_id')
            ->unique()
            ->toArray();
            
        // Get last performed dates for all exercises in a single query
        $lastPerformedDates = LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');

        // Check if user is new (has fewer than 5 total lift logs)
        $totalLiftLogs = LiftLog::where('user_id', $userId)->count();
        $isNewUser = $totalLiftLogs < 5;

        // Simplified prioritization based on user experience
        $prioritizedExerciseMap = [];
        
        if ($isNewUser) {
            // For new users: show common beginner-friendly exercises at the top
            $prioritizedExerciseMap = $this->getCommonExercisesForNewUsers($exercises);
        }

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Calculate "X ago" label for last performed date
            $lastPerformedLabel = '';
            if (isset($lastPerformedDates[$exercise->id])) {
                $lastPerformed = Carbon::parse($lastPerformedDates[$exercise->id]);
                $lastPerformedLabel = $lastPerformed->diffForHumans(['short' => true]);
            }
            
            // Enhanced category system - Recent always at top for all users
            if ($isNewUser && in_array($exercise->id, $recentExerciseIds)) {
                // Category 1: Recent exercises for new users (top priority)
                $itemType = [
                    'label' => 'Recent',
                    'cssClass' => 'recent',  // Green, lighter
                    'priority' => 1,
                    'subPriority' => 0
                ];
            } elseif ($isNewUser && isset($prioritizedExerciseMap[$exercise->id])) {
                // Category 2: Popular exercises for new users
                $rank = $prioritizedExerciseMap[$exercise->id];
                $itemType = [
                    'label' => 'Popular',
                    'cssClass' => 'in-program',  // Green, prominent
                    'priority' => 2,
                    'subPriority' => $rank  // Preserve ordering
                ];
            } elseif (!$isNewUser && in_array($exercise->id, $recentExerciseIds)) {
                // Category 1: Recent exercises for experienced users (top priority)
                $itemType = [
                    'label' => 'Recent',
                    'cssClass' => 'recent',  // Green, lighter
                    'priority' => 1,
                    'subPriority' => 0
                ];
            } elseif (!$isNewUser && isset($lastPerformedDates[$exercise->id])) {
                // Category 2: Other exercises with logs for experienced users
                $itemType = [
                    'label' => $lastPerformedLabel,
                    'cssClass' => 'in-program',  // Green, show they have history
                    'priority' => 2,
                    'subPriority' => 0
                ];
            } else {
                // Category 3: All others (never logged or no special priority)
                $priority = $isNewUser ? 3 : 3;  // Lower priority for both user types
                $itemType = [
                    'label' => $lastPerformedLabel,
                    'cssClass' => 'regular',  // Gray
                    'priority' => $priority,
                    'subPriority' => 0
                ];
            }
            
            // Always use default flow: go directly to lift log creation
            $routeParams = [
                'exercise_id' => $exercise->id,
                'redirect_to' => 'mobile-entry-lifts'
            ];
            
            // Only include date if we're NOT viewing today
            if (!$selectedDate->isToday()) {
                $routeParams['date'] = $selectedDate->toDateString();
            }
            
            $href = route('lift-logs.create', $routeParams);
            
            $items[] = [
                'id' => 'exercise-' . $exercise->id,
                'name' => $exercise->title,
                'type' => $itemType,
                'href' => $href
            ];
        }

        // Sort items: by priority first, then by subPriority (for popular exercises ranking), then alphabetical by name
        usort($items, function ($a, $b) {
            // First sort by priority (lower number = higher priority)
            $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            
            // If same priority, sort by subPriority (for recommendations to maintain engine order)
            $subPriorityA = $a['type']['subPriority'] ?? 0;
            $subPriorityB = $b['type']['subPriority'] ?? 0;
            $subPriorityComparison = $subPriorityA <=> $subPriorityB;
            if ($subPriorityComparison !== 0) {
                return $subPriorityComparison;
            }
            
            // If same priority and subPriority, sort alphabetically by name
            return strcmp($a['name'], $b['name']);
        });

        // Prepare hidden fields for create form
        $hiddenFields = [];
        // Only include date if we're NOT viewing today
        if (!$selectedDate->isToday()) {
            $hiddenFields['date'] = $selectedDate->toDateString();
        }

        return [
            'noResultsMessage' => config('mobile_entry_messages.empty_states.no_exercises_found'),
            'createForm' => [
                'action' => route('mobile-entry.create-exercise'),
                'method' => 'POST',
                'inputName' => 'exercise_name',
                'submitText' => '+',
                'buttonTextTemplate' => 'Create "{term}"',
                'ariaLabel' => 'Create new exercise',
                'hiddenFields' => $hiddenFields
            ],
            'items' => $items,
            'ariaLabels' => [
                'section' => 'Exercise selection list',
                'selectItem' => 'Add this exercise to today\'s workout'
            ],
            'filterPlaceholder' => config('mobile_entry_messages.placeholders.search_exercises')
        ];
    }

    /**
     * Get common exercises for new users based on popularity
     * 
     * @param \Illuminate\Database\Eloquent\Collection $exercises
     * @return array Map of exercise_id => priority_rank
     */
    private function getCommonExercisesForNewUsers($exercises): array
    {
        // Get available exercise IDs to filter the query
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
}
