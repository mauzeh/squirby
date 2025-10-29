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

use App\Services\DateNavigationService;
use Carbon\Carbon;

class FoodLogController extends Controller
{
    protected $nutritionService;

    protected $dateNavigationService;

    public function __construct(NutritionService $nutritionService, DateNavigationService $dateNavigationService)
    {
        $this->nutritionService = $nutritionService;
        $this->dateNavigationService = $dateNavigationService;
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
        // Handle mobile entry submissions
        if ($request->has('redirect_to') && in_array($request->input('redirect_to'), ['mobile-entry', 'mobile-entry-foods'])) {
            return $this->storeMobileEntry($request);
        }

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

        return redirect()->route('food-logs.index', ['date' => $validated['date']])->with('success', 'Log entry added successfully!');
    }

    /**
     * Handle mobile entry form submissions.
     */
    private function storeMobileEntry(Request $request)
    {
        try {
            $validated = $request->validate([
                'selected_type' => 'required|in:ingredient,meal',
                'selected_id' => 'required|numeric|min:1',
                'date' => 'required|date',
                'notes' => 'nullable|string|max:1000',
                'quantity' => 'nullable|numeric|min:0.01|max:10000',
                'portion' => 'nullable|numeric|min:0.01|max:100',
            ], [
                'selected_type.required' => 'Please select a food item.',
                'selected_type.in' => 'Invalid food type selected.',
                'selected_id.required' => 'Please select a valid food item.',
                'selected_id.numeric' => 'Invalid food item selected.',
                'selected_id.min' => 'Invalid food item selected.',
                'date.required' => 'Date is required.',
                'date.date' => 'Please provide a valid date.',
                'notes.max' => 'Notes cannot exceed 1000 characters.',
                'quantity.min' => 'Quantity must be at least 0.01.',
                'quantity.max' => 'Quantity cannot exceed 10,000.',
                'quantity.numeric' => 'Quantity must be a valid number.',
                'portion.min' => 'Portion must be at least 0.01.',
                'portion.max' => 'Portion cannot exceed 100.',
                'portion.numeric' => 'Portion must be a valid number.',
            ]);

            $selectedDate = Carbon::parse($validated['date']);
            $currentTime = Carbon::now();
            
            // Round to nearest 15 minutes for real-time logging
            $minutes = $currentTime->minute;
            $roundedMinutes = round($minutes / 15) * 15;
            $loggedAt = $currentTime->setMinute($roundedMinutes)->setSecond(0);
            
            // Set the date part to the selected date but keep the rounded time
            $loggedAt = $selectedDate->setTimeFrom($loggedAt);

            if ($validated['selected_type'] === 'ingredient') {
                // Handle ingredient logging with edge case handling
                $ingredient = Ingredient::where('id', $validated['selected_id'])
                    ->where('user_id', auth()->id())
                    ->first();

                if (!$ingredient) {
                    $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
                    return redirect()->route($redirectRoute, ['date' => $validated['date']])
                        ->with('error', 'The selected ingredient no longer exists or you do not have permission to access it.');
                }

                if (!isset($validated['quantity'])) {
                    $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
                    return redirect()->route($redirectRoute, ['date' => $validated['date']])
                        ->with('error', 'Quantity is required for ingredients.');
                }

                // Check if ingredient has a valid base unit
                if (!$ingredient->base_unit_id || !$ingredient->baseUnit) {
                    $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
                    return redirect()->route($redirectRoute, ['date' => $validated['date']])
                        ->with('error', 'The selected ingredient does not have a valid unit configured.');
                }

                FoodLog::create([
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $ingredient->base_unit_id,
                    'quantity' => $validated['quantity'],
                    'logged_at' => $loggedAt,
                    'notes' => $validated['notes'],
                    'user_id' => auth()->id(),
                ]);

                $message = 'Ingredient logged successfully!';

            } elseif ($validated['selected_type'] === 'meal') {
                // Handle meal logging with edge case handling
                $meal = Meal::with('ingredients.baseUnit')
                    ->where('id', $validated['selected_id'])
                    ->where('user_id', auth()->id())
                    ->first();

                if (!$meal) {
                    $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
                    return redirect()->route($redirectRoute, ['date' => $validated['date']])
                        ->with('error', 'The selected meal no longer exists or you do not have permission to access it.');
                }

                if (!isset($validated['portion'])) {
                    $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
                    return redirect()->route($redirectRoute, ['date' => $validated['date']])
                        ->with('error', 'Portion is required for meals.');
                }

                if ($meal->ingredients->isEmpty()) {
                    $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
                    return redirect()->route($redirectRoute, ['date' => $validated['date']])
                        ->with('error', 'The selected meal has no ingredients configured.');
                }

                $loggedCount = 0;
                $skippedIngredients = [];

                foreach ($meal->ingredients as $ingredient) {
                    // Check if ingredient still exists and has valid unit
                    if (!$ingredient->baseUnit) {
                        $skippedIngredients[] = $ingredient->name;
                        continue;
                    }

                    $notes = $meal->name . ' (Portion: ' . (float)$validated['portion'] . ')';
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

                    $loggedCount++;
                }

                if ($loggedCount === 0) {
                    $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
                    return redirect()->route($redirectRoute, ['date' => $validated['date']])
                        ->with('error', 'No ingredients from the meal could be logged due to configuration issues.');
                }

                $message = 'Meal logged successfully!';
                if (!empty($skippedIngredients)) {
                    $message .= ' Note: Some ingredients were skipped due to missing unit configuration: ' . implode(', ', $skippedIngredients);
                }
            }

            // Check for custom redirect route
            $redirectRoute = 'food-logs.mobile-entry';
            if ($request->input('redirect_to') === 'mobile-entry-foods') {
                $redirectRoute = 'mobile-entry.foods';
                // Clear the selected forms from database after successful submission
                $formService = app(\App\Services\MobileEntry\FoodLogService::class);
                $formService->removeFormAfterLogging(
                    auth()->id(), 
                    $validated['selected_type'], 
                    $validated['selected_id'], 
                    \Carbon\Carbon::parse($validated['date'])
                );
            }
            
            return redirect()->route($redirectRoute, ['date' => $validated['date']])
                ->with('success', $message);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            $errors = $e->validator->errors()->all();
            $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
            return redirect()->route($redirectRoute, ['date' => $request->input('date', Carbon::today()->toDateString())])
                ->with('error', 'Validation failed: ' . implode(' ', $errors));

        } catch (\Exception $e) {
            // Handle unexpected errors
            \Log::error('Mobile food entry error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            $redirectRoute = $request->input('redirect_to') === 'mobile-entry-foods' ? 'mobile-entry.foods' : 'food-logs.mobile-entry';
            return redirect()->route($redirectRoute, ['date' => $request->input('date', Carbon::today()->toDateString())])
                ->with('error', 'An unexpected error occurred while logging your food. Please try again.');
        }
    }

    public function edit(FoodLog $foodLog)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $ingredients = Ingredient::with('baseUnit')->orderBy('name')->get();
        return view('food_logs.edit', compact('foodLog', 'ingredients'));
    }

