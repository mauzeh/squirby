<?php

namespace App\Http\Controllers;

use App\Actions\Exercises\CreateExerciseAction;
use App\Actions\Exercises\UpdateExerciseAction;
use App\Actions\Exercises\MergeExerciseAction;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseService;
use App\Services\ExerciseMergeService;
use App\Services\ChartService;
use App\Services\ExercisePRService;
use App\Services\ComponentBuilder;
use App\Services\ExerciseFormService;
use App\Services\ExercisePageService;
use App\Presenters\LiftLogTablePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExerciseController extends Controller
{
    use AuthorizesRequests;
    
    public function __construct(
        private ExerciseService $exerciseService,
        private ExerciseMergeService $exerciseMergeService,
        private ChartService $chartService,
        private LiftLogTablePresenter $liftLogTablePresenter,
        private ExercisePRService $exercisePRService,
        private CreateExerciseAction $createExerciseAction,
        private UpdateExerciseAction $updateExerciseAction,
        private MergeExerciseAction $mergeExerciseAction,
        private ExerciseFormService $exerciseFormService,
        private ExercisePageService $exercisePageService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Only admins can access the exercise index
        $this->authorize('viewAny', Exercise::class);
        
        $userId = Auth::id();
        
        // Build components array
        $components = [
            \App\Services\ComponentBuilder::title(
                'Exercises',
                'Select an exercise to edit its details, or create a new one.'
            )->build(),
        ];
        
        // Add success/error messages if present
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Add Exercise button
        $components[] = \App\Services\ComponentBuilder::button('Add Exercise')
            ->asLink(route('exercises.create'))
            ->build();
        
        // Generate exercise selection list
        $exercises = Exercise::availableToUser($userId)
            ->with([
                'aliases' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                },
                'user' // Load user relationship for ownership display
            ])
            ->orderBy('title', 'asc')
            ->get();

        // Apply aliases to exercises
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $user = Auth::user();
        $exercises = $aliasService->applyAliasesToExercises($exercises, $user);
        
        if ($exercises->isEmpty()) {
            $components[] = \App\Services\ComponentBuilder::messages()
                ->info('No exercises found. Add one to get started!')
                ->build();
        } else {
            $listBuilder = \App\Services\ComponentBuilder::itemList()
                ->filterPlaceholder('Search exercises...')
                ->noResultsMessage('No exercises found. Create one to get started!')
                ->initialState('expanded')
                ->showCancelButton(false)
                ->restrictHeight(false);
            
            foreach ($exercises as $exercise) {
                // Show ownership information
                if ($exercise->isGlobal()) {
                    $ownershipLabel = 'Everyone';
                } else {
                    $ownershipLabel = $exercise->user_id === Auth::id() ? 'You' : $exercise->user->name;
                }
                
                $listBuilder->item(
                    (string) $exercise->id,
                    $exercise->title,
                    route('exercises.edit', $exercise),
                    $ownershipLabel,
                    'exercise-history'
                );
            }
            
            $components[] = $listBuilder->build();
        }

        $data = ['components' => $components];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $exercise = new Exercise();
        $user = Auth::user();
        
        $components = [
            \App\Services\ComponentBuilder::title('Create Exercise')->build(),
        ];

        // Add session messages if any
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }

        // Add form component
        $components[] = $this->exerciseFormService->generateExerciseForm(
            $exercise,
            $user,
            route('exercises.store'),
            'POST'
        );

        return view('mobile-entry.flexible', [
            'data' => [
                'components' => $components,
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $exercise = $this->createExerciseAction->execute($request, Auth::user());
        
        return redirect()->route('exercises.index')->with('success', 'Exercise created successfully.');
    }

    /**
     * Display the specified resource.
     */
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exercise $exercise)
    {
        $this->authorize('update', $exercise);
        $user = Auth::user();
        
        $components = [
            \App\Services\ComponentBuilder::title('Edit Exercise')->build(),
        ];

        // Add session messages if any
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }

        // Add exercise usage summary
        $components[] = $this->buildExerciseUsageSummary($exercise);

        // Add quick actions component
        $components[] = $this->buildExerciseQuickActions($exercise, $user);

        // Add form component
        $components[] = $this->exerciseFormService->generateExerciseForm(
            $exercise,
            $user,
            route('exercises.update', $exercise),
            'PUT'
        );

        return view('mobile-entry.flexible', [
            'data' => [
                'components' => $components,
            ]
        ]);
    }

    /**
     * Build simple exercise usage summary with two cards
     */
    private function buildExerciseUsageSummary(Exercise $exercise): array
    {
        // Get basic statistics
        $totalLogs = $exercise->liftLogs()->count();
        $userCount = $exercise->liftLogs()->distinct('user_id')->count('user_id');

        // Get user names
        $users = $exercise->liftLogs()
            ->with('user:id,name')
            ->get()
            ->pluck('user.name')
            ->unique()
            ->values();

        // Build summary component with two simple items
        $summaryBuilder = \App\Services\ComponentBuilder::summary();
        
        // Show usernames in label, count in value
        if ($userCount === 1) {
            $summaryBuilder->item("Users with Logs ({$users->first()})", '1');
        } elseif ($userCount > 1) {
            $userList = $users->take(3)->implode(', ');
            if ($userCount > 3) {
                $remaining = $userCount - 3;
                $userList .= ", +{$remaining} more";
            }
            $summaryBuilder->item("Users with Logs ({$userList})", number_format($userCount));
        } else {
            $summaryBuilder->item('Users with Logs', '0');
        }
        
        $summaryBuilder->item('Total Lift Logs', number_format($totalLogs));

        return $summaryBuilder->build();
    }

    /**
     * Build quick actions component for exercise edit page
     */
    private function buildExerciseQuickActions(Exercise $exercise, User $currentUser): array
    {
        $quickActions = \App\Services\ComponentBuilder::quickActions('Quick Actions');

        // Admin actions
        if ($currentUser->hasRole('Admin')) {
            if (!$exercise->isGlobal()) {
                // Promote to global
                $quickActions->formAction(
                    'fa-globe',
                    route('exercises.promote', $exercise),
                    'POST',
                    [],
                    'Promote',
                    'btn-success',
                    'Are you sure you want to promote this exercise to global status?'
                );
            } else {
                // Unpromote to personal
                $quickActions->formAction(
                    'fa-user',
                    route('exercises.unpromote', $exercise),
                    'POST',
                    [],
                    'Unpromote',
                    'btn-warning',
                    'Are you sure you want to unpromote this exercise back to personal status? This will only work if no other users have workout logs with this exercise.'
                );
            }

            // Merge action if eligible
            if ($this->isExerciseMergeEligible($exercise)) {
                $quickActions->linkAction(
                    'fa-code-branch',
                    route('exercises.show-merge', $exercise),
                    'Merge',
                    'btn-info'
                );
            }
        }

        // Delete action - always show but disable if not allowed
        $canDelete = $exercise->canBeDeletedBy($currentUser);
        $disabledReason = $canDelete ? '' : $this->getDeleteDisabledReason($exercise, $currentUser);
        
        $quickActions->formAction(
            'fa-trash',
            route('exercises.destroy', $exercise),
            'DELETE',
            [],
            'Delete',
            'btn-danger',
            'Are you sure you want to delete this exercise?',
            !$canDelete,
            $disabledReason
        );

        return $quickActions->build();
    }

    /**
     * Get the reason why delete is disabled for this exercise
     */
    private function getDeleteDisabledReason(Exercise $exercise, User $currentUser): string
    {
        // Check if user has permission to delete
        if (!$currentUser->can('delete', $exercise)) {
            if ($exercise->isGlobal() && !$currentUser->hasRole('Admin')) {
                return 'Only admins can delete global exercises';
            }
            if (!$exercise->isGlobal() && $exercise->user_id !== $currentUser->id && !$currentUser->hasRole('Admin')) {
                return 'You can only delete your own exercises';
            }
        }
        
        // Check if exercise has lift logs
        if ($exercise->liftLogs()->exists()) {
            $logCount = $exercise->liftLogs()->count();
            $logText = $logCount === 1 ? 'lift log' : 'lift logs';
            return "Cannot delete: exercise has {$logCount} associated {$logText}";
        }
        
        return 'Cannot delete this exercise';
    }

    /**
     * Check if exercise is eligible for merge (simplified version of index logic)
     */
    private function isExerciseMergeEligible(Exercise $exercise): bool
    {
        // Only user exercises can be merged
        if ($exercise->isGlobal()) {
            return false;
        }

        // Check if there are compatible global exercises for merging
        $globalExercises = Exercise::onlyGlobal()->get();
        
        foreach ($globalExercises as $global) {
            if ($exercise->isCompatibleForMerge($global)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exercise $exercise)
    {
        $this->authorize('update', $exercise);
        
        $this->updateExerciseAction->execute($request, $exercise, Auth::user());

        return redirect()->route('exercises.edit', $exercise)->with('success', 'Exercise updated successfully.');
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
     * Promote a user exercise to global exercise.
     */
    public function promote(Exercise $exercise)
    {
        $this->authorize('promoteToGlobal', $exercise);
        
        if ($exercise->isGlobal()) {
            return back()->withErrors(['error' => "Exercise '{$exercise->title}' is already global."]);
        }

        // Check if a global exercise with the same name already exists
        if (Exercise::global()->where('title', $exercise->title)->exists()) {
            return back()->withErrors(['error' => "A global exercise with the name '{$exercise->title}' already exists."]);
        }

        $exercise->update(['user_id' => null]);

        return redirect()->route('exercises.edit', $exercise)
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

        return redirect()->route('exercises.edit', $exercise)
            ->with('success', "Exercise '{$exercise->title}' unpromoted to personal exercise successfully.");
    }

    public function showLogs(Request $request, Exercise $exercise)
    {
        // Only allow viewing logs for exercises available to the user
        $availableExercise = Exercise::availableToUser()->find($exercise->id);
        if (!$availableExercise) {
            abort(403, 'Unauthorized action.');
        }
        
        $components = $this->exercisePageService->generatePage(
            $exercise,
            Auth::id(),
            'history', // Default to history tab for exercises/{id}/logs
            $request->query('from'),
            $request->query('date'),
            [] // No redirect params for this context
        );
        
        return view('mobile-entry.flexible', ['data' => ['components' => $components]]);
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

        try {
            $result = $this->mergeExerciseAction->execute($request, $exercise, Auth::user());
            
            return redirect()->route('exercises.index')
                ->with('success', $result['successMessage']);
                
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Merge failed: ' . $e->getMessage()]);
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