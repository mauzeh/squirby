<?php

namespace App\Http\Controllers;

use App\Models\MeasurementLog;
use App\Models\MeasurementType;
use Illuminate\Http\Request;

class MeasurementLogController extends Controller
{
    public function index()
    {
        $measurementLogs = MeasurementLog::with('measurementType')->orderBy('logged_at', 'desc')->get();
        return view('measurements.index', compact('measurementLogs'));
    }

    public function create()
    {
        $measurementTypes = MeasurementType::all();
        return view('measurements.create', compact('measurementTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'measurement_type_id' => 'required|exists:measurement_types,id',
            'value' => 'required|numeric',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
            'comments' => 'nullable|string',
        ]);

        $loggedAt = \Carbon\Carbon::parse($request->date)->setTimeFromTimeString($request->logged_at);

        MeasurementLog::create([
            'measurement_type_id' => $request->measurement_type_id,
            'value' => $request->value,
            'logged_at' => $loggedAt,
            'comments' => $request->comments,
        ]);

        return redirect()->route('measurements.index')->with('success', 'Measurement log created successfully.');
    }

    public function edit(MeasurementLog $measurementLog)
    {
        $measurementTypes = MeasurementType::all();
        return view('measurements.edit', compact('measurementLog', 'measurementTypes'));
    }

    public function update(Request $request, MeasurementLog $measurementLog)
    {
        $request->validate([
            'measurement_type_id' => 'required|exists:measurement_types,id',
            'value' => 'required|numeric',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
            'comments' => 'nullable|string',
        ]);

        $loggedAt = \Carbon\Carbon::parse($request->date)->setTimeFromTimeString($request->logged_at);

        $measurementLog->update([
            'measurement_type_id' => $request->measurement_type_id,
            'value' => $request->value,
            'logged_at' => $loggedAt,
            'comments' => $request->comments,
        ]);

        return redirect()->route('measurements.index')->with('success', 'Measurement log updated successfully.');
    }

    public function destroy(MeasurementLog $measurementLog)
    {
        $measurementLog->delete();

        return redirect()->route('measurements.index')->with('success', 'Measurement log deleted successfully.');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'measurement_log_ids' => 'required|array',
            'measurement_log_ids.*' => 'exists:measurement_logs,id',
        ]);

        MeasurementLog::destroy($validated['measurement_log_ids']);

        return redirect()->route('measurements.index')->with('success', 'Selected measurement logs deleted successfully!');
    }

    public function showByType(MeasurementType $measurementType)
    {
        $measurementLogs = MeasurementLog::where('measurement_type_id', $measurementType->id)->orderBy('logged_at', 'desc')->get();

        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => $measurementType->name,
                    'data' => [],
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'backgroundColor' => 'rgba(255, 99, 132, 1)',
                    'fill' => false,
                ],
            ],
        ];

        if ($measurementLogs->isNotEmpty()) {
            $earliestDate = $measurementLogs->last()->logged_at->startOfDay();
            $latestDate = $measurementLogs->first()->logged_at->endOfDay();

            $currentDate = $earliestDate->copy();
            $dataMap = $measurementLogs->keyBy(function ($item) {
                return $item->logged_at->format('Y-m-d');
            });

            while ($currentDate->lte($latestDate)) {
                $dateString = $currentDate->format('Y-m-d');
                $chartData['labels'][] = $currentDate->format('m/d');
                $chartData['datasets'][0]['data'][] = $dataMap->has($dateString) ? $dataMap[$dateString]->value : null;
                $currentDate->addDay();
            }
        }

        return view('measurements.show-by-type', compact('measurementLogs', 'chartData', 'measurementType'));
    }
}
