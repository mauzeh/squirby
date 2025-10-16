<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Http\Requests\StoreExerciseIntelligenceRequest;
use App\Http\Requests\UpdateExerciseIntelligenceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExerciseIntelligenceController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * Display a listing of exercises with their intelligence data.
     */
    public function index(): View
    {
        $exercises = Exercise::global()
            ->with('intelligence')
            ->orderBy('title', 'asc')
            ->get();

        return view('exercise-intelligence.index', compact('exercises'));
    }

    /**
     * Show the form for creating intelligence data for an exercise.
     */
    public function create(Exercise $exercise): View
    {
        // Ensure the exercise is global
        if (!$exercise->isGlobal()) {
            abort(403, 'Intelligence data can only be added to global exercises.');
        }

        // Check if intelligence already exists
        if ($exercise->hasIntelligence()) {
            return redirect()->route('exercise-intelligence.edit', $exercise->intelligence)
                ->with('info', 'Intelligence data already exists for this exercise. You can edit it here.');
        }

        return view('exercise-intelligence.create', compact('exercise'));
    }

    /**
     * Store newly created intelligence data.
     */
    public function store(StoreExerciseIntelligenceRequest $request, Exercise $exercise): RedirectResponse
    {
        // Ensure the exercise is global
        if (!$exercise->isGlobal()) {
            abort(403, 'Intelligence data can only be added to global exercises.');
        }

        // Check if intelligence already exists
        if ($exercise->hasIntelligence()) {
            return redirect()->route('exercise-intelligence.edit', $exercise->intelligence)
                ->with('error', 'Intelligence data already exists for this exercise.');
        }

        $validated = $request->validated();

        $intelligence = new ExerciseIntelligence($validated);
        $intelligence->exercise_id = $exercise->id;
        $intelligence->save();

        return redirect()->route('exercise-intelligence.index')
            ->with('success', "Intelligence data created successfully for '{$exercise->title}'.");
    }

    /**
     * Show the form for editing intelligence data.
     */
    public function edit(ExerciseIntelligence $intelligence): View
    {
        $intelligence->load('exercise');
        
        return view('exercise-intelligence.edit', compact('intelligence'));
    }

    /**
     * Update the specified intelligence data.
     */
    public function update(UpdateExerciseIntelligenceRequest $request, ExerciseIntelligence $intelligence): RedirectResponse
    {
        $validated = $request->validated();

        $intelligence->update($validated);

        return redirect()->route('exercise-intelligence.index')
            ->with('success', "Intelligence data updated successfully for '{$intelligence->exercise->title}'.");
    }

    /**
     * Remove the specified intelligence data.
     */
    public function destroy(ExerciseIntelligence $intelligence): RedirectResponse
    {
        $exerciseTitle = $intelligence->exercise->title;
        $intelligence->delete();

        return redirect()->route('exercise-intelligence.index')
            ->with('success', "Intelligence data removed successfully for '{$exerciseTitle}'.");
    }


}