<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;

use App\Services\ExerciseService;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;
use App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException;
use App\Presenters\LiftLogTablePresenter;
use App\Services\RedirectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\TrainingProgressionService;
use App\Events\LiftLogged;


class LiftLogController extends Controller
{
    protected $exerciseService;
    protected $liftLogTablePresenter;
    protected $redirectService;
    protected $liftLogTableRowBuilder;

    public function __construct(
        ExerciseService $exerciseService,
        LiftLogTablePresenter $liftLogTablePresenter,
        RedirectService $redirectService,
        \App\Services\LiftLogTableRowBuilder $liftLogTableRowBuilder
    ) {
        $this->exerciseService = $exerciseService;
        $this->liftLogTablePresenter = $liftLogTablePresenter;
        $this->redirectService = $redirectService;
        $this->liftLogTableRowBuilder = $liftLogTableRowBuilder;
    }
    /**
     * Show the form for creating a new lift log entry
     */
    public function create(Request $request)
    {
        $exerciseId = $request->input('exercise_id');
        $date = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date'))
            : \Carbon\Carbon::today();
        
        if (!$exerciseId) {
            return redirect()->route('mobile-entry.lifts')
                ->with('error', 'No exercise specified.');
        }
        
        // Capture redirect parameters
        $redirectParams = [];
        if ($request->has('redirect_to')) {
            $redirectParams['redirect_to'] = $request->input('redirect_to');
        }
        if ($request->has('workout_id')) {
            $redirectParams['workout_id'] = $request->input('workout_id');
        }
        
        // Determine back URL based on redirect parameters
        $redirectTo = $request->input('redirect_to');
        if ($redirectTo === 'workouts') {
            $workoutId = $request->input('workout_id');
            $backUrl = route('workouts.index', $workoutId ? ['workout_id' => $workoutId] : []);
        } elseif ($redirectTo === 'mobile-entry-lifts') {
            $backUrl = route('mobile-entry.lifts', ['date' => $date->toDateString()]);
        } elseif ($redirectTo === 'exercises-logs') {
            $backUrl = route('exercises.show-logs', ['exercise' => $exerciseId]);
        } else {
            // Default to mobile-entry lifts
            $backUrl = route('mobile-entry.lifts', ['date' => $date->toDateString()]);
        }
        
        // Generate the page using the service
        $liftLogService = app(\App\Services\MobileEntry\LiftLogService::class);
        
        try {
            $components = $liftLogService->generateCreatePage(
                $exerciseId,
                auth()->id(),
                $date,
                $backUrl,
                $redirectParams
            );
            
            $data = [
                'components' => $components,
                'autoscroll' => true
            ];
            
            return view('mobile-entry.flexible', compact('data'));
        } catch (\Exception $e) {
            return redirect()->route('mobile-entry.lifts')
                ->with('error', 'Exercise not found or not accessible.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = auth()->id();
        
        // Get all exercises that the user has logged, with their aliases
        $exercises = Exercise::whereHas('liftLogs', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with([
            'aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            },
            'user' // Load user relationship for badge display
        ])
        ->orderBy('title', 'asc')
        ->get();

        // Build components array
        $components = [
            \App\Services\ComponentBuilder::title(
                'Metrics',
                'Select an exercise to view your training history, personal records, and 1RM calculator.'
            )->build(),
        ];
        
        // Add success/error messages if present
        if ($sessionMessages = \App\Services\ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Build exercise list
        if ($exercises->isEmpty()) {
            $components[] = \App\Services\ComponentBuilder::messages()
                ->add('info', config('mobile_entry_messages.empty_states.metrics_getting_started'))
                ->build();
            $components[] = \App\Services\ComponentBuilder::button('Log Now')
                ->asLink(route('mobile-entry.lifts', ['expand_selection' => true]))
                ->build();
        } else {
            $aliasService = app(\App\Services\ExerciseAliasService::class);
            $listBuilder = \App\Services\ComponentBuilder::itemList();
            
            // Get lift log counts for each exercise
            $exerciseLogCounts = \App\Models\LiftLog::where('user_id', $userId)
                ->whereIn('exercise_id', $exercises->pluck('id'))
                ->select('exercise_id', \DB::raw('count(*) as log_count'))
                ->groupBy('exercise_id')
                ->pluck('log_count', 'exercise_id');
            
            foreach ($exercises as $exercise) {
                $displayName = $aliasService->getDisplayName($exercise, auth()->user());
                $logCount = $exerciseLogCounts[$exercise->id] ?? 0;
                $typeLabel = $logCount . ' ' . ($logCount === 1 ? 'log' : 'logs');
                
                $listBuilder->item(
                    (string) $exercise->id,
                    $displayName,
                    route('exercises.show-logs', ['exercise' => $exercise, 'from' => 'lift-logs-index']),
                    $typeLabel,
                    'exercise-history'
                );
            }
            
            $components[] = $listBuilder
                ->filterPlaceholder('Tap to search...')
                ->noResultsMessage('No exercises found.')
                ->initialState('expanded')
                ->showCancelButton(false)
                ->restrictHeight(false)
                ->build();
        }

        $data = ['components' => $components];

        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $exercise = Exercise::find($request->input('exercise_id'));
        $user = auth()->user();

        // Base validation rules
        $rules = [
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'nullable|date_format:H:i',
            'reps' => 'required|integer|min:1',
            'rounds' => 'required|integer|min:1',
        ];

        // Use exercise type strategy for validation rules
        if ($exercise) {
            $exerciseTypeStrategy = ExerciseTypeFactory::create($exercise);
            $typeSpecificRules = $exerciseTypeStrategy->getValidationRules($user);
            $rules = array_merge($rules, $typeSpecificRules);
        }

        $request->validate($rules);

        $loggedAtDate = Carbon::parse($request->input('date'));
        
        // If no time provided (mobile entry), use current time but ensure it stays within the selected date
        if ($request->has('logged_at') && $request->input('logged_at')) {
            $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));
        } else {
            // Use current time, but if we're logging for a different date, use a safe default time
            $currentTime = now();
            if ($loggedAtDate->toDateString() === $currentTime->toDateString()) {
                // Same date - use current time
                $loggedAt = $loggedAtDate->setTime($currentTime->hour, $currentTime->minute);
            } else {
                // Different date - use a safe default time (12:00 PM) to avoid date boundary issues
                $loggedAt = $loggedAtDate->setTime(12, 0);
            }
        }
        
        // Round time to nearest 15-minute interval, but ensure we don't cross date boundaries
        $minutes = $loggedAt->minute;
        $remainder = $minutes % 15;
        if ($remainder !== 0) {
            $newLoggedAt = $loggedAt->copy()->addMinutes(15 - $remainder);
            // Only apply rounding if it doesn't change the date
            if ($newLoggedAt->toDateString() === $loggedAtDate->toDateString()) {
                $loggedAt = $newLoggedAt;
            } else {
                // If rounding would cross date boundary, round down instead
                $loggedAt = $loggedAt->subMinutes($remainder);
            }
        }

        $liftLog = LiftLog::create([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
            'user_id' => auth()->id(),
        ]);

        LiftLogged::dispatch($liftLog);

        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        // Use exercise type strategy to process lift data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($exercise);
        
        try {
            $liftData = $exerciseTypeStrategy->processLiftData([
                'weight' => $request->input('weight'),
                'band_color' => $request->input('band_color'),
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        } catch (InvalidExerciseDataException $e) {
            // Delete the created lift log since data processing failed
            $liftLog->delete();
            return back()->withErrors(['exercise_data' => $e->getMessage()])->withInput();
        }

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $liftData['weight'] ?? 0,
                'reps' => $liftData['reps'],
                'notes' => $liftData['notes'],
                'band_color' => $liftData['band_color'],
            ]);
        }

        // Check if this is a PR
        $isPR = $this->checkIfPR($liftLog, $exercise, auth()->id());

        // Note: MobileLiftForm is deprecated - we now use direct lift-logs/create flow

        // Generate a celebratory success message with workout details
        $successMessage = $this->generateSuccessMessage($exercise, $request->input('weight'), $reps, $rounds, $request->input('band_color'), $isPR);

        return $this->redirectService->getRedirect(
            'lift_logs',
            'store',
            $request,
            [
                'submitted_lift_log_id' => $liftLog->id,
                'exercise' => $liftLog->exercise_id,
            ],
            $successMessage
        )->with('is_pr', $isPR);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LiftLog $liftLog, Request $request)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Capture redirect parameters from the request
        $redirectParams = [];
        if ($request->has('redirect_to')) {
            $redirectParams['redirect_to'] = $request->input('redirect_to');
        }
        
        // Generate edit form component using the service
        $liftLogService = app(\App\Services\MobileEntry\LiftLogService::class);
        $formComponent = $liftLogService->generateEditFormComponent($liftLog, auth()->id(), $redirectParams);
        
        $data = [
            'components' => [$formComponent],
            'autoscroll' => true
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $exercise = Exercise::find($request->input('exercise_id'));
        $user = auth()->user();

        // Base validation rules
        $rules = [
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
            'reps' => 'required|integer|min:1',
            'rounds' => 'required|integer|min:1',
        ];

        // Use exercise type strategy for validation rules
        if ($exercise) {
            $exerciseTypeStrategy = ExerciseTypeFactory::create($exercise);
            $typeSpecificRules = $exerciseTypeStrategy->getValidationRules($user);
            $rules = array_merge($rules, $typeSpecificRules);
        }

        $request->validate($rules);

        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));
        
