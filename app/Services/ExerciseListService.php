<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use Illuminate\Support\Facades\DB;

/**
 * Service for generating exercise selection lists
 * Used by both LiftLogController and ExerciseAliasController
 */
class ExerciseListService
{
    protected $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Generate an item list component for exercise selection
     * 
     * @param int $userId
     * @param array $options Configuration options:
     *   - 'filter_placeholder' => string (default: 'Search exercises...')
     *   - 'no_results_message' => string (default: 'No exercises found.')
     *   - 'initial_state' => string (default: 'expanded')
     *   - 'show_cancel_button' => bool (default: false)
     *   - 'restrict_height' => bool (default: false)
     *   - 'recent_days' => int (default: 30) - Days to consider for "recent" exercises
     *   - 'url_generator' => callable - Function to generate URL for each exercise
     *   - 'type_label_generator' => callable - Function to generate type label for each exercise
     * @return array Item list component data
     */
    public function generateExerciseList(int $userId, array $options = []): array
    {
        // Set defaults
        $options = array_merge([
            'filter_placeholder' => 'Search exercises...',
            'no_results_message' => 'No exercises found.',
            'initial_state' => 'expanded',
            'show_cancel_button' => false,
            'restrict_height' => false,
            'recent_days' => 30,
            'url_generator' => null,
            'type_label_generator' => null,
        ], $options);

        // Get user's accessible exercises with aliases and user relationship
        $exercises = Exercise::availableToUser($userId)
            ->with([
                'aliases' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                },
                'user' // Load user relationship for ownership display
            ])
            ->orderBy('user_id') // Global exercises (null) first, then user exercises
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = \App\Models\User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get recent exercises (based on lift logs)
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', now()->subDays($options['recent_days']))
            ->distinct()
            ->pluck('exercise_id')
            ->toArray();

        // Build item list
        $items = [];
        
        foreach ($exercises as $exercise) {
            $isRecent = in_array($exercise->id, $recentExerciseIds);
            
            // Generate URL using provided callback or default
            if ($options['url_generator']) {
                $href = $options['url_generator']($exercise);
            } else {
                $href = '#';
            }
            
            // Generate type label using provided callback or default
            if ($options['type_label_generator']) {
                $typeLabel = $options['type_label_generator']($exercise, $isRecent);
            } else {
                $typeLabel = $isRecent ? 'Recent' : 'All';
            }
            
            $typeCssClass = $isRecent ? 'recent' : 'all';
            $priority = $isRecent ? 1 : 2;
            
            $items[] = [
                'id' => (string) $exercise->id,
                'name' => $exercise->title,
                'href' => $href,
                'type' => [
                    'label' => $typeLabel,
                    'cssClass' => $typeCssClass,
                    'priority' => $priority
                ]
            ];
        }

