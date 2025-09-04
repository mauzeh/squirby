<?php

namespace App\Http\Controllers;

use App\Models\MeasurementLog;
use App\Models\MeasurementType;
use App\Services\TsvImporterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class MeasurementLogController extends Controller
{
    protected $tsvImporterService;

    public function __construct(TsvImporterService $tsvImporterService)
    {
        $this->tsvImporterService = $tsvImporterService;
    }

    public function index()
    {
        $measurementLogs = MeasurementLog::with('measurementType')->where('user_id', auth()->id())->orderBy('logged_at', 'desc')->get();
        $tsv = '';
        foreach ($measurementLogs->reverse() as $measurementLog) {
            $tsv .= $measurementLog->logged_at->format('m/d/Y') . "\t";
            $tsv .= $measurementLog->logged_at->format('H:i') . "\t";
            $tsv .= $measurementLog->measurementType->name . "\t";
            $tsv .= $measurementLog->value . "\t";
            $tsv .= $measurementLog->measurementType->default_unit . "\t";
            $tsv .= $measurementLog->comments . "\n";
        }
        return view('measurement-logs.index', compact('measurementLogs', 'tsv'));
    }

    public function create(Request $request)
    {
        $measurementTypes = MeasurementType::where('user_id', auth()->id())->get();
        $selectedMeasurementTypeId = $request->query('measurement_type_id');
        return view('measurement-logs.create', compact('measurementTypes', 'selectedMeasurementTypeId'));
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
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('measurement-logs.index')->with('success', 'Measurement log created successfully.');
    }

    public function edit(MeasurementLog $measurementLog)
    {
        if ($measurementLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $measurementTypes = MeasurementType::where('user_id', auth()->id())->get();
        return view('measurement-logs.edit', compact('measurementLog', 'measurementTypes'));
    }

    public function update(Request $request, MeasurementLog $measurementLog)
    {
        if ($measurementLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
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

        return redirect()->route('measurement-logs.index')->with('success', 'Measurement log updated successfully.');
    }

    public function destroy(MeasurementLog $measurementLog)
    {
        if ($measurementLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $measurementLog->delete();

        return redirect()->route('measurement-logs.index')->with('success', 'Measurement log deleted successfully.');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'measurement_log_ids' => 'required|array',
            'measurement_log_ids.*' => 'exists:measurement_logs,id',
        ]);

        $measurementLogs = MeasurementLog::whereIn('id', $validated['measurement_log_ids'])->get();

        foreach ($measurementLogs as $measurementLog) {
            if ($measurementLog->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        MeasurementLog::destroy($validated['measurement_log_ids']);

        return redirect()->route('measurement-logs.index')->with('success', 'Selected measurement logs deleted successfully!');
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'required|string',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('measurement-logs.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importMeasurements($tsvData, auth()->id());

        if ($result['importedCount'] === 0) {
            return redirect()
                ->route('measurement-logs.index')
                ->with('error', 'No measurements were imported.');
        }

        return redirect()
            ->route('measurement-logs.index')
            ->with('success', 'TSV data imported successfully!');
    }

    public function showByType(MeasurementType $measurementType)
    {
        $measurementLogs = MeasurementLog::where('measurement_type_id', $measurementType->id)->where('user_id', auth()->id())->orderBy('logged_at', 'desc')->get();

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

        return view('measurement-logs.show-by-type', compact('measurementLogs', 'chartData', 'measurementType'));
    }
}