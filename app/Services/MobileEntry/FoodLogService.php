<?php

namespace App\Services\MobileEntry;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use App\Services\NutritionService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\ComponentBuilder as C;

class FoodLogService extends MobileEntryBaseService
{
    protected NutritionService $nutritionService;

    public function __construct(NutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    /**
     * Generate a form for creating a new ingredient food log
     * 
     * @param Ingredient $ingredient
     * @param int $userId
     * @param Carbon $selectedDate
     * @param string|null $redirectTo
     * @return array
     */
    public function generateIngredientCreateForm(Ingredient $ingredient, int $userId, Carbon $selectedDate, ?string $redirectTo = null)
    {
        // Get last logged data for this ingredient
        $lastLog = FoodLog::where('user_id', $userId)
            ->where('ingredient_id', $ingredient->id)
            ->where('logged_at', '<', $selectedDate->toDateString())
            ->with(['unit'])
            ->orderBy('logged_at', 'desc')
            ->first();

        // Prepare form data
        $formData = [
            'action' => route('food-logs.store'),
            'method' => 'POST',
            'ingredient_id' => $ingredient->id,
            'date' => $selectedDate->toDateString(),
            'logged_at' => now()->format('H:i'), // Use current time
            'quantity' => $lastLog ? $lastLog->quantity : 1,
            'notes' => '',
            'unit' => $ingredient->baseUnit,
            'redirect_to' => $redirectTo ?: 'mobile-entry.foods'
        ];

        // Generate messages
        $messages = [];
        
        if ($lastLog) {
            $lastQuantity = $lastLog->quantity . ' ' . $lastLog->unit->name;
            $lastDate = $lastLog->logged_at->format('M j');
            $messages[] = [
                'type' => 'neutral',
                'prefix' => 'Last logged:',
                'text' => $lastQuantity . ' on ' . $lastDate
            ];
        }

        // Add nutrition information
        $calories = round($this->nutritionService->calculateTotalMacro($ingredient, 'calories', 1));
        $protein = round($this->nutritionService->calculateTotalMacro($ingredient, 'protein', 1), 1);
        $carbs = round($this->nutritionService->calculateTotalMacro($ingredient, 'carbs', 1), 1);
        $fats = round($this->nutritionService->calculateTotalMacro($ingredient, 'fats', 1), 1);
        
        $nutritionText = "Per {$ingredient->baseUnit->name}: {$calories} cal, {$protein}g protein, {$carbs}g carbs, {$fats}g fats";
        $messages[] = [
            'type' => 'info',
            'prefix' => 'Nutrition:',
            'text' => $nutritionText
        ];

        return $this->buildIngredientForm($formData, $messages);
    }

    /**
     * Generate a form for creating a new meal food log
     * 
     * @param Meal $meal
     * @param int $userId
     * @param Carbon $selectedDate
     * @param string|null $redirectTo
     * @return array
     */
    public function generateMealCreateForm(Meal $meal, int $userId, Carbon $selectedDate, ?string $redirectTo = null)
    {
        // Get last logged data for this meal (check for any ingredient from this meal)
        $lastMealLog = FoodLog::where('user_id', $userId)
            ->where('logged_at', '<', $selectedDate->toDateString())
            ->where('notes', 'like', $meal->name . ' (Portion:%')
            ->orderBy('logged_at', 'desc')
            ->first();

        // Extract portion from last log if available
        $defaultPortion = 1;
        if ($lastMealLog && preg_match('/Portion: ([\d.]+)\)/', $lastMealLog->notes, $matches)) {
            $defaultPortion = (float) $matches[1];
        }

        // Prepare form data
        $formData = [
            'action' => route('food-logs.add-meal'),
            'method' => 'POST',
            'meal_id' => $meal->id,
            'meal_date' => $selectedDate->toDateString(),
            'logged_at_meal' => now()->format('H:i'), // Use current time
            'portion' => $defaultPortion,
            'notes' => '',
            'redirect_to' => $redirectTo ?: 'mobile-entry.foods'
        ];

        // Generate messages
        $messages = [];
        
        if ($lastMealLog) {
            $lastDate = $lastMealLog->logged_at->format('M j');
            $messages[] = [
                'type' => 'neutral',
                'prefix' => 'Last logged:',
                'text' => $defaultPortion . ' serving on ' . $lastDate
            ];
        }

        // Calculate meal nutrition for 1 serving
        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFats = 0;
        $ingredientCount = $meal->ingredients->count();

        foreach ($meal->ingredients as $ingredient) {
            $quantity = $ingredient->pivot->quantity;
            $totalCalories += $this->nutritionService->calculateTotalMacro($ingredient, 'calories', $quantity);
            $totalProtein += $this->nutritionService->calculateTotalMacro($ingredient, 'protein', $quantity);
            $totalCarbs += $this->nutritionService->calculateTotalMacro($ingredient, 'carbs', $quantity);
            $totalFats += $this->nutritionService->calculateTotalMacro($ingredient, 'fats', $quantity);
        }

        $nutritionText = "Per serving: " . round($totalCalories) . " cal, " . round($totalProtein, 1) . "g protein, " . round($totalCarbs, 1) . "g carbs, " . round($totalFats, 1) . "g fats";
        $messages[] = [
            'type' => 'info',
            'prefix' => 'Nutrition:',
            'text' => $nutritionText
        ];

        $messages[] = [
            'type' => 'info',
            'prefix' => 'Contains:',
            'text' => $ingredientCount . ' ingredients'
        ];

        return $this->buildMealForm($formData, $messages);
    }

    /**
     * Build an ingredient form component
     * 
     * @param array $formData
     * @param array $messages
     * @return array
     */
    protected function buildIngredientForm(array $formData, array $messages)
    {
        $formBuilder = C::form($formData['ingredient_id'], $formData['unit']->name ?? 'Ingredient')
            ->type('success')
            ->formAction($formData['action']);

        // Add hidden fields (including time)
        $formBuilder->hiddenField('ingredient_id', $formData['ingredient_id']);
        $formBuilder->hiddenField('date', $formData['date']);
        $formBuilder->hiddenField('logged_at', $formData['logged_at']); // Hidden time field
        $formBuilder->hiddenField('redirect_to', $formData['redirect_to']);

        // Add informational messages only (not validation errors - those are handled at page level)
        foreach ($messages as $message) {
            if ($message['type'] !== 'error') { // Skip error messages
                $formBuilder->message($message['type'], $message['text'], $message['prefix'] ?? null);
            }
        }

        // Quantity field
        $unitName = $formData['unit']->name ?? 'unit';
        $formBuilder->numericField('quantity', 'Quantity (' . $unitName . ')', $formData['quantity'], 0.1, 0.01);

        // Notes field
        $formBuilder->textareaField('notes', 'Notes (optional)', $formData['notes'], 'Optional notes');

        // Submit button
        $formBuilder->submitButton('Log Ingredient');

        return $formBuilder->build();
    }

    /**
     * Build a meal form component
     * 
     * @param array $formData
     * @param array $messages
     * @return array
     */
    protected function buildMealForm(array $formData, array $messages)
    {
        $formBuilder = C::form($formData['meal_id'], 'Meal')
            ->type('success')
            ->formAction($formData['action']);

        // Add hidden fields (including time)
        $formBuilder->hiddenField('meal_id', $formData['meal_id']);
        $formBuilder->hiddenField('meal_date', $formData['meal_date']);
        $formBuilder->hiddenField('logged_at_meal', $formData['logged_at_meal']); // Hidden time field
        $formBuilder->hiddenField('redirect_to', $formData['redirect_to']);

        // Add informational messages only (not validation errors - those are handled at page level)
        foreach ($messages as $message) {
            if ($message['type'] !== 'error') { // Skip error messages
                $formBuilder->message($message['type'], $message['text'], $message['prefix'] ?? null);
            }
        }

        // Portion field
        $formBuilder->numericField('portion', 'Servings', $formData['portion'], 0.1, 0.1, 10);

        // Notes field
        $formBuilder->textareaField('notes', 'Notes (optional)', $formData['notes'], 'Optional notes');

        // Submit button
        $formBuilder->submitButton('Log Meal');

        return $formBuilder->build();
    }

    /**
     * Generate summary data based on user's food logs for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateSummary($userId, Carbon $selectedDate)
    {
        $foodLogs = FoodLog::with(['ingredient', 'unit'])
            ->where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->get();

        // Count of food entries today
        $entriesCount = $foodLogs->count();
        
        // Return null if no entries for this date
        if ($entriesCount === 0) {
            return null;
        }

        $dailyTotals = $this->nutritionService->calculateFoodLogTotals($foodLogs);
        
        // Get average daily calories over last 7 days for comparison
        $avgCalories = $this->getAverageDailyCalories($userId, $selectedDate);

        return [
            'values' => [
                'total' => round($dailyTotals['calories']),
                'completed' => $entriesCount,
                'average' => round($avgCalories),
                'today' => round($dailyTotals['protein'], 1)
            ],
            'labels' => [
                'total' => 'Calories',
                'completed' => 'Entries',
                'average' => '7-Day Avg',
                'today' => 'Protein (g)'
            ],
            'ariaLabels' => [
                'section' => 'Daily nutrition summary'
            ]
        ];
    }

    /**
     * Get average daily calories over the last 7 days
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return float
     */
    protected function getAverageDailyCalories($userId, Carbon $selectedDate)
    {
        $startDate = $selectedDate->copy()->subDays(7);
        $endDate = $selectedDate->copy()->subDay(); // Exclude today
        
        $dailyCalories = FoodLog::with(['ingredient'])
            ->where('user_id', $userId)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->get()
            ->groupBy(function ($log) {
                return $log->logged_at->format('Y-m-d');
            })
            ->map(function ($dayLogs) {
                return $this->nutritionService->calculateFoodLogTotals($dayLogs)['calories'];
            });
        
        return $dailyCalories->count() > 0 ? $dailyCalories->avg() : 0;
    }

    /**
     * Generate logged items data for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateLoggedItems($userId, Carbon $selectedDate)
    {
        $logs = FoodLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->with(['ingredient', 'unit'])
            ->orderBy('logged_at', 'desc')
            ->get();

        $tableBuilder = C::table()
            ->confirmMessage('deleteItem', 'Are you sure you want to delete this food log entry? This action cannot be undone.')
            ->ariaLabel('Logged food items')
            ->spacedRows();
        
        foreach ($logs as $log) {
            if (!$log->ingredient || !$log->unit) {
                continue;
            }

            // Calculate nutrition info for display
            $calories = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float)$log->quantity));
            $protein = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float)$log->quantity), 1);
            
            $quantityText = $log->quantity . ' ' . $log->unit->name;
            $caloriesText = $calories . ' cal';
            $proteinText = $protein . 'g protein';

            $deleteParams = ['redirect_to' => 'mobile-entry.foods'];
            // Only include date if we're NOT viewing today
            if (!$selectedDate->isToday()) {
                $deleteParams['date'] = $selectedDate->toDateString();
            }

            $tableBuilder->row($log->id, $log->ingredient->name, null, $log->notes)
                ->badge($quantityText, 'neutral')
                ->badge($caloriesText, 'warning', true)
                ->badge($proteinText, 'success')
                ->linkAction('fa-pencil', route('food-logs.edit', ['food_log' => $log->id, 'redirect_to' => 'mobile-entry.foods']), 'Edit', 'btn-transparent')
                ->formAction('fa-trash', route('food-logs.destroy', $log->id), 'DELETE', $deleteParams, 'Delete', 'btn-transparent', true)
                ->compact()
                ->add();
        }

        if ($logs->isEmpty()) {
            $tableBuilder->emptyMessage(config('mobile_entry_messages.empty_states.no_food_logged'));
        }

        return $tableBuilder->build();
    }

    /**
     * Generate item selection list based on user's ingredients and meals
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateItemSelectionList($userId, Carbon $selectedDate)
    {
        // Get user's ingredients with recent usage data
        $ingredients = Ingredient::where('user_id', $userId)
            ->with(['baseUnit', 'foodLogs' => function ($query) use ($userId, $selectedDate) {
                $query->where('user_id', $userId)
                    ->where('logged_at', '>=', $selectedDate->copy()->subDays(30))
                    ->orderBy('logged_at', 'desc')
                    ->limit(1);
            }])
            ->whereHas('baseUnit') // Only ingredients with valid units
            ->orderBy('name', 'asc')
            ->get();

        // Get user's meals
        $meals = Meal::where('user_id', $userId)
            ->whereHas('ingredients') // Only meals with ingredients
            ->orderBy('name', 'asc')
            ->get();

        $items = [];
        
        // Add meals first (they will have priority 1)
        foreach ($meals as $meal) {
            $routeParams = [
                'meal' => $meal->id
            ];
            // Only include date if we're NOT viewing today
            if (!$selectedDate->isToday()) {
                $routeParams['date'] = $selectedDate->toDateString();
            }
            // Add redirect_to parameter for mobile entry context
            $routeParams['redirect_to'] = 'mobile-entry.foods';
            
            $items[] = [
                'id' => 'meal-' . $meal->id,
                'name' => $meal->name,
                'type' => $this->getItemTypeConfig('meal'),
                'href' => route('food-logs.create-meal', $routeParams)
            ];
        }
        
        // Add ingredients (no recent prioritization)
        foreach ($ingredients as $ingredient) {
            $itemType = $this->determineIngredientType($ingredient, $userId);

            $routeParams = [
                'ingredient' => $ingredient->id
            ];
            // Only include date if we're NOT viewing today
            if (!$selectedDate->isToday()) {
                $routeParams['date'] = $selectedDate->toDateString();
            }
            // Add redirect_to parameter for mobile entry context
            $routeParams['redirect_to'] = 'mobile-entry.foods';

            $items[] = [
                'id' => 'ingredient-' . $ingredient->id,
                'name' => $ingredient->name,
                'type' => $itemType,
                'href' => route('food-logs.create-ingredient', $routeParams)
            ];
        }

        // Sort items: by priority first, then alphabetical by name
        usort($items, function ($a, $b) {
            $priorityComparison = $a['type']['priority'] <=> $b['type']['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            return strcmp($a['name'], $b['name']);
        });

        return [
            'noResultsMessage' => config('mobile_entry_messages.empty_states.no_food_items_found'),
            'createForm' => [
                'action' => route('ingredients.create'),
                'method' => 'GET',
                'inputName' => 'name',
                'submitText' => '+',
                'buttonTextTemplate' => 'Create "{term}"',
                'ariaLabel' => 'Create new ingredient',
                'hiddenFields' => []
            ],
            'items' => $items,
            'ariaLabels' => [
                'section' => 'Food item selection list',
                'selectItem' => 'Select this food item to log'
            ],
            'filterPlaceholder' => config('mobile_entry_messages.placeholders.search_food')
        ];
    }



    /**
     * Determine the item type configuration for an ingredient
     * 
     * @param \App\Models\Ingredient $ingredient
     * @param int $userId
     * @param array $recentIngredientIds
     * @return array Item type configuration with label and cssClass
     */
    protected function determineIngredientType($ingredient, $userId)
    {
        // Check if it's a user's custom ingredient
        $isCustom = $ingredient->user_id === $userId;

        // All ingredients are just "Ingredient" type now
        if ($isCustom) {
            return $this->getItemTypeConfig('custom');
        } else {
            return $this->getItemTypeConfig('regular');
        }
    }

    /**
     * Get item type configuration
     * 
     * @param string $typeKey
     * @return array Configuration with label and cssClass
     */
    protected function getItemTypeConfig($typeKey)
    {
        $itemTypes = [
            'custom' => [
                'label' => 'Ingredient',
                'cssClass' => 'custom',
                'priority' => 2
            ],
            'regular' => [
                'label' => 'Ingredient',
                'cssClass' => 'regular',
                'priority' => 2
            ],
            'meal' => [
                'label' => 'Meal',
                'cssClass' => 'highlighted',
                'priority' => 1
            ]
        ];

        return $itemTypes[$typeKey] ?? $itemTypes['regular'];
    }

    /**
     * Generate forms based on request parameters and database data
     * 
     * @deprecated This method is deprecated. Food logging now uses direct navigation like lifts.
     * @param int $userId
     * @param Carbon $selectedDate
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function generateForms($userId, Carbon $selectedDate, $request)
    {
        // Deprecated: Return empty array as forms are no longer generated on the main page
        return [];
    }

    /**
     * Generate a form for a specific ingredient
     * 
     * @deprecated This method is deprecated. Use generateIngredientCreateForm instead.
     * @param int $userId
     * @param int $ingredientId
     * @param Carbon $selectedDate
     * @return array|null
     */
    public function generateIngredientForm($userId, $ingredientId, Carbon $selectedDate)
    {
        // Deprecated: Return null
        return null;
    }

    /**
     * Generate a form for a specific meal
     * 
     * @deprecated This method is deprecated. Use generateMealCreateForm instead.
     * @param int $userId
     * @param int $mealId
     * @param Carbon $selectedDate
     * @return array|null
     */
    public function generateMealForm($userId, $mealId, Carbon $selectedDate)
    {
        // Deprecated: Return null
        return null;
    }

    /**
     * Get last session data for an ingredient
     * 
     * @param int $ingredientId
     * @param Carbon $beforeDate
     * @param int $userId
     * @return array|null
     */
    public function getLastIngredientSession($ingredientId, Carbon $beforeDate, $userId)
    {
        $lastLog = FoodLog::where('user_id', $userId)
            ->where('ingredient_id', $ingredientId)
            ->where('logged_at', '<', $beforeDate->toDateString())
            ->with(['ingredient', 'unit'])
            ->orderBy('logged_at', 'desc')
            ->first();
        
        if (!$lastLog) {
            return null;
        }
        
        return [
            'quantity' => $lastLog->quantity,
            'unit' => $lastLog->unit->name,
            'date' => $lastLog->logged_at->format('M j'),
            'notes' => $lastLog->notes
        ];
    }

    /**
     * Generate messages for an ingredient form based on last session
     * 
     * @param \App\Models\Ingredient $ingredient
     * @param array|null $lastSession
     * @return array
     */
    public function generateIngredientFormMessages($ingredient, $lastSession)
    {
        $messages = [];
        
        // Add instructional message for new users or first-time ingredients
        if (!$lastSession) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'How to log:',
                'text' => str_replace(':food', $ingredient->name, config('mobile_entry_messages.form_guidance.how_to_log_food'))
            ];
        }
        
