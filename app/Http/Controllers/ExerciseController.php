<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseService;
use App\Services\ExerciseMergeService;
use App\Services\ChartService;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Presenters\LiftLogTablePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExerciseController extends Controller
{
    use AuthorizesRequests;
    
    protected $exerciseService;
    protected $exerciseMergeService;
    protected $chartService;
    protected $liftLogTablePresenter;

    public function __construct(ExerciseService $exerciseService, ExerciseMergeService $exerciseMergeService, \App\Services\ChartService $chartService, LiftLogTablePresenter $liftLogTablePresenter)
    {
        $this->exerciseService = $exerciseService;
        $this->exerciseMergeService = $exerciseMergeService;
        $this->chartService = $chartService;
        $this->liftLogTablePresenter = $liftLogTablePresenter;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Eager load current user's roles to avoid repeated queries
        $currentUser = auth()->user()->load('roles');
        
        $exercises = Exercise::availableToUser()
            ->with([
                'user.roles', // Load user relationship with roles for displaying user names and checking permissions
                'aliases' => function ($query) {
                    $query->where('user_id', auth()->id());
                }
            ])
            ->withCount('liftLogs') // Add lift logs count for performance
            ->orderBy('user_id') // Global exercises (null) first, then user exercises
            ->orderBy('title', 'asc')
            ->get();
            
        // Precompute merge eligibility for admin users to avoid N+1 queries
        $mergeEligibleIds = collect();
        if ($currentUser->hasRole('Admin')) {
            $userExercises = $exercises->where('user_id', '!=', null);
            if ($userExercises->isNotEmpty()) {
                // Get all global exercises for comparison
                $globalExercises = Exercise::onlyGlobal()->get();
                
                foreach ($userExercises as $exercise) {
                    $hasCompatibleTargets = $globalExercises->contains(function ($global) use ($exercise) {
                        return $exercise->isCompatibleForMerge($global);
                    });
                    
                    if ($hasCompatibleTargets) {
                        $mergeEligibleIds->push($exercise->id);
                    }
                }
            }
        }
        
        return view('exercises.index', compact('exercises', 'mergeEligibleIds'))->with('exerciseMergeService', $this->exerciseMergeService);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $canCreateGlobal = auth()->user()->hasRole('Admin');
        return view('exercises.create', compact('canCreateGlobal'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $availableTypes = ExerciseTypeFactory::getAvailableTypes();
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exercise_type' => 'required|in:' . implode(',', $availableTypes),
            'is_global' => 'nullable|boolean',
        ]);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            $this->authorize('createGlobalExercise', Exercise::class);
        }

        // Check for name conflicts
        $this->validateExerciseName($validated['title'], $validated['is_global'] ?? false);

        $exerciseType = $validated['exercise_type'];

        // Create a temporary exercise to determine the strategy
        $tempExercise = new Exercise([
            'exercise_type' => $exerciseType,
        ]);

        // Use exercise type strategy to process exercise data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($tempExercise);
        $processedData = $exerciseTypeStrategy->processExerciseData($validated);

        $exercise = new Exercise([
            'title' => $processedData['title'],
            'description' => $processedData['description'],
            'exercise_type' => $exerciseType,
        ]);
        
        if ($validated['is_global'] ?? false) {
            $exercise->user_id = null;
        } else {
            $exercise->user_id = auth()->id();
        }

        $exercise->save();

        return redirect()->route('exercises.index')->with('success', 'Exercise created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exercise $exercise)
    {
        return view('exercises.show', compact('exercise'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exercise $exercise)
    {
        $this->authorize('update', $exercise);
        $canCreateGlobal = auth()->user()->hasRole('Admin');
        return view('exercises.edit', compact('exercise', 'canCreateGlobal'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exercise $exercise)
    {
        $this->authorize('update', $exercise);
        
        $availableTypes = ExerciseTypeFactory::getAvailableTypes();
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exercise_type' => 'required|in:' . implode(',', $availableTypes),
            'is_global' => 'nullable|boolean',
        ]);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            $this->authorize('createGlobalExercise', Exercise::class);
        }

        // Check for name conflicts (excluding current exercise)
        $this->validateExerciseNameForUpdate($exercise, $validated['title'], $validated['is_global'] ?? false);

        $exerciseType = $validated['exercise_type'];

        // Create a temporary exercise with new values to determine the strategy
        $tempExercise = new Exercise([
            'exercise_type' => $exerciseType,
        ]);

        // Use exercise type strategy to process exercise data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($tempExercise);
        $processedData = $exerciseTypeStrategy->processExerciseData($validated);

        $exercise->update([
            'title' => $processedData['title'],
            'description' => $processedData['description'],
            'exercise_type' => $exerciseType,
            'user_id' => ($validated['is_global'] ?? false) ? null : auth()->id()
        ]);

        return redirect()->route('exercises.index')->with('success', 'Exercise updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exercise $exercise)
    {
        $this->authorize('delete', $exercise);
        
        if ($exercise->liftLogs()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete exercise: it has associated lift logs.']);
        }
        
        $exercise->delete();
        
        return redirect()->route('exercises.index')->with('success', 'Exercise deleted successfully.');
    }

    /**
     * Remove the specified resources from storage.
     */
    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'exercise_ids' => 'required|array',
            'exercise_ids.*' => 'exists:exercises,id',
        ]);

        $exercises = Exercise::whereIn('id', $validated['exercise_ids'])->get();

        foreach ($exercises as $exercise) {
            $this->authorize('delete', $exercise);
            
            if ($exercise->liftLogs()->exists()) {
                return back()->withErrors(['error' => "Cannot delete exercise '{$exercise->title}': it has associated lift logs."]);
            }
        }

        Exercise::destroy($validated['exercise_ids']);

        return redirect()->route('exercises.index')->with('success', 'Selected exercises deleted successfully!');
    }

    /**
     * Promote a user exercise to global exercise.
     */
    public function promote(Exercise $exercise)
    {
        $this->authorize('promoteToGlobal', $exercise);
        
        if ($exercise->isGlobal()) {
            return back()->withErrors(['error' => "Exercise '{$exercise->title}' is already global."]);
        }

        $exercise->update(['user_id' => null]);

        return redirect()->route('exercises.index')
            ->with('success', "Exercise '{$exercise->title}' promoted to global status successfully.");
    }

    /**
     * Unpromote a global exercise back to user exercise.
     */
    public function unpromote(Exercise $exercise)
    {
        $this->authorize('unpromoteToUser', $exercise);
        
        if (!$exercise->isGlobal()) {
            return back()->withErrors(['error' => "Exercise '{$exercise->title}' is not a global exercise."]);
        }

        // Determine original owner from earliest lift log
        $originalOwner = $this->determineOriginalOwner($exercise);
        
        if (!$originalOwner) {
            return back()->withErrors(['error' => "Cannot determine original owner for exercise '{$exercise->title}'."]);
        }

        // Check if other users have lift logs with this exercise
        $otherUsersCount = $exercise->liftLogs()
            ->where('user_id', '!=', $originalOwner->id)
            ->distinct('user_id')
            ->count('user_id');

        if ($otherUsersCount > 0) {
            $userText = $otherUsersCount === 1 ? 'user has' : 'users have';
            return back()->withErrors(['error' => "Cannot unpromote exercise '{$exercise->title}': {$otherUsersCount} other {$userText} workout logs with this exercise. The exercise must remain global to preserve their data."]);
        }

        $exercise->update(['user_id' => $originalOwner->id]);

        return redirect()->route('exercises.index')
            ->with('success', "Exercise '{$exercise->title}' unpromoted to personal exercise successfully.");
    }



    public function showLogs(Request $request, Exercise $exercise)
    {
        // Only allow viewing logs for exercises available to the user
        $availableExercise = Exercise::availableToUser()->find($exercise->id);
        if (!$availableExercise) {
            abort(403, 'Unauthorized action.');
        }
        
        // Eager load aliases for the exercise
        $exercise->load(['aliases' => function ($query) {
            $query->where('user_id', auth()->id());
        }]);
        
        $liftLogs = $exercise->liftLogs()
            ->with(['liftSets', 'exercise.aliases' => function ($query) {
                $query->where('user_id', auth()->id());
            }])
            ->where('user_id', auth()->id())
            ->orderBy('logged_at', 'desc') // Most recent first
            ->get();

        $chartData = $this->chartService->generateProgressChart($liftLogs, $exercise);

        // Get display name (alias if exists, otherwise title)
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $displayName = $aliasService->getDisplayName($exercise, auth()->user());

        // Build components
        $components = [];
        
        // Title
        $components[] = \App\Services\ComponentBuilder::title($displayName)->build();
        
        // Messages from session
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Add chart if we have data
        if (!empty($chartData['datasets'])) {
            $strategy = $exercise->getTypeStrategy();
            $chartTitle = $strategy->getChartTitle();
            
            $components[] = \App\Services\ComponentBuilder::chart('progressChart', $chartTitle)
                ->type('line')
                ->datasets($chartData['datasets'])
                ->timeScale('day')
                ->beginAtZero()
                ->showLegend()
                ->ariaLabel($exercise->title . ' progress chart')
                ->build();
        }
        
        // Build table using shared service
        if ($liftLogs->isNotEmpty()) {
            $liftLogTableRowBuilder = app(\App\Services\LiftLogTableRowBuilder::class);
            
            $rows = $liftLogTableRowBuilder->buildRows($liftLogs, [
                'showDateBadge' => true,
                'showCheckbox' => false,
                'showViewLogsAction' => false, // Don't show "view logs" when already viewing logs
                'showDeleteAction' => false,
            ]);
            
            $tableBuilder = \App\Services\ComponentBuilder::table()
                ->rows($rows)
                ->emptyMessage('No lift logs found for this exercise.')
                ->ariaLabel('Exercise logs')
                ->spacedRows();

            $components[] = $tableBuilder->build();
        } else {
            // Show empty message
            $components[] = \App\Services\ComponentBuilder::rawHtml('<p>No lift logs found for this exercise.</p>');
        }
        
        $data = ['components' => $components];

        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the merge target selection page.
     */
    public function showMerge(Exercise $exercise)
    {
        $this->authorize('merge', $exercise);

        // Check if exercise can be merged
        if (!$this->exerciseMergeService->canBeMerged($exercise)) {
            return back()->withErrors(['error' => "Exercise '{$exercise->title}' cannot be merged. It must be a user exercise with compatible global targets available."]);
        }

        // Get potential target exercises
        $potentialTargets = $this->exerciseMergeService->getPotentialTargets($exercise);
        
        // Get merge statistics for the source exercise
        $sourceStats = $this->exerciseMergeService->getMergeStatistics($exercise);
        
        // Get statistics and compatibility info for each target
        $targetsWithInfo = $potentialTargets->map(function ($target) use ($exercise) {
            $stats = $this->exerciseMergeService->getMergeStatistics($target);
            $compatibility = $this->exerciseMergeService->validateMergeCompatibility($exercise, $target);
            
            return [
                'exercise' => $target,
                'stats' => $stats,
                'compatibility' => $compatibility
            ];
        });

        return view('exercises.merge', compact('exercise', 'sourceStats', 'targetsWithInfo'));
    }

    /**
     * Execute the merge operation.
     */
    public function merge(Request $request, Exercise $exercise)
    {
        $this->authorize('merge', $exercise);

        $validated = $request->validate([
            'target_exercise_id' => 'required|exists:exercises,id',
            'create_alias' => 'nullable|boolean',
        ]);

        $targetExercise = Exercise::findOrFail($validated['target_exercise_id']);

        // Validate compatibility
        $compatibility = $this->exerciseMergeService->validateMergeCompatibility($exercise, $targetExercise);
        
        if (!$compatibility['can_merge']) {
            return back()->withErrors(['error' => 'Merge failed: ' . implode(', ', $compatibility['errors'])]);
        }

        // Get create_alias parameter, default to true if not provided
        $createAlias = $request->boolean('create_alias', true);

        try {
            $this->exerciseMergeService->mergeExercises($exercise, $targetExercise, auth()->user(), $createAlias);
            
            // Build success message
            $successMessage = "Exercise '{$exercise->title}' successfully merged into '{$targetExercise->title}'. All workout data has been preserved.";
            
            // Add alias creation note if applicable
            if ($createAlias && $exercise->user) {
                $successMessage .= " An alias has been created so the owner will continue to see '{$exercise->title}'.";
            }
            
            return redirect()->route('exercises.index')
                ->with('success', $successMessage);
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Merge failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Validate exercise name for conflicts when creating new exercise.
     */
    private function validateExerciseName(string $title, bool $isGlobal): void
    {
        if ($isGlobal) {
            // Check if global exercise with same name exists
            if (Exercise::global()->where('title', $title)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'A global exercise with this name already exists.'
                ]);
            }
        } else {
            // Check if user has exercise with same name OR global exercise exists
            $userId = auth()->id();
            $conflicts = Exercise::where('title', $title)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->exists();

            if ($conflicts) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'An exercise with this name already exists.'
                ]);
            }
        }
    }

    /**
     * Validate exercise name for conflicts when updating existing exercise.
     */
    private function validateExerciseNameForUpdate(Exercise $exercise, string $title, bool $isGlobal): void
    {
        if ($isGlobal) {
            // Check if another global exercise with same name exists
            if (Exercise::global()->where('title', $title)->where('id', '!=', $exercise->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'A global exercise with this name already exists.'
                ]);
            }
        } else {
            // Check if user has another exercise with same name OR global exercise exists
            $userId = auth()->id();
            $conflicts = Exercise::where('title', $title)
                ->where('id', '!=', $exercise->id)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->exists();

            if ($conflicts) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'An exercise with this name already exists.'
                ]);
            }
        }
    }

    /**
     * Determine the original owner of an exercise based on lift logs.
     */
    private function determineOriginalOwner(Exercise $exercise): ?User
    {
        // Get the user who has the earliest lift log for this exercise
        $earliestLog = $exercise->liftLogs()
            ->with('user')
            ->orderBy('logged_at', 'asc')
            ->first();

        return $earliestLog ? $earliestLog->user : null;
    }
}