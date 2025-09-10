<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Exercise;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use Carbon\Carbon;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('name')->get();
        $programs = Program::with('exercise')
            ->where('user_id', auth()->id())
            ->whereDate('date', Carbon::today())
            ->get();

        return view('programs.index', compact('exercises', 'programs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Not used, form is on the index page.
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProgramRequest $request)
    {
        $program = new Program($request->validated());
        $program->user_id = auth()->id();
        $program->save();

        return redirect()->route('programs.index')->with('success', 'Program entry created.');
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

        $exercises = Exercise::where('user_id', auth()->id())->orderBy('name')->get();

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

        $program->update($request->validated());

        return redirect()->route('programs.index')->with('success', 'Program entry updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program)
    {
        if ($program->user_id !== auth()->id()) {
            abort(403);
        }

        $program->delete();

        return redirect()->route('programs.index')->with('success', 'Program entry deleted.');
    }
}