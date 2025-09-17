<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Exercise;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ProgramTsvImporterService;
use App\Services\TrainingProgressionService;
use App\Models\LiftLog;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, TrainingProgressionService $trainingProgressionService)
    {
        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $programs = Program::with('exercise')
            ->where('user_id', auth()->id())
            ->whereDate('date', $selectedDate->toDateString())
            ->orderBy('priority')
            ->get();

        if ($selectedDate->isToday() || $selectedDate->isTomorrow() || $selectedDate->copy()->addDay()->isTomorrow()) {
            foreach ($programs as $program) {
                // Only suggest weight for non-bodyweight exercises
                if (!$program->exercise->is_bodyweight) {
                    $program->suggestedNextWeight = $trainingProgressionService->suggestNextWeight(
                        auth()->id(),
                        $program->exercise_id,
                        $program->reps, // Assuming 'reps' from the program is the target reps
                        $selectedDate // Pass the selected date for lookback
                    );
                } else {
                    $program->suggestedNextWeight = null; // Or a specific message for bodyweight
                }
            }
        } else {
            foreach ($programs as $program) {
                $program->suggestedNextWeight = null;
            }
        }

        return view('programs.index', compact('programs', 'selectedDate'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title')->get();
        $highestPriority = Program::where('user_id', auth()->id())
            ->whereDate('date', $date->toDateString())
            ->max('priority');

        // If no programs exist, or if the next available priority is less than 100, default to 100.
        // Otherwise, use the next available priority.
        $defaultPriority = ($highestPriority === null || $highestPriority + 1 < 100) ? 100 : $highestPriority + 1;

        return view('programs.create', compact('exercises', 'date', 'defaultPriority'));
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

        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title')->get();

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

        if ($request->input('redirect_to') === 'mobile-entry') {
            return redirect()->route('lift-logs.mobile-entry', ['date' => $date])->with('success', 'Program entry deleted.');
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

    public function import(Request $request, ProgramTsvImporterService $importerService)
    {
        $request->validate([
            'tsv_content' => 'required|string',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->input('date'));
        $tsvContent = $request->input('tsv_content');

        $result = $importerService->import($tsvContent, auth()->id());

        $message = 'Successfully imported ' . $result['importedCount'] . ' program entries.';
        if (count($result['notFound']) > 0) {
            $message .= ' Some exercises not found: ' . implode(', ', array_unique($result['notFound'])) . '.';
        }
        if (count($result['invalidRows']) > 0) {
            $message .= ' Some rows were invalid.';
        }

        if (count($result['notFound']) > 0 || count($result['invalidRows']) > 0) {
            return redirect()->route('programs.index', ['date' => $date->format('Y-m-d')])->withErrors($result['invalidRows'])->with('error', $message);
        } else {
            return redirect()->route('programs.index', ['date' => $date->format('Y-m-d')])->with('success', $message);
        }
    }

    public function quickAdd(Request $request, Exercise $exercise, $date, TrainingProgressionService $trainingProgressionService)
    {
        $reps = $trainingProgressionService->suggestNextRepCount(auth()->id(), $exercise->id);
        $sets = $trainingProgressionService->suggestNextSetCount(auth()->id(), $exercise->id);

        // Find the highest priority for the given date
        $maxPriority = Program::where('user_id', auth()->id())
            ->whereDate('date', $date)
            ->max('priority');

        $newPriority = $maxPriority !== null ? $maxPriority + 1 : 100;

        Program::create([
            'exercise_id' => $exercise->id,
            'user_id' => auth()->id(),
            'date' => $date,
            'sets' => $sets,
            'reps' => $reps,
            'priority' => $newPriority,
        ]);

        return redirect()->route('lift-logs.mobile-entry', ['date' => $date])->with('success', 'Exercise added to program successfully.');
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

        $maxPriority = Program::where('user_id', auth()->id())
            ->whereDate('date', $date)
            ->max('priority');

        Program::create([
            'exercise_id' => $exercise->id,
            'user_id' => auth()->id(),
            'date' => $date,
            'sets' => config('training.defaults.sets', 3),
            'reps' => config('training.defaults.reps', 10),
            'priority' => $maxPriority + 1,
        ]);

        return redirect()->route('lift-logs.mobile-entry', ['date' => $date])->with('success', 'New exercise created and added to program successfully.');
    }

    public function moveUp(Request $request, Program $program)
    {
        $this->swapPriority($program, 'up');
        return redirect()->route('lift-logs.mobile-entry', ['date' => $program->date]);
    }

    public function moveDown(Request $request, Program $program)
    {
        $this->swapPriority($program, 'down');
        return redirect()->route('lift-logs.mobile-entry', ['date' => $program->date]);
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
