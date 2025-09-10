<?php

namespace App\Http\Controllers;

use App\Models\WorkoutProgram;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class WorkoutProgramController extends Controller
{
    /**
     * Display a listing of workout programs for a specific date.
     */
    public function index(Request $request)
    {
        // Get the selected date from request or default to today
        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        
        // Get all exercises for the current user for the creation form
        $exercises = Exercise::where('user_id', auth()->id())
            ->orderBy('title')
            ->get();
        
        // Get workout programs for the selected date
        $workoutPrograms = WorkoutProgram::with(['exercises' => function($query) {
                $query->orderByPivot('exercise_order');
            }])
            ->where('user_id', auth()->id())
            ->forDate($selectedDate)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('workout_programs.index', compact('workoutPrograms', 'exercises', 'selectedDate'));
    }

    /**
     * Show the form for creating a new workout program.
     */
    public function create(Request $request)
    {
        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        
        $exercises = Exercise::where('user_id', auth()->id())
            ->orderBy('title')
            ->get();
        
        return view('workout_programs.create', compact('exercises', 'selectedDate'));
    }

    /**
     * Store a newly created workout program in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'exercises' => 'required|array|min:1',
            'exercises.*.exercise_id' => 'required|exists:exercises,id',
            'exercises.*.sets' => 'required|integer|min:1|max:20',
            'exercises.*.reps' => 'required|integer|min:1|max:100',
            'exercises.*.notes' => 'nullable|string|max:255',
            'exercises.*.exercise_type' => 'required|in:main,accessory',
        ]);

        // Verify all exercises belong to the current user
        $exerciseIds = collect($validated['exercises'])->pluck('exercise_id');
        $userExercises = Exercise::where('user_id', auth()->id())
            ->whereIn('id', $exerciseIds)
            ->pluck('id');
        
        if ($userExercises->count() !== $exerciseIds->count()) {
            return back()->withErrors(['exercises' => 'One or more exercises do not belong to you.']);
        }

        // Create the workout program
        $workoutProgram = WorkoutProgram::create([
            'user_id' => auth()->id(),
            'date' => $validated['date'],
            'name' => $validated['name'],
            'notes' => $validated['notes'],
        ]);

        // Attach exercises with pivot data
        foreach ($validated['exercises'] as $index => $exerciseData) {
            $workoutProgram->exercises()->attach($exerciseData['exercise_id'], [
                'sets' => $exerciseData['sets'],
                'reps' => $exerciseData['reps'],
                'notes' => $exerciseData['notes'],
                'exercise_order' => $index + 1,
                'exercise_type' => $exerciseData['exercise_type'],
            ]);
        }

        return redirect()->route('workout-programs.index', ['date' => $validated['date']])
            ->with('success', 'Workout program created successfully!');
    }

    /**
     * Display the specified workout program.
     */
    public function show(WorkoutProgram $workoutProgram)
    {
        // Ensure user can only view their own programs
        if ($workoutProgram->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $workoutProgram->load(['exercises' => function($query) {
            $query->orderByPivot('exercise_order');
        }]);

        return view('workout_programs.show', compact('workoutProgram'));
    }

    /**
     * Show the form for editing the specified workout program.
     */
    public function edit(WorkoutProgram $workoutProgram)
    {
        // Ensure user can only edit their own programs
        if ($workoutProgram->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $workoutProgram->load(['exercises' => function($query) {
            $query->orderByPivot('exercise_order');
        }]);

        $exercises = Exercise::where('user_id', auth()->id())
            ->orderBy('title')
            ->get();

        return view('workout_programs.edit', compact('workoutProgram', 'exercises'));
    }

    /**
     * Update the specified workout program in storage.
     */
    public function update(Request $request, WorkoutProgram $workoutProgram)
    {
        // Ensure user can only update their own programs
        if ($workoutProgram->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'exercises' => 'required|array|min:1',
            'exercises.*.exercise_id' => 'required|exists:exercises,id',
            'exercises.*.sets' => 'required|integer|min:1|max:20',
            'exercises.*.reps' => 'required|integer|min:1|max:100',
            'exercises.*.notes' => 'nullable|string|max:255',
            'exercises.*.exercise_type' => 'required|in:main,accessory',
        ]);

        // Verify all exercises belong to the current user
        $exerciseIds = collect($validated['exercises'])->pluck('exercise_id');
        $userExercises = Exercise::where('user_id', auth()->id())
            ->whereIn('id', $exerciseIds)
            ->pluck('id');
        
        if ($userExercises->count() !== $exerciseIds->count()) {
            return back()->withErrors(['exercises' => 'One or more exercises do not belong to you.']);
        }

        // Update the workout program
        $workoutProgram->update([
            'date' => $validated['date'],
            'name' => $validated['name'],
            'notes' => $validated['notes'],
        ]);

        // Detach all existing exercises and reattach with new data
        $workoutProgram->exercises()->detach();
        
        foreach ($validated['exercises'] as $index => $exerciseData) {
            $workoutProgram->exercises()->attach($exerciseData['exercise_id'], [
                'sets' => $exerciseData['sets'],
                'reps' => $exerciseData['reps'],
                'notes' => $exerciseData['notes'],
                'exercise_order' => $index + 1,
                'exercise_type' => $exerciseData['exercise_type'],
            ]);
        }

        return redirect()->route('workout-programs.index', ['date' => $validated['date']])
            ->with('success', 'Workout program updated successfully!');
    }

    /**
     * Remove the specified workout program from storage.
     */
    public function destroy(WorkoutProgram $workoutProgram)
    {
        // Ensure user can only delete their own programs
        if ($workoutProgram->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $date = $workoutProgram->date->format('Y-m-d');
        
        // Delete the program (exercises will be detached automatically due to cascade)
        $workoutProgram->delete();

        return redirect()->route('workout-programs.index', ['date' => $date])
            ->with('success', 'Workout program deleted successfully!');
    }
}