<?php

namespace App\Http\Controllers;

use App\Models\MeasurementType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeasurementTypeController extends Controller
{
    public function index()
    {
        $measurementTypes = MeasurementType::where('user_id', auth()->id())->get();
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

        MeasurementType::create(array_merge($request->all(), ['user_id' => auth()->id()]));

        return redirect()->route('measurement-types.index')->with('success', 'Measurement type created successfully.');
    }

    public function edit(MeasurementType $measurementType)
    {
        if ($measurementType->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        return view('measurement_types.edit', compact('measurementType'));
    }

    public function update(Request $request, MeasurementType $measurementType)
    {
        if ($measurementType->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'default_unit' => 'required|string|max:255',
        ]);

        $measurementType->update($request->all());

        return redirect()->route('measurement-types.index')->with('success', 'Measurement type updated successfully.');
    }

    public function destroy(MeasurementType $measurementType)
    {
        if ($measurementType->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $measurementType->delete();

        return redirect()->route('measurement-types.index')->with('success', 'Measurement type deleted successfully.');
    }
}