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
        private ExerciseFormService $exerciseFormService
    ) {}

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
        
        // Build components for flexible UI
        $components = $this->buildExerciseIndexComponents($exercises, $mergeEligibleIds, $currentUser);
        $data = ['components' => $components];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Build components for the exercise index page.
     */
    private function buildExerciseIndexComponents($exercises, $mergeEligibleIds, $currentUser)
    {
        $components = [];
        
        // Title
        $components[] = \App\Services\ComponentBuilder::title('Exercises')->build();
        
        // Messages from session
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Add Exercise button
        $components[] = \App\Services\ComponentBuilder::button('Add Exercise')
            ->asLink(route('exercises.create'))
            ->build();
        
        // Build table
        if ($exercises->isEmpty()) {
            $components[] = \App\Services\ComponentBuilder::messages()
                ->info('No exercises found. Add one to get started!')
                ->build();
        } else {
            $tableBuilder = \App\Services\ComponentBuilder::table()
                ->confirmMessage('deleteItem', 'Are you sure you want to delete this exercise?')
                ->ariaLabel('Exercises list')
                ->spacedRows();
            
            foreach ($exercises as $exercise) {
                // Get display name (alias if exists, otherwise title)
                $aliasService = app(\App\Services\ExerciseAliasService::class);
                $displayName = $aliasService->getDisplayName($exercise, auth()->user());
                
                // Determine badge type and text
                if ($exercise->isGlobal()) {
                    $badgeText = 'Everyone';
                    $badgeType = 'success';
                } else {
                    $badgeText = $exercise->user_id === auth()->id() ? 'You' : $exercise->user->name;
                    $badgeType = 'warning';
                }
                
                // Build exercise type display
                $exerciseType = ucfirst(str_replace('_', ' ', $exercise->exercise_type));
                
                $rowBuilder = $tableBuilder->row(
                    $exercise->id,
                    $displayName
                )
                ->badge($badgeText, $badgeType)
                ->badge($exerciseType, 'info')
                ->compact();
                
                // Add edit action if user can edit
                if ($exercise->canBeEditedBy($currentUser)) {
                    $rowBuilder->linkAction('fa-pencil', route('exercises.edit', $exercise), 'Edit', 'btn-secondary');
                }
                
                // Add admin actions
                if ($currentUser->hasRole('Admin')) {
                    if (!$exercise->isGlobal()) {
                        // Promote to global
                        $rowBuilder->formAction(
                            'fa-globe', 
                            route('exercises.promote', $exercise), 
                            'POST', 
                            [], 
                            'Promote to global', 
                            'btn-success', 
                            'Are you sure you want to promote this exercise to global status?'
                        );
                    } else {
                        // Unpromote to personal
                        $rowBuilder->formAction(
                            'fa-user', 
                            route('exercises.unpromote', $exercise), 
                            'POST', 
                            [], 
                            'Unpromote to personal exercise', 
                            'btn-warning', 
                            'Are you sure you want to unpromote this exercise back to personal status? This will only work if no other users have workout logs with this exercise.'
                        );
                    }
                    
                    // Merge action if eligible
                    if ($mergeEligibleIds->contains($exercise->id)) {
                        $rowBuilder->linkAction(
                            'fa-code-branch', 
                            route('exercises.show-merge', $exercise), 
                            'Merge exercise', 
                            'btn-info'
                        );
                    }
                }
                
                // Add delete action if user can delete
                if ($exercise->canBeDeletedBy($currentUser)) {
                    $rowBuilder->formAction(
                        'fa-trash', 
                        route('exercises.destroy', $exercise), 
                        'DELETE', 
                        [], 
                        'Delete', 
                        'btn-danger', 
                        'Are you sure you want to delete this exercise?'
                    );
                }
                
                $rowBuilder->add();
            }
            
            $components[] = $tableBuilder->build();
        }
        
        return $components;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $exercise = new Exercise();
        $user = auth()->user();
        
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
        $exercise = $this->createExerciseAction->execute($request, auth()->user());
        
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
        $user = auth()->user();
        
        $components = [
            \App\Services\ComponentBuilder::title('Edit Exercise')->build(),
        ];

        // Add session messages if any
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }

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
                    'Promote to Global',
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
                    'Unpromote to Personal',
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
        if ($exercise->canBeDeletedBy($currentUser)) {
            $quickActions->formAction(
                'fa-trash',
                route('exercises.destroy', $exercise),
                'DELETE',
                [],
                'Delete',
                'btn-danger',
                'Are you sure you want to delete this exercise?'
            );
        } else {
            // Show disabled delete button with reason
            $reason = $this->getDeleteDisabledReason($exercise, $currentUser);
            $quickActions->disabledAction(
                'fa-trash',
                'Delete',
                'btn-danger',
                $reason
            );
        }

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
        
        $this->updateExerciseAction->execute($request, $exercise, auth()->user());

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
        
        // Determine back URL based on where user came from
        $from = $request->query('from');
        $date = $request->query('date');
        
        if ($from === 'mobile-entry-lifts') {
            $backUrl = route('mobile-entry.lifts', $date ? ['date' => $date] : []);
        } elseif ($from === 'lift-logs-index') {
            $backUrl = route('lift-logs.index');
        } else {
            // Default to lift-logs index if no 'from' parameter
            $backUrl = route('lift-logs.index');
        }
        
        // Title with back button
        $components[] = \App\Services\ComponentBuilder::title($displayName)
            ->backButton('fa-arrow-left', $backUrl, 'Back')
            ->build();
        
        // Messages from session
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Show friendly tip if no lift logs exist
        if ($liftLogs->isEmpty()) {
            $components[] = \App\Services\ComponentBuilder::messages()
                ->tip('No training data yet. Click "Log now" to record your first workout for this exercise.')
                ->build();
        }
        
        // Log now button - determine redirect based on where user came from
        $redirectTo = $from === 'mobile-entry-lifts' ? 'mobile-entry-lifts' : 'exercises-logs';
        $logNowParams = [
            'exercise_id' => $exercise->id,
            'redirect_to' => $redirectTo
        ];
        
        // Add date parameter if coming from mobile-entry-lifts
        if ($from === 'mobile-entry-lifts' && $date) {
            $logNowParams['date'] = $date;
        }
        
        $components[] = \App\Services\ComponentBuilder::button('Log Now')
            ->asLink(route('lift-logs.create', $logNowParams))
            ->build();
        
        // PR Cards and Calculator Grid (if exercise supports it)
        if ($this->exercisePRService->supportsPRTracking($exercise)) {
            $prData = $this->exercisePRService->getPRData($exercise, auth()->user(), 10);
            $estimated1RM = null;
            
            // Check if we have any actual 1-3 rep PRs
            $hasActualPRs = $prData && (
                ($prData['rep_1'] ?? null) !== null ||
                ($prData['rep_2'] ?? null) !== null ||
                ($prData['rep_3'] ?? null) !== null
            );
            
            // If no actual PRs, get estimated 1RM from best lift
            if (!$hasActualPRs) {
                $estimated1RM = $this->exercisePRService->getEstimated1RM($exercise, auth()->user());
            }
            
            if ($prData || $estimated1RM) {
                // Build PR Cards component only if we have actual PR data
                if ($prData) {
                    $prCardsBuilder = \App\Services\ComponentBuilder::prCards('Heaviest Lifts')
                        ->scrollable(); // Enable horizontal scrolling
                    
                    // Show PRs for 1-10 reps
                    for ($reps = 1; $reps <= 10; $reps++) {
                        $key = "rep_{$reps}";
                        $label = "1 × {$reps}";
                        
                        if (isset($prData[$key]) && $prData[$key] !== null) {
                            $prCardsBuilder->card($label, $prData[$key]['weight'], 'lbs', $prData[$key]['date']);
                        } else {
                            $prCardsBuilder->card($label, null, 'lbs');
                        }
                    }
                    
                    $components[] = $prCardsBuilder->build();
                }
                
                // Build Calculator Grid component
                $calculatorGrid = $this->exercisePRService->getCalculatorGrid(
                    $exercise,
                    $prData ?? [],
                    $estimated1RM
                );
                
                if ($calculatorGrid) {
                    $gridTitle = $calculatorGrid['is_estimated'] 
                        ? '1-Rep Max Percentages (Estimated)' 
                        : '1-Rep Max Percentages';
                    
                    // Add info message if data is estimated
                    if ($calculatorGrid['is_estimated']) {
                        $components[] = \App\Services\ComponentBuilder::messages()
                            ->info('This 1-rep max is estimated based on your previous lifts using a standard formula. For more accurate training percentages, test your actual 1, 2, or 3 rep max.')
                            ->build();
                    }
                    // Add warning if PR data is stale (older than 6 months)
                    elseif ($prData && $this->exercisePRService->isPRDataStale($prData)) {
                        $components[] = \App\Services\ComponentBuilder::messages()
                            ->warning('Your max lift data is over 6 months old. Consider retesting your 1, 2, or 3 rep max to ensure accurate training percentages.')
                            ->build();
                    }
                    
                    $components[] = \App\Services\ComponentBuilder::calculatorGrid($gridTitle)
                        ->columns($calculatorGrid['columns'])
                        ->percentages([100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45])
                        ->rows($calculatorGrid['rows'])
                        ->build();
                }
            }
        }
        
        // Add chart if we have data (only when lift logs exist)
        if ($liftLogs->isNotEmpty() && !empty($chartData['datasets'])) {
            $strategy = $exercise->getTypeStrategy();
            $chartTitle = $strategy->getChartTitle();
            
            // Determine appropriate time scale and format based on data range
            $oldestLog = $liftLogs->last();
            $newestLog = $liftLogs->first();
            $daysDiff = $oldestLog->logged_at->diffInDays($newestLog->logged_at);
            
            // Choose time unit and display format based on data span
            if ($daysDiff > 730) { // More than 2 years
                $timeUnit = 'month';
                $displayFormat = 'MMM yyyy'; // "Jan 2023"
            } elseif ($daysDiff > 365) { // More than 1 year
                $timeUnit = 'month';
                $displayFormat = 'MMM yy'; // "Jan 23"
            } elseif ($daysDiff > 90) { // More than 3 months
                $timeUnit = 'month';
                $displayFormat = 'MMM d'; // "Jan 15"
            } else {
                $timeUnit = 'day';
                $displayFormat = 'MMM d'; // "Jan 15"
            }
            
            $chartBuilder = \App\Services\ComponentBuilder::chart('progressChart', $chartTitle)
                ->type('line')
                ->datasets($chartData['datasets'])
                ->timeScale($timeUnit, $displayFormat)
                ->showLegend()
                ->ariaLabel($exercise->title . ' progress chart')
                ->containerClass('chart-container-styled')
                ->height(300)
                ->noAspectRatio()
                ->labelColors();
            
            // Only use beginAtZero for non-1RM charts
            if ($chartTitle !== '1RM Progress') {
                $chartBuilder->beginAtZero();
            }
            
            $components[] = $chartBuilder->build();
        }
        
        // Build table using shared service (only if we have data)
        if ($liftLogs->isNotEmpty()) {
            $liftLogTableRowBuilder = app(\App\Services\LiftLogTableRowBuilder::class);
            
            $rows = $liftLogTableRowBuilder->buildRows($liftLogs, [
                'showDateBadge' => true,
                'showCheckbox' => false,
                'showViewLogsAction' => false, // Don't show "view logs" when already viewing logs
                'showDeleteAction' => false,
                'redirectContext' => 'exercises-logs', // For edit/delete redirects
            ]);
            
            $tableBuilder = \App\Services\ComponentBuilder::table()
                ->rows($rows)
                ->emptyMessage('No lift logs found for this exercise.')
                ->ariaLabel('Exercise logs')
                ->spacedRows();

            $components[] = $tableBuilder->build();
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

        try {
            $result = $this->mergeExerciseAction->execute($request, $exercise, auth()->user());
            
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