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

    public function __construct(ExerciseService $exerciseService, \App\Services\ChartService $chartService)
    {
        $this->exerciseService = $exerciseService;
        $this->chartService = $chartService;
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
}