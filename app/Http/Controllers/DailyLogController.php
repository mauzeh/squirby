<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NutritionService;
use Carbon\Carbon;

class DailyLogController extends Controller
{
    protected $nutritionService;

    public function __construct(NutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Needed for the compact function
        $ingredients = Ingredient::with('baseUnit')->orderBy('name')->get();
        $units = Unit::all();

        $meals = Meal::all();
        $nutritionService = $this->nutritionService;

        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $dailyLogs = DailyLog::with(['ingredient', 'unit'])
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->orderBy('logged_at', 'desc')
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

        $logEntry = DailyLog::create($validated);

        return redirect()->route('daily-logs.index', ['date' => $validated['date']])->with('success', 'Log entry added successfully!');
    }

    public function edit(DailyLog $dailyLog)
    {
        $ingredients = Ingredient::with('baseUnit')->orderBy('name')->get();
        return view('daily_logs.edit', compact('dailyLog', 'ingredients'));
    }

    public function update(Request $request, DailyLog $dailyLog)
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

        $dailyLog->update($validated);

        return redirect()->route('daily-logs.index', ['date' => $validated['date']])->with('success', 'Log entry updated successfully!');
    }

    public function destroy(DailyLog $dailyLog)
    {
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

        $date = DailyLog::find($validated['daily_log_ids'][0])->logged_at->format('Y-m-d');

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

        $selectedDate = Carbon::parse($validated['meal_date']);
        $loggedAt = $selectedDate->setTimeFromTimeString($validated['logged_at_meal']);

        foreach ($meal->ingredients as $ingredient) {
            $notes = $meal->name . ' (Portion: ' . (float)$validated['portion'] . ')';
            if (!empty($validated['notes'])) {
                $notes .= ': ' . $validated['notes'];
            }

            DailyLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->base_unit_id,
                'quantity' => $ingredient->pivot->quantity * $validated['portion'],
                'logged_at' => $loggedAt,
                'notes' => $notes,
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

        $tsvData = $validated['tsv_data'];
        $date = Carbon::parse($validated['date']);

        $rows = explode("\n", $tsvData);

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = str_getcsv($row, "\t");

            // Skip row if it doesn't have the expected number of columns
            if (count($columns) < 5) {
                continue;
            }

            $ingredient = Ingredient::where('name', $columns[2])->first();

            if ($ingredient) {
                $loggedAt = Carbon::parse($date->format('Y-m-d') . ' ' . $columns[1]);

                DailyLog::create([
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $ingredient->base_unit_id,
                    'quantity' => $columns[4],
                    'logged_at' => $loggedAt,
                    'notes' => $columns[3],
                ]);
            }
        }

        return redirect()->route('daily-logs.index', ['date' => $date->format('Y-m-d')])->with('success', 'TSV data imported successfully!');
    }
}