<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ingredients = Ingredient::all();
        $units = Unit::all();
        $todayLogs = DailyLog::with(['ingredient', 'unit'])
            ->whereDate('created_at', today())
            ->get();

        $dailyTotals = $this->calculateDailyTotals($todayLogs);

        return view('daily_logs.index', compact('ingredients', 'units', 'todayLogs', 'dailyTotals'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'unit_id' => 'required|exists:units,id',
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
            $ingredient = $log->ingredient;
            $quantityInGrams = $log->quantity; // Assuming quantity is already in grams or can be converted

            // For simplicity, assuming 1 unit of quantity is 1 gram for now.
            // A more robust solution would involve unit conversions.
            // For example, if unit is 'cups', convert cups to grams for the ingredient.
            // For this task, I will assume 'quantity' is directly proportional to 100g nutritional values.
            // If 'quantity' is in grams, then (quantity / 100) * nutritional_value.
            // If 'quantity' is in 'pieces', then it's more complex and depends on average weight per piece.

            // Let's assume 'quantity' is in grams for calculation purposes.
            // Nutritional values are per 100 grams.
            $factor = $quantityInGrams / 100;

            $totals['calories'] += $ingredient->calories * $factor;
            $totals['protein'] += $ingredient->protein * $factor;
            $totals['carbs'] += $ingredient->carbs * $factor;
            $totals['added_sugars'] += $ingredient->added_sugars * $factor;
            $totals['fats'] += $ingredient->fats * $factor;
            $totals['sodium'] += $ingredient->sodium * $factor;
            $totals['iron'] += $ingredient->iron * $factor;
            $totals['potassium'] += $ingredient->potassium * $factor;
        }

        return $totals;
    }
}