        return [
            'items' => $items,
            'filterPlaceholder' => $options['filter_placeholder'],
            'noResultsMessage' => $options['no_results_message'],
            'initialState' => $options['initial_state'],
            'showCancelButton' => $options['show_cancel_button'],
            'restrictHeight' => $options['restrict_height'],
        ];
    }

    /**
     * Generate a complete exercise selection list component for workout templates
     * 
     * @param int $userId
     * @param \App\Models\Workout $workout
     * @param bool $shouldExpand Whether the list should start expanded
     * @return array Complete item list component
     */
    public function generateWorkoutExerciseList(int $userId, $workout, bool $shouldExpand = false): array
    {
        // Get user's accessible exercises with aliases
        $exercises = Exercise::availableToUser($userId)
            ->with(['aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = \App\Models\User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get exercises already in this workout (to exclude from selection list)
        $workoutExerciseIds = $workout->exercises()->pluck('exercise_id')->toArray();

        // Get recent exercises (last 7 days) for the "Recent" category
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', \Carbon\Carbon::now()->subDays(7))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get last performed dates for all exercises
        $lastPerformedDates = LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');

        // Get top 10 recommended exercises
        $recommendationEngine = app(\App\Services\RecommendationEngine::class);
        $recommendations = $recommendationEngine->getRecommendations($userId, 10);
        
        $recommendationMap = [];
        foreach ($recommendations as $index => $recommendation) {
            $exerciseId = $recommendation['exercise']->id;
            $recommendationMap[$exerciseId] = $index + 1;
        }

        $items = [];
        
        foreach ($exercises as $exercise) {
            // Skip exercises already in workout
            if (in_array($exercise->id, $workoutExerciseIds)) {
                continue;
            }
            
            // Calculate "X ago" label
            $lastPerformedLabel = '';
            if (isset($lastPerformedDates[$exercise->id])) {
                $lastPerformed = \Carbon\Carbon::parse($lastPerformedDates[$exercise->id]);
                $lastPerformedLabel = $lastPerformed->diffForHumans(['short' => true]);
            }
            
            // Categorize exercises
            if (isset($recommendationMap[$exercise->id])) {
                $rank = $recommendationMap[$exercise->id];
                $itemType = [
                    'label' => '<i class="fas fa-star"></i> Recommended',
                    'cssClass' => 'in-program',
                    'priority' => 1,
                    'subPriority' => $rank
                ];
            } elseif (in_array($exercise->id, $recentExerciseIds)) {
                $itemType = [
                    'label' => 'Recent',
                    'cssClass' => 'recent',
                    'priority' => 2,
                    'subPriority' => 0
                ];
            } else {
                $itemType = [
                    'label' => $lastPerformedLabel,
                    'cssClass' => 'regular',
                    'priority' => 3,
                    'subPriority' => 0
                ];
            }

            $items[] = [
                'id' => 'exercise-' . $exercise->id,
                'name' => $exercise->title,
                'type' => $itemType,
                'href' => route('workouts.add-exercise', [
                    $workout->id,
                    'exercise' => $exercise->id
                ])
            ];
        }

        // Sort items
        usort($items, function ($a, $b) {
            $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            
            $subPriorityA = $a['type']['subPriority'] ?? 0;
            $subPriorityB = $b['type']['subPriority'] ?? 0;
            $subPriorityComparison = $subPriorityA <=> $subPriorityB;
            if ($subPriorityComparison !== 0) {
                return $subPriorityComparison;
            }
            
            return strcmp($a['name'], $b['name']);
        });

        // Build the component
        $listBuilder = ComponentBuilder::itemList()
            ->filterPlaceholder('Search exercises...')
            ->noResultsMessage('No exercises found.');
        
        if ($shouldExpand) {
            $listBuilder->initialState('expanded');
        }

        foreach ($items as $item) {
            $listBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }

        // Add create form
        $listBuilder->createForm(
            route('workouts.create-exercise', $workout->id),
            'exercise_name',
            [],
            'Create "{term}"',
            'POST'
        );

        return $listBuilder->build();
    }

    /**
     * Generate a complete exercise list component for metrics page
     * 
     * @param int $userId
     * @return array Complete item list component
     */
    public function generateMetricsExerciseList(int $userId): array
    {
        // Get all exercises that the user has logged
        $exercises = $this->getLoggedExercises($userId);

        // Get lift log counts for each exercise
        $exerciseLogCounts = $this->getExerciseLogCounts($userId, $exercises->pluck('id')->toArray());
        
        // Get recent exercises (last 4 weeks) - matching mobile-entry/lifts behavior
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', now()->subDays(28))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();
        
        // Get last performed dates for sorting within non-recent group
        $lastPerformedDates = LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');
        
        // Separate into recent and other groups
        $recentExercises = $exercises->filter(function ($exercise) use ($recentExerciseIds) {
            return in_array($exercise->id, $recentExerciseIds);
        })->sortBy('title')->values(); // Sort alphabetically within recent
        
        $otherExercises = $exercises->filter(function ($exercise) use ($recentExerciseIds) {
            return !in_array($exercise->id, $recentExerciseIds);
        })->sortByDesc(function ($exercise) use ($lastPerformedDates) {
            return $lastPerformedDates[$exercise->id] ?? '1970-01-01';
        })->values(); // Sort by recency for others
        
        // Merge back together: recent (alphabetical) then others (by recency)
        $finalExercises = $recentExercises->concat($otherExercises);
        
        $listBuilder = ComponentBuilder::itemList();
        
        foreach ($finalExercises as $exercise) {
            $displayName = $this->aliasService->getDisplayName($exercise, \App\Models\User::find($userId));
            $logCount = $exerciseLogCounts[$exercise->id] ?? 0;
            $typeLabel = $logCount . ' ' . ($logCount === 1 ? 'log' : 'logs');
            
            // Determine if this is a recent exercise (last 4 weeks)
            $isRecent = in_array($exercise->id, $recentExerciseIds);
            $cssClass = $isRecent ? 'recent' : 'exercise-history';
            $priority = $isRecent ? 1 : 2;
            
            $listBuilder->item(
                (string) $exercise->id,
                $displayName,
                route('exercises.show-logs', ['exercise' => $exercise, 'from' => 'lift-logs-index']),
                $typeLabel,
                $cssClass,
                $priority
            );
        }
        
        return $listBuilder
            ->filterPlaceholder('Tap to search...')
            ->noResultsMessage('No exercises found.')
            ->initialState('expanded')
            ->showCancelButton(false)
            ->restrictHeight(false)
            ->build();
    }

    /**
     * Generate a complete exercise list component for alias linking
     * 
     * @param int $userId
     * @param string $aliasName The alias name being linked
     * @param int|null $workoutId Optional workout ID for redirect
     * @return array Complete item list component
     */
    public function generateAliasLinkingExerciseList(int $userId, string $aliasName, ?int $workoutId = null): array
    {
        // Get user's accessible exercises with aliases
        $exercises = Exercise::availableToUser($userId)
            ->with(['aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = \App\Models\User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get recent exercises (last 30 days)
        $recentExerciseIds = LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('exercise_id')
            ->toArray();

        // Build the component
        $listBuilder = ComponentBuilder::itemList()
            ->filterPlaceholder('Search exercises...')
            ->noResultsMessage('No exercises found.')
            ->initialState('expanded')
            ->showCancelButton(false)
            ->restrictHeight(false);

        foreach ($exercises as $exercise) {
            $isRecent = in_array($exercise->id, $recentExerciseIds);
            $typeLabel = $isRecent ? 'Recent' : 'All';
            $typeCssClass = $isRecent ? 'recent' : 'all';
            $priority = $isRecent ? 1 : 2;
            
            $listBuilder->item(
                (string) $exercise->id,
                $exercise->title,
                route('exercise-aliases.store', [
                    'exercise_id' => $exercise->id,
                    'alias_name' => $aliasName,
                    'workout_id' => $workoutId
                ]),
                $typeLabel,
                $typeCssClass,
                $priority
            );
        }

        // Add create form for new exercises
        $listBuilder->createForm(
            route('exercise-aliases.create-and-link'),
            'exercise_name',
            [
                'alias_name' => $aliasName,
                'workout_id' => $workoutId
            ],
            'Create "{term}"',
            'POST'
        );

        return $listBuilder->build();
    }

    /**
     * Get exercises that the user has logged (for metrics page)
     * 
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLoggedExercises(int $userId)
    {
        return Exercise::whereHas('liftLogs', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with([
            'aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            },
            'user' // Load user relationship for badge display
        ])
        ->orderBy('title', 'asc')
        ->get();
    }

    /**
     * Get lift log counts for exercises
     * 
     * @param int $userId
     * @param array $exerciseIds
     * @return \Illuminate\Support\Collection
     */
    public function getExerciseLogCounts(int $userId, array $exerciseIds)
    {
        return LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exerciseIds)
            ->select('exercise_id', DB::raw('count(*) as log_count'))
            ->groupBy('exercise_id')
            ->pluck('log_count', 'exercise_id');
    }
}
