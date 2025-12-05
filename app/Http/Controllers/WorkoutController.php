<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\ComponentBuilder as C;
use App\Services\WodLoggingService;
use App\Services\WodDisplayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkoutController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    protected $wodLoggingService;
    protected $wodDisplayService;
    protected $exerciseListService;

    public function __construct(
        WodLoggingService $wodLoggingService, 
        WodDisplayService $wodDisplayService,
        \App\Services\ExerciseListService $exerciseListService
    ) {
        $this->wodLoggingService = $wodLoggingService;
        $this->wodDisplayService = $wodDisplayService;
        $this->exerciseListService = $exerciseListService;
    }

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

                // For WODs, show only exercises from parsed data (skip block headers)
                if ($isWod) {
                    $this->wodLoggingService->addWodExercisesToRow($rowBuilder, $workout, $today, $loggedExerciseData, $hasLoggedExercisesToday);
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
            // WOD creation form with code editor
            $exampleSyntax = "# Block 1: Strength\n[[Back Squat]]: 5-5-5-5-5\n[[Bench Press]]: 3x8\n\n# Block 2: Conditioning\nAMRAP 12min:\n10 [[Box Jumps]]\n15 [[Push-ups]]\n20 [[Air Squats]]";
            
            // Syntax help message
            $components[] = C::messages()
                ->info('Use simple text syntax to create your workout. Start blocks with #, then list exercises with their rep schemes.')
                ->build();
            
            // Single form with embedded code editor
            $components[] = [
                'type' => 'wod-form',
                'data' => [
                    'id' => 'create-wod',
                    'title' => 'WOD Details',
                    'formAction' => route('workouts.store'),
                    'formType' => 'primary',
                    'nameField' => [
                        'label' => 'WOD Name:',
                        'value' => '',
                        'placeholder' => 'e.g., Monday Strength'
                    ],
                    'codeEditor' => [
                        'id' => 'wod-syntax-editor',
                        'label' => 'WOD Syntax',
                        'name' => 'wod_syntax',
                        'value' => '',
                        'placeholder' => $exampleSyntax,
                        'mode' => 'wod-syntax',
                        'height' => '400px',
                        'lineNumbers' => true
                    ],
                    'descriptionField' => [
                        'label' => 'Description:',
                        'value' => '',
                        'placeholder' => 'Optional'
                    ],
                    'submitButton' => 'Create WOD'
                ],
                'requiresScript' => 'mobile-entry/components/code-editor'
            ];
            
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

        // Exercise selection list - generated by service
        $components[] = $this->exerciseListService->generateWorkoutExerciseList(
            Auth::id(), 
            $workout, 
            $shouldExpandList
        );

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

        // WOD Display (formatted preview)
        if ($workout->wod_syntax) {
            $processedMarkdown = $this->wodDisplayService->processForDisplay($workout);
            $components[] = C::markdown($processedMarkdown)->classes('wod-display')->build();
        }

        // WOD Exercise Table (loggable exercises)
        if ($workout->wod_parsed && isset($workout->wod_parsed['blocks'])) {
            $tableBuilder = C::table();
            
            // Create a dummy row for the workout
            $rowBuilder = $tableBuilder->row(
                $workout->id,
                $workout->name,
                null,
                null
            );
            
            // Add exercises as sub-items (same logic as index)
            $today = Carbon::today();
            $todaysLiftLogs = \App\Models\LiftLog::where('user_id', Auth::id())
                ->whereDate('logged_at', $today)
                ->with(['liftSets'])
                ->get();
            
            $loggedExerciseData = [];
            foreach ($todaysLiftLogs as $log) {
                $loggedExerciseData[$log->exercise_id] = $log;
            }
            
            $hasLoggedExercisesToday = false;
            $this->wodLoggingService->addWodExercisesToRow($rowBuilder, $workout, $today, $loggedExerciseData, $hasLoggedExercisesToday);
            
            $rowBuilder->initialState('expanded')->add();
            
            $components[] = $tableBuilder->build();
        }

        // Syntax help message
        $components[] = C::messages()
            ->info('Use simple text syntax to structure your workout. Start blocks with #, then list exercises with their rep schemes.')
            ->build();
        
        // Edit form with code editor
        $exampleSyntax = "# Block 1: Strength\n[[Back Squat]]: 5-5-5-5-5\n[[Bench Press]]: 3x8\n\n# Block 2: Conditioning\nAMRAP 12min:\n10 [[Box Jumps]]\n15 [[Push-ups]]\n20 [[Air Squats]]";
        
        $components[] = [
            'type' => 'wod-form',
            'data' => [
                'id' => 'edit-wod',
                'title' => 'WOD Details',
                'formAction' => route('workouts.update', $workout->id),
                'formType' => 'info',
                'nameField' => [
                    'label' => 'WOD Name:',
                    'value' => $workout->name,
                    'placeholder' => 'e.g., Monday Strength'
                ],
                'codeEditor' => [
                    'id' => 'wod-syntax-editor',
                    'label' => 'WOD Syntax',
                    'name' => 'wod_syntax',
                    'value' => $workout->wod_syntax ?? '',
                    'placeholder' => $exampleSyntax,
                    'mode' => 'wod-syntax',
                    'height' => '400px',
                    'lineNumbers' => true
                ],
                'descriptionField' => [
                    'label' => 'Description:',
                    'value' => $workout->description ?? '',
                    'placeholder' => 'Optional'
                ],
                'submitButton' => 'Update WOD',
                'hiddenFields' => [
                    '_method' => 'PUT',
                    'type' => 'wod'
                ]
            ],
            'requiresScript' => 'mobile-entry/components/code-editor'
        ];
        
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
     * Format special format label
     */
    private function formatSpecialFormatLabel($format): string
    {
        return $format['description'] ?? 'Format';
    }
    
    /**
     * Build syntax guide component
     */
    private function buildSyntaxGuide(): array
    {
        $tableBuilder = C::table();
        
        // Header row
        $headerRow = $tableBuilder->row(0, 'Syntax Quick Reference', 'Click to expand/collapse', null)
            ->collapsible()
            ->compact();
        
        // Blocks
        $headerRow->subItem(1, 'Blocks', '# Block Name', 'Start each section with #')
            ->compact()
            ->add();
        
        // Exercise Names
        $headerRow->subItem(2, 'Exercise Names', '[Exercise Name]', 'Enclose exercise names in brackets')
            ->compact()
            ->add();
        
        // Sets x Reps
        $headerRow->subItem(3, 'Sets x Reps', '[Exercise]: 3x8', '3 sets of 8 reps')
            ->compact()
            ->add();
        
        // Rep Ladder
        $headerRow->subItem(4, 'Rep Ladder', '[Exercise]: 5-5-5-3-3-1', 'Descending/ascending reps')
            ->compact()
            ->add();
        
        // Rep Range
        $headerRow->subItem(5, 'Rep Range', '[Exercise]: 3x8-12', '3 sets of 8-12 reps')
            ->compact()
            ->add();
        
        // AMRAP
        $headerRow->subItem(6, 'AMRAP', 'AMRAP 12min:<br />10 [Exercise A]<br />15 [Exercise B]', 'As Many Rounds As Possible')
            ->compact()
            ->add();
        
        // EMOM
        $headerRow->subItem(7, 'EMOM', 'EMOM 16min:<br />5 [Exercise A]', 'Every Minute On the Minute')
            ->compact()
            ->add();
        
        // For Time
        $headerRow->subItem(8, 'For Time', '21-15-9 For Time:<br />[Exercise A]<br />[Exercise B]', 'Complete as fast as possible')
            ->compact()
            ->add();
        
        // Rounds
        $headerRow->subItem(9, 'Rounds', '5 Rounds:<br />10 [Exercise A]<br />20 [Exercise B]', 'Fixed number of rounds')
            ->compact()
            ->add();
        
        $headerRow->add();
        
        return $tableBuilder->build();
    }
}
