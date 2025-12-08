<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DetectsSimpleWorkouts;
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

    protected $wodDisplayService;

    public function __construct(WodDisplayService $wodDisplayService) 
    {
        $this->wodDisplayService = $wodDisplayService;
    }

    /**
     * Display a listing of the user's workouts
     */
    public function index(Request $request)
    {
        // Get user's workouts
        $workouts = Workout::where('user_id', Auth::id())
            ->with(['exercises.exercise'])
            ->orderBy('name')
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
                
                $line1 = $workout->name;
                
                // Get exercises for display
                $exerciseCount = $workout->exercises->count();
                if ($exerciseCount > 0) {
                    $exerciseNames = $workout->exercises->pluck('exercise.title')->filter()->toArray();
                    $exerciseList = implode(', ', $exerciseNames);
                    $line2 = $exerciseCount . ' ' . ($exerciseCount === 1 ? 'exercise' : 'exercises') . ': ' . $exerciseList;
                } else {
                    $line2 = 'No exercises';
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
     * Store a newly created workout (Advanced WOD Syntax)
     * Only accessible to Admins and when impersonating
     */
    public function store(Request $request)
    {
        // Only admins and impersonators can create advanced workouts
        if (!$this->canAccessAdvancedWorkouts()) {
            return redirect()->route('workouts.create-simple')
                ->with('error', 'Advanced workout creation is only available to admins.');
        }

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
            'is_public' => false,
        ]);
        
        return redirect()
            ->route('workouts.edit', $workout->id)
            ->with('success', 'Workout created!');
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



        // Delete workout form
        $components[] = C::form('delete-workout', 'Removing this workout')
            ->formAction(route('workouts.destroy', $workout->id))
            ->hiddenField('_method', 'DELETE')
            ->message('info', 'User logs will be preserved if this workout is deleted.')
            ->submitButton('Delete Workout')
            ->submitButtonClass('btn-danger')
            ->confirmMessage('Are you sure you want to delete this workout? This action cannot be undone.')
            ->build();

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Update the specified workout (Advanced WOD Syntax)
     * Only accessible to Admins and when impersonating
     */
    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        // Only admins and impersonators can update advanced workouts
        if (!$this->canAccessAdvancedWorkouts()) {
            return redirect()->route('workouts.edit-simple', $workout)
                ->with('error', 'Advanced workout editing is only available to admins.');
        }

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
}
