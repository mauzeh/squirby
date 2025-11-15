<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\MobileLiftForm;

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


class LiftLogController extends Controller
{
    protected $exerciseService;
    protected $liftLogTablePresenter;
    protected $redirectService;

    public function __construct(
        ExerciseService $exerciseService,
        LiftLogTablePresenter $liftLogTablePresenter,
        RedirectService $redirectService
    ) {
        $this->exerciseService = $exerciseService;
        $this->liftLogTablePresenter = $liftLogTablePresenter;
        $this->redirectService = $redirectService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = auth()->id();
        
        // Eager load all necessary relationships with selective fields to prevent N+1 queries
        $liftLogs = LiftLog::with([
            'exercise:id,title,exercise_type',
            'exercise.aliases' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            },
            'liftSets:id,lift_log_id,weight,reps,band_color'
        ])
        ->select('id', 'exercise_id', 'user_id', 'logged_at', 'comments')
        ->where('user_id', $userId)
        ->orderBy('logged_at', 'desc') // Most recent first
        ->get();

        // Build table using component system
        $tableBuilder = \App\Services\ComponentBuilder::table();
        $isAdmin = auth()->user()->hasRole('Admin');
        
        foreach ($liftLogs as $liftLog) {
            $strategy = $liftLog->exercise->getTypeStrategy();
            $displayData = $strategy->formatMobileSummaryDisplay($liftLog);
            $dateBadge = $this->liftLogTablePresenter->getDateBadge($liftLog);
            $displayName = $liftLog->exercise->aliases->isNotEmpty() 
                ? $liftLog->exercise->aliases->first()->alias_name 
                : $liftLog->exercise->title;
            
            $rowBuilder = $tableBuilder->row(
                $liftLog->id,
                $displayName,
                $liftLog->comments,
                null
            )
            ->checkbox($isAdmin)
            ->badge($dateBadge['text'], $dateBadge['color'])
            ->badge($displayData['repsSets'], 'neutral');
            
            // Add weight badge if applicable
            if ($displayData['showWeight']) {
                $rowBuilder->badge($displayData['weight'], 'dark', true);
            }
            
            $rowBuilder
                ->linkAction('fa-chart-line', route('exercises.show-logs', $liftLog->exercise), 'View logs', 'btn-info-circle')
                ->linkAction('fa-pencil', route('lift-logs.edit', $liftLog), 'Edit', 'btn-transparent')
                ->compact()
                ->wrapActions()
                ->wrapText()
                ->add();
        }
        
        $tableBuilder
            ->emptyMessage('No lift logs found. Add one to get started!')
            ->confirmMessage('deleteItem', 'Are you sure you want to delete this lift log?')
            ->ariaLabel('Lift logs')
            ->spacedRows();

        $components = [$tableBuilder->build()];
        
        // Only add bulk selection controls for admins
        if ($isAdmin) {
            array_unshift($components, \App\Services\ComponentBuilder::selectAllControl('select-all-lift-logs', 'Select All')->build());
            
            $components[] = \App\Services\ComponentBuilder::bulkActionForm(
                'bulk-delete-lift-logs',
                route('lift-logs.destroy-selected'),
                'Delete Selected'
            )
            ->confirmMessage('Are you sure you want to delete :count lift log(s)?')
            ->checkboxSelector('.template-checkbox')
            ->inputName('lift_log_ids')
            ->ariaLabel('Delete selected lift logs')
            ->build();
        }

        $data = ['components' => $components];

        return view('lift-logs.index-flexible', compact('data'));
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

        // Delete the corresponding MobileLiftForm if it exists
        if ($request->has('mobile_lift_form_id')) {
            MobileLiftForm::where('id', $request->input('mobile_lift_form_id'))
                ->where('user_id', auth()->id())
                ->delete();
        }

        // Generate a celebratory success message with workout details
        $successMessage = $this->generateSuccessMessage($exercise, $request->input('weight'), $reps, $rounds, $request->input('band_color'));

        return $this->redirectService->getRedirect(
            'lift_logs',
            'store',
            $request,
            [
                'submitted_lift_log_id' => $liftLog->id,
                'exercise' => $liftLog->exercise_id,
            ],
            $successMessage
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Load aliases for the lift log's exercise
        $liftLog->load(['exercise.aliases' => function ($query) {
            $query->where('user_id', auth()->id());
        }]);
        
        $exercises = Exercise::availableToUser()
            ->with(['aliases' => function ($query) {
                $query->where('user_id', auth()->id());
            }])
            ->orderBy('title', 'asc')
            ->get();
        return view('lift-logs.edit', compact('liftLog', 'exercises'));
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

    public function destroySelected(Request $request)
    {
        // Only admins can bulk delete
        if (!auth()->user()->hasRole('Admin')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'lift_log_ids' => 'required|array',
            'lift_log_ids.*' => 'exists:lift_logs,id',
        ]);

        $liftLogs = LiftLog::whereIn('id', $validated['lift_log_ids'])->get();

        foreach ($liftLogs as $liftLog) {
            if ($liftLog->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        $count = count($validated['lift_log_ids']);
        
        LiftLog::destroy($validated['lift_log_ids']);

        $message = $count === 1 
            ? config('mobile_entry_messages.success.bulk_deleted_single')
            : str_replace(':count', $count, config('mobile_entry_messages.success.bulk_deleted_multiple'));

        return redirect()->route('lift-logs.index')->with('success', $message);
    }

    /**
     * Generate a celebratory success message with workout details
     * 
     * @param \App\Models\Exercise $exercise
     * @param float|null $weight
     * @param int $reps
     * @param int $rounds
     * @param string|null $bandColor
     * @return string
     */
    private function generateSuccessMessage($exercise, $weight, $reps, $rounds, $bandColor = null)
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
        return str_replace([':exercise', ':details'], [$exerciseTitle, $workoutDescription], $randomTemplate);
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

}