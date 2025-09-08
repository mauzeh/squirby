<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Services\TsvImporterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
        $workouts = Workout::with('exercise')->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();

        $datasets = [];
        $groupedWorkouts = $workouts->groupBy('exercise.title');

        $colors = [
            'rgba(255, 99, 132, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)'
        ];
        $colorIndex = 0;

        foreach ($groupedWorkouts as $exerciseName => $exerciseWorkouts) {
            $datasets[] = [
                'label' => $exerciseName,
                'data' => $exerciseWorkouts->map(function ($workout) {
                    return [
                        'x' => $workout->logged_at->toIso8601String(),
                        'y' => $workout->best_one_rep_max,
                    ];
                }),
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => $colors[$colorIndex % count($colors)],
                'fill' => false,
            ];
            $colorIndex++;
        }

        $chartData['datasets'] = $datasets;

        return view('workouts.index', compact('workouts', 'exercises', 'chartData'));
    }

    

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'weight' => 'required|numeric',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
        ]);

        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));

        $workout = Workout::create([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
            'user_id' => auth()->id(),
        ]);

        $weight = $request->input('weight');
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        for ($i = 0; $i < $rounds; $i++) {
            $workout->workoutSets()->create([
                'weight' => $weight,
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        }

        return redirect()->route('workouts.index')->with('success', 'Workout created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Workout $workout)
    {
        if ($workout->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();
        return view('workouts.edit', compact('workout', 'exercises'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Workout $workout)
    {
        if ($workout->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
        ]);

        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));

        $workout->update([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
        ]);

        // Delete existing workout sets
        $workout->workoutSets()->delete();

        // Create new workout sets
        $weight = $request->input('weight');
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        for ($i = 0; $i < $rounds; $i++) {
            $workout->workoutSets()->create([
                'weight' => $weight,
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        }

        return redirect()->route('workouts.index')->with('success', 'Workout updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Workout $workout)
    {
        if ($workout->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $workout->delete();

        return redirect()->route('workouts.index')->with('success', 'Workout deleted successfully.');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'workout_ids' => 'required|array',
            'workout_ids.*' => 'exists:workouts,id',
        ]);

        $workouts = Workout::whereIn('id', $validated['workout_ids'])->get();

        foreach ($workouts as $workout) {
            if ($workout->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        Workout::destroy($validated['workout_ids']);

        return redirect()->route('workouts.index')->with('success', 'Selected workouts deleted successfully!');
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'nullable|string',
            'date' => 'required|date',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('workouts.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importWorkouts($tsvData, $validated['date'], auth()->id());

        $value=true;

        if ($result['importedCount'] === 0 && !empty($result['notFound'])) {
            return redirect()
                ->route('workouts.index')
                ->with('error', 'No exercises found for: ' . implode(', ', $result['notFound']));
        } elseif ($result['importedCount'] === 0 && !empty($result['invalidRows'])) {
            return redirect()
                ->route('workouts.index')
                ->with('error', 'No workouts imported due to invalid data in rows: ' . implode(', ', array_map(function($row) { return '"' . $row . '"' ; }, $result['invalidRows'])));
        } elseif (!empty($result['importedCount']) && !empty($result['invalidRows'])) {
            return redirect()
                ->route('workouts.index')
                ->with('success', 'TSV data imported successfully with some invalid rows. Invalid rows: ' . implode(', ', array_map(function($row) { return '"' . $row . '"' ; }, $result['invalidRows'])));
        }

        return redirect()
            ->route('workouts.index')
            ->with('success', 'TSV data imported successfully!');
    }
}