    public function update(Request $request, FoodLog $foodLog)
    {
        if ($foodLog->user_id !== auth()->id()) {
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

        $foodLog->update($validated);

        return redirect()->route('food-logs.index', ['date' => $validated['date']])->with('success', 'Log entry updated successfully!');
    }

    public function destroy(FoodLog $foodLog, Request $request)
    {
        if ($foodLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $date = $foodLog->logged_at->format('Y-m-d');
        $foodLog->delete();

        // Check for redirect parameter
        $redirectTo = $request->input('redirect_to');
        if ($redirectTo === 'mobile-entry-foods') {
            return redirect()->route('mobile-entry.foods', ['date' => $date])->with('success', 'Log entry deleted successfully!');
        } elseif ($redirectTo === 'mobile-entry') {
            return redirect()->route('food-logs.mobile-entry', ['date' => $date])->with('success', 'Log entry deleted successfully!');
        }

        return redirect()->route('food-logs.index', ['date' => $date])->with('success', 'Log entry deleted successfully!');
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
            $notes = $meal->name . ' (Portion: ' . (float)$validated['portion'] . ')';
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

        return redirect()->route('food-logs.index', ['date' => $validated['meal_date']])->with('success', 'Meal added to log successfully!');
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
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Date', 'Time', 'Ingredient', 'Notes', 'Quantity', 'Unit', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fats (g)', 'Added Sugars (g)', 'Sodium (mg)', 'Iron (mg)', 'Potassium (mg)', 'Fiber (g)', 'Calcium (mg)', 'Caffeine (mg)', 'Cost');

        $callback = function() use($foodLogs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($foodLogs as $log) {
                $row['Date']  = $log->logged_at->format('m/d/Y');
                $row['Time']  = $log->logged_at->format('H:i');
                $row['Ingredient']    = $log->ingredient->name;
                $row['Quantity']    = $log->quantity;
                $row['Unit']  = $log->unit->name;
                $row['Calories'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float)$log->quantity));
                $row['Protein (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float)$log->quantity), 1);
                $row['Carbs (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'carbs', (float)$log->quantity), 1);
                $row['Fats (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fats', (float)$log->quantity), 1);
                $row['Added Sugars (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'added_sugars', (float)$log->quantity), 1);
                $row['Sodium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'sodium', (float)$log->quantity), 1);
                $row['Iron (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'iron', (float)$log->quantity), 1);
                $row['Potassium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'potassium', (float)$log->quantity), 1);
                $row['Fiber (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fiber', (float)$log->quantity), 1);
                $row['Calcium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calcium', (float)$log->quantity), 1);
                $row['Caffeine (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'caffeine', (float)$log->quantity), 1);
                $row['Cost'] = number_format($this->nutritionService->calculateCostForQuantity($log->ingredient, (float)$log->quantity), 2);
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
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Date', 'Time', 'Ingredient', 'Notes', 'Quantity', 'Unit', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fats (g)', 'Added Sugars (g)', 'Sodium (mg)', 'Iron (mg)', 'Potassium (mg)', 'Fiber (g)', 'Calcium (mg)', 'Caffeine (mg)', 'Cost');

        $callback = function() use($foodLogs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($foodLogs as $log) {
                $row['Date']  = $log->logged_at->format('m/d/Y');
                $row['Time']  = $log->logged_at->format('H:i');
                $row['Ingredient']    = $log->ingredient->name;
                $row['Quantity']    = $log->quantity;
                $row['Unit']  = $log->unit->name;
                $row['Calories'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float)$log->quantity));
                $row['Protein (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float)$log->quantity), 1);
                $row['Carbs (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'carbs', (float)$log->quantity), 1);
                $row['Fats (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fats', (float)$log->quantity), 1);
                $row['Added Sugars (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'added_sugars', (float)$log->quantity), 1);
                $row['Sodium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'sodium', (float)$log->quantity), 1);
                $row['Iron (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'iron', (float)$log->quantity), 1);
                $row['Potassium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'potassium', (float)$log->quantity), 1);
                $row['Fiber (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fiber', (float)$log->quantity), 1);
                $row['Calcium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calcium', (float)$log->quantity), 1);
                $row['Caffeine (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'caffeine', (float)$log->quantity), 1);
                $row['Cost'] = number_format($this->nutritionService->calculateCostForQuantity($log->ingredient, (float)$log->quantity), 2);
                $row['Notes'] = $log->notes;

                fputcsv($file, array($row['Date'], $row['Time'], $row['Ingredient'], $row['Notes'], $row['Quantity'], $row['Unit'], $row['Calories'], $row['Protein (g)'], $row['Carbs (g)'], $row['Fats (g)'], $row['Added Sugars (g)'], $row['Sodium (mg)'], $row['Iron (mg)'], $row['Potassium (mg)'], $row['Fiber (g)'], $row['Calcium (mg)'], $row['Caffeine (mg)'], $row['Cost']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Display the mobile food entry interface.
     */
    public function mobileEntry(Request $request)
    {
        try {
            $selectedDate = $request->input('date') ? 
                Carbon::parse($request->input('date')) : Carbon::today();
            
            // Validate date is not too far in the future or past
            $maxDate = Carbon::today()->addYear();
            $minDate = Carbon::today()->subYears(5);
            
            if ($selectedDate->gt($maxDate) || $selectedDate->lt($minDate)) {
                $selectedDate = Carbon::today();
            }
            
            // Get user ingredients and meals for autocomplete with error handling
            $ingredients = Ingredient::where('user_id', auth()->id())
                ->with('baseUnit')
                ->whereHas('baseUnit') // Only include ingredients with valid units
                ->orderBy('name')
                ->get();
            
            $meals = Meal::where('user_id', auth()->id())
                ->whereHas('ingredients') // Only include meals with ingredients
                ->orderBy('name')
                ->get();
            
            // Get existing food logs for the date with error handling
            $foodLogs = FoodLog::with(['ingredient', 'unit'])
                ->where('user_id', auth()->id())
                ->whereDate('logged_at', $selectedDate->toDateString())
                ->whereHas('ingredient') // Only include logs with valid ingredients
                ->whereHas('unit') // Only include logs with valid units
                ->orderBy('logged_at', 'desc')
                ->get();
            
            // Calculate daily totals with error handling
            try {
                $dailyTotals = $this->nutritionService->calculateFoodLogTotals($foodLogs);
            } catch (\Exception $e) {
                \Log::warning('Error calculating daily totals: ' . $e->getMessage());
                $dailyTotals = [
                    'calories' => 0,
                    'protein' => 0,
                    'carbs' => 0,
                    'fats' => 0,
                    'fiber' => 0,
                    'added_sugars' => 0,
                    'sodium' => 0,
                    'calcium' => 0,
                    'iron' => 0,
                    'potassium' => 0,
                    'caffeine' => 0,
                    'cost' => 0,
                ];
            }
            
            return view('food_logs.mobile-entry', compact(
                'selectedDate', 'ingredients', 'meals', 'foodLogs', 'dailyTotals'
            ))->with('nutritionService', $this->nutritionService);

        } catch (\Exception $e) {
            \Log::error('Mobile food entry page error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'date' => $request->input('date'),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback to today's date with empty data
            $selectedDate = Carbon::today();
            $ingredients = collect();
            $meals = collect();
            $foodLogs = collect();
            $dailyTotals = [
                'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fats' => 0,
                'fiber' => 0, 'added_sugars' => 0, 'sodium' => 0, 'calcium' => 0,
                'iron' => 0, 'potassium' => 0, 'caffeine' => 0, 'cost' => 0,
            ];

            return view('food_logs.mobile-entry', compact(
                'selectedDate', 'ingredients', 'meals', 'foodLogs', 'dailyTotals'
            ))->with('nutritionService', $this->nutritionService)
              ->with('error', 'There was an issue loading the food entry page. Some features may not work properly.');
        }
    }
}