<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;

use App\Services\ExerciseService;
use App\Services\LiftLogService;
use App\Presenters\LiftLogTablePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\TrainingProgressionService;


class LiftLogController extends Controller
{
    protected $exerciseService;
    protected $liftLogService;
    protected $liftLogTablePresenter;

    public function __construct(ExerciseService $exerciseService, LiftLogService $liftLogService, LiftLogTablePresenter $liftLogTablePresenter)
    {
        $this->exerciseService = $exerciseService;
        $this->liftLogService = $liftLogService;
        $this->liftLogTablePresenter = $liftLogTablePresenter;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = auth()->id();
        
        // Eager load all necessary relationships with selective fields to prevent N+1 queries
        $liftLogs = LiftLog::with([
            'exercise:id,title,band_type,is_bodyweight', 
            'liftSets:id,lift_log_id,weight,reps,band_color',
            'user:id' // Load user for potential bodyweight calculations
        ])
        ->select('id', 'exercise_id', 'user_id', 'logged_at', 'comments')
        ->where('user_id', $userId)
        ->orderBy('logged_at', 'asc')
        ->get();

        // Pre-fetch bodyweight measurements for all dates to avoid N+1 queries in OneRepMaxCalculatorService
        $this->liftLogService->preloadBodyweightMeasurements($liftLogs, $userId);

        $exercises = $this->exerciseService->getExercisesWithLogs();
        $displayExercises = $this->exerciseService->getDisplayExercises(5);

        // Format data using presenter
        $tableData = $this->liftLogTablePresenter->formatForTable($liftLogs, false);

        return view('lift-logs.index', compact('displayExercises', 'exercises') + $tableData);
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $exercise = Exercise::find($request->input('exercise_id'));
        $user = auth()->user();

        $rules = [
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'nullable|date_format:H:i',
            'reps' => 'required|integer|min:1',
            'rounds' => 'required|integer|min:1',
        ];

        if ($exercise && $exercise->band_type) {
            $rules['band_color'] = 'required|string';
        } else {
            // For bodyweight exercises, only require weight if user has show_extra_weight enabled
            if ($exercise && $exercise->is_bodyweight && !$user->shouldShowExtraWeight()) {
                $rules['weight'] = 'nullable|numeric';
            } else {
                $rules['weight'] = 'required|numeric';
            }
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

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $exercise->band_type ? 0 : ($request->input('weight') ?? 0),
                'reps' => $reps,
                'notes' => $request->input('comments'),
                'band_color' => $exercise->band_type ? $request->input('band_color') : null,
            ]);
        }

        // Generate a celebratory success message with workout details
        $successMessage = $this->generateSuccessMessage($exercise, $request->input('weight'), $reps, $rounds, $request->input('band_color'));

        if ($request->input('redirect_to') === 'mobile-entry') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'), // Assuming program_id is passed from the form
            ];
            return redirect()->route('mobile-entry.lifts', $redirectParams)->with('success', $successMessage);
        } elseif ($request->input('redirect_to') === 'mobile-entry-lifts') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'),
            ];
            
            return redirect()->route('mobile-entry.lifts', $redirectParams)->with('success', $successMessage);
        } else {
            return redirect()->route('exercises.show-logs', ['exercise' => $liftLog->exercise_id])->with('success', $successMessage);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $exercises = Exercise::availableToUser()->orderBy('title', 'asc')->get();
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

        $rules = [
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
            'reps' => 'required|integer|min:1',
            'rounds' => 'required|integer|min:1',
        ];

        if ($exercise && $exercise->band_type) {
            $rules['band_color'] = 'required|string';
        } else {
            // For bodyweight exercises, only require weight if user has show_extra_weight enabled
            if ($exercise && $exercise->is_bodyweight && !$user->shouldShowExtraWeight()) {
                $rules['weight'] = 'nullable|numeric';
            } else {
                $rules['weight'] = 'required|numeric';
            }
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

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $exercise->band_type ? 0 : ($request->input('weight') ?? 0),
                'reps' => $reps,
                'notes' => $request->input('comments'),
                'band_color' => $exercise->band_type ? $request->input('band_color') : null,
            ]);
        }

        if ($request->input('redirect_to') === 'mobile-entry') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'), // Assuming program_id is passed from the form
            ];
            return redirect()->route('mobile-entry.lifts', $redirectParams)->with('success', 'Lift log updated successfully.');
        } elseif ($request->input('redirect_to') === 'mobile-entry-lifts') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'),
            ];
            return redirect()->route('mobile-entry.lifts', $redirectParams)->with('success', 'Lift log updated successfully.');
        } else {
            return redirect()->route('exercises.show-logs', ['exercise' => $liftLog->exercise_id])->with('success', 'Lift log updated successfully.');
        }
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
        $isMobileEntry = in_array(request()->input('redirect_to'), ['mobile-entry', 'mobile-entry-lifts']);
        
        // Generate a specific deletion message before deleting
        $deletionMessage = $this->generateDeletionMessage($liftLog, $isMobileEntry);
        
        $liftLog->delete();

        if (request()->input('redirect_to') === 'mobile-entry') {
            return redirect()->route('mobile-entry.lifts', ['date' => request()->input('date')])->with('success', $deletionMessage);
        } elseif (request()->input('redirect_to') === 'mobile-entry-lifts') {
            return redirect()->route('mobile-entry.lifts', ['date' => request()->input('date')])->with('success', $deletionMessage);
        }

        return redirect()->route('lift-logs.index')->with('success', $deletionMessage);
    }

    public function destroySelected(Request $request)
    {
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
        $exerciseTitle = $exercise->title;
        
        // Build the workout description
        if ($exercise->band_type && $bandColor) {
            // Band exercise
            $workoutDescription = ucfirst($bandColor) . ' band × ' . $reps . ' reps × ' . $rounds . ' sets';
        } elseif ($exercise->is_bodyweight) {
            // Bodyweight exercise
            if ($weight > 0) {
                $workoutDescription = '+' . $weight . ' lbs × ' . $reps . ' reps × ' . $rounds . ' sets';
            } else {
                $workoutDescription = $reps . ' reps × ' . $rounds . ' sets';
            }
        } else {
            // Regular weighted exercise
            $workoutDescription = $weight . ' lbs × ' . $reps . ' reps × ' . $rounds . ' sets';
        }
        
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
        $exerciseTitle = $exercise->title;
        
        // Add helpful reminder for mobile-entry context
        if ($isMobileEntry) {
            return str_replace(':exercise', $exerciseTitle, config('mobile_entry_messages.success.lift_deleted_mobile'));
        }
        
        return str_replace(':exercise', $exerciseTitle, config('mobile_entry_messages.success.lift_deleted'));
    }

}