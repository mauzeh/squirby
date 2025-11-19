<?php

namespace App\Http\Controllers;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\NutritionService;
use App\Services\MobileEntry\FoodLogService;
use App\Services\DateNavigationService;
use App\Services\RedirectService;
use Carbon\Carbon;

class FoodLogController extends Controller
{
    protected $nutritionService;
    protected $dateNavigationService;
    protected $foodLogService;
    protected $redirectService;

    public function __construct(
        NutritionService $nutritionService,
        DateNavigationService $dateNavigationService,
        FoodLogService $foodLogService,
        RedirectService $redirectService
    ) {
        $this->nutritionService = $nutritionService;
        $this->dateNavigationService = $dateNavigationService;
        $this->foodLogService = $foodLogService;
        $this->redirectService = $redirectService;
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

        $selectedDate = $this->dateNavigationService->parseSelectedDate($request->input('date'));

        $foodLogs = FoodLog::with(['ingredient', 'unit'])
            ->where('food_logs.user_id', auth()->id())
            ->join('ingredients', 'food_logs.ingredient_id', '=', 'ingredients.id')
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->orderBy('logged_at', 'desc')
            ->orderBy('ingredients.name', 'asc')
            ->select('food_logs.*')
            ->get();

        $groupedLogs = $foodLogs->groupBy(function ($log) {
            return $log->logged_at->format('Y-m-d H:i:s');
        });

        $dailyTotals = $nutritionService->calculateFoodLogTotals($foodLogs);

        // Get date navigation data
        $navigationData = $this->dateNavigationService->getNavigationData(
            $selectedDate,
            FoodLog::class,
            auth()->id(),
            'food-logs.index'
        );

        return view('food_logs.index', compact('foodLogs', 'groupedLogs', 'dailyTotals', 'ingredients', 'units', 'meals', 'selectedDate', 'nutritionService', 'navigationData'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // Original desktop store logic
        $request->merge([
            'quantity' => str_replace(',', '.', $request->input('quantity')),
        ]);

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

        $logEntry = FoodLog::create(array_merge($validated, ['user_id' => auth()->id()]));

        // Handle mobile entry redirects
        if ($request->has('redirect_to') && in_array($request->input('redirect_to'), ['mobile-entry', 'mobile-entry-foods'])) {
            // Remove the mobile food form after successful logging
            $this->foodLogService->removeFormAfterLogging(
                auth()->id(),
                'ingredient',
                $validated['ingredient_id'],
                Carbon::parse($validated['date'])
            );

            // Generate celebratory message
            $celebratoryMessage = $this->generateCelebratoryMessage($logEntry);

            return $this->redirectService->getRedirect(
                'food_logs',
                'store',
                $request,
                [],
                $celebratoryMessage
            );
        }

        return $this->redirectService->getRedirect(
            'food_logs',
            'store',
            $request,
            [],
            'Log entry added successfully!'
        );
    }



    public function edit(FoodLog $foodLog, FoodLogService $foodLogService)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $form = $foodLogService->generateEditForm($foodLog);

        // Add a title component
        $title = \App\Services\ComponentBuilder::title('Edit Food Log', $foodLog->logged_at->format('M d, Y - H:i'))->build();

        return view('mobile-entry.flexible', [
            'data' => [
                'components' => [$title, $form]
            ]
        ]);
    }

    public function update(Request $request, FoodLog $foodLog)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $validated = $request->validate([
            'ingredient_id' => 'sometimes|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'logged_at' => 'sometimes|date_format:H:i',
            'date' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['date']) && isset($validated['logged_at'])) {
            $loggedAtDate = Carbon::parse($validated['date']);
            $validated['logged_at'] = $loggedAtDate->setTimeFromTimeString($validated['logged_at']);
        }

        $foodLog->update($validated);

        $redirectDate = $validated['date'] ?? $foodLog->logged_at->format('Y-m-d');

        return redirect()->route('food-logs.index', ['date' => $redirectDate])->with('success', 'Log entry updated successfully!');
    }

    public function destroy(FoodLog $foodLog, Request $request)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $date = $foodLog->logged_at->format('Y-m-d');
        $foodLog->delete();

