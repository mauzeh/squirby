<?php

namespace App\Http\Controllers;

use App\Models\BodyLog;
use App\Models\MeasurementType;
use App\Services\TsvImporterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BodyLogController extends Controller
{
    protected $tsvImporterService;

    public function __construct(TsvImporterService $tsvImporterService)
    {
        $this->tsvImporterService = $tsvImporterService;
    }

    public function index()
    {
        $bodyLogs = BodyLog::with('measurementType')->where('user_id', auth()->id())->orderBy('logged_at', 'desc')->get();
        $tsv = '';
        foreach ($bodyLogs->reverse() as $bodyLog) {
            $tsv .= $bodyLog->logged_at->format('m/d/Y') . "\t";
            $tsv .= $bodyLog->logged_at->format('H:i') . "\t";
            $tsv .= $bodyLog->measurementType->name . "\t";
            $tsv .= $bodyLog->value . "\t";
            $tsv .= $bodyLog->measurementType->default_unit . "\t";
            $tsv .= $bodyLog->comments . "\n";
        }
        return view('body-logs.index', compact('bodyLogs', 'tsv'));
    }

    public function create(Request $request)
    {
        $measurementTypes = MeasurementType::where('user_id', auth()->id())->get();
        $selectedMeasurementTypeId = $request->query('measurement_type_id');
        return view('body-logs.create', compact('measurementTypes', 'selectedMeasurementTypeId'));
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

        BodyLog::create([
            'measurement_type_id' => $request->measurement_type_id,
            'value' => $request->value,
            'logged_at' => $loggedAt,
            'comments' => $request->comments,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('body-logs.index')->with('success', 'Body log created successfully.');
    }

    public function edit(BodyLog $bodyLog)
    {
        if ($bodyLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $measurementTypes = MeasurementType::where('user_id', auth()->id())->get();
        return view('body-logs.edit', compact('bodyLog', 'measurementTypes'));
    }

    public function update(Request $request, BodyLog $bodyLog)
    {
        if ($bodyLog->user_id !== auth()->id()) {
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

        $bodyLog->update([
            'measurement_type_id' => $request->measurement_type_id,
            'value' => $request->value,
            'logged_at' => $loggedAt,
            'comments' => $request->comments,
        ]);

        return redirect()->route('body-logs.index')->with('success', 'Body log updated successfully.');
    }

    public function destroy(BodyLog $bodyLog)
    {
        if ($bodyLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $bodyLog->delete();

        return redirect()->route('body-logs.index')->with('success', 'Body log deleted successfully.');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'body_log_ids' => 'required|array',
            'body_log_ids.*' => 'exists:body_logs,id',
        ]);

        $bodyLogs = BodyLog::whereIn('id', $validated['body_log_ids'])->get();

        foreach ($bodyLogs as $bodyLog) {
            if ($bodyLog->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        BodyLog::destroy($validated['body_log_ids']);

        return redirect()->route('body-logs.index')->with('success', 'Selected body logs deleted successfully!');
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'nullable|string',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('body-logs.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importMeasurements($tsvData, auth()->id());

        if ($result['importedCount'] === 0) {
            return redirect()
                ->route('body-logs.index')
                ->with('error', 'No measurements were imported.');
        }

        return redirect()
            ->route('body-logs.index')
            ->with('success', 'TSV data imported successfully!');
    }

    public function showByType(MeasurementType $measurementType)
    {
        $bodyLogs = BodyLog::where('measurement_type_id', $measurementType->id)->where('user_id', auth()->id())->orderBy('logged_at', 'desc')->get();

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

        if ($bodyLogs->isNotEmpty()) {
            $earliestDate = $bodyLogs->last()->logged_at->startOfDay();
            $latestDate = $bodyLogs->first()->logged_at->endOfDay();

            $currentDate = $earliestDate->copy();
            $dataMap = $bodyLogs->keyBy(function ($item) {
                return $item->logged_at->format('Y-m-d');
            });

            while ($currentDate->lte($latestDate)) {
                $dateString = $currentDate->format('Y-m-d');
                $chartData['labels'][] = $currentDate->format('m/d');
                $chartData['datasets'][0]['data'][] = $dataMap->has($dateString) ? $dataMap[$dateString]->value : null;
                $currentDate->addDay();
            }
        }

        return view('body-logs.show-by-type', compact('bodyLogs', 'chartData', 'measurementType'));
    }
}