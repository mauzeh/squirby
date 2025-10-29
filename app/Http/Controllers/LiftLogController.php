<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;

use App\Services\ExerciseService;
use App\Presenters\LiftLogTablePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\TrainingProgressionService;


class LiftLogController extends Controller
{
    protected $exerciseService;
    protected $liftLogTablePresenter;

    public function __construct(ExerciseService $exerciseService, LiftLogTablePresenter $liftLogTablePresenter)
    {
        $this->exerciseService = $exerciseService;
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
        $this->preloadBodyweightMeasurements($liftLogs, $userId);

        $exercises = Exercise::availableToUser()->orderBy('title', 'asc')->get();
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

        if ($request->input('redirect_to') === 'mobile-entry') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'), // Assuming program_id is passed from the form
            ];
            return redirect()->route('mobile-entry.lifts', $redirectParams)->with('success', 'Lift log created successfully.');
        } elseif ($request->input('redirect_to') === 'mobile-entry-lifts') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'),
            ];
            
            return redirect()->route('mobile-entry.lifts', $redirectParams)->with('success', 'Lift log created successfully.');
        } else {
            return redirect()->route('exercises.show-logs', ['exercise' => $liftLog->exercise_id])->with('success', 'Lift log created successfully.');
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
        $liftLog->delete();

        if (request()->input('redirect_to') === 'mobile-entry') {
            return redirect()->route('mobile-entry.lifts', ['date' => request()->input('date')])->with('success', 'Lift log deleted successfully.');
        } elseif (request()->input('redirect_to') === 'mobile-entry-lifts') {
            return redirect()->route('mobile-entry.lifts', ['date' => request()->input('date')])->with('success', 'Lift log deleted successfully.');
        }

        return redirect()->route('lift-logs.index')->with('success', 'Lift log deleted successfully.');
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

        LiftLog::destroy($validated['lift_log_ids']);

        return redirect()->route('lift-logs.index')->with('success', 'Selected lift logs deleted successfully!');
    }





    /**
     * Preload bodyweight measurements to avoid N+1 queries in OneRepMaxCalculatorService
     */
    private function preloadBodyweightMeasurements($liftLogs, $userId)
    {
        // Get all unique dates from lift logs
        $dates = $liftLogs->pluck('logged_at')->map(function($date) {
            return $date->toDateString();
        })->unique()->values();

        if ($dates->isEmpty()) {
            return;
        }

        // Get all bodyweight measurements up to the latest date
        $latestDate = $dates->max();
        $bodyweightMeasurements = \App\Models\BodyLog::where('user_id', $userId)
            ->whereHas('measurementType', function ($query) {
                $query->where('name', 'Bodyweight');
            })
            ->whereDate('logged_at', '<=', $latestDate)
            ->orderBy('logged_at', 'desc')
            ->get()
            ->keyBy(function($measurement) {
                return $measurement->logged_at->toDateString();
            });

        // Cache bodyweight measurements on each lift log to avoid repeated queries
        foreach ($liftLogs as $liftLog) {
            $logDate = $liftLog->logged_at->toDateString();
            
            // Find the most recent bodyweight measurement on or before this date
            $bodyweightMeasurement = null;
            foreach ($bodyweightMeasurements as $measurementDate => $measurement) {
                if ($measurementDate <= $logDate) {
                    $bodyweightMeasurement = $measurement;
                    break;
                }
            }
            
            // Cache the bodyweight value on the lift log
            $liftLog->cached_bodyweight = $bodyweightMeasurement ? $bodyweightMeasurement->value : 0;
        }
    }
}