<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Exercise;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ProgramTsvImporterService;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $exercises = Exercise::where('user_id', auth()->id())->orderBy('name')->get();
        $programs = Program::with('exercise')
            ->where('user_id', auth()->id())
            ->whereDate('date', $selectedDate->toDateString())
            ->orderBy('priority')
            ->get();

        return view('programs.index', compact('exercises', 'programs', 'selectedDate'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('name')->get();
        return view('programs.create', compact('exercises', 'date'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProgramRequest $request)
    {
        $validated = $request->validated();
        $program = new Program($validated);
        $program->user_id = auth()->id();
        $program->save();

        return redirect()->route('programs.index', ['date' => $validated['date']])->with('success', 'Program entry created.');
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

        $validated = $request->validated();
        $program->update($validated);

        return redirect()->route('programs.index', ['date' => $validated['date']])->with('success', 'Program entry updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Program $program)
    {
        if ($program->user_id !== auth()->id()) {
            abort(403);
        }

        $program->delete();

        $date = $request->input('date');

        return redirect()->route('programs.index', ['date' => $date])->with('success', 'Program entry deleted.');
    }

    /**
     * Remove the specified resources from storage.
     */
    public function destroySelected(Request $request)
    {
        $request->validate([
            'program_ids' => 'required|array',
            'program_ids.*' => 'exists:programs,id',
        ]);

        Program::whereIn('id', $request->program_ids)
            ->where('user_id', auth()->id())
            ->delete();

        $date = $request->input('date');

        return redirect()->route('programs.index', ['date' => $date])->with('success', 'Selected program entries deleted.');
    }

    public function import(Request $request, ProgramTsvImporterService $importerService)
    {
        $request->validate([
            'tsv_content' => 'required|string',
            'date' => 'required|date',
        ]);

        $dateForImport = Carbon::parse($request->input('date'));
        $tsvContent = $request->input('tsv_content');

        $result = $importerService->import($tsvContent, auth()->id(), $dateForImport);

        $message = 'Successfully imported ' . $result['importedCount'] . ' program entries.';
        if (count($result['notFound']) > 0) {
            $message .= ' Some exercises not found: ' . implode(', ', array_unique($result['notFound'])) . '.';
        }
        if (count($result['invalidRows']) > 0) {
            $message .= ' Some rows were invalid.';
        }

        if (count($result['notFound']) > 0 || count($result['invalidRows']) > 0) {
            return redirect()->route('programs.index', ['date' => $dateForImport->format('Y-m-d')])->withErrors($result['invalidRows'])->with('error', $message);
        } else {
            return redirect()->route('programs.index', ['date' => $dateForImport->format('Y-m-d')])->with('success', $message);
        }
    }
}
