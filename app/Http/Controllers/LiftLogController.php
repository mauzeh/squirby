<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;

use App\Services\ExerciseService;
use App\Presenters\LiftLogTablePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $liftLogs = LiftLog::with(['exercise', 'liftSets'])->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();
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

        $programs = \App\Models\Program::with('exercise')
            ->where('user_id', auth()->id())
            ->whereDate('date', $selectedDate->toDateString())
            ->orderBy('priority')
            ->get();

        if ($selectedDate->isToday() || $selectedDate->isTomorrow() || $selectedDate->copy()->addDay()->isTomorrow()) {
            foreach ($programs as $program) {
                $suggestionDetails = $trainingProgressionService->getSuggestionDetails(
                    auth()->id(),
                    $program->exercise_id,
                    $selectedDate
                );

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
                    // No suggestion details available
                    $program->suggestedNextWeight = null;
                    $program->lastWeight = null;
                    $program->suggestedBandColor = null;
                }
            }
        } else {
            foreach ($programs as $program) {
                $program->suggestedNextWeight = null;
                $program->lastWeight = null;
                $program->suggestedBandColor = null;
                
            }
        }

        // Always fetch last workout data for each exercise, regardless of suggestions
        foreach ($programs as $program) {
            $lastLog = \App\Models\LiftLog::where('user_id', auth()->id())
                ->where('exercise_id', $program->exercise_id)
                ->orderBy('logged_at', 'desc')
                ->first();
            
            if ($lastLog) {
                // For banded exercises, display_weight returns the band color, not a numeric weight
                if ($program->exercise->band_type) {
                    $program->lastWorkoutWeight = $lastLog->display_weight; // This will be the band color
                    $program->lastWorkoutIsBanded = true;
                } else {
                    $program->lastWorkoutWeight = $lastLog->display_weight; // This will be numeric
                    $program->lastWorkoutIsBanded = false;
                }
                $program->lastWorkoutReps = $lastLog->display_reps;
                $program->lastWorkoutSets = $lastLog->display_rounds;
                $program->lastWorkoutDate = $lastLog->logged_at;
                $program->lastWorkoutTimeAgo = $lastLog->logged_at->diffForHumans();
            }
        }

        $submittedLiftLog = null;
        if ($request->has('submitted_lift_log_id')) {
            $submittedLiftLog = \App\Models\LiftLog::with('liftSets', 'exercise')->find($request->input('submitted_lift_log_id'));
        }

        // Fetch all lift logs for the selected date and user
        $dailyLiftLogs = \App\Models\LiftLog::with('liftSets', 'exercise')
            ->where('user_id', auth()->id())
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->get()
            ->keyBy('exercise_id'); // Key by exercise_id for easy lookup

        $exercises = \App\Models\Exercise::availableToUser()
            ->orderBy('title')
            ->get()
            ->map(function ($exercise) {
                $exercise->is_user_created = !$exercise->isGlobal();
                return $exercise;
            });

        // Get top-3 exercise recommendations
        $recommendations = [];
        try {
            $programExerciseIds = $programs->pluck('exercise_id')->toArray();
            $targetRecommendations = 3;
            $maxAttempts = 20; // Get up to 20 recommendations to ensure we can find 3 that aren't in the program
            
            // Get user's global exercise preference
            $showGlobalExercises = auth()->user()->show_global_exercises;
            
            $allRecommendations = $recommendationEngine->getRecommendations(auth()->id(), $maxAttempts);
            
            // Filter out exercises that are already in today's program
            $filteredRecommendations = [];
            foreach ($allRecommendations as $recommendation) {
                if (count($filteredRecommendations) >= $targetRecommendations) {
                    break; // We have enough recommendations
                }
                
                $exercise = $recommendation['exercise'];
                $isExerciseInProgram = in_array($exercise->id, $programExerciseIds);
                
                // The recommendation engine already respects user's global exercise preference
                if (!$isExerciseInProgram) {
                    $filteredRecommendations[] = $recommendation;
                }
            }
            
            $recommendations = $filteredRecommendations;
        } catch (\Exception $e) {
            // If recommendations fail, continue without them
            \Log::warning('Failed to get recommendations for mobile entry: ' . $e->getMessage());
        }

        return view('lift-logs.mobile-entry', compact('programs', 'selectedDate', 'submittedLiftLog', 'dailyLiftLogs', 'exercises', 'recommendations'));
    }
}