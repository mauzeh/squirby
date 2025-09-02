<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\NutritionService;
use App\Services\TsvImporterService;
use Carbon\Carbon;

class DailyLogController extends Controller
{
    protected $nutritionService;
    protected $tsvImporterService;

    public function __construct(NutritionService $nutritionService, TsvImporterService $tsvImporterService)
    {
        $this->nutritionService = $nutritionService;
        $this->tsvImporterService = $tsvImporterService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Needed for the compact function
        $ingredients = Ingredient::where('user_id', auth()->id())->with('baseUnit')->orderBy('name')->get();
        $units = Unit::all();

        $meals = Meal::where('user_id', auth()->id())->get();
        $nutritionService = $this->nutritionService;

        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $dailyLogs = DailyLog::with(['ingredient', 'unit'])
            ->where('daily_logs.user_id', auth()->id())
            ->join('ingredients', 'daily_logs.ingredient_id', '=', 'ingredients.id')
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->orderBy('logged_at', 'desc')
            ->orderBy('ingredients.name', 'asc')
            ->select('daily_logs.*')
            ->get();

        $groupedLogs = $dailyLogs->groupBy(function ($log) {
            return $log->logged_at->format('Y-m-d H:i:s');
        });

        $dailyTotals = $nutritionService->calculateDailyTotals($dailyLogs);

        return view('daily_logs.index', compact('dailyLogs', 'groupedLogs', 'dailyTotals', 'ingredients', 'units', 'meals', 'selectedDate', 'nutritionService'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'logged_at' => 'required|date_format:H:i',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
        $validated['unit_id'] = $ingredient->base_unit_id;

        $loggedAtDate = Carbon::parse($validated['date']);
        $validated['logged_at'] = $loggedAtDate->setTimeFromTimeString($validated['logged_at']);

        $logEntry = DailyLog::create(array_merge($validated, ['user_id' => auth()->id()]));

        return redirect()->route('daily-logs.index', ['date' => $validated['date']])->with('success', 'Log entry added successfully!');
    }

    public function edit(DailyLog $dailyLog)
    {
        if ($dailyLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $ingredients = Ingredient::with('baseUnit')->orderBy('name')->get();
        return view('daily_logs.edit', compact('dailyLog', 'ingredients'));
    }

    public function update(Request $request, DailyLog $dailyLog)
    {
        if ($dailyLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'logged_at' => 'required|date_format:H:i',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
        $validated['unit_id'] = $ingredient->base_unit_id;

        $loggedAtDate = Carbon::parse($validated['date']);
        $validated['logged_at'] = $loggedAtDate->setTimeFromTimeString($validated['logged_at']);

        $dailyLog->update($validated);

        return redirect()->route('daily-logs.index', ['date' => $validated['date']])->with('success', 'Log entry updated successfully!');
    }

    public function destroy(DailyLog $dailyLog)
    {
        if ($dailyLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $date = $dailyLog->logged_at->format('Y-m-d');
        $dailyLog->delete();

        return redirect()->route('daily-logs.index', ['date' => $date])->with('success', 'Log entry deleted successfully!');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'daily_log_ids' => 'required|array',
            'daily_log_ids.*' => 'exists:daily_logs,id',
        ]);

        $dailyLogs = DailyLog::whereIn('id', $validated['daily_log_ids'])->get();

        foreach ($dailyLogs as $dailyLog) {
            if ($dailyLog->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        $date = $dailyLogs->first()->logged_at->format('Y-m-d');

        DailyLog::destroy($validated['daily_log_ids']);

        return redirect()->route('daily-logs.index', ['date' => $date])->with('success', 'Selected log entries deleted successfully!');
    }

    public function addMealToLog(Request $request)
    {
        $validated = $request->validate([
            'meal_id' => 'required|exists:meals,id',
            'portion' => 'required|numeric|min:0.05',
            'logged_at_meal' => 'required|date_format:H:i',
            'meal_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $meal = Meal::with('ingredients')->find($validated['meal_id']);

        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $selectedDate = Carbon::parse($validated['meal_date']);
        $loggedAt = $selectedDate->setTimeFromTimeString($validated['logged_at_meal']);

        foreach ($meal->ingredients as $ingredient) {
            $notes = $meal->name . ' (Portion: ' . (float)$validated['portion'] . ')';
            if (!empty($meal->comments)) {
                $notes .= ' - ' . $meal->comments;
            }
            if (!empty($validated['notes'])) {
                $notes .= ': ' . $validated['notes'];
            }

            DailyLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->base_unit_id,
                'quantity' => $ingredient->pivot->quantity * $validated['portion'],
                'logged_at' => $loggedAt,
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);
        }

        return redirect()->route('daily-logs.index', ['date' => $validated['meal_date']])->with('success', 'Meal added to log successfully!');
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'required|string',
            'date' => 'required|date',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('daily-logs.index', ['date' => $validated['date']])
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importDailyLogs($tsvData, $validated['date'], auth()->id());

        if ($result['importedCount'] === 0 && !empty($result['notFound'])) {
            return redirect()
                ->route('daily-logs.index', ['date' => $validated['date']])
                ->with('error', 'No ingredients found for: ' . implode(', ', $result['notFound']));
        }

        return redirect()
            ->route('daily-logs.index', ['date' => $validated['date']])
            ->with('success', 'TSV data imported successfully!');
    }
}