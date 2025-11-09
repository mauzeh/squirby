<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Exercise;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Services\TrainingProgressionService;
use App\Services\DateNavigationService;
use App\Services\ExerciseService;
use App\Services\ExerciseAliasService;
use App\Models\LiftLog;

class ProgramController extends Controller
{
    protected TrainingProgressionService $trainingProgressionService;
    protected ExerciseService $exerciseService;
    protected ExerciseAliasService $aliasService;

    public function __construct(
        TrainingProgressionService $trainingProgressionService, 
        ExerciseService $exerciseService,
        ExerciseAliasService $aliasService
    )
    {
        $this->trainingProgressionService = $trainingProgressionService;
        $this->exerciseService = $exerciseService;
        $this->aliasService = $aliasService;
    }

    /**
     * Calculate sets and reps for a given exercise and date using TrainingProgressionService
     */
    private function calculateSetsAndReps(int $exerciseId, Carbon $date): array
    {
        try {
            $suggestion = $this->trainingProgressionService->getSuggestionDetails(
                auth()->id(), 
                $exerciseId, 
                $date
            );
            
            // Validate suggestion data if it exists
            if ($suggestion && $this->isValidSuggestionData($suggestion)) {
                return [
                    'sets' => $suggestion->sets,
                    'reps' => $suggestion->reps,
                    'suggestion_available' => true
                ];
            }
            
            // Fall back to defaults if no suggestion or invalid suggestion data
            return $this->getDefaultSetsAndReps();
            
        } catch (\Exception $e) {
            // Fall back to defaults if TrainingProgressionService fails
            return $this->getDefaultSetsAndReps();
        }
    }

    /**
     * Validate suggestion data to ensure it contains valid sets and reps
     */
    private function isValidSuggestionData($suggestion): bool
    {
        return isset($suggestion->sets) && 
               isset($suggestion->reps) && 
               is_numeric($suggestion->sets) && 
               is_numeric($suggestion->reps) && 
               $suggestion->sets > 0 && 
               $suggestion->reps > 0;
    }

