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
use App\Services\RecommendationEngine;

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
            $rules['weight'] = 'required|numeric';
        }

        $request->validate($rules);

        $loggedAtDate = Carbon::parse($request->input('date'));
        
        // If no time provided (mobile entry), use current time
        if ($request->has('logged_at') && $request->input('logged_at')) {
            $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));
        } else {
            $loggedAt = $loggedAtDate->setTime(now()->hour, now()->minute);
        }
        
        // Round time to nearest 15-minute interval
        $minutes = $loggedAt->minute;
        $remainder = $minutes % 15;
        if ($remainder !== 0) {
            $loggedAt->addMinutes(15 - $remainder);
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
                'weight' => $exercise->band_type ? 0 : $request->input('weight'),
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
            return redirect()->route('lift-logs.mobile-entry', $redirectParams)->with('success', 'Lift log created successfully.');
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
            $rules['weight'] = 'required|numeric';
        }

        $request->validate($rules);

        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));
        
        // Round time to nearest 15-minute interval
        $minutes = $loggedAt->minute;
        $remainder = $minutes % 15;
        if ($remainder !== 0) {
            $loggedAt->addMinutes(15 - $remainder);
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
                'weight' => $exercise->band_type ? 0 : $request->input('weight'),
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
            return redirect()->route('lift-logs.mobile-entry', $redirectParams)->with('success', 'Lift log updated successfully.');
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
            return redirect()->route('lift-logs.mobile-entry', ['date' => request()->input('date')])->with('success', 'Lift log deleted successfully.');
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



    public function mobileEntry(Request $request, \App\Services\TrainingProgressionService $trainingProgressionService, RecommendationEngine $recommendationEngine)
    {
        $selectedDate = $request->input('date') ? \Carbon\Carbon::parse($request->input('date')) : \Carbon\Carbon::today();
        $userId = auth()->id();

        // Load programs with exercise relationship in one query
        $programs = \App\Models\Program::with('exercise')
            ->where('user_id', $userId)
            ->whereDate('date', $selectedDate->toDateString())
            ->orderBy('priority')
            ->get();

        // Get all exercise IDs from programs for batch queries
        $programExerciseIds = $programs->pluck('exercise_id')->toArray();

        // Batch fetch last logs for all program exercises using a more efficient approach
        $lastLogs = [];
        if (!empty($programExerciseIds)) {
            // Use a subquery to get the latest log ID for each exercise, then fetch those logs with sets
            $latestLogIds = \DB::table('lift_logs')
                ->select('id', 'exercise_id', 'logged_at')
                ->where('user_id', $userId)
                ->whereIn('exercise_id', $programExerciseIds)
                ->whereIn('id', function($query) use ($userId, $programExerciseIds) {
                    $query->select(\DB::raw('MAX(id)'))
                        ->from('lift_logs')
                        ->where('user_id', $userId)
                        ->whereIn('exercise_id', $programExerciseIds)
                        ->groupBy('exercise_id');
                })
                ->get()
                ->keyBy('exercise_id');

            if (!$latestLogIds->isEmpty()) {
                $logIds = $latestLogIds->pluck('id')->toArray();
                $logsWithSets = \App\Models\LiftLog::with(['liftSets' => function($query) {
                        $query->select('lift_log_id', 'weight', 'reps', 'band_color')->orderBy('id');
                    }, 'exercise'])
                    ->whereIn('id', $logIds)
                    ->get()
                    ->keyBy('exercise_id');

                $lastLogs = $logsWithSets;
            }
        }

        // Process suggestions and last workout data efficiently
        $shouldGetSuggestions = $selectedDate->isToday() || $selectedDate->isTomorrow() || $selectedDate->copy()->addDay()->isTomorrow();
        
        foreach ($programs as $program) {
            // Get suggestion details if needed
            if ($shouldGetSuggestions) {
                $lastLog = $lastLogs[$program->exercise_id] ?? null;
                if ($lastLog) {
                    $suggestionDetails = $this->getSuggestionDetailsOptimized($lastLog, $trainingProgressionService, $userId, $program->exercise_id, $selectedDate);
                    
                    if ($suggestionDetails) {
                        if (isset($suggestionDetails->band_color)) {
                            // Banded exercise
                            $program->suggestedBandColor = $suggestionDetails->band_color;
                            $program->reps = $suggestionDetails->reps;
                            $program->sets = $suggestionDetails->sets;
                            $program->suggestedNextWeight = null;
                            $program->lastWeight = null;
                        } else {
                            // Regular weighted exercise
                            $program->suggestedNextWeight = $suggestionDetails->suggestedWeight ?? null;
                            $program->lastWeight = $suggestionDetails->lastWeight ?? null;
                            $program->lastReps = $suggestionDetails->lastReps ?? null;
                            $program->lastSets = $suggestionDetails->lastSets ?? null;
                            $program->reps = $suggestionDetails->reps;
                            $program->sets = $suggestionDetails->sets;
                            $program->suggestedBandColor = null;
                        }
                    } else {
                        $this->setNullSuggestions($program);
                    }
                } else {
                    $this->setNullSuggestions($program);
                }
            } else {
                $this->setNullSuggestions($program);
            }

            // Set last workout data from cached results
            $lastLog = $lastLogs[$program->exercise_id] ?? null;
            if ($lastLog) {
                $firstSet = $lastLog->liftSets->first();
                if ($firstSet) {
                    if ($program->exercise->band_type) {
                        $program->lastWorkoutWeight = $firstSet->band_color ?? 'N/A';
                        $program->lastWorkoutIsBanded = true;
                    } else {
                        $program->lastWorkoutWeight = $firstSet->weight ?? 0;
                        $program->lastWorkoutIsBanded = false;
                    }
                    $program->lastWorkoutReps = $firstSet->reps;
                    $program->lastWorkoutSets = $lastLog->liftSets->count();
                    $program->lastWorkoutDate = $lastLog->logged_at;
                    $program->lastWorkoutTimeAgo = $lastLog->logged_at->diffForHumans();
                }
            }
        }

        $submittedLiftLog = null;
        if ($request->has('submitted_lift_log_id')) {
            $submittedLiftLog = \App\Models\LiftLog::with(['liftSets', 'exercise'])->find($request->input('submitted_lift_log_id'));
        }

        // Fetch all lift logs for the selected date and user in one query
        $dailyLiftLogs = \App\Models\LiftLog::with(['liftSets', 'exercise'])
            ->where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->get()
            ->keyBy('exercise_id');

        // Load exercises in one query
        $exercises = \App\Models\Exercise::availableToUser()
            ->orderBy('title')
            ->get()
            ->map(function ($exercise) {
                $exercise->is_user_created = !$exercise->isGlobal();
                return $exercise;
            });

        // Get recommendations efficiently with caching
        $recommendations = [];
        try {
            $targetRecommendations = 3;
            $maxAttempts = 20;
            
            // Use lightweight mode for faster mobile entry loading
            $allRecommendations = $recommendationEngine->getRecommendations($userId, $maxAttempts, null, true);
            
            // Filter out exercises that are already in today's program
            $filteredRecommendations = [];
            foreach ($allRecommendations as $recommendation) {
                if (count($filteredRecommendations) >= $targetRecommendations) {
                    break;
                }
                
                $exercise = $recommendation['exercise'];
                if (!in_array($exercise->id, $programExerciseIds)) {
                    $filteredRecommendations[] = $recommendation;
                }
            }
            
            $recommendations = $filteredRecommendations;
        } catch (\Exception $e) {
            \Log::warning('Failed to get recommendations for mobile entry: ' . $e->getMessage());
        }

        return view('lift-logs.mobile-entry', compact('programs', 'selectedDate', 'submittedLiftLog', 'dailyLiftLogs', 'exercises', 'recommendations'));
    }

    private function getSuggestionDetailsOptimized($lastLog, $trainingProgressionService, $userId, $exerciseId, $selectedDate)
    {
        // Use the already loaded lastLog instead of querying again
        if (!$lastLog || !$lastLog->exercise) {
            return null;
        }

        // Handle banded exercises using the proper TrainingProgressionService
        if ($lastLog->exercise->band_type !== null) {
            return $trainingProgressionService->getSuggestionDetailsWithLog($lastLog, $userId, $exerciseId, $selectedDate);
        }

        // For weighted exercises, use simple progression logic to avoid additional queries
        $firstSet = $lastLog->liftSets->first();
        if (!$firstSet) {
            return null;
        }

        $lastWeight = $firstSet->weight ?? 0;
        $lastReps = $firstSet->reps ?? 0;

        // Simple double progression logic
        if ($lastReps >= 8 && $lastReps <= 12) {
            $suggestedWeight = $lastWeight;
            $suggestedReps = $lastReps + 1;

            if ($lastReps >= 12) {
                $suggestedWeight = $lastWeight + 5.0;
                $suggestedReps = 8;
            }

            return (object)[
                'suggestedWeight' => $suggestedWeight,
                'reps' => $suggestedReps,
                'sets' => $lastLog->liftSets->count(),
                'lastWeight' => $lastWeight,
                'lastReps' => $lastReps,
                'lastSets' => $lastLog->liftSets->count(),
            ];
        }

        // For other rep ranges, suggest linear progression
        return (object)[
            'suggestedWeight' => $lastWeight + 5.0,
            'reps' => $lastReps,
            'sets' => $lastLog->liftSets->count(),
            'lastWeight' => $lastWeight,
            'lastReps' => $lastReps,
            'lastSets' => $lastLog->liftSets->count(),
        ];
    }

    private function setNullSuggestions($program)
    {
        $program->suggestedNextWeight = null;
        $program->lastWeight = null;
        $program->suggestedBandColor = null;
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