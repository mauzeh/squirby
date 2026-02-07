<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseMatchingAlias;
use App\Models\Workout;
use App\Services\ComponentBuilder as C;
use App\Services\UnifiedExerciseListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExerciseMatchingAliasController extends Controller
{
    protected $unifiedExerciseListService;

    public function __construct(UnifiedExerciseListService $unifiedExerciseListService)
    {
        $this->unifiedExerciseListService = $unifiedExerciseListService;
    }

    /**
     * Show the exercise selection page for creating an alias
     */
    public function create(Request $request)
    {
        $aliasName = $request->input('alias_name');
        $workoutId = $request->input('workout_id');
        
        if (!$aliasName) {
            return redirect()->route('workouts.index')
                ->with('error', 'No alias name specified.');
        }
        
        $components = [];
        
        // Title with back button
        $backUrl = $workoutId 
            ? route('workouts.edit', $workoutId)
            : route('workouts.index');
        
        $components[] = C::title('Link "' . $aliasName . '"')
            ->subtitle('Select the exercise this refers to')
            ->backButton('fa-arrow-left', $backUrl, 'Back to workout')
            ->build();
        
        // Info message
        $components[] = C::messages()
            ->info('Select an exercise below to create an alias. This will make "' . $aliasName . '" clickable in your workouts.')
            ->build();
        
        // Generate exercise list using unified service
        $exerciseListData = $this->unifiedExerciseListService->generate(Auth::id(), [
            'context' => 'alias-linking',
            'filter_exercises' => 'all',
            'show_popular' => false,
            'url_generator' => fn($exercise) => route('exercise-aliases.store', [
                'exercise_id' => $exercise->id,
                'alias_name' => $aliasName,
                'workout_id' => $workoutId
            ]),
            'create_form' => [
                'action' => route('exercise-aliases.create-and-link'),
                'inputName' => 'exercise_name',
                'hiddenFields' => [
                    'alias_name' => $aliasName,
                    'workout_id' => $workoutId
                ],
                'buttonTemplate' => 'Create "{term}"',
                'method' => 'POST',
                'ariaLabel' => 'Create new exercise and link alias',
                'submitText' => 'Create Exercise'
            ],
            'initial_state' => 'expanded',
            'show_cancel_button' => false,
            'restrict_height' => false,
            'recent_days' => 30,
            'aria_labels' => [
                'section' => 'Exercise selection list',
                'selectItem' => 'Select exercise to link alias',
            ],
        ]);
        
        $components[] = [
            'type' => 'item-list',
            'data' => $exerciseListData,
        ];
        
        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Store a new exercise alias
     */
    public function store(Request $request)
    {
        $exerciseId = $request->input('exercise_id');
        $aliasName = $request->input('alias_name');
        $workoutId = $request->input('workout_id');
        
        if (!$exerciseId || !$aliasName) {
            return redirect()->route('workouts.index')
                ->with('error', 'Missing required information.');
        }
        
        // Verify exercise exists and is accessible
        $exercise = Exercise::where('id', $exerciseId)
            ->availableToUser(Auth::id())
            ->first();
        
        if (!$exercise) {
            return redirect()->route('workouts.index')
                ->with('error', 'Exercise not found.');
        }
        
        // Check if alias already exists for this exercise
        $existingAlias = ExerciseMatchingAlias::where('exercise_id', $exerciseId)
            ->where('alias', $aliasName)
            ->first();
        
        if ($existingAlias) {
            $message = 'Alias "' . $aliasName . '" already exists for ' . $exercise->title . '!';
        } else {
            // Check if alias exists for a different exercise
            $aliasForDifferentExercise = ExerciseMatchingAlias::where('alias', $aliasName)
                ->where('exercise_id', '!=', $exerciseId)
                ->first();
            
            if ($aliasForDifferentExercise) {
                // Update existing alias to point to new exercise
                $aliasForDifferentExercise->update(['exercise_id' => $exerciseId]);
                $message = 'Alias "' . $aliasName . '" updated to link to ' . $exercise->title . '!';
            } else {
                // Create new alias
                ExerciseMatchingAlias::create([
                    'exercise_id' => $exerciseId,
                    'alias' => $aliasName,
                ]);
                $message = 'Alias "' . $aliasName . '" created for ' . $exercise->title . '!';
            }
        }
        
        // Redirect back to workout
        $redirectUrl = $workoutId 
            ? route('workouts.edit', $workoutId)
            : route('workouts.index');
        
        return redirect($redirectUrl)->with('success', $message);
    }
    
    /**
     * Create a new exercise and link it with an alias
     */
    public function createAndLink(Request $request)
    {
        $request->validate([
            'exercise_name' => 'required|string|max:255',
            'alias_name' => 'required|string|max:255',
        ]);
        
        $workoutId = $request->input('workout_id');
        $aliasName = $request->input('alias_name');
        
        // Check if exercise already exists
        $existingExercise = Exercise::where('title', $request->input('exercise_name'))
            ->availableToUser(Auth::id())
            ->first();
        
        if ($existingExercise) {
            // Exercise exists, just create the alias
            return $this->store(new Request([
                'exercise_id' => $existingExercise->id,
                'alias_name' => $aliasName,
                'workout_id' => $workoutId
            ]));
        }
        
        // Generate unique canonical name
        $canonicalName = $this->generateUniqueCanonicalName($request->input('exercise_name'), Auth::id());
        
        // Create the new exercise
        $exercise = Exercise::create([
            'title' => $request->input('exercise_name'),
            'user_id' => Auth::id(),
            'exercise_type' => 'regular',
            'canonical_name' => $canonicalName
        ]);
        
        // Create the alias
        ExerciseMatchingAlias::create([
            'exercise_id' => $exercise->id,
            'alias' => $aliasName,
        ]);
        
        // Redirect back to workout
        $redirectUrl = $workoutId 
            ? route('workouts.edit', $workoutId)
            : route('workouts.index');
        
        return redirect($redirectUrl)
            ->with('success', 'Exercise "' . $exercise->title . '" created and linked to "' . $aliasName . '"!');
    }
    
    /**
     * Generate a unique canonical name for an exercise
     */
    private function generateUniqueCanonicalName($title, $userId)
    {
        $baseCanonicalName = \Illuminate\Support\Str::slug($title, '_');
        $canonicalName = $baseCanonicalName;
        $counter = 1;

        while ($this->canonicalNameExists($canonicalName, $userId)) {
            $canonicalName = $baseCanonicalName . '_' . $counter;
            $counter++;
        }

        return $canonicalName;
    }

    /**
     * Check if a canonical name already exists for the user
     */
    private function canonicalNameExists($canonicalName, $userId)
    {
        return Exercise::where('canonical_name', $canonicalName)
            ->availableToUser($userId)
            ->exists();
    }
}