        return $this->redirectService->getRedirect(
            'food_logs',
            'destroy',
            $request,
            ['date' => $date],
            'Log entry deleted successfully!'
        );
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'food_log_ids' => 'required|array',
            'food_log_ids.*' => 'exists:food_logs,id',
        ]);

        $foodLogs = FoodLog::whereIn('id', $validated['food_log_ids'])->get();

        foreach ($foodLogs as $foodLog) {
            if ($foodLog->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        $date = $foodLogs->first()->logged_at->format('Y-m-d');

        FoodLog::destroy($validated['food_log_ids']);

        return redirect()->route('food-logs.index', ['date' => $date])->with('success', 'Selected log entries deleted successfully!');
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
            $notes = $meal->name . ' (Portion: ' . (float) $validated['portion'] . ')';
            if (!empty($meal->comments)) {
                $notes .= ' - ' . $meal->comments;
            }
            if (!empty($validated['notes'])) {
                $notes .= ': ' . $validated['notes'];
            }

            FoodLog::create([
                'ingredient_id' => $ingredient->id,
                'unit_id' => $ingredient->base_unit_id,
                'quantity' => $ingredient->pivot->quantity * $validated['portion'],
                'logged_at' => $loggedAt,
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);
        }

        // Handle mobile entry redirects
        if ($request->has('redirect_to') && in_array($request->input('redirect_to'), ['mobile-entry', 'mobile-entry-foods'])) {
            // Remove the mobile food form after successful logging
            $this->foodLogService->removeFormAfterLogging(
                auth()->id(),
                'meal',
                $validated['meal_id'],
                Carbon::parse($validated['meal_date'])
            );

            // Generate celebratory message for meal
            $celebratoryMessage = $this->generateMealCelebratoryMessage($meal, $validated['portion']);

            return $this->redirectService->getRedirect(
                'food_logs',
                'add_meal',
                $request,
                ['date' => $validated['meal_date']],
                $celebratoryMessage
            );
        }

        return $this->redirectService->getRedirect(
            'food_logs',
            'add_meal',
            $request,
            ['date' => $validated['meal_date']],
            'Meal added to log successfully!'
        );
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $foodLogs = FoodLog::with(['ingredient', 'unit'])
            ->where('user_id', auth()->id())
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->orderBy('logged_at', 'asc')
            ->get();

        $fileName = 'daily_log_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = array('Date', 'Time', 'Ingredient', 'Notes', 'Quantity', 'Unit', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fats (g)', 'Added Sugars (g)', 'Sodium (mg)', 'Iron (mg)', 'Potassium (mg)', 'Fiber (g)', 'Calcium (mg)', 'Caffeine (mg)', 'Cost');

        $callback = function () use ($foodLogs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($foodLogs as $log) {
                $row['Date'] = $log->logged_at->format('m/d/Y');
                $row['Time'] = $log->logged_at->format('H:i');
                $row['Ingredient'] = $log->ingredient->name;
                $row['Quantity'] = $log->quantity;
                $row['Unit'] = $log->unit->name;
                $row['Calories'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float) $log->quantity));
                $row['Protein (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float) $log->quantity), 1);
                $row['Carbs (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'carbs', (float) $log->quantity), 1);
                $row['Fats (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fats', (float) $log->quantity), 1);
                $row['Added Sugars (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'added_sugars', (float) $log->quantity), 1);
                $row['Sodium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'sodium', (float) $log->quantity), 1);
                $row['Iron (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'iron', (float) $log->quantity), 1);
                $row['Potassium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'potassium', (float) $log->quantity), 1);
                $row['Fiber (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fiber', (float) $log->quantity), 1);
                $row['Calcium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calcium', (float) $log->quantity), 1);
                $row['Caffeine (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'caffeine', (float) $log->quantity), 1);
                $row['Cost'] = number_format($this->nutritionService->calculateCostForQuantity($log->ingredient, (float) $log->quantity), 2);
                $row['Notes'] = $log->notes;

                fputcsv($file, array($row['Date'], $row['Time'], $row['Ingredient'], $row['Notes'], $row['Quantity'], $row['Unit'], $row['Calories'], $row['Protein (g)'], $row['Carbs (g)'], $row['Fats (g)'], $row['Added Sugars (g)'], $row['Sodium (mg)'], $row['Iron (mg)'], $row['Potassium (mg)'], $row['Fiber (g)'], $row['Calcium (mg)'], $row['Caffeine (mg)'], $row['Cost']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportAll(Request $request)
    {
        $foodLogs = FoodLog::with(['ingredient', 'unit'])
            ->where('user_id', auth()->id())
            ->orderBy('logged_at', 'asc')
            ->get();

        $fileName = 'daily_log_all_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = array('Date', 'Time', 'Ingredient', 'Notes', 'Quantity', 'Unit', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fats (g)', 'Added Sugars (g)', 'Sodium (mg)', 'Iron (mg)', 'Potassium (mg)', 'Fiber (g)', 'Calcium (mg)', 'Caffeine (mg)', 'Cost');

        $callback = function () use ($foodLogs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($foodLogs as $log) {
                $row['Date'] = $log->logged_at->format('m/d/Y');
                $row['Time'] = $log->logged_at->format('H:i');
                $row['Ingredient'] = $log->ingredient->name;
                $row['Quantity'] = $log->quantity;
                $row['Unit'] = $log->unit->name;
                $row['Calories'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float) $log->quantity));
                $row['Protein (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float) $log->quantity), 1);
                $row['Carbs (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'carbs', (float) $log->quantity), 1);
                $row['Fats (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fats', (float) $log->quantity), 1);
                $row['Added Sugars (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'added_sugars', (float) $log->quantity), 1);
                $row['Sodium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'sodium', (float) $log->quantity), 1);
                $row['Iron (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'iron', (float) $log->quantity), 1);
                $row['Potassium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'potassium', (float) $log->quantity), 1);
                $row['Fiber (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fiber', (float) $log->quantity), 1);
                $row['Calcium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calcium', (float) $log->quantity), 1);
                $row['Caffeine (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'caffeine', (float) $log->quantity), 1);
                $row['Cost'] = number_format($this->nutritionService->calculateCostForQuantity($log->ingredient, (float) $log->quantity), 2);
                $row['Notes'] = $log->notes;

                fputcsv($file, array($row['Date'], $row['Time'], $row['Ingredient'], $row['Notes'], $row['Quantity'], $row['Unit'], $row['Calories'], $row['Protein (g)'], $row['Carbs (g)'], $row['Fats (g)'], $row['Added Sugars (g)'], $row['Sodium (mg)'], $row['Iron (mg)'], $row['Potassium (mg)'], $row['Fiber (g)'], $row['Calcium (mg)'], $row['Caffeine (mg)'], $row['Cost']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate a celebratory message for food logging
     * 
     * @param \App\Models\FoodLog $foodLog
     * @return string
     */
    private function generateCelebratoryMessage($foodLog)
    {
        $ingredient = $foodLog->ingredient;
        $foodName = $ingredient->name;

        // Calculate nutrition info for the logged quantity
        $calories = round(app(\App\Services\NutritionService::class)->calculateTotalMacro($ingredient, 'calories', (float) $foodLog->quantity));
        $protein = round(app(\App\Services\NutritionService::class)->calculateTotalMacro($ingredient, 'protein', (float) $foodLog->quantity), 1);

        // Generate food description
        $quantityText = $foodLog->quantity . ' ' . $foodLog->unit->name;
        $nutritionText = $calories . ' cal, ' . $protein . 'g protein';
        $foodDescription = $quantityText . ' • ' . $nutritionText;

        // Get celebratory messages from config and replace placeholders
        $celebrationTemplates = config('mobile_entry_messages.success.food_logged');
        $randomTemplate = $celebrationTemplates[array_rand($celebrationTemplates)];

        // Replace placeholders in the template
        return str_replace([':food', ':details'], [$foodName, $foodDescription], $randomTemplate);
    }

    /**
     * Generate a celebratory message for meal logging
     * 
     * @param \App\Models\Meal $meal
     * @param float $portion
     * @return string
     */
    private function generateMealCelebratoryMessage($meal, $portion)
    {
        $mealName = $meal->name;

        // Calculate total nutrition for the meal portion
        $totalCalories = 0;
        $totalProtein = 0;
        $nutritionService = app(\App\Services\NutritionService::class);

        foreach ($meal->ingredients as $ingredient) {
            $quantity = $ingredient->pivot->quantity * $portion;
            $totalCalories += $nutritionService->calculateTotalMacro($ingredient, 'calories', $quantity);
            $totalProtein += $nutritionService->calculateTotalMacro($ingredient, 'protein', $quantity);
        }

        // Generate meal description
        $portionText = $portion == 1 ? '1 serving' : $portion . ' servings';
        $nutritionText = round($totalCalories) . ' cal, ' . round($totalProtein, 1) . 'g protein';
        $mealDescription = $portionText . ' • ' . $nutritionText;

        // Get celebratory messages from config and replace placeholders
        $celebrationTemplates = config('mobile_entry_messages.success.meal_logged');
        $randomTemplate = $celebrationTemplates[array_rand($celebrationTemplates)];

        // Replace placeholders in the template
        return str_replace([':meal', ':details'], [$mealName, $mealDescription], $randomTemplate);
    }

}