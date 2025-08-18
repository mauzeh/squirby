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
        $ingredients = Ingredient::with('baseUnit')->get();
        $units = Unit::all();

        $meals = Meal::all();
        $nutritionService = $this->nutritionService;

        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $dailyLogs = DailyLog::with(['ingredient', 'unit'])
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->orderBy('logged_at', 'desc')
            ->get();

        $dailyTotals = $nutritionService->calculateDailyTotals($dailyLogs);

        return view('daily_logs.index', compact('dailyLogs', 'dailyTotals', 'ingredients', 'units', 'meals', 'selectedDate', 'nutritionService'));
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
        $ingredients = Ingredient::with('baseUnit')->get();
        return view('daily_logs.edit', compact('dailyLog', 'ingredients'));
    }

    public function update(Request $request, DailyLog $dailyLog)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'logged_at' => 'required|date_format:H:i',
            'date' => 'required|date',
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

    public function destroyDay(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $deletedCount = DailyLog::whereDate('logged_at', $validated['date'])->delete();

        return redirect()->route('daily-logs.index', ['date' => $validated['date']])->with('success', 'All logs for the day deleted successfully!');
    }

    public function addMealToLog(Request $request)
    {
        $validated = $request->validate([
            'meal_id' => 'required|exists:meals,id',
            'portion' => 'required|numeric|min:0.05',
            'logged_at_meal' => 'required|date_format:H:i',
            'meal_date' => 'required|date',
        ]);

        $meal = Meal::with('ingredients')->find($validated['meal_id']);

        $selectedDate = Carbon::parse($validated['meal_date']);
        $loggedAt = $selectedDate->setTimeFromTimeString($validated['logged_at_meal']);

        foreach ($meal->ingredients as $ingredient) {
            DailyLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->base_unit_id,
                'quantity' => $ingredient->pivot->quantity * $validated['portion'],
                'logged_at' => $loggedAt,
            ]);
        }

        return redirect()->route('daily-logs.index', ['date' => $validated['meal_date']])->with('success', 'Meal added to log successfully!');
    }

    public function showExportForm()
    {
        return view('daily_logs.export');
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $dailyLogs = DailyLog::with(['ingredient', 'unit'])
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->orderBy('logged_at', 'desc')
            ->get();

        $fileName = 'daily_log_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Time', 'Ingredient', 'Quantity', 'Unit', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fats (g)', 'Added Sugars (g)', 'Sodium (mg)', 'Iron (mg)', 'Potassium (mg)', 'Cost');

        $callback = function() use($dailyLogs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($dailyLogs as $log) {
                $row['Time']  = $log->logged_at->format('H:i');
                $row['Ingredient']    = $log->ingredient->name;
                $row['Quantity']    = $log->quantity;
                $row['Unit']  = $log->unit->name;
                $row['Calories'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', $log->quantity));
                $row['Protein (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', $log->quantity), 1);
                $row['Carbs (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'carbs', $log->quantity), 1);
                $row['Fats (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fats', $log->quantity), 1);
                $row['Added Sugars (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'added_sugars', $log->quantity), 1);
                $row['Sodium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'sodium', $log->quantity), 1);
                $row['Iron (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'iron', $log->quantity), 1);
                $row['Potassium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'potassium', $log->quantity), 1);
                $row['Cost'] = number_format($this->nutritionService->calculateCostForQuantity($log->ingredient, $log->quantity), 2);

                fputcsv($file, array($row['Time'], $row['Ingredient'], $row['Quantity'], $row['Unit'], $row['Calories'], $row['Protein (g)'], $row['Carbs (g)'], $row['Fats (g)'], $row['Added Sugars (g)'], $row['Sodium (mg)'], $row['Iron (mg)'], $row['Potassium (mg)'], $row['Cost']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}