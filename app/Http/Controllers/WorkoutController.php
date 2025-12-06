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

    public function __construct(
        WodLoggingService $wodLoggingService, 
        WodDisplayService $wodDisplayService
    ) {
        $this->wodLoggingService = $wodLoggingService;
        $this->wodDisplayService = $wodDisplayService;
    }

    /**
     * Display a listing of the user's workouts
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        $expandWorkoutId = $request->query('workout_id'); // For manual expansion after delete
        
        // Get user's workouts
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
            ->subtitle('Your workout programs')
            ->build();

        // Table of workouts with exercises as sub-items
        if ($workouts->isNotEmpty()) {
            // Create button (shown when there are workouts)
            $components[] = C::button('Create Workout')
                ->asLink(route('workouts.create'))
                ->build();
            $tableBuilder = C::table();

            foreach ($workouts as $workout) {
                $isWod = $workout->isWod();
                $line1 = $workout->name;
                
                // Get exercises for display
                $exercisesToDisplay = $workout->exercises->pluck('exercise.title')->toArray();
                
                $exerciseCount = count($exercisesToDisplay);
                if ($exerciseCount > 0) {
                    $exerciseList = implode(', ', $exercisesToDisplay);
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
                ->linkAction('fa-info', route('workouts.edit', $workout->id), 'View workout details', 'btn-info-circle')
                ->compact();

                // Check if this workout has any exercises logged today (for auto-expand)
                $hasLoggedExercisesToday = false;

                // Add exercises as sub-items with log now button or edit button
                if ($workout->exercises->isNotEmpty()) {
                    foreach ($workout->exercises as $index => $exercise) {
                        $exerciseLine1 = $exercise->exercise->title;
                        // Show scheme if available (for WODs)
                        $exerciseLine2 = $exercise->scheme ?? null;
                        $exerciseLine3 = null;
                        
                        // Check if exercise was logged today
                        if (in_array($exercise->exercise_id, $loggedExerciseIds)) {
                            $hasLoggedExercisesToday = true;
                            
                            // Get the lift log data
                            $liftLog = $loggedExerciseData[$exercise->exercise_id];
                            
                            // Show comments in line3 if they exist (keep scheme in line2)
                            if (!empty($liftLog->comments)) {
                                $exerciseLine3 = $liftLog->comments;
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
                            // Not logged yet - show exercise name and scheme
                            $subItemBuilder = $rowBuilder->subItem(
                                $exercise->id,
                                $exerciseLine1,
                                $exerciseLine2,
                                $exerciseLine3
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
                ->info('No workouts yet. Create your first workout to get started!')
                ->build();
            
            $components[] = C::button('Create Workout')
                ->asLink(route('workouts.create'))
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new workout
     */
    public function create(Request $request)
    {
        $components = [];

        // Title
        $components[] = C::title('Create Workout')
            ->subtitle('Define your workout program')
            ->build();

        // WOD creation form with code editor
        $exampleSyntax = "# Block 1: Strength\n[[Back Squat]]: 5-5-5-5-5\n[[Bench Press]]: 3x8\n\n# Block 2: Conditioning\nAMRAP 12min:\n10 [[Box Jumps]]\n15 [[Push-ups]]\n20 [[Air Squats]]";
        
        // Single form with embedded code editor
        $components[] = [
            'type' => 'wod-form',
            'data' => [
                'id' => 'create-workout',
                'title' => 'Workout Details',
                'formAction' => route('workouts.store'),
                'formType' => 'primary',
                'nameField' => [
                    'label' => 'Workout Name:',
                    'value' => '',
                    'placeholder' => 'e.g., Monday Strength'
                ],
                'codeEditor' => [
                    'id' => 'wod-syntax-editor',
                    'label' => 'Workout Syntax',
                    'name' => 'wod_syntax',
                    'value' => '',
                    'placeholder' => $exampleSyntax,
                    'mode' => 'wod-syntax',
                    'height' => '600px',
                    'lineNumbers' => true
                ],
                'descriptionField' => [
                    'label' => 'Description:',
                    'value' => '',
                    'placeholder' => 'Optional'
                ],
                'submitButton' => 'Create Workout'
            ],
            'requiresScript' => [
                'mobile-entry/components/code-editor',
                'mobile-entry/components/code-editor-autocomplete'
            ]
        ];

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created workout
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'wod_syntax' => 'required|string',
        ]);
        
        // Parse the workout syntax
        $parser = app(\App\Services\WodParser::class);
        try {
            $parsed = $parser->parse($validated['wod_syntax']);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to parse workout syntax: ' . $e->getMessage());
        }
        
        $workout = Workout::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'wod_syntax' => $validated['wod_syntax'],
            'wod_parsed' => $parsed, // Auto-syncs exercises via model event
            'is_public' => false,
        ]);
        
        return redirect()
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Workout created!');
    }

    /**
     * Show the form for editing the specified workout
     */
    public function edit(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $components = [];

        // Title with back button
        $components[] = C::title($workout->name)
            ->subtitle('Edit Workout')
            ->backButton('fa-arrow-left', route('workouts.index'), 'Back to workouts')
            ->build();

        // Messages from session
        if ($sessionMessages = C::messagesFromSession()) {
            $components[] = $sessionMessages;
        }

        // Workout Display (formatted preview)
        if ($workout->wod_syntax) {
            $processedMarkdown = $this->wodDisplayService->processForDisplay($workout);
            $components[] = C::markdown($processedMarkdown)->classes('wod-display')->build();
        }

        // Edit form with code editor
        $exampleSyntax = "# Block 1: Strength\n[[Back Squat]]: 5-5-5-5-5\n[[Bench Press]]: 3x8\n\n# Block 2: Conditioning\nAMRAP 12min:\n10 [[Box Jumps]]\n15 [[Push-ups]]\n20 [[Air Squats]]";
        
        $components[] = [
            'type' => 'wod-form',
            'data' => [
                'id' => 'edit-workout',
                'title' => 'Workout Details',
                'formAction' => route('workouts.update', $workout->id),
                'formType' => 'info',
                'nameField' => [
                    'label' => 'Workout Name:',
                    'value' => $workout->name,
                    'placeholder' => 'e.g., Monday Strength'
                ],
                'codeEditor' => [
                    'id' => 'wod-syntax-editor',
                    'label' => 'Workout Syntax',
                    'name' => 'wod_syntax',
                    'value' => $workout->wod_syntax ?? '',
                    'placeholder' => $exampleSyntax,
                    'mode' => 'wod-syntax',
                    'height' => '600px',
                    'lineNumbers' => true
                ],
                'descriptionField' => [
                    'label' => 'Description:',
                    'value' => $workout->description ?? '',
                    'placeholder' => 'Optional'
                ],
                'submitButton' => 'Update Workout',
                'hiddenFields' => [
                    '_method' => 'PUT'
                ]
            ],
            'requiresScript' => [
                'mobile-entry/components/code-editor',
                'mobile-entry/components/code-editor-autocomplete'
            ]
        ];

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

        // Delete workout form
        $components[] = C::form('delete-workout', 'Danger Zone')
            ->formAction(route('workouts.destroy', $workout->id))
            ->hiddenField('_method', 'DELETE')
            ->message('warning', 'Once this workout is deleted, all of its data will be permanently removed.')
            ->submitButton('Delete Workout')
            ->submitButtonClass('btn-danger')
            ->confirmMessage('Are you sure you want to delete this workout? This action cannot be undone.')
            ->build();

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Update the specified workout
     */
    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'wod_syntax' => 'required|string',
        ]);
        
        // Parse the workout syntax
        $parser = app(\App\Services\WodParser::class);
        try {
            $parsed = $parser->parse($validated['wod_syntax']);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to parse workout syntax: ' . $e->getMessage());
        }
        
        $workout->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'wod_syntax' => $validated['wod_syntax'],
            'wod_parsed' => $parsed, // Auto-syncs exercises via model event
        ]);
        
        return redirect()
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Workout updated!');
    }

    /**
     * Remove the specified workout
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
     * Apply a workout to a specific date
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
            ->with('success', 'Workout "' . $workout->name . '" applied!');
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
