<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\ComponentBuilder as C;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkoutController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * Display a listing of the user's workouts
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        $expandWorkoutId = $request->query('workout_id'); // For manual expansion after delete
        
        $workouts = Workout::where('user_id', Auth::id())
            ->with(['exercises.exercise.aliases'])
            ->orderBy('name')
            ->get();

        // Get today's logged exercises with their lift log data for this user
        $todaysLiftLogs = \App\Models\LiftLog::where('user_id', Auth::id())
            ->whereDate('logged_at', $today)
            ->with(['liftSets'])
            ->get();
        
        // Create a map of exercise_id => lift log for quick lookup
        $loggedExerciseData = [];
        foreach ($todaysLiftLogs as $log) {
            $loggedExerciseData[$log->exercise_id] = $log;
        }
        
        $loggedExerciseIds = array_keys($loggedExerciseData);

        // Apply aliases to all exercises
        $user = Auth::user();
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        
        foreach ($workouts as $workout) {
            foreach ($workout->exercises as $workoutExercise) {
                if ($workoutExercise->exercise) {
                    $displayName = $aliasService->getDisplayName($workoutExercise->exercise, $user);
                    $workoutExercise->exercise->title = $displayName;
                }
            }
        }

        $components = [];

        // Title
        $components[] = C::title('Workouts')
            ->subtitle('Save and reuse your favorite workouts')
            ->build();

        // Create button
        $components[] = C::button('Create New Workout')
            ->asLink(route('workouts.create'))
            ->build();

        // Table of workouts with exercises as sub-items
        if ($workouts->isNotEmpty()) {
            $tableBuilder = C::table();

            foreach ($workouts as $workout) {
                $line1 = $workout->name;
                $exerciseCount = $workout->exercises->count();
                
                // Build exercise list with titles
                if ($exerciseCount > 0) {
                    $exerciseTitles = $workout->exercises->pluck('exercise.title')->toArray();
                    $exerciseList = implode(', ', $exerciseTitles);
                    $line2 = $exerciseCount . ' ' . ($exerciseCount === 1 ? 'exercise' : 'exercises') . ': ' . $exerciseList;
                } else {
                    $line2 = 'No exercises';
                }
                
                $line3 = $workout->description ?: null;

                $rowBuilder = $tableBuilder->row(
                    $workout->id,
                    $line1,
                    $line2,
                    $line3
                )
                ->titleClass('cell-title-large')
                ->linkAction('fa-pencil', route('workouts.edit', $workout->id), 'Edit workout', 'btn-transparent');

                // Check if this workout has any exercises logged today (for auto-expand)
                $hasLoggedExercisesToday = false;

                // Add exercises as sub-items with log now button or edit button
                if ($workout->exercises->isNotEmpty()) {
                    foreach ($workout->exercises as $index => $exercise) {
                        $exerciseLine1 = $exercise->exercise->title;
                        $exerciseLine2 = null;
                        $exerciseLine3 = null;
                        
                        // Check if exercise was logged today
                        if (in_array($exercise->exercise_id, $loggedExerciseIds)) {
                            $hasLoggedExercisesToday = true;
                            
                            // Get the lift log data
                            $liftLog = $loggedExerciseData[$exercise->exercise_id];
                            
                            // Show comments inline if they exist
                            if (!empty($liftLog->comments)) {
                                $exerciseLine2 = $liftLog->comments;
                            }
                            
                            // Format the lift data using exercise type strategy
                            $strategy = $exercise->exercise->getTypeStrategy();
                            $formattedMessage = $strategy->formatLoggedItemDisplay($liftLog);
                            
                            $subItemBuilder = $rowBuilder->subItem(
                                $exercise->id,
                                $exerciseLine1,
                                $exerciseLine2,
                                $exerciseLine3
                            );
                            
                            // Add green success message with lift details
                            $subItemBuilder->message('success', $formattedMessage, 'Completed:');
                            
                            // Show edit pencil icon (transparent style)
                            $subItemBuilder->linkAction(
                                'fa-pencil', 
                                route('lift-logs.edit', ['lift_log' => $liftLog->id]), 
                                'Edit lift log', 
                                'btn-transparent'
                            );
                            
                            // Add delete button for the lift log
                            $subItemBuilder->formAction(
                                'fa-trash',
                                route('lift-logs.destroy', ['lift_log' => $liftLog->id]),
                                'DELETE',
                                [
                                    'redirect_to' => 'workouts',
                                    'workout_id' => $workout->id
                                ],
                                'Delete lift log',
                                'btn-danger',
                                true
                            );
                        } else {
                            // Not logged yet - show suggestion inline
                            $trainingProgressionService = app(\App\Services\TrainingProgressionService::class);
                            $exerciseLine2 = $trainingProgressionService->formatSuggestionText(Auth::id(), $exercise->exercise_id);
                            
                            $subItemBuilder = $rowBuilder->subItem(
                                $exercise->id,
                                $exerciseLine1,
                                $exerciseLine2,
                                null
                            );
                            
                            // Show log now button
                            $logUrl = route('mobile-entry.add-lift-form', [
                                'exercise' => $exercise->exercise_id,
                                'date' => $today->toDateString(),
                                'redirect_to' => 'workouts'
                            ]);
                            $subItemBuilder->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now');
                        }
                        
                        $subItemBuilder->add();
                    }
                }

                // Auto-expand workouts that have any exercises logged today
                // OR if this workout was explicitly requested (e.g., after deletion)
                // OR if there's only one workout (always show it expanded)
                if ($hasLoggedExercisesToday || ($expandWorkoutId && $workout->id == $expandWorkoutId) || $workouts->count() === 1) {
                    $rowBuilder->initialState('expanded');
                }
                
                $rowBuilder->add();
            }

            $components[] = $tableBuilder
                ->confirmMessage('deleteItem', 'Are you sure you want to delete this workout, exercise, or lift log? This action cannot be undone.')
                ->build();
        } else {
            $components[] = C::messages()
                ->info('No templates yet. Create your first template to get started!')
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        $components = [];

        // Title
        $components[] = C::title('Create Workout')->build();

        // Form
        $components[] = C::form('create-template', 'Workout Details')
            ->type('primary')
            ->formAction(route('workouts.store'))
            ->textField('name', 'Workout Name:', '', 'e.g., Push Day')
            ->textField('description', 'Description:', '', 'Optional')
            ->submitButton('Create Workout')
            ->build();

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workout = Workout::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => false,
        ]);

        return redirect()
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Workout created! Now add exercises.');
    }

    /**
     * Show the form for editing the specified template
     */
    public function edit(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

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

        // Title with back button
        $components[] = C::title($workout->name)
            ->subtitle('Edit workout')
            ->backButton('fa-arrow-left', route('workouts.index'), 'Back to workouts')
            ->build();

        // Messages
        if (session('success')) {
            $components[] = C::messages()
                ->success(session('success'))
                ->build();
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
                $itemSelectionList['createForm']['hiddenFields']
            );
        }

        $components[] = $itemListBuilder->build();

        // Table of exercises
        if ($workout->exercises->isNotEmpty()) {
            $tableBuilder = C::table();
            
            $exerciseCount = $workout->exercises->count();

            foreach ($workout->exercises as $index => $exercise) {
                $line1 = $exercise->exercise->title;
                $line2 = 'Priority: ' . $exercise->order;
                $line3 = null;
                
                $isFirst = $index === 0;
                $isLast = $index === $exerciseCount - 1;
                
                $rowBuilder = $tableBuilder->row($exercise->id, $line1, $line2, $line3);
                
                // Add move up button (disabled if first)
                if (!$isFirst) {
                    $rowBuilder->linkAction(
                        'fa-arrow-up',
                        route('workouts.move-exercise', [$workout->id, $exercise->id, 'direction' => 'up']),
                        'Move up'
                    );
                } else {
                    $rowBuilder->linkAction(
                        'fa-arrow-up',
                        '#',
                        'Move up',
                        'btn-disabled'
                    );
                }
                
                // Add move down button (disabled if last)
                if (!$isLast) {
                    $rowBuilder->linkAction(
                        'fa-arrow-down',
                        route('workouts.move-exercise', [$workout->id, $exercise->id, 'direction' => 'down']),
                        'Move down'
                    );
                } else {
                    $rowBuilder->linkAction(
                        'fa-arrow-down',
                        '#',
                        'Move down',
                        'btn-disabled'
                    );
                }
                
                // Add delete button
                $rowBuilder->formAction(
                    'fa-trash',
                    route('workouts.remove-exercise', [$workout->id, $exercise->id]),
                    'DELETE',
                    [],
                    'Remove exercise',
                    'btn-danger',
                    true
                );
                
                $rowBuilder->add();
            }

            $components[] = $tableBuilder
                ->confirmMessage('deleteItem', 'Are you sure you want to remove this exercise from the workout?')
                ->build();
        } else {
            $components[] = C::messages()
                ->info('No exercises yet. Add your first exercise above.')
                ->build();
        }

        // Template details form at bottom
        $components[] = C::form('edit-template-details', 'Workout Details')
            ->type('info')
            ->formAction(route('workouts.update', $workout->id))
            ->textField('name', 'Workout Name:', $workout->name, 'e.g., Push Day')
            ->textField('description', 'Description:', $workout->description ?? '', 'Optional')
            ->textareaField('notes', 'Notes:', $workout->notes ?? '', 'Optional workout notes')
            ->hiddenField('_method', 'PUT')
            ->submitButton('Update Workout')
            ->build();

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
     * Update the specified template
     */
    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $workout->update($validated);

        return redirect()
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Workout updated!');
    }

    /**
     * Remove the specified template
     */
    public function destroy(Workout $workout)
    {
        $this->authorize('delete', $workout);

        $workout->delete();

        return redirect()
            ->route('workouts.index')
            ->with('success', 'Workout deleted!');
    }

    /**
     * Add an exercise to a template
     */
    public function addExercise(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $exerciseId = $request->input('exercise');
        
        if (!$exerciseId) {
            return redirect()
                ->route('workouts.edit', $workout->id)
                ->with('error', 'No exercise specified.');
        }

        $exercise = \App\Models\Exercise::where('id', $exerciseId)
            ->availableToUser(Auth::id())
            ->first();

        if (!$exercise) {
            return redirect()
                ->route('workouts.edit', $workout->id)
                ->with('error', 'Exercise not found.');
        }

        // Check if exercise already exists in template
        $exists = WorkoutExercise::where('workout_id', $workout->id)
            ->where('exercise_id', $exercise->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('workouts.edit', $workout->id)
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
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Exercise added!');
    }

    /**
     * Create a new exercise and add it to the template
     */
    public function createExercise(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'exercise_name' => 'required|string|max:255',
        ]);

        // Find or create exercise
        $exercise = \App\Models\Exercise::firstOrCreate(
            ['title' => $validated['exercise_name']],
            ['user_id' => Auth::id()]
        );

        // Check if exercise already exists in template
        $exists = WorkoutExercise::where('workout_id', $workout->id)
            ->where('exercise_id', $exercise->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->route('workouts.edit', $workout->id)
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
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Exercise created and added!');
    }

    /**
     * Move an exercise up or down in the template
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
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Exercise order updated!');
    }

    /**
     * Remove an exercise from a template
     */
    public function removeExercise(Workout $workout, WorkoutExercise $exercise)
    {
        $this->authorize('update', $workout);

        if ($exercise->workout_id !== $workout->id) {
            abort(404);
        }

        $exercise->delete();

        return redirect()
            ->route('workouts.edit', $workout->id)
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

        // Get exercises already in this template (to exclude from selection list)
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

        return [
            'noResultsMessage' => 'No exercises found.',
            'createForm' => [
                'action' => route('workouts.create-exercise', $workout->id),
                'inputName' => 'exercise_name',
                'hiddenFields' => []
            ],
            'items' => $items,
            'filterPlaceholder' => 'Search exercises...'
        ];
    }

    /**
     * Apply a template to a specific date
     */
    public function apply(Request $request, Workout $workout)
    {
        $this->authorize('view', $workout);

        $date = $request->input('date') 
            ? Carbon::parse($request->input('date'))
            : Carbon::today();
        
        $workout->applyToDate($date, Auth::user());

        return redirect()
            ->route('mobile-entry.lifts', ['date' => $date->toDateString()])
            ->with('success', 'Template "' . $workout->name . '" applied!');
    }

    /**
     * Show workouts for applying to a specific date
     */
    public function browse(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        
        $workouts = Workout::where('user_id', Auth::id())
            ->withCount('exercises')
            ->orderBy('name')
            ->get();

        $components = [];

        // Title
        $components[] = C::title('Apply Workout')
            ->subtitle('Choose a workout for ' . Carbon::parse($date)->format('M j, Y'))
            ->build();

        // Table of workouts with apply links
        if ($workouts->isNotEmpty()) {
            $tableBuilder = C::table();

            foreach ($workouts as $workout) {
                $line1 = $workout->name;
                $line2 = $workout->exercises_count . ' exercises';
                $line3 = $workout->description ? substr($workout->description, 0, 50) : null;

                // Use custom "Apply" button
                $applyUrl = route('workouts.apply', $workout->id) . '?date=' . $date;
                
                $tableBuilder->row(
                    $workout->id,
                    $line1,
                    $line2,
                    $line3
                )
                ->linkAction('fa-check', $applyUrl, 'Apply workout', 'btn-log-now')
                ->add();
            }

            $components[] = $tableBuilder->build();
        } else {
            $components[] = C::messages()
                ->info('No workouts yet. Create your first workout!')
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }
}
