<?php

namespace App\Http\Controllers;

use App\Actions\Workouts\CreateWorkoutAction;
use App\Actions\Workouts\UpdateWorkoutAction;
use App\Http\Controllers\Concerns\DetectsSimpleWorkouts;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\ComponentBuilder as C;
use App\Services\WodDisplayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkoutController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    use DetectsSimpleWorkouts;

    public function __construct(
        private WodDisplayService $wodDisplayService,
        private \App\Services\WorkoutExerciseListService $exerciseListService,
        private CreateWorkoutAction $createWorkoutAction,
        private UpdateWorkoutAction $updateWorkoutAction
    ) {}

    /**
     * Get exercise names for autocomplete
     */
    private function getExerciseNames()
    {
        $userId = Auth::id();
        
        return Exercise::availableToUser($userId)
            ->select('title')
            ->orderBy('title')
            ->get()
            ->pluck('title')
            ->unique()
            ->values();
    }

    /**
     * Display a listing of the user's workouts
     */
    public function index(Request $request)
    {
        // Get user's workouts
        $workouts = Workout::where('user_id', Auth::id())
            ->with(['exercises.exercise'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $components = [];

        // Title
        $components[] = C::title('Workouts')
            ->subtitle('Tap a workout to edit exercises and log your lifts')
            ->build();

        // Table of workouts
        if ($workouts->isNotEmpty()) {
            // Create buttons - admins get both options, regular users only get simple
            $components[] = C::button('Create Workout')
                ->asLink(route('workouts.create-simple'))
                ->build();
            
            // Show advanced workout button for admins and when impersonating
            if ($this->canAccessAdvancedWorkouts()) {
                $components[] = C::button('Create Advanced Workout')
                    ->asLink(route('workouts.create'))
                    ->build();
            }
            
            $tableBuilder = C::table();

            foreach ($workouts as $workout) {
                $isSimple = $this->isSimpleWorkout($workout);
                $isAdmin = $this->canAccessAdvancedWorkouts();
                
                // For simple workouts, generate intelligent label based on exercises
                // For advanced workouts, use the stored name
                if ($isSimple) {
                    $nameGenerator = app(\App\Services\WorkoutNameGenerator::class);
                    $line1 = $nameGenerator->generateFromWorkout($workout);
                } else {
                    $line1 = $workout->name;
                }
                
                // Get exercises for display
                if ($isSimple) {
                    // Simple workout: use workout_exercises table
                    $exerciseCount = $workout->exercises->count();
                    if ($exerciseCount > 0) {
                        $exerciseNames = $workout->exercises->pluck('exercise.title')->filter()->toArray();
                        $line2 = implode(', ', $exerciseNames);
                    } else {
                        $line2 = 'No exercises';
                    }
                } else {
                    // Advanced workout: parse exercises from WOD syntax
                    $wodParser = app(\App\Services\WodParser::class);
                    $exerciseNames = $wodParser->extractLoggableExercises($workout->wod_syntax);
                    $exerciseCount = count($exerciseNames);
                    
                    if ($exerciseCount > 0) {
                        $line2 = implode(', ', $exerciseNames);
                    } else {
                        $line2 = 'No exercises';
                    }
                }
                
                $line3 = $workout->description ?: null;

                // Route to appropriate edit page based on workout type and user role
                if ($isSimple) {
                    $editRoute = route('workouts.edit-simple', $workout->id);
                } else {
                    // Advanced workout - only admins can edit
                    if ($isAdmin) {
                        $editRoute = route('workouts.edit', $workout->id);
                    } else {
                        // Regular users can't edit advanced workouts - no edit button
                        $editRoute = null;
                    }
                }

                $rowBuilder = $tableBuilder->row(
                    $workout->id,
                    $line1,
                    $line2,
                    $line3
                );
                
                // Add time badge - green if modified today, neutral otherwise
                $badgeColor = $workout->updated_at->isToday() ? 'success' : 'neutral';
                
                // Show "X days ago" for up to 14 days, then show the date
                $daysAgo = (int) $workout->updated_at->diffInDays(now());
                if ($daysAgo <= 14) {
                    if ($daysAgo === 0) {
                        // Today - show hours/minutes
                        $timeText = $workout->updated_at->diffForHumans();
                    } else {
                        // 1-14 days - always show as days
                        $timeText = $daysAgo === 1 ? '1 day ago' : $daysAgo . ' days ago';
                    }
                } else {
                    $timeText = $workout->updated_at->format('M j, Y');
                }
                
                $rowBuilder->badge($timeText, $badgeColor);
                
                // Make the entire row clickable if user has permission to edit
                if ($editRoute) {
                    $rowBuilder->clickable($editRoute);
                }
                
                $rowBuilder->wrapText()->compact()->add();
            }

            $components[] = $tableBuilder->build();
        } else {
            // Empty state: show message first, then buttons
            $components[] = C::messages()
                ->info('No workouts yet. Create your first workout to get started!')
                ->build();
            
            // Create buttons - admins get both options, regular users only get simple
            $components[] = C::button('Create Workout')
                ->asLink(route('workouts.create-simple'))
                ->build();
            
            // Show advanced workout button for admins and when impersonating
            if ($this->canAccessAdvancedWorkouts()) {
                $components[] = C::button('Create Advanced Workout')
                    ->asLink(route('workouts.create'))
                    ->build();
            }
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new workout (Advanced WOD Syntax)
     * Only accessible to Admins and when impersonating
     */
    public function create(Request $request)
    {
        // Only admins and impersonators can create advanced workouts
        if (!$this->canAccessAdvancedWorkouts()) {
            return redirect()->route('workouts.create-simple')
                ->with('info', 'Advanced workout creation is only available to admins.');
        }

        $components = [];

        // Title
        $components[] = C::title('Create Workout')
            ->subtitle('Define your workout program')
            ->build();

        // WOD creation form with code editor
        $exampleSyntax = "# Block 1: Strength\n[Back Squat]: 5-5-5-5-5\n[Bench Press]: 3x8\n\n# Block 2: Conditioning\nAMRAP 12min:\n10 [Box Jumps]\n15 [Push-ups]\n20 [Air Squats]";
        
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

        $data = ['components' => $components, 'exerciseNames' => $this->getExerciseNames()];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created workout (Advanced WOD Syntax)
     * Only accessible to Admins and when impersonating
     */
    public function store(Request $request)
    {
        try {
            $workout = $this->createWorkoutAction->execute($request, auth()->user());
            
            return redirect()
                ->route('workouts.edit', $workout->id)
                ->with('success', 'Workout created!');
                
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->route('workouts.create-simple')
                ->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified workout (Advanced WOD Syntax)
     * Only accessible to Admins and when impersonating
     */
    public function edit(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        // Auto-detect and redirect if simple workout
        if ($this->isSimpleWorkout($workout)) {
            return redirect()->route('workouts.edit-simple', $workout);
        }

        // Only admins and impersonators can edit advanced workouts
        if (!$this->canAccessAdvancedWorkouts()) {
            return redirect()->route('workouts.edit-simple', $workout)
                ->with('error', 'Advanced workout editing is only available to admins.');
        }

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

        // Exercise list table (read-only - derived from WOD syntax)
        $exerciseListTable = $this->exerciseListService->generateExerciseListTable($workout, [
            'redirectContext' => 'advanced-workout',
            'showPlayButtons' => true,
            'showMoveButtons' => false,  // No manual reordering for advanced workouts
            'showDeleteButtons' => false, // No manual deletion for advanced workouts
            'showLoggedStatus' => true,
            'compactMode' => true,
        ]);
        
        $components[] = $exerciseListTable;

        // Edit form with code editor
        $exampleSyntax = "# Block 1: Strength\n[Back Squat]: 5-5-5-5-5\n[Bench Press]: 3x8\n\n# Block 2: Conditioning\nAMRAP 12min:\n10 [Box Jumps]\n15 [Push-ups]\n20 [Air Squats]";
        
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

        // Delete workout form
        $components[] = C::form('delete-workout', 'Removing this workout')
            ->formAction(route('workouts.destroy', $workout->id))
            ->hiddenField('_method', 'DELETE')
            ->message('info', 'User logs will be preserved if this workout is deleted.')
            ->submitButton('Delete Workout')
            ->submitButtonClass('btn-danger')
            ->confirmMessage('Are you sure you want to delete this workout? This action cannot be undone.')
            ->build();

        $data = ['components' => $components, 'exerciseNames' => $this->getExerciseNames()];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Update the specified workout (Advanced WOD Syntax)
     * Only accessible to Admins and when impersonating
     */
    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        try {
            $this->updateWorkoutAction->execute($request, $workout, auth()->user());
            
            return redirect()
                ->route('workouts.edit', $workout->id)
                ->with('success', 'Workout updated!');
                
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->route('workouts.edit-simple', $workout)
                ->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
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
}
