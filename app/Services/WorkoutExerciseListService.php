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
    protected $exerciseMatchingService;

    public function __construct(
        \App\Services\ExerciseAliasService $aliasService,
        \App\Services\ExerciseMatchingService $exerciseMatchingService
    ) {
        $this->aliasService = $aliasService;
        $this->exerciseMatchingService = $exerciseMatchingService;
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

        // Get recent exercises (last 4 weeks) - matching lift-logs/index behavior
        $recentExerciseIds = \App\Models\LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', Carbon::now()->subDays(28))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get last performed dates for sorting within non-recent group
        $lastPerformedDates = \App\Models\LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');

        // Get lift log counts for each exercise
        $exerciseLogCounts = \App\Models\LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('count(*) as log_count'))
            ->groupBy('exercise_id')
            ->pluck('log_count', 'exercise_id');

        // Separate into recent and other groups
        $recentExercises = collect();
        $otherExercises = collect();
        
        foreach ($exercises as $exercise) {
            if (in_array($exercise->id, $recentExerciseIds)) {
                $recentExercises->push($exercise);
            } else {
                $otherExercises->push($exercise);
            }
        }
        
        // Sort recent exercises alphabetically
        $recentExercises = $recentExercises->sortBy('title')->values();
        
        // Sort other exercises by recency (most recent first)
        $otherExercises = $otherExercises->sortByDesc(function ($exercise) use ($lastPerformedDates) {
            return $lastPerformedDates[$exercise->id] ?? '1970-01-01';
        })->values();
        
        // Merge back together: recent (alphabetical) then others (by recency)
        $finalExercises = $recentExercises->concat($otherExercises);

        $itemListBuilder = C::itemList()
            ->filterPlaceholder('Search exercises...')
            ->noResultsMessage('No exercises found.')
            ->initialState($options['initialState']);

        foreach ($finalExercises as $exercise) {
            $logCount = $exerciseLogCounts[$exercise->id] ?? 0;
            $typeLabel = $logCount . ' ' . ($logCount === 1 ? 'log' : 'logs');
            
            // Determine if this is a recent exercise (last 4 weeks)
            $isRecent = in_array($exercise->id, $recentExerciseIds);
            $cssClass = $isRecent ? 'recent' : 'exercise-history';
            $priority = $isRecent ? 1 : 2;
            
            $itemListBuilder->item(
                'exercise-' . $exercise->id,
                $exercise->title,
                $this->getAddExerciseRoute($workout, $exercise, $options['redirectContext']),
                $typeLabel,
                $cssClass,
                $priority
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

        // Get recent exercises (last 4 weeks) - matching lift-logs/index behavior
        $recentExerciseIds = \App\Models\LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', Carbon::now()->subDays(28))
            ->pluck('exercise_id')
            ->unique()
            ->toArray();

        // Get last performed dates for sorting within non-recent group
        $lastPerformedDates = \App\Models\LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('MAX(logged_at) as last_logged_at'))
            ->groupBy('exercise_id')
            ->pluck('last_logged_at', 'exercise_id');

        // Get lift log counts for each exercise
        $exerciseLogCounts = \App\Models\LiftLog::where('user_id', $userId)
            ->whereIn('exercise_id', $exercises->pluck('id'))
            ->select('exercise_id', \DB::raw('count(*) as log_count'))
            ->groupBy('exercise_id')
            ->pluck('log_count', 'exercise_id');

        // Separate into recent and other groups
        $recentExercises = collect();
        $otherExercises = collect();
        
        foreach ($exercises as $exercise) {
            if (in_array($exercise->id, $recentExerciseIds)) {
                $recentExercises->push($exercise);
            } else {
                $otherExercises->push($exercise);
            }
        }
        
        // Sort recent exercises alphabetically
        $recentExercises = $recentExercises->sortBy('title')->values();
        
        // Sort other exercises by recency (most recent first)
        $otherExercises = $otherExercises->sortByDesc(function ($exercise) use ($lastPerformedDates) {
            return $lastPerformedDates[$exercise->id] ?? '1970-01-01';
        })->values();
        
        // Merge back together: recent (alphabetical) then others (by recency)
        $finalExercises = $recentExercises->concat($otherExercises);

        $itemListBuilder = C::itemList()
            ->filterPlaceholder('Search exercises...')
            ->noResultsMessage('No exercises found.')
            ->initialState('expanded')
            ->showCancelButton(false);

        foreach ($finalExercises as $exercise) {
            $logCount = $exerciseLogCounts[$exercise->id] ?? 0;
            $typeLabel = $logCount . ' ' . ($logCount === 1 ? 'log' : 'logs');
            
            // Determine if this is a recent exercise (last 4 weeks)
            $isRecent = in_array($exercise->id, $recentExerciseIds);
            $cssClass = $isRecent ? 'recent' : 'exercise-history';
            $priority = $isRecent ? 1 : 2;
            
            $itemListBuilder->item(
                'exercise-' . $exercise->id,
                $exercise->title,
                route('simple-workouts.add-exercise-new', [
                    'exercise' => $exercise->id
                ]),
                $typeLabel,
                $cssClass,
                $priority
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
                            'text' => 'No loggable exercises found in workout syntax. Use [Exercise Name] to make exercises loggable.'
                        ]
                    ]
                ]
            ];
        }

        // Use fuzzy matching to find exercises (similar to WOD Display)
        $matchedExercises = [];
        foreach ($exerciseNames as $exerciseName) {
            $matchedExercise = $this->exerciseMatchingService->findBestMatch($exerciseName, Auth::id());
            $matchedExercises[$exerciseName] = $matchedExercise;
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
        $user = Auth::user();

        foreach ($exerciseNames as $index => $exerciseName) {
            $exercise = $matchedExercises[$exerciseName];
            
            if (!$exercise) {
                // Exercise not found - show with alias creation option
                $line1 = $exerciseName;
                $line2 = 'Exercise not found - create alias to link it';
                $line3 = null;
                
                $rowBuilder = $tableBuilder->row($index, $line1, $line2, $line3);
                
                if ($options['compactMode']) {
                    $rowBuilder->compact();
                }
                
                // Add alias creation button instead of play button
                $aliasUrl = route('exercise-aliases.create', [
                    'alias_name' => $exerciseName,
                    'workout_id' => $workout->id
                ]);
                $rowBuilder->linkAction('fa-link', $aliasUrl, 'Create alias', 'btn-secondary');
                
                $rowBuilder->add();
                continue;
            }

            // Apply alias if exists (preserve original WOD name if it's an alias)
            $displayName = $this->aliasService->getDisplayName($exercise, $user);
            
            // If the WOD name differs from the exercise title, show both
            if (strtolower($exerciseName) !== strtolower($exercise->title)) {
                $line1 = $exerciseName . ' → ' . $displayName;
            } else {
                $line1 = $displayName;
            }
            
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