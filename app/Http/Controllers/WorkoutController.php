<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Workout;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workouts = Workout::with('exercise')->orderBy('logged_at', 'desc')->get();
        return view('workouts.index', compact('workouts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $exercises = Exercise::all();
        return view('workouts.create', compact('exercises'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'working_set_weight' => 'required|numeric',
            'working_set_reps' => 'required|integer',
            'working_set_rounds' => 'required|integer',
            'warmup_sets_comments' => 'nullable|string',
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
            'working_set_weight' => 'required|numeric',
            'working_set_reps' => 'required|integer',
            'working_set_rounds' => 'required|integer',
            'warmup_sets_comments' => 'nullable|string',
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
}