        // Add last session info if available
        if ($lastSession) {
            $messageText = $lastSession['quantity'] . ' ' . $lastSession['unit'];
            
            $messages[] = [
                'type' => 'info',
                'prefix' => str_replace(':date', $lastSession['date'], config('mobile_entry_messages.form_guidance.last_logged')),
                'text' => $messageText
            ];
        }
        
        // Add last session notes if available
        if ($lastSession && !empty($lastSession['notes'])) {
            $messages[] = [
                'type' => 'neutral',
                'prefix' => config('mobile_entry_messages.form_guidance.your_last_notes'),
                'text' => $lastSession['notes']
            ];
        }
        
        // Add nutrition info
        $calories = round($ingredient->calories);
        $protein = round($ingredient->protein, 1);
        $carbs = round($ingredient->carbs, 1);
        $fats = round($ingredient->fats, 1);
        
        // Build nutrition text with available macros
        $nutritionParts = [];
        if ($calories > 0) $nutritionParts[] = $calories . ' cal';
        if ($protein > 0) $nutritionParts[] = $protein . 'g protein';
        if ($carbs > 0) $nutritionParts[] = $carbs . 'g carbs';
        if ($fats > 0) $nutritionParts[] = $fats . 'g fat';
        
        if (!empty($nutritionParts)) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => config('mobile_entry_messages.form_guidance.nutrition_info'),
                'text' => 'Per ' . $ingredient->base_quantity . ' ' . $ingredient->baseUnit->name . ': ' . implode(', ', $nutritionParts)
            ];
        }
        
        return $messages;
    }

    /**
     * Get appropriate quantity increment based on unit type and default value
     * 
     * @param string $unitName
     * @param float $defaultValue
     * @return float
     */
    public function getQuantityIncrement($unitName, $defaultValue = 1)
    {
        $unitName = strtolower($unitName);
        
        // For very small default values, use smaller increments
        if ($defaultValue < 0.5) {
            return 0.1;
        }
        
        // Small increments for precise measurements
        if (in_array($unitName, ['tsp', 'teaspoon', 'tbsp', 'tablespoon', 'oz', 'ounce'])) {
            // Use 0.5 for tablespoons to avoid step validation issues
            return 0.5;
        }
        
        // Medium increments for common measurements
        if (in_array($unitName, ['cup', 'cups', 'ml', 'milliliter'])) {
            return 0.5;
        }
        
        // Larger increments for bulk measurements
        if (in_array($unitName, ['lb', 'pound', 'kg', 'kilogram'])) {
            return 1;
        }
        
        // For grams, use different increments based on typical quantities
        if (in_array($unitName, ['g', 'gram'])) {
            if ($defaultValue >= 100) {
                return 10; // 10g increments for larger quantities
            } else {
                return 5; // 5g increments for smaller quantities
            }
        }
        
        // Default increment
        return 0.5;
    }

    /**
     * Add a food form by finding the ingredient/meal and storing it in database
     * 
     * @deprecated This method is deprecated. Food logging now uses direct navigation like lifts.
     * @param int $userId
     * @param string $type 'ingredient' or 'meal'
     * @param int $id Ingredient or Meal ID
     * @param Carbon $selectedDate
     * @return array Result with success/error status and message
     */
    public function addFoodForm($userId, $type, $id, Carbon $selectedDate)
    {
        // Deprecated: Return error message
        return [
            'success' => false,
            'message' => 'This feature has been deprecated. Please use the direct navigation instead.'
        ];
    }

    /**
     * Create a new ingredient
     * 
     * @param int $userId
     * @param string $ingredientName
     * @param Carbon $selectedDate
     * @return array Result with success/error status and message
     */
    public function createIngredient($userId, $ingredientName, Carbon $selectedDate)
    {
        // Check if ingredient with similar name already exists
        $existingIngredient = Ingredient::where('name', $ingredientName)
            ->where('user_id', $userId)
            ->first();
        
        if ($existingIngredient) {
            return [
                'success' => false,
                'message' => str_replace(':ingredient', $ingredientName, config('mobile_entry_messages.error.ingredient_already_exists'))
            ];
        }
        
        // Get default unit (assuming 'g' for grams exists)
        $defaultUnit = Unit::where('name', 'g')->first();
        
        if (!$defaultUnit) {
            return [
                'success' => false,
                'message' => 'Default unit not found. Please contact administrator.'
            ];
        }
        
        // Create the new ingredient with basic defaults
        $ingredient = Ingredient::create([
            'name' => $ingredientName,
            'user_id' => $userId,
            'protein' => 0,
            'carbs' => 0,
            'fats' => 0,
            'base_quantity' => 100,
            'base_unit_id' => $defaultUnit->id,
            'cost_per_unit' => 0
        ]);
        
        return [
            'success' => true,
            'message' => str_replace(':ingredient', $ingredient->name, config('mobile_entry_messages.success.ingredient_created'))
        ];
    }

    /**
     * Remove a food form from the interface
     * 
     * @deprecated This method is deprecated. Food logging now uses direct navigation like lifts.
     * @param int $userId
     * @param string $formId Form ID (format: ingredient-{id} or meal-{id})
     * @return array Result with success/error status and message
     */
    public function removeFoodForm($userId, $formId)
    {
        // Deprecated: Return error message
        return [
            'success' => false,
            'message' => 'This feature has been deprecated. Please use the direct navigation instead.'
        ];
    }

    /**
     * Clean up old mobile food forms for a user (keep only last 3 days)
     * 
     * @deprecated This method is deprecated. Mobile food forms table has been removed.
     * @param int $userId
     * @param Carbon $currentDate
     */
    public function cleanupOldForms($userId, Carbon $currentDate)
    {
        // Deprecated: Table no longer exists
        return;
    }

    /**
     * Remove a specific form after successful logging
     * 
     * @deprecated This method is deprecated. Mobile food forms table has been removed.
     * @param int $userId
     * @param string $type
     * @param int $itemId
     * @param Carbon $date
     * @return bool
     */
    public function removeFormAfterLogging($userId, $type, $itemId, Carbon $date)
    {
        // Deprecated: Table no longer exists
        return true;
    }

    /**
     * Generate contextual help messages based on user's current state
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateContextualHelpMessages($userId, Carbon $selectedDate)
    {
        $messages = [];
        
        // Check if user has logged any food today
        $loggedCount = FoodLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->count();
        
        if ($loggedCount === 0) {
            // No food logged yet today
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Getting started:',
                'text' => config('mobile_entry_messages.contextual_help.getting_started_food')
            ];
        } else {
            // Has logged food today
            $messages[] = [
                'type' => 'success',
                'prefix' => 'Great tracking:',
                'text' => config('mobile_entry_messages.contextual_help.daily_logging_complete')
            ];
        }
        
        return $messages;
    }

    private function getRoundedTime()
    {
        $now = now();
        $minute = $now->minute;
        $remainder = $minute % 15;
        if ($remainder < 8) {
            $now->subMinutes($remainder);
        } else {
            $now->addMinutes(15 - $remainder);
        }
        return $now->format('H:i');
    }

    /*
     *
     * Generate edit form for a food log entry
     * 
     * @param FoodLog $foodLog
     * @param string|null $redirectTo
     * @return array
     */
    public function generateEditForm(FoodLog $foodLog, ?string $redirectTo = null)
    {
        $formId = 'edit-food-log-' . $foodLog->id;

        // Determine increment based on unit
        $increment = 1;
        if ($foodLog->ingredient && $foodLog->ingredient->baseUnit) {
            $increment = $this->getQuantityIncrement($foodLog->ingredient->baseUnit->name, $foodLog->quantity);
        }

        $builder = C::form($formId, $foodLog->ingredient->name)
            ->formAction(route('food-logs.update', $foodLog->id))
            ->hiddenField('_method', 'PUT')
            ->numericField('quantity', 'Quantity', $foodLog->quantity, $increment, 0.01, null, 'any')
            ->textareaField('notes', 'Notes', $foodLog->notes ?? '')
            ->submitButton('Update Entry');

        if ($redirectTo) {
            $builder->hiddenField('redirect_to', $redirectTo);
        }

        return $builder->build();
    }
}