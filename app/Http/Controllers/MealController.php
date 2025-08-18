<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\Ingredient;
use App\Models\DailyLog;
use App\Services\NutritionService;
use Illuminate\Http\Request;

class MealController extends Controller
{
    protected $nutritionService;

    public function __construct(NutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $meals = Meal::with('ingredients')->get();

        foreach ($meals as $meal) {
            $meal->total_macros = $this->nutritionService->calculateDailyTotals($meal->ingredients);
        }

        return view('meals.index', compact('meals'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $ingredients = Ingredient::orderBy('name')->get();
        return view('meals.create', compact('ingredients'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:meals,name',
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'nullable|exists:ingredients,id',
            'ingredients.*.quantity' => 'nullable|numeric|min:0.01',
        ]);

        $meal = Meal::create(['name' => $request->name]);

        foreach ($request->ingredients as $item) {
            if (isset($item['ingredient_id']) && isset($item['quantity'])) {
                $meal->ingredients()->attach($item['ingredient_id'], ['quantity' => $item['quantity']]);
            }
        }

        return redirect()->route('meals.index')->with('success', 'Meal created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Meal $meal)
    {
        // Not needed as per user request
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Meal $meal)
    {
        $meal->load('ingredients');
        $ingredients = Ingredient::orderBy('name')->get();
        return view('meals.edit', compact('meal', 'ingredients'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Meal $meal)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:meals,name,' . $meal->id,
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'nullable|exists:ingredients,id',
            'ingredients.*.quantity' => 'nullable|numeric|min:0.01',
        ]);

        $meal->update(['name' => $request->name]);

        $syncData = [];
        foreach ($request->ingredients as $item) {
            if (isset($item['ingredient_id']) && isset($item['quantity'])) {
                $syncData[$item['ingredient_id']] = ['quantity' => $item['quantity']];
            }
        }
        $meal->ingredients()->sync($syncData);

        return redirect()->route('meals.index')->with('success', 'Meal updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Meal $meal)
    {
        $meal->delete();
        return redirect()->route('meals.index')->with('success', 'Meal deleted successfully.');
    }

    public function createFromLogs(Request $request)
    {
        $request->validate([
            'daily_log_ids' => 'required|array',
            'daily_log_ids.*' => 'exists:daily_logs,id',
            'meal_name' => 'required|string|max:255|unique:meals,name',
        ]);

        $meal = Meal::create(['name' => $request->meal_name]);

        $dailyLogs = DailyLog::whereIn('id', $request->daily_log_ids)->get();

        foreach ($dailyLogs as $log) {
            $meal->ingredients()->attach($log->ingredient_id, ['quantity' => $log->quantity]);
        }

        return redirect()->route('meals.index')->with('success', 'Meal created successfully from log entries.');
    }
}