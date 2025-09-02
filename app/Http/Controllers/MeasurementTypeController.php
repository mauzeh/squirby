<?php

namespace App\Http\Controllers;

use App\Models\MeasurementType;
use Illuminate\Http\Request;

class MeasurementTypeController extends Controller
{
    public function index()
    {
        $measurementTypes = MeasurementType::all();
        return view('measurement_types.index', compact('measurementTypes'));
    }

    public function create()
    {
        return view('measurement_types.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'default_unit' => 'required|string|max:255',
        ]);

        MeasurementType::create($request->all());

        return redirect()->route('measurement-types.index')->with('success', 'Measurement type created successfully.');
    }

    public function edit(MeasurementType $measurementType)
    {
        return view('measurement_types.edit', compact('measurementType'));
    }

    public function update(Request $request, MeasurementType $measurementType)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'default_unit' => 'required|string|max:255',
        ]);

        $measurementType->update($request->all());

        return redirect()->route('measurement-types.index')->with('success', 'Measurement type updated successfully.');
    }

    public function destroy(MeasurementType $measurementType)
    {
        $measurementType->delete();

        return redirect()->route('measurement-types.index')->with('success', 'Measurement type deleted successfully.');
    }
}