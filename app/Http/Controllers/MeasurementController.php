<?php

namespace App\Http\Controllers;

use App\Models\Measurement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeasurementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $measurements = Measurement::orderBy('logged_at', 'desc')->get();
        return view('measurements.index', compact('measurements'));
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
            'unit' => ['required', Rule::in(['lbs', 'in', 'cm'])],
            'logged_at' => 'required|date',
        ]);

        Measurement::create($request->all());

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
            'unit' => ['required', Rule::in(['lbs', 'in', 'cm'])],
            'logged_at' => 'required|date',
        ]);

        $measurement->update($request->all());

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
}