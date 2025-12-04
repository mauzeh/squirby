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
        
        // Get user's workouts (both templates and WODs)
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
            ->subtitle('WODs and workout templates')
            ->build();

        // Table of workouts with exercises as sub-items
        if ($workouts->isNotEmpty()) {
            // Create buttons (shown when there are workouts)
            $components[] = C::button('Create WOD')
                ->asLink(route('workouts.create', ['type' => 'wod']))
                ->build();
            $components[] = C::button('Create Template')
                ->asLink(route('workouts.create', ['type' => 'template']))
                ->build();
            $tableBuilder = C::table();

            foreach ($workouts as $workout) {
                $isWod = $workout->isWod();
                $line1 = $workout->name;
                $exerciseCount = $isWod ? 0 : $workout->exercises->count();
                
                // Build exercise list or WOD preview
                if ($isWod) {
                    $parsed = $workout->wod_parsed;
                    if ($parsed && isset($parsed['blocks'])) {
                        $blockCount = count($parsed['blocks']);
                        $line2 = $blockCount . ' ' . ($blockCount === 1 ? 'block' : 'blocks');
                    } else {
                        $line2 = 'WOD';
                    }
                    $line3 = $workout->description ?: 'Workout of the Day';
                } else {
                    if ($exerciseCount > 0) {
                        $exerciseTitles = $workout->exercises->pluck('exercise.title')->toArray();
                        $exerciseList = implode(', ', $exerciseTitles);
                        $line2 = $exerciseCount . ' ' . ($exerciseCount === 1 ? 'exercise' : 'exercises') . ': ' . $exerciseList;
                    } else {
                        $line2 = 'No exercises';
                    }
                    $line3 = $workout->description ?: null;
                }

                $rowBuilder = $tableBuilder->row(
                    $workout->id,
                    $line1,
                    $line2,
                    $line3
                )
                ->linkAction('fa-info', route('workouts.edit', $workout->id), 'View workout details', 'btn-info-circle')
                ->compact();

                // Check if this workout has any exercises logged today (for auto-expand)
                $hasLoggedExercisesToday = false;

                // For WODs, show blocks and exercises from parsed data
                if ($isWod && $workout->wod_parsed && isset($workout->wod_parsed['blocks'])) {
                    $subItemId = 1000; // Start with high number to avoid conflicts
                    foreach ($workout->wod_parsed['blocks'] as $blockIndex => $block) {
                        // Add block header as sub-item
                        $blockSubItem = $rowBuilder->subItem(
                            $subItemId++,
                            '📦 ' . $block['name'],
                            null,
                            null
                        );
                        $blockSubItem->compact()->add();
                        
                        // Add exercises in this block
                        foreach ($block['exercises'] as $exIndex => $exercise) {
                            $this->addWodExerciseSubItem($rowBuilder, $exercise, $subItemId, $today, $loggedExerciseData, $hasLoggedExercisesToday, $workout);
                            $subItemId++;
                        }
                    }
                }
                // For templates, add exercises as sub-items with log now button or edit button
                elseif ($workout->exercises->isNotEmpty()) {
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
                            )
                            ->compact();
                        } else {
                            // Not logged yet - show only exercise name
                            $subItemBuilder = $rowBuilder->subItem(
                                $exercise->id,
                                $exerciseLine1,
                                null,
                                null
                            );
                            
                            // Show log now button - link to lift-logs/create
                            $logUrl = route('lift-logs.create', [
                                'exercise_id' => $exercise->exercise_id,
                                'date' => $today->toDateString(),
                                'redirect_to' => 'workouts',
                                'workout_id' => $workout->id
                            ]);
                            $subItemBuilder->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now')
                                ->compact();
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
            // Empty state: show message first, then button
            $components[] = C::messages()
                ->info('No templates yet. Create your first template to get started!')
                ->build();
            
            $components[] = C::button('Create New Workout')
                ->asLink(route('workouts.create'))
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new template or WOD
     */
    public function create(Request $request)
    {
        $type = $request->query('type', 'template');
        $isWod = $type === 'wod';
        
        $components = [];

        // Title
        $components[] = C::title($isWod ? 'Create WOD' : 'Create Template')
            ->subtitle($isWod ? 'Workout of the Day' : 'Reusable workout template')
            ->build();

        if ($isWod) {
            // WOD creation form with syntax textarea
            $exampleSyntax = "# Block 1: Strength\nBack Squat: 5-5-5-5-5\nBench Press: 3x8\n\n# Block 2: Conditioning\nAMRAP 12min:\n  10 Box Jumps\n  15 Push-ups\n  20 Air Squats";
            
            // Syntax help message
            $components[] = C::messages()
                ->info('Use simple text syntax to create your workout. Start blocks with #, then list exercises with their rep schemes.')
                ->build();
            
            $components[] = C::form('create-wod', 'WOD Details')
                ->type('primary')
                ->formAction(route('workouts.store'))
                ->textField('name', 'WOD Name:', '', 'e.g., Monday Strength')
                ->textareaField('wod_syntax', 'WOD Syntax:', '', $exampleSyntax)
                ->textField('description', 'Description:', '', 'Optional')
                ->hiddenField('type', 'wod')
                ->submitButton('Create WOD')
                ->build();
            
            // Syntax guide
            $components[] = $this->buildSyntaxGuide();
        } else {
            // Template creation form
            $components[] = C::form('create-template', 'Template Details')
                ->type('primary')
                ->formAction(route('workouts.store'))
                ->textField('name', 'Template Name:', '', 'e.g., Push Day')
                ->textField('description', 'Description:', '', 'Optional')
                ->hiddenField('type', 'template')
                ->submitButton('Create Template')
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created template or WOD
     */
    public function store(Request $request)
    {
        $type = $request->input('type', 'template');
        $isWod = $type === 'wod';
        
        if ($isWod) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'wod_syntax' => 'required|string',
            ]);
            
            // Parse the WOD syntax
            $parser = app(\App\Services\WodParser::class);
            try {
                $parsed = $parser->parse($validated['wod_syntax']);
            } catch (\Exception $e) {
                return back()
                    ->withInput()
                    ->with('error', 'Failed to parse WOD syntax: ' . $e->getMessage());
            }
            
            $workout = Workout::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'wod_syntax' => $validated['wod_syntax'],
                'wod_parsed' => $parsed,
                'is_public' => false,
            ]);
            
            return redirect()
                ->route('workouts.edit', $workout->id)
                ->with('success', 'WOD created!');
        } else {
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
                ->with('success', 'Template created! Now add exercises.');
        }
    }

    /**
     * Show the form for editing the specified template or WOD
     */
    public function edit(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $isWod = $workout->isWod();
        
        if ($isWod) {
            return $this->editWod($request, $workout);
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

        // Title with back button
        $components[] = C::title($workout->name)
            ->subtitle('Edit template')
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
     * Update the specified template or WOD
     */
    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $type = $request->input('type', 'template');
        $isWod = $type === 'wod';
        
        if ($isWod) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'wod_syntax' => 'required|string',
            ]);
            
            // Parse the WOD syntax
            $parser = app(\App\Services\WodParser::class);
            try {
                $parsed = $parser->parse($validated['wod_syntax']);
            } catch (\Exception $e) {
                return back()
                    ->withInput()
                    ->with('error', 'Failed to parse WOD syntax: ' . $e->getMessage());
            }
            
            $workout->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'wod_syntax' => $validated['wod_syntax'],
                'wod_parsed' => $parsed,
            ]);
            
            return redirect()
                ->route('workouts.edit', $workout->id)
                ->with('success', 'WOD updated!');
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $workout->update($validated);

            return redirect()
                ->route('workouts.edit', $workout->id)
                ->with('success', 'Template updated!');
        }
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
                'buttonTextTemplate' => 'Create "{term}"',
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

    /**
     * Edit WOD (different UI than template)
     */
    private function editWod(Request $request, Workout $workout)
    {
        $components = [];

        // Title with back button
        $components[] = C::title($workout->name)
            ->subtitle('Edit WOD')
            ->backButton('fa-arrow-left', route('workouts.index'), 'Back to workouts')
            ->build();

        // Messages from session
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }

        // WOD Preview (parsed blocks)
        if ($workout->wod_parsed && isset($workout->wod_parsed['blocks'])) {
            $tableBuilder = C::table();
            $rowId = 1;
            
            foreach ($workout->wod_parsed['blocks'] as $block) {
                // Block header
                $blockRow = $tableBuilder->row(
                    $rowId++,
                    '📦 ' . $block['name'],
                    null,
                    null
                );
                $blockRow->compact()->add();
                
                // Exercises in block
                foreach ($block['exercises'] as $exercise) {
                    $this->addWodExercisePreview($tableBuilder, $exercise, $rowId);
                    $rowId++;
                }
            }
            
            $components[] = $tableBuilder->build();
        }

        // Syntax help message
        $components[] = C::messages()
            ->info('Use simple text syntax to structure your workout. Start blocks with #, then list exercises with their rep schemes.')
            ->build();
        
        // Edit form
        $components[] = C::form('edit-wod', 'WOD Details')
            ->type('info')
            ->formAction(route('workouts.update', $workout->id))
            ->textField('name', 'WOD Name:', $workout->name, 'e.g., Monday Strength')
            ->textareaField('wod_syntax', 'WOD Syntax:', $workout->wod_syntax ?? '', 'Use WOD syntax')
            ->textField('description', 'Description:', $workout->description ?? '', 'Optional')
            ->hiddenField('_method', 'PUT')
            ->hiddenField('type', 'wod')
            ->submitButton('Update WOD')
            ->build();
        
        // Syntax guide
        $components[] = $this->buildSyntaxGuide();

        // Delete workout button
        $components[] = [
            'type' => 'delete-button',
            'data' => [
                'text' => 'Delete WOD',
                'action' => route('workouts.destroy', $workout->id),
                'method' => 'DELETE',
                'confirmMessage' => 'Are you sure you want to delete this WOD? This action cannot be undone.'
            ]
        ];

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Add WOD exercise as sub-item in workout index
     */
    private function addWodExerciseSubItem($rowBuilder, $exercise, &$subItemId, $today, $loggedExerciseData, &$hasLoggedExercisesToday, $workout)
    {
        if ($exercise['type'] === 'special_format') {
            // Special format header
            $formatLabel = $this->formatSpecialFormatLabel($exercise);
            $subItem = $rowBuilder->subItem(
                $subItemId++,
                $formatLabel,
                null,
                null
            );
            $subItem->compact()->add();
            
            // Nested exercises
            if (isset($exercise['exercises'])) {
                foreach ($exercise['exercises'] as $nestedEx) {
                    $this->addWodExerciseSubItem($rowBuilder, $nestedEx, $subItemId, $today, $loggedExerciseData, $hasLoggedExercisesToday, $workout);
                }
            }
        } else {
            // Regular exercise
            $exerciseName = $exercise['name'];
            $scheme = isset($exercise['scheme']) ? $exercise['scheme']['display'] : (isset($exercise['reps']) ? $exercise['reps'] . ' reps' : '');
            
            $subItem = $rowBuilder->subItem(
                $subItemId,
                $exerciseName,
                $scheme,
                null
            );
            
            // Try to find matching exercise in database to allow logging
            $matchingExercise = \App\Models\Exercise::where('title', 'LIKE', '%' . $exerciseName . '%')
                ->availableToUser(Auth::id())
                ->first();
            
            if ($matchingExercise) {
                // Check if logged today
                if (isset($loggedExerciseData[$matchingExercise->id])) {
                    $hasLoggedExercisesToday = true;
                    $liftLog = $loggedExerciseData[$matchingExercise->id];
                    $strategy = $matchingExercise->getTypeStrategy();
                    $formattedMessage = $strategy->formatLoggedItemDisplay($liftLog);
                    
                    $subItem->message('success', $formattedMessage, 'Completed:');
                    $subItem->linkAction('fa-pencil', route('lift-logs.edit', ['lift_log' => $liftLog->id]), 'Edit lift log', 'btn-transparent');
                    $subItem->formAction('fa-trash', route('lift-logs.destroy', ['lift_log' => $liftLog->id]), 'DELETE', ['redirect_to' => 'workouts', 'workout_id' => $workout->id], 'Delete lift log', 'btn-danger', true);
                } else {
                    // Not logged yet
                    $logUrl = route('lift-logs.create', [
                        'exercise_id' => $matchingExercise->id,
                        'date' => $today->toDateString(),
                        'redirect_to' => 'workouts',
                        'workout_id' => $workout->id
                    ]);
                    $subItem->linkAction('fa-play', $logUrl, 'Log now', 'btn-log-now');
                }
            }
            
            $subItem->compact()->add();
        }
    }

    /**
     * Add WOD exercise preview in edit view
     */
    private function addWodExercisePreview($tableBuilder, $exercise, &$rowId)
    {
        if ($exercise['type'] === 'special_format') {
            $formatLabel = $this->formatSpecialFormatLabel($exercise);
            $row = $tableBuilder->row(
                $rowId++,
                '  ' . $formatLabel,
                null,
                null
            );
            $row->compact()->add();
            
            if (isset($exercise['exercises'])) {
                foreach ($exercise['exercises'] as $nestedEx) {
                    $this->addWodExercisePreview($tableBuilder, $nestedEx, $rowId);
                }
            }
        } else {
            $exerciseName = $exercise['name'];
            $scheme = isset($exercise['scheme']) ? $exercise['scheme']['display'] : (isset($exercise['reps']) ? $exercise['reps'] . ' reps' : '');
            
            $row = $tableBuilder->row(
                $rowId++,
                '  ' . $exerciseName,
                $scheme,
                null
            );
            $row->compact()->add();
        }
    }

    /**
     * Format special format label
     */
    private function formatSpecialFormatLabel($format): string
    {
        if ($format['format'] === 'AMRAP' && isset($format['duration'])) {
            return '⏱️ AMRAP ' . $format['duration'] . 'min';
        }
        
        if ($format['format'] === 'EMOM' && isset($format['duration'])) {
            return '⏱️ EMOM ' . $format['duration'] . 'min';
        }
        
        if ($format['format'] === 'For Time') {
            if (isset($format['rep_scheme'])) {
                return '⏱️ ' . $format['rep_scheme'] . ' For Time';
            }
            return '⏱️ For Time';
        }
        
        if ($format['format'] === 'Rounds' && isset($format['rounds'])) {
            return '🔄 ' . $format['rounds'] . ' Rounds';
        }
        
        return $format['description'] ?? 'Custom Format';
    }
    
    /**
     * Build syntax guide component
     */
    private function buildSyntaxGuide(): array
    {
        $tableBuilder = C::table();
        
        // Header row
        $headerRow = $tableBuilder->row(0, '📖 Syntax Quick Reference', 'Click to expand/collapse', null)
            ->collapsible()
            ->compact();
        
        // Blocks
        $headerRow->subItem(1, '📦 Blocks', '# Block Name', 'Start each section with #')
            ->compact()
            ->add();
        
        // Sets x Reps
        $headerRow->subItem(2, '💪 Sets x Reps', 'Exercise: 3x8', '3 sets of 8 reps')
            ->compact()
            ->add();
        
        // Rep Ladder
        $headerRow->subItem(3, '📈 Rep Ladder', 'Exercise: 5-5-5-3-3-1', 'Descending/ascending reps')
            ->compact()
            ->add();
        
        // Rep Range
        $headerRow->subItem(4, '📊 Rep Range', 'Exercise: 3x8-12', '3 sets of 8-12 reps')
            ->compact()
            ->add();
        
        // AMRAP
        $headerRow->subItem(5, '⏱️ AMRAP', 'AMRAP 12min:<br />  10 Exercise A<br />  15 Exercise B', 'As Many Rounds As Possible')
            ->compact()
            ->add();
        
        // EMOM
        $headerRow->subItem(6, '⏱️ EMOM', 'EMOM 16min:<br />  5 Exercise A', 'Every Minute On the Minute')
            ->compact()
            ->add();
        
        // For Time
        $headerRow->subItem(7, '⏱️ For Time', '21-15-9 For Time:<br />  Exercise A<br />  Exercise B', 'Complete as fast as possible')
            ->compact()
            ->add();
        
        // Rounds
        $headerRow->subItem(8, '🔄 Rounds', '5 Rounds:<br />  10 Exercise A<br />  20 Exercise B', 'Fixed number of rounds')
            ->compact()
            ->add();
        
        $headerRow->add();
        
        return $tableBuilder->build();
    }
}
