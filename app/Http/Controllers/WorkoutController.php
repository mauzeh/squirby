<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Workout;
use App\Services\TsvImporterService;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    protected $tsvImporterService;

    public function __construct(TsvImporterService $tsvImporterService)
    {
        $this->tsvImporterService = $tsvImporterService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workouts = Workout::with('exercise')->orderBy('logged_at', 'desc')->get();
        $exercises = Exercise::all();
        return view('workouts.index', compact('workouts', 'exercises'));
    }

    

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'weight' => 'required|numeric',
            'reps' => 'required|integer',
            'rounds' => 'required|integer',
            'comments' => 'nullable|string',
            'logged_at' => 'required|date',
        ]);

        Workout::create($request->all());

        return redirect()->route('workouts.index')->with('success', 'Workout created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Workout $workout)
    {
        return view('workouts.show', compact('workout'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Workout $workout)
    {
        $exercises = Exercise::all();
        return view('workouts.edit', compact('workout', 'exercises'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Workout $workout)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'weight' => 'required|numeric',
            'reps' => 'required|integer',
            'rounds' => 'required|integer',
            'comments' => 'nullable|string',
            'logged_at' => 'required|date',
        ]);

        $workout->update($request->all());

        return redirect()->route('workouts.index')->with('success', 'Workout updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workout $workout)
    {
        $workout->delete();

        return redirect()->route('workouts.index')->with('success', 'Workout deleted successfully.');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'workout_ids' => 'required|array',
            'workout_ids.*' => 'exists:workouts,id',
        ]);

        Workout::destroy($validated['workout_ids']);

        return redirect()->route('workouts.index')->with('success', 'Selected workouts deleted successfully!');
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'required|string',
            'date' => 'required|date',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('workouts.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importWorkouts($tsvData, $validated['date']);

        if ($result['importedCount'] === 0 && !empty($result['notFound'])) {
            return redirect()
                ->route('workouts.index')
                ->with('error', 'No exercises found for: ' . implode(', ', $result['notFound']));
        }

        return redirect()
            ->route('workouts.index')
            ->with('success', 'TSV data imported successfully!');
    }
}