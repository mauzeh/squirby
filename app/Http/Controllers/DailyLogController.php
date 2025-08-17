<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $ingredients = Ingredient::all();
        $units = Unit::all();

        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $dailyLogs = DailyLog::with(['ingredient', 'unit'])
            ->whereDate('created_at', $selectedDate->toDateString())
            ->orderBy('created_at', 'asc')
            ->get();

        $dailyTotals = $this->calculateDailyTotals($dailyLogs);

        // Get all unique dates that have log entries, ordered descending
        $availableDates = DailyLog::select(DB::raw('DATE(created_at) as date'))
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date');

        return view('daily_logs.index', compact('ingredients', 'units', 'dailyLogs', 'dailyTotals', 'selectedDate', 'availableDates'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'unit_id' => ['required', 'exists:units,id', function ($attribute, $value, $fail) use ($request) {
                $ingredient = Ingredient::find($request->input('ingredient_id'));
                if ($ingredient && $ingredient->base_unit_id != $value) {
                    $fail('The selected unit must be the base unit for the ingredient.');
                }
            }],
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $logEntry = DailyLog::create($validated);

        return redirect()->route('daily_logs.index')->with('success', 'Log entry added successfully!');
    }

    /**
     * Calculate daily macro totals from a collection of DailyLog entries.
     */
    private function calculateDailyTotals($logs)
    {
        $totals = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'added_sugars' => 0,
            'fats' => 0,
            'sodium' => 0,
            'iron' => 0,
            'potassium' => 0,
        ];

        foreach ($logs as $log) {
            $totals['calories'] += $log->ingredient->calculateCaloriesForQuantity($log->quantity, $log->unit);
            $totals['protein'] += $log->ingredient->calculateProteinForQuantity($log->quantity, $log->unit);
            $totals['carbs'] += $log->ingredient->calculateCarbsForQuantity($log->quantity, $log->unit);
            $totals['added_sugars'] += $log->ingredient->calculateAddedSugarsForQuantity($log->quantity, $log->unit);
            $totals['fats'] += $log->ingredient->calculateFatsForQuantity($log->quantity, $log->unit);
            $totals['sodium'] += $log->ingredient->calculateSodiumForQuantity($log->quantity, $log->unit);
            $totals['iron'] += $log->ingredient->calculateIronForQuantity($log->quantity, $log->unit);
            $totals['potassium'] += $log->ingredient->calculatePotassiumForQuantity($log->quantity, $log->unit);
        }

        return $totals;
    }
}
