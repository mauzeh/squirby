<?php

namespace App\Services\MobileEntry;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\Unit;
use App\Models\MobileFoodForm;
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

            $tableBuilder->row($log->id, $log->ingredient->name, null, $log->notes)
                ->badge($quantityText, 'neutral')
                ->badge($caloriesText, 'warning', true)
                ->badge($proteinText, 'success')
                ->linkAction('fa-pencil', route('food-logs.edit', ['food_log' => $log->id, 'redirect_to' => 'mobile-entry.foods']), 'Edit', 'btn-transparent')
                ->formAction('fa-trash', route('food-logs.destroy', $log->id), 'DELETE', [
                    'redirect_to' => 'mobile-entry.foods',
                    'date' => $selectedDate->toDateString()
                ], 'Delete', 'btn-transparent', true)
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
            $items[] = [
                'id' => 'meal-' . $meal->id,
                'name' => $meal->name . ' (Meal)',
                'type' => $this->getItemTypeConfig('meal'),
                'href' => route('mobile-entry.add-food-form', [
                    'type' => 'meal',
                    'id' => $meal->id,
                    'date' => $selectedDate->toDateString()
                ])
            ];
        }
        
        // Add ingredients (no recent prioritization)
        foreach ($ingredients as $ingredient) {
            $itemType = $this->determineIngredientType($ingredient, $userId);

            $items[] = [
                'id' => 'ingredient-' . $ingredient->id,
                'name' => $ingredient->name,
                'type' => $itemType,
                'href' => route('mobile-entry.add-food-form', [
                    'type' => 'ingredient',
                    'id' => $ingredient->id,
                    'date' => $selectedDate->toDateString()
                ])
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
     * @param int $userId
     * @param Carbon $selectedDate
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function generateForms($userId, Carbon $selectedDate, $request)
    {
        $forms = [];
        
        // Get selected items from database
        $selectedItems = MobileFoodForm::forUserAndDate($userId, $selectedDate)->get();
        
        foreach ($selectedItems as $item) {
            if ($item->type === 'ingredient') {
                $form = $this->generateIngredientForm($userId, $item->item_id, $selectedDate);
                if ($form) {
                    $forms[] = $form;
                }
            } elseif ($item->type === 'meal') {
                $form = $this->generateMealForm($userId, $item->item_id, $selectedDate);
                if ($form) {
                    $forms[] = $form;
                }
            }
        }
        
        return $forms;
    }

    /**
     * Generate a form for a specific ingredient
     * 
     * @param int $userId
     * @param int $ingredientId
     * @param Carbon $selectedDate
     * @return array|null
     */
    public function generateIngredientForm($userId, $ingredientId, Carbon $selectedDate)
    {
        $ingredient = Ingredient::where('id', $ingredientId)
            ->where('user_id', $userId)
            ->with('baseUnit')
            ->first();
            
        if (!$ingredient || !$ingredient->baseUnit) {
            return null;
        }
        
        // Get last session data for this ingredient
        $lastSession = $this->getLastIngredientSession($ingredient->id, $selectedDate, $userId);
        
        // Generate form ID
        $formId = 'ingredient-' . $ingredient->id;
        
        // Determine default quantity
        $defaultQuantity = $lastSession['quantity'] ?? 1;
        
        // Generate messages based on last session
        $messages = $this->generateIngredientFormMessages($ingredient, $lastSession);
        
        // Build form using ComponentBuilder
        $formBuilder = C::form($formId, $ingredient->name)
            ->type('success')
            ->formAction(route('food-logs.store'))
            ->deleteAction(route('mobile-entry.remove-food-form', ['id' => $formId]));
        
        // Add messages
        foreach ($messages as $message) {
            $formBuilder->message($message['type'], $message['text'], $message['prefix'] ?? null);
        }
        
        // Build and customize form data
        $formData = $formBuilder->build();
        $formData['data']['numericFields'] = [
            [
                'id' => $formId . '-quantity',
                'name' => 'quantity',
                'label' => 'Quantity (' . $ingredient->baseUnit->name . '):',
                'defaultValue' => round($defaultQuantity, 2),
                'increment' => $this->getQuantityIncrement($ingredient->baseUnit->name, $defaultQuantity),
                'step' => 'any',
                'min' => 0.01,
                'max' => 1000,
                'ariaLabels' => [
                    'decrease' => 'Decrease quantity',
                    'increase' => 'Increase quantity'
                ]
            ],
            [
                'id' => $formId . '-notes',
                'name' => 'notes',
                'label' => 'Notes:',
                'type' => 'textarea',
                'placeholder' => config('mobile_entry_messages.placeholders.food_notes'),
                'defaultValue' => '',
                'ariaLabels' => [
                    'field' => 'Notes'
                ]
            ]
        ];
        $formData['data']['buttons'] = [
            'decrement' => '-',
            'increment' => '+',
            'submit' => 'Log ' . $ingredient->name
        ];
        $formData['data']['ariaLabels'] = [
            'section' => $ingredient->name . ' entry',
            'deleteForm' => 'Remove this food form'
        ];
        $formData['data']['hiddenFields'] = [
            'ingredient_id' => $ingredient->id,
            'logged_at' => $this->getRoundedTime(),
            'date' => $selectedDate->toDateString(),
            'redirect_to' => 'mobile-entry-foods'
        ];
        $formData['data']['deleteParams'] = [
            'date' => $selectedDate->toDateString()
        ];
        
        return $formData;
    }

    /**
     * Generate a form for a specific meal
     * 
     * @param int $userId
     * @param int $mealId
     * @param Carbon $selectedDate
     * @return array|null
     */
    public function generateMealForm($userId, $mealId, Carbon $selectedDate)
    {
        $meal = Meal::where('id', $mealId)
            ->where('user_id', $userId)
            ->with('ingredients.baseUnit')
            ->first();
            
        if (!$meal || $meal->ingredients->isEmpty()) {
            return null;
        }
        
        // Generate form ID
        $formId = 'meal-' . $meal->id;
        
        // Get nutrition info for the meal
        $totalCalories = 0;
        $totalProtein = 0;
        
        foreach ($meal->ingredients as $ingredient) {
            $quantity = $ingredient->pivot->quantity;
            $totalCalories += $this->nutritionService->calculateTotalMacro($ingredient, 'calories', $quantity);
            $totalProtein += $this->nutritionService->calculateTotalMacro($ingredient, 'protein', $quantity);
        }
        
        $messages = [
            [
                'type' => 'info',
                'prefix' => config('mobile_entry_messages.form_guidance.meal_contains'),
                'text' => $meal->ingredients->count() . ' ingredients'
            ],
            [
                'type' => 'tip',
                'prefix' => config('mobile_entry_messages.form_guidance.nutrition_info'),
                'text' => round($totalCalories) . ' cal, ' . round($totalProtein, 1) . 'g protein per serving'
            ]
        ];
        
        if ($meal->comments) {
            $messages[] = [
                'type' => 'neutral',
                'prefix' => 'Meal notes:',
                'text' => $meal->comments
            ];
        }
        
        // Build form using ComponentBuilder
        $formBuilder = C::form($formId, $meal->name . ' (Meal)')
            ->type('success')
            ->formAction(route('food-logs.add-meal'))
            ->deleteAction(route('mobile-entry.remove-food-form', ['id' => $formId]));
        
        // Add messages
        foreach ($messages as $message) {
            $formBuilder->message($message['type'], $message['text'], $message['prefix'] ?? null);
        }
        
        // Build and customize form data
        $formData = $formBuilder->build();
        $formData['data']['itemName'] = $meal->name;
        $formData['data']['numericFields'] = [
            [
                'id' => $formId . '-portion',
                'name' => 'portion',
                'label' => 'Portion:',
                'defaultValue' => 1.0,
                'increment' => 0.25,
                'step' => 'any',
                'min' => 0.1,
                'max' => 10,
                'ariaLabels' => [
                    'decrease' => 'Decrease portion',
                    'increase' => 'Increase portion'
                ]
            ],
            [
                'id' => $formId . '-notes',
                'name' => 'notes',
                'label' => 'Notes:',
                'type' => 'textarea',
                'placeholder' => config('mobile_entry_messages.placeholders.food_notes'),
                'defaultValue' => '',
                'ariaLabels' => [
                    'field' => 'Notes'
                ]
            ]
        ];
        $formData['data']['buttons'] = [
            'decrement' => '-',
            'increment' => '+',
            'submit' => 'Log ' . $meal->name
        ];
        $formData['data']['ariaLabels'] = [
            'section' => $meal->name . ' entry',
            'deleteForm' => 'Remove this meal form'
        ];
        $formData['data']['hiddenFields'] = [
            'meal_id' => $meal->id,
            'logged_at_meal' => $this->getRoundedTime(),
            'meal_date' => $selectedDate->toDateString(),
            'redirect_to' => 'mobile-entry-foods'
        ];
        $formData['data']['deleteParams'] = [
            'date' => $selectedDate->toDateString()
        ];
        
        return $formData;
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
     * @param int $userId
     * @param string $type 'ingredient' or 'meal'
     * @param int $id Ingredient or Meal ID
     * @param Carbon $selectedDate
     * @return array Result with success/error status and message
     */
    public function addFoodForm($userId, $type, $id, Carbon $selectedDate)
    {
        if ($type === 'ingredient') {
            $ingredient = Ingredient::where('id', $id)
                ->where('user_id', $userId)
                ->with('baseUnit')
                ->first();
            
            if (!$ingredient) {
                return [
                    'success' => false,
                    'message' => config('mobile_entry_messages.error.food_not_found')
                ];
            }
            
            if (!$ingredient->baseUnit) {
                return [
                    'success' => false,
                    'message' => config('mobile_entry_messages.error.ingredient_no_unit')
                ];
            }
            
            // Check if already added
            $existingForm = MobileFoodForm::where('user_id', $userId)
                ->where('date', $selectedDate->toDateString())
                ->where('type', $type)
                ->where('item_id', $id)
                ->first();
            
            if ($existingForm) {
                return [
                    'success' => false,
                    'message' => str_replace(':food', $ingredient->name, config('mobile_entry_messages.error.food_already_in_forms'))
                ];
            }
            
            // Add to database
            try {
                MobileFoodForm::create([
                    'user_id' => $userId,
                    'date' => $selectedDate->toDateString(),
                    'type' => $type,
                    'item_id' => $id,
                    'item_name' => $ingredient->name
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle unique constraint violation
                if ($e->getCode() === '23000') {
                    return [
                        'success' => false,
                        'message' => "{$ingredient->name} is already added to your forms."
                    ];
                }
                throw $e;
            }
            
            return [
                'success' => true,
                'message' => ''
            ];
            
        } elseif ($type === 'meal') {
            $meal = Meal::where('id', $id)
                ->where('user_id', $userId)
                ->with('ingredients')
                ->first();
            
            if (!$meal) {
                return [
                    'success' => false,
                    'message' => config('mobile_entry_messages.error.food_not_found')
                ];
            }
            
            if ($meal->ingredients->isEmpty()) {
                return [
                    'success' => false,
                    'message' => config('mobile_entry_messages.error.meal_no_ingredients')
                ];
            }
            
            // Check if already added
            $existingForm = MobileFoodForm::where('user_id', $userId)
                ->where('date', $selectedDate->toDateString())
                ->where('type', $type)
                ->where('item_id', $id)
                ->first();
            
            if ($existingForm) {
                return [
                    'success' => false,
                    'message' => str_replace(':food', $meal->name, config('mobile_entry_messages.error.food_already_in_forms'))
                ];
            }
            
            // Add to database
            try {
                MobileFoodForm::create([
                    'user_id' => $userId,
                    'date' => $selectedDate->toDateString(),
                    'type' => $type,
                    'item_id' => $id,
                    'item_name' => $meal->name
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle unique constraint violation
                if ($e->getCode() === '23000') {
                    return [
                        'success' => false,
                        'message' => "{$meal->name} is already added to your forms."
                    ];
                }
                throw $e;
            }
            
            return [
                'success' => true,
                'message' => ''
            ];
        }
        
        return [
            'success' => false,
            'message' => config('mobile_entry_messages.error.food_not_found')
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
     * @param int $userId
     * @param string $formId Form ID (format: ingredient-{id} or meal-{id})
     * @return array Result with success/error status and message
     */
    public function removeFoodForm($userId, $formId)
    {
        // Extract type and id from formId (e.g., "ingredient-123" or "meal-456")
        if (preg_match('/^(ingredient|meal)-(\d+)$/', $formId, $matches)) {
            $type = $matches[1];
            $id = $matches[2];
            
            $form = MobileFoodForm::where('user_id', $userId)
                ->where('type', $type)
                ->where('item_id', $id)
                ->first();
            
            if ($form) {
                $itemName = $form->item_name;
                $form->delete();
                
                return [
                    'success' => true,
                    'message' => str_replace(':food', $itemName, config('mobile_entry_messages.success.food_form_removed'))
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => config('mobile_entry_messages.error.food_form_not_found')
        ];
    }

    /**
     * Clean up old mobile food forms for a user (keep only last 3 days)
     * 
     * @param int $userId
     * @param Carbon $currentDate
     */
    public function cleanupOldForms($userId, Carbon $currentDate)
    {
        MobileFoodForm::where('user_id', $userId)
            ->where('date', '<', $currentDate->copy()->subDays(3))
            ->delete();
    }

    /**
     * Remove a specific form after successful logging
     * 
     * @param int $userId
     * @param string $type
     * @param int $itemId
     * @param Carbon $date
     * @return bool
     */
    public function removeFormAfterLogging($userId, $type, $itemId, Carbon $date)
    {
        $form = MobileFoodForm::where('user_id', $userId)
            ->where('type', $type)
            ->where('item_id', $itemId)
            ->whereDate('date', $date->toDateString())
            ->first();
            
        if ($form) {
            \Log::info("Removing mobile food form after successful logging", [
                'user_id' => $userId,
                'type' => $type,
                'item_id' => $itemId,
                'item_name' => $form->item_name,
                'date' => $date->toDateString()
            ]);
            
            $form->delete();
            return true;
        }
        
        \Log::warning("Mobile food form not found for removal", [
            'user_id' => $userId,
            'type' => $type,
            'item_id' => $itemId,
            'date' => $date->toDateString()
        ]);
        
        return false;
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
        
        // Check if user has any food forms for today
        $formCount = MobileFoodForm::forUserAndDate($userId, $selectedDate)->count();
            
        // Check if user has logged any food today
        $loggedCount = FoodLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->count();
        
        if ($formCount === 0 && $loggedCount === 0) {
            // First time user or no food items added yet
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Getting started:',
                'text' => config('mobile_entry_messages.contextual_help.getting_started_food')
            ];
        } elseif ($formCount > 0 && $loggedCount === 0) {
            // Has food items ready but hasn't logged anything
            $plural = $formCount > 1 ? 's' : '';
            $text = str_replace([':count', ':plural'], [$formCount, $plural], config('mobile_entry_messages.contextual_help.ready_to_log_food'));
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Ready to log:',
                'text' => $text
            ];
        } elseif ($formCount > 0 && $loggedCount > 0) {
            // Has logged some but has more forms to complete
            $plural = $formCount > 1 ? 's' : '';
            $text = str_replace([':count', ':plural'], [$formCount, $plural], config('mobile_entry_messages.contextual_help.keep_logging_food'));
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Keep going:',
                'text' => $text
            ];
        } elseif ($loggedCount > 0) {
            // Has logged food but no pending forms
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