        // Round time to nearest 15-minute interval, but ensure we don't cross date boundaries
        $minutes = $loggedAt->minute;
        $remainder = $minutes % 15;
        if ($remainder !== 0) {
            $newLoggedAt = $loggedAt->copy()->addMinutes(15 - $remainder);
            // Only apply rounding if it doesn't change the date
            if ($newLoggedAt->toDateString() === $loggedAtDate->toDateString()) {
                $loggedAt = $newLoggedAt;
            } else {
                // If rounding would cross date boundary, round down instead
                $loggedAt = $loggedAt->subMinutes($remainder);
            }
        }

        $liftLog->update([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
        ]);

        // Delete existing lift sets
        $liftLog->liftSets()->delete();

        // Create new lift sets
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        // Use exercise type strategy to process lift data
        $exerciseTypeStrategy = ExerciseTypeFactory::create($exercise);
        
        try {
            $liftData = $exerciseTypeStrategy->processLiftData([
                'weight' => $request->input('weight'),
                'band_color' => $request->input('band_color'),
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        } catch (InvalidExerciseDataException $e) {
            return back()->withErrors(['exercise_data' => $e->getMessage()])->withInput();
        }

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $liftData['weight'] ?? 0,
                'reps' => $liftData['reps'],
                'notes' => $liftData['notes'],
                'band_color' => $liftData['band_color'],
            ]);
        }

        return $this->redirectService->getRedirect(
            'lift_logs',
            'update',
            $request,
            [
                'submitted_lift_log_id' => $liftLog->id,
                'exercise' => $liftLog->exercise_id,
            ],
            'Lift log updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Check if we're in mobile-entry context
        $isMobileEntry = in_array(request()->input('redirect_to'), ['mobile-entry', 'mobile-entry-lifts', 'workouts']);
        
        // Generate a specific deletion message before deleting
        $deletionMessage = $this->generateDeletionMessage($liftLog, $isMobileEntry);
        
        $liftLog->delete();

        return $this->redirectService->getRedirect(
            'lift_logs',
            'destroy',
            request(),
            [],
            $deletionMessage
        );
    }

    /**
     * Generate a celebratory success message with workout details
     * 
     * @param \App\Models\Exercise $exercise
     * @param float|null $weight
     * @param int $reps
     * @param int $rounds
     * @param string|null $bandColor
     * @param bool $isPR
     * @return string
     */
    private function generateSuccessMessage($exercise, $weight, $reps, $rounds, $bandColor = null, $isPR = false)
    {
        // Get display name (alias if exists, otherwise title)
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $exerciseTitle = $aliasService->getDisplayName($exercise, auth()->user());
        
        // Use strategy pattern to format workout description
        $strategy = $exercise->getTypeStrategy();
        $workoutDescription = $strategy->formatSuccessMessageDescription($weight, $reps, $rounds, $bandColor);
        
        // Get celebratory messages from config and replace placeholders
        $celebrationTemplates = config('mobile_entry_messages.success.lift_logged');
        $randomTemplate = $celebrationTemplates[array_rand($celebrationTemplates)];
        
        // Replace placeholders in the template
        $message = str_replace([':exercise', ':details'], [$exerciseTitle, $workoutDescription], $randomTemplate);
        
        // Add PR indicator if this is a personal record
        if ($isPR) {
            $message .= ' 🎉 NEW PR!';
        }
        
        return $message;
    }

    /**
     * Generate a simple deletion message with exercise name
     * 
     * @param \App\Models\LiftLog $liftLog
     * @param bool $isMobileEntry Whether this deletion is happening in mobile-entry context
     * @return string
     */
    private function generateDeletionMessage($liftLog, $isMobileEntry = false)
    {
        $exercise = $liftLog->exercise;
        
        // Get display name (alias if exists, otherwise title)
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $exerciseTitle = $aliasService->getDisplayName($exercise, auth()->user());
        
        // Add helpful reminder for mobile-entry context
        if ($isMobileEntry) {
            return str_replace(':exercise', $exerciseTitle, config('mobile_entry_messages.success.lift_deleted_mobile'));
        }
        
        return str_replace(':exercise', $exerciseTitle, config('mobile_entry_messages.success.lift_deleted'));
    }

    /**
     * Check if the logged lift is a personal record
     * Uses the same 1RM-based logic as the table row builder for consistency
     * 
     * @param \App\Models\LiftLog $liftLog
     * @param \App\Models\Exercise $exercise
     * @param int $userId
     * @return bool
     */
    private function checkIfPR($liftLog, $exercise, $userId)
    {
        $strategy = $exercise->getTypeStrategy();
        
        // Only exercises that support 1RM calculation can have PRs
        if (!$strategy->canCalculate1RM()) {
            return false;
        }
        
        // Get all previous lift logs for this exercise (before this one)
        $previousLogs = LiftLog::where('exercise_id', $exercise->id)
            ->where('user_id', $userId)
            ->where('logged_at', '<', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        // Calculate the best estimated 1RM from the current log
        $currentBest1RM = 0;
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                try {
                    $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $liftLog);
                    if ($estimated1RM > $currentBest1RM) {
                        $currentBest1RM = $estimated1RM;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // If this is the first log, it's a PR
        if ($previousLogs->isEmpty()) {
            return $currentBest1RM > 0;
        }
        
        // Find the best estimated 1RM from all previous logs
        $previousBest1RM = 0;
        foreach ($previousLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $previousBest1RM) {
                            $previousBest1RM = $estimated1RM;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // This is a PR if the current 1RM beats the previous best
        $tolerance = 0.1; // Small tolerance for floating point comparison
        return $currentBest1RM > $previousBest1RM + $tolerance;
    }

}