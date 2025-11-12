<?php

namespace App\Http\Controllers;

use App\Models\BodyLog;
use App\Models\MeasurementType;

use App\Services\ChartService;
use App\Services\RedirectService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BodyLogController extends Controller
{
    protected $chartService;
    protected $redirectService;

    public function __construct(ChartService $chartService, RedirectService $redirectService)
    {
        $this->chartService = $chartService;
        $this->redirectService = $redirectService;
    }

    public function index()
    {
        $bodyLogs = BodyLog::with('measurementType')->where('user_id', auth()->id())->orderBy('logged_at', 'desc')->get();
        return view('body-logs.index', compact('bodyLogs'));
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

        // Check if entry already exists for this measurement type and date
        $existingLog = BodyLog::where('user_id', auth()->id())
            ->where('measurement_type_id', $request->measurement_type_id)
            ->whereDate('logged_at', $request->date)
            ->first();

        if ($existingLog) {
            // Update existing entry
            $existingLog->update([
                'value' => $request->value,
                'logged_at' => $loggedAt,
                'comments' => $request->comments,
            ]);
            $successMessage = 'Measurement updated successfully.';
        } else {
            // Create new entry
            BodyLog::create([
                'measurement_type_id' => $request->measurement_type_id,
                'value' => $request->value,
                'logged_at' => $loggedAt,
                'comments' => $request->comments,
                'user_id' => auth()->id(),
            ]);
            $successMessage = 'Measurement logged successfully.';
        }

        return $this->redirectService->getRedirect(
            'body_logs',
            'store',
            $request,
            [],
            $successMessage
        );
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

        return $this->redirectService->getRedirect(
            'body_logs',
            'update',
            $request,
            [],
            'Measurement updated successfully.'
        );
    }

    public function destroy(BodyLog $bodyLog)
    {
        if ($bodyLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        
        $bodyLog->delete();

        return $this->redirectService->getRedirect(
            'body_logs',
            'destroy',
            request(),
            [],
            'Measurement deleted successfully.'
        );
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

    public function showByType(MeasurementType $measurementType)
    {
        $bodyLogs = BodyLog::where('measurement_type_id', $measurementType->id)->where('user_id', auth()->id())->orderBy('logged_at', 'desc')->get();

        $chartData = $this->chartService->generateBodyLogChartData($bodyLogs, $measurementType);

        return view('body-logs.show-by-type', compact('bodyLogs', 'chartData', 'measurementType'));
    }
}