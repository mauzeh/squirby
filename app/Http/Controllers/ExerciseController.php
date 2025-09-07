<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExerciseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $exercises = Exercise::where('user_id', auth()->id())->get();
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

    public function showLogs(Exercise $exercise)
    {
        if ($exercise->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $workouts = $exercise->workouts()->with('workoutSets')->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();

        $chartData = [
            'datasets' => [
                [
                    'label' => '1RM (est.)',
                    'data' => $workouts->map(function ($workout) {
                        return [
                            'x' => $workout->logged_at->toIso8601String(),
                            'y' => $workout->best_one_rep_max,
                        ];
                    }),
                    'backgroundColor' => 'rgba(0, 123, 255, 0.5)',
                    'borderColor' => 'rgba(0, 123, 255, 1)',
                    'borderWidth' => 1
                ]
            ]
        ];

        $workouts = $workouts->reverse();

        return view('exercises.logs', compact('exercise', 'workouts', 'chartData'));
    }
}