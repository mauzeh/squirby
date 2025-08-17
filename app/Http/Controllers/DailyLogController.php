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

        // Get all unique dates that have log entries, ordered descending
        $availableDates = DailyLog::select(DB::raw('DATE(logged_at) as date'))
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date');

        return view('daily_logs.index', compact('ingredients', 'units', 'dailyLogs', 'dailyTotals', 'selectedDate', 'availableDates', 'nutritionService', 'meals'));
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
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
        $validated['unit_id'] = $ingredient->base_unit_id;

        $selectedDate = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        $validated['logged_at'] = $selectedDate->setTimeFromTimeString($validated['logged_at']);

        $logEntry = DailyLog::create($validated);

        return redirect()->route('daily-logs.index')->with('success', 'Log entry added successfully!');
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
        ]);

        $ingredient = Ingredient::find($validated['ingredient_id']);
        $validated['unit_id'] = $ingredient->base_unit_id;

        $loggedAt = Carbon::parse($dailyLog->logged_at);
        $validated['logged_at'] = $loggedAt->setTimeFromTimeString($validated['logged_at']);

        $dailyLog->update($validated);

        return redirect()->route('daily-logs.index')->with('success', 'Log entry updated successfully!');
    }

    public function destroy(DailyLog $dailyLog)
    {
        $dailyLog->delete();

        return redirect()->route('daily-logs.index')->with('success', 'Log entry deleted successfully!');
    }

    public function addMealToLog(Request $request)
    {
        $validated = $request->validate([
            'meal_id' => 'required|exists:meals,id',
            'portion' => 'required|numeric|min:0.01',
        ]);

        $meal = Meal::with('ingredients')->find($validated['meal_id']);

        foreach ($meal->ingredients as $ingredient) {
            DailyLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->base_unit_id,
                'quantity' => $ingredient->pivot->quantity * $validated['portion'],
                'logged_at' => now()->timezone(config('app.timezone')),
            ]);
        }

        return redirect()->route('daily-logs.index')->with('success', 'Meal added to log successfully!');
    }
}

