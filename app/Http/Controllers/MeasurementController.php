<?php

namespace App\Http\Controllers;

use App\Models\Measurement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class MeasurementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $measurements = Measurement::orderBy('logged_at', 'asc')->get();
        $tsv = '';
        foreach ($measurements as $measurement) {
            $tsv .= $measurement->logged_at->format('m/d/Y') . "	";
            $tsv .= $measurement->logged_at->format('H:i') . "	";
            $tsv .= $measurement->name . "	";
            $tsv .= $measurement->value . "	";
            $tsv .= $measurement->unit . "	";
            $tsv .= $measurement->comments . "
";
        }
        return view('measurements.index', compact('measurements', 'tsv'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('measurements.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', Rule::in(['Waist', 'Arm', 'Chest', 'Bodyweight'])],
            'value' => 'required|numeric',
            'unit' => ['required', Rule::in(['lbs', 'kg', 'in', 'cm'])],
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
            'comments' => 'nullable|string',
        ]);

        $loggedAt = \Carbon\Carbon::parse($request->date)->setTimeFromTimeString($request->logged_at);

        Measurement::create([
            'name' => $request->name,
            'value' => $request->value,
            'unit' => $request->unit,
            'logged_at' => $loggedAt,
            'comments' => $request->comments,
        ]);

        return redirect()->route('measurements.index')->with('success', 'Measurement created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Measurement $measurement)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Measurement $measurement)
    {
        return view('measurements.edit', compact('measurement'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Measurement $measurement)
    {
        $request->validate([
            'name' => ['required', Rule::in(['Waist', 'Arm', 'Chest', 'Bodyweight'])],
            'value' => 'required|numeric',
            'unit' => ['required', Rule::in(['lbs', 'kg', 'in', 'cm'])],
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
            'comments' => 'nullable|string',
        ]);

        $loggedAt = \Carbon\Carbon::parse($request->date)->setTimeFromTimeString($request->logged_at);

        $measurement->update([
            'name' => $request->name,
            'value' => $request->value,
            'unit' => $request->unit,
            'logged_at' => $loggedAt,
            'comments' => $request->comments,
        ]);

        return redirect()->route('measurements.index')->with('success', 'Measurement updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Measurement $measurement)
    {
        $measurement->delete();

        return redirect()->route('measurements.index')->with('success', 'Measurement deleted successfully.');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'measurement_ids' => 'required|array',
            'measurement_ids.*' => 'exists:measurements,id',
        ]);

        Measurement::destroy($validated['measurement_ids']);

        return redirect()->route('measurements.index')->with('success', 'Selected measurements deleted successfully!');
    }
}