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

    protected $exerciseListService;

    public function __construct(\App\Services\WorkoutExerciseListService $exerciseListService)
    {
        $this->exerciseListService = $exerciseListService;
    }

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
        $exerciseSelectionList = $this->exerciseListService->generateExerciseSelectionListForNew(Auth::id());
        $components[] = $exerciseSelectionList;

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

        // Title with back button - use generated label without exercise count
        $nameGenerator = app(\App\Services\WorkoutNameGenerator::class);
        $workoutLabel = $nameGenerator->generateFromWorkout($workout, false);
        
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
        $exerciseSelectionList = $this->exerciseListService->generateExerciseSelectionList($workout, [
            'redirectContext' => 'simple-workout',
            'initialState' => $shouldExpandList ? 'expanded' : 'collapsed'
        ]);
        
        $components[] = $exerciseSelectionList;

        // Exercise list table
        $exerciseListTable = $this->exerciseListService->generateExerciseListTable($workout, [
            'redirectContext' => 'simple-workout',
            'showPlayButtons' => true,
            'showMoveButtons' => true,
            'showDeleteButtons' => true,
            'showLoggedStatus' => true,
            'compactMode' => true,
        ]);
        
        $components[] = $exerciseListTable;

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

        // Check if this is the last exercise in the workout
        $exerciseCount = $workout->exercises()->count();
        $isLastExercise = $exerciseCount === 1;
        $isSimpleWorkout = $this->isSimpleWorkout($workout);

        $exercise->delete();

        // If this was the last exercise in a simple workout, delete the workout entirely
        if ($isLastExercise && $isSimpleWorkout) {
            $workout->delete();
            
            return redirect()
                ->route('workouts.index')
                ->with('success', 'Last exercise removed. Workout deleted.');
        }

        return redirect()
            ->route('workouts.edit-simple', $workout->id)
            ->with('success', 'Exercise removed!');
    }
}
