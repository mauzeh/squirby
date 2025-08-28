<?php

namespace App\Http\Controllers;

use App\Models\Measurement;
use App\Services\TsvImporterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class MeasurementController extends Controller
{
    protected $tsvImporterService;

    public function __construct(TsvImporterService $tsvImporterService)
    {
        $this->tsvImporterService = $tsvImporterService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $measurements = Measurement::orderBy('logged_at', 'desc')->get();
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

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'required|string',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('measurements.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importMeasurements($tsvData);

        if ($result['importedCount'] === 0) {
            return redirect()
                ->route('measurements.index')
                ->with('error', 'No measurements were imported.');
        }

        return redirect()
            ->route('measurements.index')
            ->with('success', 'TSV data imported successfully!');
    }

    public function showByName($name)
    {
        $measurements = Measurement::where('name', $name)->orderBy('logged_at', 'desc')->get();

        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => $name,
                    'data' => [],
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'backgroundColor' => 'rgba(255, 99, 132, 1)',
                    'fill' => false,
                ],
            ],
        ];

        if ($measurements->isNotEmpty()) {
            $startDate = $measurements->first()->logged_at->startOfDay();
            $endDate = $measurements->last()->logged_at->endOfDay();

            $currentDate = $startDate->copy();
            $dataMap = $measurements->keyBy(function ($item) {
                return $item->logged_at->format('Y-m-d');
            });

            while ($currentDate->lte($endDate)) {
                $dateString = $currentDate->format('Y-m-d');
                $chartData['labels'][] = $currentDate->format('m/d');
                $chartData['datasets'][0]['data'][] = $dataMap->has($dateString) ? $dataMap[$dateString]->value : null;
                $currentDate->addDay();
            }
        }

        return view('measurements.show-by-name', compact('measurements', 'chartData', 'name'));
    }
}