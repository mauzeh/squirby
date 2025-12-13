<?php

namespace App\Services;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\ComponentBuilder as C;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class WorkoutExerciseListService
{
    protected $aliasService;

    public function __construct(\App\Services\ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Generate exercise list table component for a workout
     * 
     * @param Workout $workout
     * @param array $options Configuration options
     * @return array Table component data
     */
    public function generateExerciseListTable(Workout $workout, array $options = []): array
    {
        // Default options
        $options = array_merge([
            'showPlayButtons' => true,
            'showMoveButtons' => true,
            'showDeleteButtons' => true,
            'showLoggedStatus' => true,
            'compactMode' => true,
            'redirectContext' => 'simple-workout', // or 'advanced-workout'
        ], $options);

        // Handle advanced workouts (WOD syntax) vs simple workouts (workout_exercises table)
        if ($options['redirectContext'] === 'advanced-workout' && $workout->wod_syntax) {
            return $this->generateAdvancedWorkoutExerciseTable($workout, $options);
        }

        // Load exercises with relationships for simple workouts
        $workout->load('exercises.exercise.aliases');
        
        // Apply aliases to exercises
        $user = Auth::user();
        foreach ($workout->exercises as $workoutExercise) {
            if ($workoutExercise->exercise) {
                $displayName = $this->aliasService->getDisplayName($workoutExercise->exercise, $user);
                $workoutExercise->exercise->title = $displayName;
            }
        }

        if ($workout->exercises->isEmpty()) {
            return [
                'type' => 'messages',
                'data' => [
                    'messages' => [
                        [
                            'type' => 'info',
                            'text' => 'No exercises in this workout yet.'
                        ]
                    ]
                ]
            ];
        }

        // Get today's logged exercises for this user if showing logged status
        $loggedExerciseData = [];
        if ($options['showLoggedStatus']) {
            $today = Carbon::today();
            $todaysLiftLogs = \App\Models\LiftLog::where('user_id', Auth::id())
                ->whereDate('logged_at', $today)
                ->with(['liftSets'])
                ->get();
            
            // Create a map of exercise_id => lift log for quick lookup
            foreach ($todaysLiftLogs as $log) {
                $loggedExerciseData[$log->exercise_id] = $log;
            }
        }

        $tableBuilder = C::table();
        $exerciseCount = $workout->exercises->count();

        foreach ($workout->exercises as $index => $exercise) {
            $line1 = $exercise->exercise->title;
            
            // Check if this exercise was logged today
            $isLoggedToday = isset($loggedExerciseData[$exercise->exercise_id]);
            
            if ($options['showLoggedStatus'] && $isLoggedToday) {
                // Don't show line2 for logged exercises - we'll use a message instead
                $line2 = null;
            } else {
                // Show helpful message about logging
                $line2 = 'Tap play to begin logging';
            }
            
            $line3 = null;
            
            $isFirst = $index === 0;
            $isLast = $index === $exerciseCount - 1;
            
            $rowBuilder = $tableBuilder->row($exercise->id, $line1, $line2, $line3);
            
            if ($options['compactMode']) {
                $rowBuilder->compact();
            }
            
            // Add green message box for logged exercises
            if ($options['showLoggedStatus'] && $isLoggedToday) {
                $liftLog = $loggedExerciseData[$exercise->exercise_id];
                $strategy = $exercise->exercise->getTypeStrategy();
                $loggedData = $strategy->formatLoggedItemDisplay($liftLog);
                $rowBuilder->message('success', $loggedData, 'Completed:');
            }
            
            // Add play button to start logging (only if not logged today or not showing logged status)
            if ($options['showPlayButtons'] && (!$options['showLoggedStatus'] || !$isLoggedToday)) {
                $today = Carbon::today();
                $logUrl = route('lift-logs.create', [
                    'exercise_id' => $exercise->exercise_id,
                    'date' => $today->toDateString(),
                    'redirect_to' => $options['redirectContext'],
                    'workout_id' => $workout->id
                ]);
                $rowBuilder->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now');
            }
            
            // Add move buttons if enabled
            if ($options['showMoveButtons'] && $exerciseCount > 1) {
                // Show only one arrow button to save space:
                // - Last item shows up arrow (to move it up)
                // - All other items show down arrow (to move them down)
                if ($isLast && !$isFirst) {
                    // Last item: show up arrow (transparent)
                    $rowBuilder->linkAction(
                        'fa-arrow-up',
                        $this->getMoveExerciseRoute($workout, $exercise, 'up', $options['redirectContext']),
                        'Move up',
                        'btn-transparent'
                    );
                } elseif (!$isLast) {
                    // Not last item: show down arrow (transparent)
                    $rowBuilder->linkAction(
                        'fa-arrow-down',
                        $this->getMoveExerciseRoute($workout, $exercise, 'down', $options['redirectContext']),
                        'Move down',
                        'btn-transparent'
                    );
                }
                // Note: If there's only one item (isFirst && isLast), no arrow is shown
            }
            
            // Add delete button if enabled
            if ($options['showDeleteButtons']) {
                $rowBuilder->formAction(
                    'fa-trash',
                    $this->getRemoveExerciseRoute($workout, $exercise, $options['redirectContext']),
                    'DELETE',
                    [],
                    'Remove exercise',
                    'btn-transparent',
                    true
                );
            }
            
            $rowBuilder->add();
        }

        return $tableBuilder
            ->confirmMessage('deleteItem', 'Are you sure you want to remove this exercise from the workout?')
            ->build();
    }

    /**
     * Generate exercise selection list for adding exercises to a workout
     * 
     * @param Workout $workout
     * @param array $options Configuration options
     * @return array Item list component data
     */
    public function generateExerciseSelectionList(Workout $workout, array $options = []): array
    {
        // Default options
        $options = array_merge([
            'redirectContext' => 'simple-workout',
            'initialState' => 'collapsed', // or 'expanded'
        ], $options);

        $userId = Auth::id();
        
        // Get user's accessible exercises with aliases
        $exercises = \App\Models\Exercise::availableToUser($userId)
            ->with(['aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = Auth::user();
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get exercises already in this workout (to exclude from selection list)
        $workoutExerciseIds = $workout->exercises()->pluck('exercise_id')->toArray();

        // Get recent exercises (last 7 days) for the "Recent" category
        $recentExerciseIds = \App\Models\LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', Carbon::now()->subDays(7))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get last performed dates for all exercises
        $lastPerformedDates = \App\Models\LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('MAX(logged_at) as last_logged_at'))
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
                $lastPerformed = Carbon::parse($lastPerformedDates[$exercise->id]);
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
                'href' => $this->getAddExerciseRoute($workout, $exercise, $options['redirectContext'])
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

        $itemListBuilder = C::itemList()
            ->filterPlaceholder('Search exercises...')
            ->noResultsMessage('No exercises found.')
            ->initialState($options['initialState']);

        foreach ($items as $item) {
            $itemListBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }

        // Add create form
        $itemListBuilder->createForm(
            $this->getCreateExerciseRoute($workout, $options['redirectContext']),
            'exercise_name',
            [],
            'Create "{term}"',
            'POST'
        );

        return $itemListBuilder->build();
    }

    /**
     * Get the route for moving an exercise (simple workouts only)
     */
    protected function getMoveExerciseRoute(Workout $workout, WorkoutExercise $exercise, string $direction, string $context): string
    {
        return route('simple-workouts.move-exercise', [$workout->id, $exercise->id, 'direction' => $direction]);
    }

    /**
     * Get the route for removing an exercise (simple workouts only)
     */
    protected function getRemoveExerciseRoute(Workout $workout, WorkoutExercise $exercise, string $context): string
    {
        return route('simple-workouts.remove-exercise', [$workout->id, $exercise->id]);
    }

    /**
     * Get the route for adding an exercise (simple workouts only)
     */
    protected function getAddExerciseRoute(Workout $workout, \App\Models\Exercise $exercise, string $context): string
    {
        return route('simple-workouts.add-exercise', [
            $workout->id,
            'exercise' => $exercise->id
        ]);
    }

    /**
     * Get the route for creating a new exercise (simple workouts only)
     */
    protected function getCreateExerciseRoute(Workout $workout, string $context): string
    {
        return route('simple-workouts.create-exercise', $workout->id);
    }

    /**
     * Generate exercise selection list for new simple workout creation (no workout exists yet)
     * 
     * @param int $userId
     * @return array Item list component data
     */
    public function generateExerciseSelectionListForNew(int $userId): array
    {
        // Get user's accessible exercises with aliases
        $exercises = \App\Models\Exercise::availableToUser($userId)
            ->with(['aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $user = \App\Models\User::find($userId);
        $exercises = $this->aliasService->applyAliasesToExercises($exercises, $user);

        // Get recent exercises (last 7 days) for the "Recent" category
        $recentExerciseIds = \App\Models\LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', Carbon::now()->subDays(7))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get last performed dates for all exercises
        $lastPerformedDates = \App\Models\LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('MAX(logged_at) as last_logged_at'))
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
            // Calculate "X ago" label
            $lastPerformedLabel = '';
            if (isset($lastPerformedDates[$exercise->id])) {
                $lastPerformed = Carbon::parse($lastPerformedDates[$exercise->id]);
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
                'href' => route('simple-workouts.add-exercise-new', [
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

        $itemListBuilder = C::itemList()
            ->filterPlaceholder('Search exercises...')
            ->noResultsMessage('No exercises found.')
            ->initialState('expanded');

        foreach ($items as $item) {
            $itemListBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }

        // Add create form
        $itemListBuilder->createForm(
            route('simple-workouts.create-exercise-new'),
            'exercise_name',
            [],
            'Create "{term}"',
            'POST'
        );

        return $itemListBuilder->build();
    }

    /**
     * Generate exercise table for advanced workouts by parsing WOD syntax
     * 
     * @param Workout $workout
     * @param array $options
     * @return array
     */
    protected function generateAdvancedWorkoutExerciseTable(Workout $workout, array $options): array
    {
        // Extract loggable exercise names from WOD syntax
        $wodParser = app(\App\Services\WodParser::class);
        $exerciseNames = $wodParser->extractLoggableExercises($workout->wod_syntax);

        if (empty($exerciseNames)) {
            return [
                'type' => 'messages',
                'data' => [
                    'messages' => [
                        [
                            'type' => 'info',
                            'text' => 'No loggable exercises found in workout syntax. Use [[Exercise Name]] to make exercises loggable.'
                        ]
                    ]
                ]
            ];
        }

        // Find actual Exercise models for these names
        $exercises = \App\Models\Exercise::whereIn('title', $exerciseNames)
            ->availableToUser(Auth::id())
            ->with(['aliases' => function ($query) {
                $query->where('user_id', Auth::id());
            }])
            ->get()
            ->keyBy('title');

        // Get today's logged exercises for this user if showing logged status
        $loggedExerciseData = [];
        if ($options['showLoggedStatus']) {
            $today = Carbon::today();
            $todaysLiftLogs = \App\Models\LiftLog::where('user_id', Auth::id())
                ->whereDate('logged_at', $today)
                ->with(['liftSets'])
                ->get();
            
            // Create a map of exercise_id => lift log for quick lookup
            foreach ($todaysLiftLogs as $log) {
                $loggedExerciseData[$log->exercise_id] = $log;
            }
        }

        $tableBuilder = C::table();
        $user = Auth::user();

        foreach ($exerciseNames as $index => $exerciseName) {
            $exercise = $exercises->get($exerciseName);
            
            if (!$exercise) {
                // Exercise doesn't exist in database - show as unavailable
                $line1 = $exerciseName;
                $line2 = 'Exercise not found - create it first';
                $line3 = null;
                
                $rowBuilder = $tableBuilder->row($index, $line1, $line2, $line3);
                
                if ($options['compactMode']) {
                    $rowBuilder->compact();
                }
                
                $rowBuilder->add();
                continue;
            }

            // Apply alias if exists
            $displayName = $this->aliasService->getDisplayName($exercise, $user);
            
            $line1 = $displayName;
            
            // Check if this exercise was logged today
            $isLoggedToday = isset($loggedExerciseData[$exercise->id]);
            
            if ($options['showLoggedStatus'] && $isLoggedToday) {
                $line2 = null; // Will show completion message instead
            } else {
                $line2 = 'Tap play to begin logging';
            }
            
            $line3 = null;
            
            $rowBuilder = $tableBuilder->row((int)$exercise->id, $line1, $line2, $line3);
            
            if ($options['compactMode']) {
                $rowBuilder->compact();
            }
            
            // Add green message box for logged exercises
            if ($options['showLoggedStatus'] && $isLoggedToday) {
                $liftLog = $loggedExerciseData[$exercise->id];
                $strategy = $exercise->getTypeStrategy();
                $loggedData = $strategy->formatLoggedItemDisplay($liftLog);
                $rowBuilder->message('success', $loggedData, 'Completed:');
            }
            
            // Add play button to start logging (only if not logged today or not showing logged status)
            if ($options['showPlayButtons'] && (!$options['showLoggedStatus'] || !$isLoggedToday)) {
                $today = Carbon::today();
                $logUrl = route('lift-logs.create', [
                    'exercise_id' => $exercise->id,
                    'date' => $today->toDateString(),
                    'redirect_to' => $options['redirectContext'],
                    'workout_id' => $workout->id
                ]);
                $rowBuilder->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now');
            }
            
            $rowBuilder->add();
        }

        return $tableBuilder->build();
    }
}