    /**
     * Get default sets and reps with proper fallbacks
     */
    private function getDefaultSetsAndReps(): array
    {
        $defaultSets = config('training.defaults.sets', 3);
        $defaultReps = config('training.defaults.reps', 10);
        
        // Ensure we have valid positive integers, fall back to hardcoded values if not
        $sets = (is_numeric($defaultSets) && $defaultSets > 0) ? (int)$defaultSets : 3;
        $reps = (is_numeric($defaultReps) && $defaultReps > 0) ? (int)$defaultReps : 10;
        
        return [
            'sets' => $sets,
            'reps' => $reps,
            'suggestion_available' => false
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, DateNavigationService $dateNavigationService)
    {
        $selectedDate = $dateNavigationService->parseSelectedDate($request->input('date'));

        $programs = Program::with(['exercise' => function ($query) {
                $query->with(['aliases' => function ($aliasQuery) {
                    $aliasQuery->where('user_id', auth()->id());
                }]);
            }])
            ->where('user_id', auth()->id())
            ->whereDate('date', $selectedDate->toDateString())
            ->orderBy('priority')
            ->get();

        if ($selectedDate->isToday() || $selectedDate->isTomorrow() || $selectedDate->copy()->addDay()->isTomorrow()) {
            foreach ($programs as $program) {
                if (!$program->exercise->isType('bodyweight')) {
                    $suggestionDetails = $this->trainingProgressionService->getSuggestionDetails(
                        auth()->id(),
                        $program->exercise_id,
                        $selectedDate
                    );

                    if ($suggestionDetails) {
                        $program->suggestedNextWeight = $suggestionDetails->suggestedWeight ?? null;
                        $program->lastWeight = $suggestionDetails->lastWeight ?? null;
                        
                    } else {
                        $program->suggestedNextWeight = null;
                        $program->lastWeight = null;
                        
                    }
                } else {
                    $program->suggestedNextWeight = null;
                    $program->lastWeight = null;
                    
                }
            }
        } else {
            foreach ($programs as $program) {
                $program->suggestedNextWeight = null;
                $program->lastWeight = null;
                
            }
        }

        // Apply aliases to program exercises
        foreach ($programs as $program) {
            if ($program->exercise) {
                $displayName = $this->aliasService->getDisplayName($program->exercise, auth()->user());
                $program->exercise->title = $displayName;
            }
        }

        // Get date navigation data
        $navigationData = $dateNavigationService->getNavigationData(
            $selectedDate,
            Program::class,
            auth()->id(),
            'programs.index'
        );

        // Fetch exercise data for the selector
        $displayExercises = $this->exerciseService->getDisplayExercises(5);
        $allExercises = Exercise::availableToUser()
            ->with(['aliases' => function ($query) {
                $query->where('user_id', auth()->id());
            }])
            ->orderBy('title')
            ->get();
        
        // Apply aliases to all exercises for the dropdown
        $allExercises = $this->aliasService->applyAliasesToExercises($allExercises, auth()->user());

        return view('programs.index', compact('programs', 'selectedDate', 'navigationData', 'displayExercises', 'allExercises'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        $exercises = Exercise::availableToUser()
            ->with(['aliases' => function ($query) {
                $query->where('user_id', auth()->id());
            }])
            ->orderBy('title')
            ->get();
        $highestPriority = Program::where('user_id', auth()->id())
            ->whereDate('date', $date->toDateString())
            ->max('priority');

        // If no programs exist, or if the next available priority is less than 100, default to 100.
        // Otherwise, use the next available priority.
        $defaultPriority = ($highestPriority === null || $highestPriority + 1 < 100) ? 100 : $highestPriority + 1;

        // Calculate default sets and reps for display purposes
        $defaultSetsReps = [
            'sets' => config('training.defaults.sets', 3),
            'reps' => config('training.defaults.reps', 10),
            'suggestion_available' => false
        ];

        return view('programs.create', compact('exercises', 'date', 'defaultPriority', 'defaultSetsReps'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProgramRequest $request)
    {
        $validated = $request->validated();

        if (!empty($validated['new_exercise_name'])) {
            $exercise = new Exercise();
            $exercise->title = $validated['new_exercise_name'];
            $exercise->user_id = auth()->id();
            $exercise->save();
            $validated['exercise_id'] = $exercise->id;
        }

        // Calculate sets and reps using TrainingProgressionService
        $date = Carbon::parse($validated['date']);
        $calculatedSetsReps = $this->calculateSetsAndReps($validated['exercise_id'], $date);
        
        // Override any sets/reps values with calculated ones
        $validated['sets'] = $calculatedSetsReps['sets'];
        $validated['reps'] = $calculatedSetsReps['reps'];

        $program = new Program($validated);
        $program->user_id = auth()->id();
        $program->save();

        return redirect()->route('programs.index', ['date' => $validated['date']])->with('success', 'Program entry created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program)
    {
        // Not used.
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Program $program)
    {
        if ($program->user_id !== auth()->id()) {
            abort(403);
        }

        // Eager load the program's exercise with aliases
        $program->load(['exercise' => function ($query) {
            $query->with(['aliases' => function ($aliasQuery) {
                $aliasQuery->where('user_id', auth()->id());
            }]);
        }]);

        $exercises = Exercise::availableToUser()
            ->with(['aliases' => function ($query) {
                $query->where('user_id', auth()->id());
            }])
            ->orderBy('title')
            ->get();

        return view('programs.edit', compact('program', 'exercises'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProgramRequest $request, Program $program)
    {
        if ($program->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validated();

        if (!empty($validated['new_exercise_name'])) {
            $exercise = new Exercise();
            $exercise->title = $validated['new_exercise_name'];
            $exercise->user_id = auth()->id();
            $exercise->save();
            $validated['exercise_id'] = $exercise->id;
        }

        $program->update($validated);

        return redirect()->route('programs.index', ['date' => $validated['date']])->with('success', 'Program entry updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Program $program)
    {
        if ($program->user_id !== auth()->id()) {
            abort(403);
        }

        $program->delete();

        $date = $request->input('date');

        if ($request->input('redirect_to') === 'recommendations') {
            // Get all query parameters except 'program', 'redirect_to', and '_method'
            $queryParams = $request->except(['program', 'redirect_to', '_method']);
            return redirect()->route('recommendations.index', $queryParams)->with('success', 'Program entry deleted.');
        }

        if ($request->input('redirect_to') === 'mobile-entry') {
            return redirect()->route('mobile-entry.lifts', ['date' => $date]);
        }

        return redirect()->route('programs.index', ['date' => $date])->with('success', 'Program entry deleted.');
    }

    /**
     * Remove the specified resources from storage.
     */
    public function destroySelected(Request $request)
    {
        $request->validate([
            'program_ids' => 'required|array',
            'program_ids.*' => 'exists:programs,id',
        ]);

        Program::whereIn('id', $request->program_ids)
            ->where('user_id', auth()->id())
            ->delete();

        $date = $request->input('date');

        return redirect()->route('programs.index', ['date' => $date])->with('success', 'Selected program entries deleted.');
    }



    public function quickAdd(Request $request, Exercise $exercise, $date)
    {
        // Use the same robust calculation method as store()
        $calculatedSetsReps = $this->calculateSetsAndReps($exercise->id, Carbon::parse($date));

        // Find the lowest priority for the given date to add new exercises at the top
        $minPriority = Program::where('user_id', auth()->id())
            ->whereDate('date', $date)
            ->min('priority');

        $newPriority = $minPriority !== null ? $minPriority - 1 : 100;

        Program::create([
            'exercise_id' => $exercise->id,
            'user_id' => auth()->id(),
            'date' => $date,
            'sets' => $calculatedSetsReps['sets'],
            'reps' => $calculatedSetsReps['reps'],
            'priority' => $newPriority,
        ]);

        if ($request->input('redirect_to') === 'recommendations') {
            // Get all query parameters except 'exercise', 'date', and 'redirect_to'
            $queryParams = $request->except(['exercise', 'date', 'redirect_to']);
            return redirect()->route('recommendations.index', $queryParams)->with('success', 'Exercise added to program successfully.');
        }

        if ($request->input('redirect_to') === 'mobile-entry') {
            return redirect()->route('mobile-entry.lifts', ['date' => $date]);
        }

        return redirect()->route('programs.index', ['date' => $date])->with('success', 'Exercise added to program successfully.');
    }

    public function quickCreate(Request $request, $date)
    {
        $request->validate([
            'exercise_name' => 'required|string|max:255',
        ]);

        $exercise = Exercise::create([
            'title' => $request->input('exercise_name'),
            'user_id' => auth()->id(),
        ]);

        // Use robust default calculation for new exercises (no progression data expected)
        $defaultSetsReps = $this->getDefaultSetsAndReps();

        $minPriority = Program::where('user_id', auth()->id())
            ->whereDate('date', $date)
            ->min('priority');

        Program::create([
            'exercise_id' => $exercise->id,
            'user_id' => auth()->id(),
            'date' => $date,
            'sets' => $defaultSetsReps['sets'],
            'reps' => $defaultSetsReps['reps'],
            'priority' => $minPriority !== null ? $minPriority - 1 : 100,
        ]);

        if ($request->input('redirect_to') === 'mobile-entry') {
            return redirect()->route('mobile-entry.lifts', ['date' => $date]);
        }

        return redirect()->route('programs.index', ['date' => $date])->with('success', 'New exercise created and added to program successfully.');
    }

    public function moveUp(Request $request, Program $program)
    {
        $this->swapPriority($program, 'up');
        return redirect()->route('mobile-entry.lifts', ['date' => $program->date->toDateString()]);
    }

    public function moveDown(Request $request, Program $program)
    {
        $this->swapPriority($program, 'down');
        return redirect()->route('mobile-entry.lifts', ['date' => $program->date->toDateString()]);
    }

    private function swapPriority(Program $program, $direction)
    {
        $query = Program::where('user_id', $program->user_id)
            ->where('date', $program->date);

        if ($direction === 'up') {
            $otherProgram = $query->where('priority', '<', $program->priority)->orderBy('priority', 'desc')->first();
        } else {
            $otherProgram = $query->where('priority', '>', $program->priority)->orderBy('priority', 'asc')->first();
        }

        if ($otherProgram) {
            $tempPriority = $program->priority;
            $program->update(['priority' => $otherProgram->priority]);
            $otherProgram->update(['priority' => $tempPriority]);
        }
    }
}
