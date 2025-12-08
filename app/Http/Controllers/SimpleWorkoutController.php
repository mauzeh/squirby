<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DetectsSimpleWorkouts;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\ComponentBuilder as C;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimpleWorkoutController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    use DetectsSimpleWorkouts;

    /**
     * Show the form for creating a new simple workout (no DB persistence yet)
     */
    public function create()
    {
        $components = [];

        // Title
        $components[] = C::title('Create Workout')->build();

        // Info message
        $components[] = C::messages()
            ->info('Select exercises below to add them to your workout.')
            ->build();

        // Exercise selection list - always expanded on create page
        // Don't pass a default name - let the controller generate it based on the exercise
        $itemSelectionList = $this->generateExerciseSelectionListForNew(Auth::id());
        
        $itemListBuilder = C::itemList()
            ->filterPlaceholder($itemSelectionList['filterPlaceholder'])
            ->noResultsMessage($itemSelectionList['noResultsMessage'])
            ->initialState('expanded');

        foreach ($itemSelectionList['items'] as $item) {
            $itemListBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }

        if (isset($itemSelectionList['createForm'])) {
            $itemListBuilder->createForm(
                $itemSelectionList['createForm']['action'],
                $itemSelectionList['createForm']['inputName'],
                $itemSelectionList['createForm']['hiddenFields'],
                $itemSelectionList['createForm']['buttonTextTemplate'] ?? 'Create "{term}"',
                $itemSelectionList['createForm']['method'] ?? 'POST'
            );
        }

        $components[] = $itemListBuilder->build();

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created simple workout (deprecated - now handled by create())
     */
    public function store(Request $request)
    {
        // This method is kept for backwards compatibility but is no longer used
        // in the normal flow. Users are redirected directly from create() to edit()
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workout = Workout::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'wod_syntax' => null, // Explicitly null for simple workouts
            'is_public' => false,
        ]);

        return redirect()
            ->route('workouts.edit-simple', $workout->id)
            ->with('success', 'Workout created! Now add exercises.');
    }

    /**
     * Show the form for editing the specified simple workout
     */
    public function edit(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        // If workout has WOD syntax, check if user can access advanced features
        if (!$this->isSimpleWorkout($workout)) {
            if ($this->canAccessAdvancedWorkouts()) {
                // Admin users can access advanced editor
                return redirect()->route('workouts.edit', $workout)
                    ->with('info', 'This workout uses advanced syntax.');
            } else {
                // Regular users cannot edit advanced workouts
                return redirect()->route('workouts.index')
                    ->with('error', 'This workout uses advanced syntax and can only be edited by admins.');
            }
        }

        $workout->load('exercises.exercise.aliases');
        
        // Apply aliases to exercises
        $user = Auth::user();
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        foreach ($workout->exercises as $workoutExercise) {
            if ($workoutExercise->exercise) {
                $displayName = $aliasService->getDisplayName($workoutExercise->exercise, $user);
                $workoutExercise->exercise->title = $displayName;
            }
        }

        // Check if we should expand the list (from "Add exercises" button)
        $shouldExpandList = $request->query('expand') === 'true';

        $components = [];

        // Title with back button - use generated label instead of stored name
        $nameGenerator = app(\App\Services\WorkoutNameGenerator::class);
        $workoutLabel = $nameGenerator->generateFromWorkout($workout);
        
        $components[] = C::title($workoutLabel)
            ->subtitle('Edit workout')
            ->backButton('fa-arrow-left', route('workouts.index'), 'Back to workouts')
            ->build();

        // Messages from session
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }

        // Add Exercise button - hidden if list should be expanded
        $buttonBuilder = C::button('Add Exercise')
            ->ariaLabel('Add exercise to workout')
            ->addClass('btn-add-item');
        
        if ($shouldExpandList) {
            $buttonBuilder->initialState('hidden');
        }
        
        $components[] = $buttonBuilder->build();

        // Exercise selection list - expanded if coming from "Add exercises" button
        $itemSelectionList = $this->generateExerciseSelectionList(Auth::id(), $workout);
        
        $itemListBuilder = C::itemList()
            ->filterPlaceholder($itemSelectionList['filterPlaceholder'])
            ->noResultsMessage($itemSelectionList['noResultsMessage']);
        
        if ($shouldExpandList) {
            $itemListBuilder->initialState('expanded');
        }

        foreach ($itemSelectionList['items'] as $item) {
            $itemListBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                $item['type']['label'],
                $item['type']['cssClass'],
                $item['type']['priority']
            );
        }

        if (isset($itemSelectionList['createForm'])) {
            $itemListBuilder->createForm(
                $itemSelectionList['createForm']['action'],
                $itemSelectionList['createForm']['inputName'],
                $itemSelectionList['createForm']['hiddenFields'],
                $itemSelectionList['createForm']['buttonTextTemplate'] ?? 'Create "{term}"',
                $itemSelectionList['createForm']['method'] ?? 'POST'
            );
        }

        $components[] = $itemListBuilder->build();

        // Table of exercises
        if ($workout->exercises->isNotEmpty()) {
            $tableBuilder = C::table();
            
            $exerciseCount = $workout->exercises->count();
            
            // Get today's logged exercises for this user
            $today = \Carbon\Carbon::today();
            $todaysLiftLogs = \App\Models\LiftLog::where('user_id', Auth::id())
                ->whereDate('logged_at', $today)
                ->with(['liftSets'])
                ->get();
            
            // Create a map of exercise_id => lift log for quick lookup
            $loggedExerciseData = [];
            foreach ($todaysLiftLogs as $log) {
                $loggedExerciseData[$log->exercise_id] = $log;
            }

            foreach ($workout->exercises as $index => $exercise) {
                $line1 = $exercise->exercise->title;
                
                // Check if this exercise was logged today
                $isLoggedToday = isset($loggedExerciseData[$exercise->exercise_id]);
                
                if ($isLoggedToday) {
                    // Don't show line2 for logged exercises - we'll use a message instead
                    $line2 = null;
                } else {
                    // Show helpful message about logging
                    $line2 = 'Tap play to begin logging';
                }
                
                $line3 = null;
                
                $isFirst = $index === 0;
                $isLast = $index === $exerciseCount - 1;
                
                $rowBuilder = $tableBuilder->row($exercise->id, $line1, $line2, $line3)
                    ->compact(); // Enable compact mode for smaller buttons
                
                // Add green message box for logged exercises
                if ($isLoggedToday) {
                    $liftLog = $loggedExerciseData[$exercise->exercise_id];
                    $strategy = $exercise->exercise->getTypeStrategy();
                    $loggedData = $strategy->formatLoggedItemDisplay($liftLog);
                    $rowBuilder->message('success', $loggedData, 'Completed:');
                }
                
                // Add play button to start logging (only if not logged today)
                if (!$isLoggedToday) {
                    $logUrl = route('lift-logs.create', [
                        'exercise_id' => $exercise->exercise_id,
                        'date' => $today->toDateString(),
                        'redirect_to' => 'simple-workout',
                        'workout_id' => $workout->id
                    ]);
                    $rowBuilder->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now');
                }
                
                // Show only one arrow button to save space:
                // - Last item shows up arrow (to move it up)
                // - All other items show down arrow (to move them down)
                if ($isLast && !$isFirst) {
                    // Last item: show up arrow
                    $rowBuilder->linkAction(
                        'fa-arrow-up',
                        route('simple-workouts.move-exercise', [$workout->id, $exercise->id, 'direction' => 'up']),
                        'Move up'
                    );
                } elseif (!$isLast) {
                    // Not last item: show down arrow
                    $rowBuilder->linkAction(
                        'fa-arrow-down',
                        route('simple-workouts.move-exercise', [$workout->id, $exercise->id, 'direction' => 'down']),
                        'Move down'
                    );
                }
                // Note: If there's only one item (isFirst && isLast), no arrow is shown
                
                // Add delete button
                $rowBuilder->formAction(
                    'fa-trash',
                    route('simple-workouts.remove-exercise', [$workout->id, $exercise->id]),
                    'DELETE',
                    [],
                    'Remove exercise',
                    '',
                    true
                );
                
                $rowBuilder->add();
            }

            $components[] = $tableBuilder
                ->confirmMessage('deleteItem', 'Are you sure you want to remove this exercise from the workout?')
                ->build();
        }

        // Delete workout button
        $components[] = [
            'type' => 'delete-button',
            'data' => [
                'text' => 'Delete Workout',
                'action' => route('workouts.destroy', $workout->id),
                'method' => 'DELETE',
                'confirmMessage' => 'Are you sure you want to delete this workout? This action cannot be undone.'
            ]
        ];

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Update the specified simple workout
     */
    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        // Ensure this is a simple workout
        if (!$this->isSimpleWorkout($workout)) {
            return redirect()->route('workouts.edit', $workout)
                ->with('error', 'Cannot update advanced workout in simple mode.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workout->update([
            'name' => $validated['name'],
            // wod_syntax stays null
        ]);

        return redirect()
            ->route('workouts.edit-simple', $workout->id)
            ->with('success', 'Workout updated!');
    }

    /**
     * Add an exercise to a workout (creates workout if it doesn't exist)
     */
    public function addExercise(Request $request, Workout $workout = null)
    {
        $exerciseId = $request->input('exercise');
        $workoutName = $request->input('workout_name');
        
        if (!$exerciseId) {
            if ($workout) {
                return redirect()
                    ->route('workouts.edit-simple', $workout->id)
                    ->with('error', 'No exercise specified.');
            } else {
                return redirect()
                    ->route('workouts.create-simple')
                    ->with('error', 'No exercise specified.');
            }
        }

        $exercise = \App\Models\Exercise::where('id', $exerciseId)
            ->availableToUser(Auth::id())
            ->first();

        if (!$exercise) {
            if ($workout) {
                return redirect()
                    ->route('workouts.edit-simple', $workout->id)
                    ->with('error', 'Exercise not found.');
            } else {
                return redirect()
                    ->route('workouts.create-simple')
                    ->with('error', 'Exercise not found.');
            }
        }

        // Create workout if it doesn't exist (first exercise being added)
        if (!$workout) {
            // For simple workouts, use a generic name since we generate labels dynamically
            // The name field is kept for advanced workouts but not used for simple ones
            $name = $workoutName ?: 'Workout';
            
            $workout = Workout::create([
                'user_id' => Auth::id(),
                'name' => $name,
                'description' => null,
                'wod_syntax' => null,
                'is_public' => false,
            ]);
        } else {
            $this->authorize('update', $workout);
        }

        // Check if exercise already exists in workout
        $exists = WorkoutExercise::where('workout_id', $workout->id)
            ->where('exercise_id', $exercise->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('workouts.edit-simple', $workout->id)
                ->with('warning', 'Exercise already in workout.');
        }

        // Get next order (priority)
        $maxOrder = $workout->exercises()->max('order') ?? 0;

        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('workouts.edit-simple', $workout->id)
            ->with('success', 'Exercise added!');
    }

    /**
     * Create a new exercise and add it to the workout (creates workout if it doesn't exist)
     */
    public function createExercise(Request $request, Workout $workout = null)
    {
        $validated = $request->validate([
            'exercise_name' => 'required|string|max:255',
            'workout_name' => 'nullable|string|max:255',
        ]);

        // Find or create exercise
        $exercise = \App\Models\Exercise::firstOrCreate(
            ['title' => $validated['exercise_name']],
            ['user_id' => Auth::id()]
        );

        // Create workout if it doesn't exist (first exercise being added)
        if (!$workout) {
            // Use provided name, or generate intelligent name based on exercise
            $nameGenerator = app(\App\Services\WorkoutNameGenerator::class);
            $name = $validated['workout_name'] ?? $nameGenerator->generate($exercise);
            
            $workout = Workout::create([
                'user_id' => Auth::id(),
                'name' => $name,
                'description' => null,
                'wod_syntax' => null,
                'is_public' => false,
            ]);
        } else {
            $this->authorize('update', $workout);
        }

        // Check if exercise already exists in workout
        $exists = WorkoutExercise::where('workout_id', $workout->id)
            ->where('exercise_id', $exercise->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('workouts.edit-simple', $workout->id)
                ->with('warning', 'Exercise already in workout.');
        }

        // Get next order (priority)
        $maxOrder = $workout->exercises()->max('order') ?? 0;

        WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('workouts.edit-simple', $workout->id)
            ->with('success', 'Exercise created and added!');
    }

    /**
     * Move an exercise up or down in the workout
     */
    public function moveExercise(Request $request, Workout $workout, WorkoutExercise $exercise)
    {
        $this->authorize('update', $workout);

        if ($exercise->workout_id !== $workout->id) {
            abort(404);
        }

        $direction = $request->input('direction');
        
        if ($direction === 'up') {
            // Find the exercise above this one
            $swapWith = WorkoutExercise::where('workout_id', $workout->id)
                ->where('order', '<', $exercise->order)
                ->orderBy('order', 'desc')
                ->first();
        } else {
            // Find the exercise below this one
            $swapWith = WorkoutExercise::where('workout_id', $workout->id)
                ->where('order', '>', $exercise->order)
                ->orderBy('order', 'asc')
                ->first();
        }

        if ($swapWith) {
            // Swap the order values
            $tempOrder = $exercise->order;
            $exercise->order = $swapWith->order;
            $swapWith->order = $tempOrder;
            
            $exercise->save();
            $swapWith->save();
        }

        return redirect()
            ->route('workouts.edit-simple', $workout->id)
            ->with('success', 'Exercise order updated!');
    }

    /**
     * Remove an exercise from a workout
     */
    public function removeExercise(Workout $workout, WorkoutExercise $exercise)
    {
        $this->authorize('update', $workout);

        if ($exercise->workout_id !== $workout->id) {
            abort(404);
        }

        $exercise->delete();

        return redirect()
            ->route('workouts.edit-simple', $workout->id)
            ->with('success', 'Exercise removed!');
    }

    /**
     * Generate exercise selection list (similar to mobile entry)
     */
    private function generateExerciseSelectionList($userId, Workout $workout)
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
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $exercises = $aliasService->applyAliasesToExercises($exercises, $user);

        // Get exercises already in this workout (to exclude from selection list)
        $workoutExerciseIds = $workout->exercises()->pluck('exercise_id')->toArray();

        // Get recent exercises (last 7 days) for the "Recent" category
        $recentExerciseIds = \App\Models\LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', \Carbon\Carbon::now()->subDays(7))
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
                'href' => route('simple-workouts.add-exercise', [
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

        return [
            'noResultsMessage' => 'No exercises found.',
            'createForm' => [
                'action' => route('simple-workouts.create-exercise', $workout->id),
                'inputName' => 'exercise_name',
                'buttonTextTemplate' => 'Create "{term}"',
                'hiddenFields' => []
            ],
            'items' => $items,
            'filterPlaceholder' => 'Search exercises...'
        ];
    }

    /**
     * Generate exercise selection list for new workout (no workout ID yet)
     */
    private function generateExerciseSelectionListForNew($userId)
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
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $exercises = $aliasService->applyAliasesToExercises($exercises, $user);

        // Get recent exercises (last 7 days) for the "Recent" category
        $recentExerciseIds = \App\Models\LiftLog::where('user_id', $userId)
            ->where('logged_at', '>=', \Carbon\Carbon::now()->subDays(7))
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

        return [
            'noResultsMessage' => 'No exercises found.',
            'createForm' => [
                'action' => route('simple-workouts.create-exercise-new'),
                'inputName' => 'exercise_name',
                'buttonTextTemplate' => 'Create "{term}"',
                'hiddenFields' => []
            ],
            'items' => $items,
            'filterPlaceholder' => 'Search exercises...'
        ];
    }


}
