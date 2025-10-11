<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Services\ExerciseService;
use App\Services\ChartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExerciseController extends Controller
{
    protected $exerciseService;
    protected $chartService;
    protected $tsvImporterService;

    public function __construct(ExerciseService $exerciseService, \App\Services\ChartService $chartService, \App\Services\TsvImporterService $tsvImporterService)
    {
        $this->exerciseService = $exerciseService;
        $this->chartService = $chartService;
        $this->tsvImporterService = $tsvImporterService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();
        return view('exercises.index', compact('exercises'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('exercises.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_bodyweight' => 'nullable|boolean',
        ]);

        Exercise::create(array_merge($validated, ['user_id' => auth()->id(), 'is_bodyweight' => $request->has('is_bodyweight')]));

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
        if ($exercise->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        return view('exercises.edit', compact('exercise'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exercise $exercise)
    {
        if ($exercise->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_bodyweight' => 'nullable|boolean',
        ]);

        $exercise->update(array_merge($validated, ['is_bodyweight' => $request->boolean('is_bodyweight')]));

        return redirect()->route('exercises.index')->with('success', 'Exercise updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exercise $exercise)
    {
        if ($exercise->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $exercise->delete();

        return redirect()->route('exercises.index')->with('success', 'Exercise deleted successfully.');
    }

    /**
     * Remove the specified resources from storage.
     */
    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'exercise_ids' => 'required|array',
            'exercise_ids.*' => 'exists:exercises,id',
        ]);

        $exercises = Exercise::whereIn('id', $validated['exercise_ids'])->get();

        foreach ($exercises as $exercise) {
            if ($exercise->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        Exercise::destroy($validated['exercise_ids']);

        return redirect()->route('exercises.index')->with('success', 'Selected exercises deleted successfully!');
    }

    public function showLogs(Request $request, Exercise $exercise)
    {
        if ($exercise->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $liftLogs = $exercise->liftLogs()->with('liftSets')->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();

        $displayExercises = $this->exerciseService->getDisplayExercises(5);

        $chartData = $this->chartService->generateBestPerDay($liftLogs);

        $liftLogs = $liftLogs->reverse();

        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();

        $sets = $request->input('sets');
        $reps = $request->input('reps');
        $weight = $request->input('weight');

        return view('exercises.logs', compact('exercise', 'liftLogs', 'chartData', 'displayExercises', 'exercises', 'sets', 'reps', 'weight'));
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'nullable|string',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('exercises.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importExercises($tsvData, auth()->id());

        // Handle case with only invalid rows and no valid data
        if ($result['importedCount'] === 0 && $result['updatedCount'] === 0 && !empty($result['invalidRows'])) {
            return redirect()
                ->route('exercises.index')
                ->with('error', 'No exercises were imported due to invalid data in rows: ' . implode(', ', array_map(function($row) { return '"' . $row . '"'; }, $result['invalidRows'])));
        }

        $message = 'TSV data processed successfully! ';
        $countParts = [];
        if ($result['importedCount'] > 0) {
            $countParts[] = $result['importedCount'] . ' exercise(s) imported';
        }
        if ($result['updatedCount'] > 0) {
            $countParts[] = $result['updatedCount'] . ' exercise(s) updated';
        }
        
        if (!empty($countParts)) {
            $message .= implode(', ', $countParts) . '.';
        } else {
            // Handle case where nothing was imported or updated (all duplicates)
            $message .= 'No new data was imported or updated - all entries already exist with the same data.';
        }
        if (!empty($result['invalidRows'])) {
            $message .= '. Some rows were invalid: ' . implode(', ', array_map(function($row) { return '"' . $row . '"'; }, $result['invalidRows']));
        }

        return redirect()
            ->route('exercises.index')
            ->with('success', 'TSV data processed successfully! ' . $message);
    }
}