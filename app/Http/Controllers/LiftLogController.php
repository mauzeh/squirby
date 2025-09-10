<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\TsvImporterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class LiftLogController extends Controller
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
        $liftLogs = LiftLog::with('exercise')->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();

        $topExercises = LiftLog::select('exercise_id')
            ->where('user_id', auth()->id())
            ->groupBy('exercise_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(3)
            ->pluck('exercise_id');

        $charts = [];
        $colors = [
            ['borderColor' => 'rgba(255, 99, 132, 1)', 'backgroundColor' => 'rgba(255, 99, 132, 0.2)'],
            ['borderColor' => 'rgba(54, 162, 235, 1)', 'backgroundColor' => 'rgba(54, 162, 235, 0.2)'],
            ['borderColor' => 'rgba(255, 206, 86, 1)', 'backgroundColor' => 'rgba(255, 206, 86, 0.2)'],
        ];
        $colorIndex = 0;

        foreach ($topExercises as $exerciseId) {
            $exercise = Exercise::find($exerciseId);
            $exerciseLiftLogs = $liftLogs->where('exercise_id', $exerciseId);

            $minDate = $exerciseLiftLogs->min('logged_at');
            $maxDate = $exerciseLiftLogs->max('logged_at');

            $datasets = [];
            $datasets[] = [
                'label' => $exercise->title,
                'data' => $exerciseLiftLogs->map(function ($liftLog) {
                    return [
                        'x' => $liftLog->logged_at->toIso8601String(),
                        'y' => $liftLog->best_one_rep_max,
                    ];
                })->values(),
                'borderColor' => $colors[$colorIndex % count($colors)]['borderColor'],
                'backgroundColor' => $colors[$colorIndex % count($colors)]['backgroundColor'],
                'fill' => false,
            ];

            $charts[] = [
                'title' => $exercise->title,
                'exercise_id' => $exercise->id,
                'chartData' => ['datasets' => $datasets],
                'minDate' => $minDate ? $minDate->toIso8601String() : null,
                'maxDate' => $maxDate ? $maxDate->toIso8601String() : null,
            ];
            $colorIndex++;
        }

        return view('lift-logs.index', compact('liftLogs', 'exercises', 'charts'));
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

        $liftLog = LiftLog::create([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
            'user_id' => auth()->id(),
        ]);

        $weight = $request->input('weight');
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $weight,
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        }

        return redirect()->route('lift-logs.index')->with('success', 'Lift log created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();
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
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
        ]);

        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));

        $liftLog->update([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
        ]);

        // Delete existing lift sets
        $liftLog->liftSets()->delete();

        // Create new lift sets
        $weight = $request->input('weight');
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $weight,
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        }

        return redirect()->route('lift-logs.index')->with('success', 'Lift log updated successfully.');
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

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'nullable|string',
            'date' => 'required|date',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('lift-logs.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importLiftLogs($tsvData, $validated['date'], auth()->id());

        $value=true;

        if ($result['importedCount'] === 0 && !empty($result['notFound'])) {
            return redirect()
                ->route('lift-logs.index')
                ->with('error', 'No exercises found for: ' . implode(', ', $result['notFound']));
        } elseif ($result['importedCount'] === 0 && !empty($result['invalidRows'])) {
            return redirect()
                ->route('lift-logs.index')
                ->with('error', 'No lift logs imported due to invalid data in rows: ' . implode(', ', array_map(function($row) { return '"' . $row . '"' ; }, $result['invalidRows'])));
        } elseif (!empty($result['importedCount']) && !empty($result['invalidRows'])) {
            return redirect()
                ->route('lift-logs.index')
                ->with('success', 'TSV data imported successfully with some invalid rows. Invalid rows: ' . implode(', ', array_map(function($row) { return '"' . $row . '"' ; }, $result['invalidRows'])));
        }

        return redirect()
            ->route('lift-logs.index')
            ->with('success', 'TSV data imported successfully!');
    }
}