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

class FoodLogService
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

        $dailyTotals = $this->nutritionService->calculateFoodLogTotals($foodLogs);
        
        // Count of food entries today
        $entriesCount = $foodLogs->count();
        
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
                'average' => 'Avg Cal',
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

        $items = [];
        foreach ($logs as $log) {
            if (!$log->ingredient || !$log->unit) {
                continue;
            }

            // Calculate nutrition info for display
            $calories = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', (float)$log->quantity));
            $protein = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', (float)$log->quantity), 1);
            
            $quantityText = $log->quantity . ' ' . $log->unit->name;
            $nutritionText = $calories . ' cal, ' . $protein . 'g protein';

            $items[] = [
                'id' => $log->id,
                'title' => $log->ingredient->name,
                'editAction' => route('food-logs.edit', ['food_log' => $log->id]),
                'deleteAction' => route('food-logs.destroy', ['food_log' => $log->id]),
                'deleteParams' => [
                    'redirect_to' => 'mobile-entry.foods',
                    'date' => $selectedDate->toDateString()
                ],
                'message' => [
                    'type' => 'success',
                    'prefix' => 'Logged:',
                    'text' => $quantityText . ' • ' . $nutritionText
                ],
                'freeformText' => $log->notes
            ];
        }

        $result = [
            'items' => $items,
            'confirmMessages' => [
                'deleteItem' => 'Are you sure you want to delete this food log entry? This action cannot be undone.',
                'removeForm' => 'Are you sure you want to remove this food item from today\'s quick entry?'
            ],
            'ariaLabels' => [
                'section' => 'Logged food entries',
                'editItem' => 'Edit logged entry',
                'deleteItem' => 'Delete logged entry'
            ]
        ];

        // Only include empty message when there are no items
        if (empty($items)) {
            $result['emptyMessage'] = 'No food entries logged yet today!';
        }

        return $result;
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

        // Get the top 5 most recently used ingredients
        $recentIngredientIds = $this->getTopRecentIngredientIds($userId, $selectedDate, 5);

        $items = [];
        
        // Add ingredients
        foreach ($ingredients as $ingredient) {
            $itemType = $this->determineIngredientType($ingredient, $userId, $recentIngredientIds);

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

        // Add meals
        foreach ($meals as $meal) {
            $items[] = [
                'id' => 'meal-' . $meal->id,
                'name' => $meal->name . ' (Meal)',
                'type' => [
                    'label' => 'Meal',
                    'cssClass' => 'meal',
                    'priority' => 2
                ],
                'href' => route('mobile-entry.add-food-form', [
                    'type' => 'meal',
                    'id' => $meal->id,
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
            'noResultsMessage' => 'No food items found. Hit "+" to save as new ingredient.',
            'createForm' => [
                'action' => route('mobile-entry.create-ingredient'),
                'method' => 'POST',
                'inputName' => 'ingredient_name',
                'submitText' => '+',
                'ariaLabel' => 'Create new ingredient',
                'hiddenFields' => [
                    'date' => $selectedDate->toDateString()
                ]
            ],
            'items' => $items,
            'ariaLabels' => [
                'section' => 'Food item selection list',
                'selectItem' => 'Select this food item to log'
            ],
            'filterPlaceholder' => 'Filter food items...'
        ];
    }

    /**
     * Get the top N most recently used ingredient IDs for a user
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @param int $limit
     * @return array
     */
    protected function getTopRecentIngredientIds($userId, Carbon $selectedDate, $limit = 5)
    {
        return FoodLog::where('user_id', $userId)
            ->where('logged_at', '<', $selectedDate->toDateString())
            ->where('logged_at', '>=', $selectedDate->copy()->subDays(30))
            ->select('ingredient_id')
            ->groupBy('ingredient_id')
            ->orderByRaw('MAX(logged_at) DESC')
            ->limit($limit)
            ->pluck('ingredient_id')
            ->toArray();
    }

    /**
     * Determine the item type configuration for an ingredient
     * 
     * @param \App\Models\Ingredient $ingredient
     * @param int $userId
     * @param array $recentIngredientIds
     * @return array Item type configuration with label and cssClass
     */
    protected function determineIngredientType($ingredient, $userId, $recentIngredientIds = [])
    {
        // Check if ingredient is in the top 5 most recently used
        $isTopRecent = in_array($ingredient->id, $recentIngredientIds);

        // Check if it's a user's custom ingredient
        $isCustom = $ingredient->user_id === $userId;

        // Determine type based on priority: recent > custom > regular
        if ($isTopRecent) {
            return $this->getItemTypeConfig('recent');
        } elseif ($isCustom) {
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
            'recent' => [
                'label' => 'Recent',
                'cssClass' => 'recent',
                'priority' => 1
            ],
            'custom' => [
                'label' => 'My Food',
                'cssClass' => 'custom',
                'priority' => 2
            ],
            'regular' => [
                'label' => 'Available',
                'cssClass' => 'regular',
                'priority' => 3
            ],
            'meal' => [
                'label' => 'Meal',
                'cssClass' => 'meal',
                'priority' => 2
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
        
        return [
            'id' => $formId,
            'type' => 'food',
            'title' => $ingredient->name,
            'itemName' => $ingredient->name,
            'formAction' => route('food-logs.store'),
            'deleteAction' => route('mobile-entry.remove-food-form', ['id' => $formId]),
            'deleteParams' => [
                'date' => $selectedDate->toDateString()
            ],
            'messages' => $messages,
            'numericFields' => [
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
                ]
            ],
            'commentField' => [
                'id' => $formId . '-notes',
                'name' => 'notes',
                'label' => 'Notes:',
                'placeholder' => 'Any additional notes...',
                'defaultValue' => ''
            ],
            'buttons' => [
                'decrement' => '-',
                'increment' => '+',
                'submit' => 'Log ' . $ingredient->name
            ],
            'ariaLabels' => [
                'section' => $ingredient->name . ' entry',
                'deleteForm' => 'Remove this food form'
            ],
            // Hidden fields for form submission
            'hiddenFields' => [
                'selected_type' => 'ingredient',
                'selected_id' => $ingredient->id,
                'date' => $selectedDate->toDateString(),
                'redirect_to' => 'mobile-entry-foods'
            ]
        ];
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
                'prefix' => 'Meal contains:',
                'text' => $meal->ingredients->count() . ' ingredients'
            ],
            [
                'type' => 'tip',
                'prefix' => 'Per serving:',
                'text' => round($totalCalories) . ' cal, ' . round($totalProtein, 1) . 'g protein'
            ]
        ];
        
        if ($meal->comments) {
            $messages[] = [
                'type' => 'neutral',
                'prefix' => 'Notes:',
                'text' => $meal->comments
            ];
        }
        
        return [
            'id' => $formId,
            'type' => 'food',
            'title' => $meal->name . ' (Meal)',
            'itemName' => $meal->name,
            'formAction' => route('food-logs.store'),
            'deleteAction' => route('mobile-entry.remove-food-form', ['id' => $formId]),
            'deleteParams' => [
                'date' => $selectedDate->toDateString()
            ],
            'messages' => $messages,
            'numericFields' => [
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
                ]
            ],
            'commentField' => [
                'id' => $formId . '-notes',
                'name' => 'notes',
                'label' => 'Notes:',
                'placeholder' => 'Any additional notes...',
                'defaultValue' => ''
            ],
            'buttons' => [
                'decrement' => '-',
                'increment' => '+',
                'submit' => 'Log ' . $meal->name
            ],
            'ariaLabels' => [
                'section' => $meal->name . ' entry',
                'deleteForm' => 'Remove this meal form'
            ],
            // Hidden fields for form submission
            'hiddenFields' => [
                'selected_type' => 'meal',
                'selected_id' => $meal->id,
                'date' => $selectedDate->toDateString(),
                'redirect_to' => 'mobile-entry-foods'
            ]
        ];
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
        
        // Add last session info if available
        if ($lastSession) {
            $messageText = $lastSession['quantity'] . ' ' . $lastSession['unit'];
            
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Last logged (' . $lastSession['date'] . '):',
                'text' => $messageText
            ];
        }
        
        // Add last session notes if available
        if ($lastSession && !empty($lastSession['notes'])) {
            $messages[] = [
                'type' => 'neutral',
                'prefix' => 'Last notes:',
                'text' => $lastSession['notes']
            ];
        }
        
        // Add nutrition info
        $calories = round($ingredient->calories);
        $protein = round($ingredient->protein, 1);
        
        $messages[] = [
            'type' => 'tip',
            'prefix' => 'Per ' . $ingredient->base_quantity . ' ' . $ingredient->baseUnit->name . ':',
            'text' => $calories . ' cal, ' . $protein . 'g protein'
        ];
        
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
                    'message' => 'Ingredient not found or not accessible.'
                ];
            }
            
            if (!$ingredient->baseUnit) {
                return [
                    'success' => false,
                    'message' => 'Ingredient does not have a valid unit configured.'
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
                    'message' => "{$ingredient->name} is already added to your forms."
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
                'message' => "Added {$ingredient->name} form. You can now log it below."
            ];
            
        } elseif ($type === 'meal') {
            $meal = Meal::where('id', $id)
                ->where('user_id', $userId)
                ->with('ingredients')
                ->first();
            
            if (!$meal) {
                return [
                    'success' => false,
                    'message' => 'Meal not found or not accessible.'
                ];
            }
            
            if ($meal->ingredients->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Meal has no ingredients configured.'
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
                    'message' => "{$meal->name} is already added to your forms."
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
                'message' => "Added {$meal->name} form. You can now log it below."
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Invalid food type specified.'
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
                'message' => "Ingredient '{$ingredientName}' already exists."
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
            'message' => "Created new ingredient: {$ingredient->name}. Please update its nutrition information."
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
                    'message' => "Removed {$itemName} from your forms."
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Food form not found.'
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
     * Generate interface messages from session data
     * 
     * @param array $sessionMessages
     * @return array
     */
    public function generateInterfaceMessages($sessionMessages = [])
    {
        $systemMessages = $this->generateSystemMessages($sessionMessages);
        
        return [
            'messages' => $systemMessages,
            'hasMessages' => !empty($systemMessages),
            'messageCount' => count($systemMessages)
        ];
    }

    /**
     * Generate system messages from session flash data
     * 
     * @param array $sessionMessages
     * @return array
     */
    private function generateSystemMessages($sessionMessages)
    {
        $messages = [];
        
        if (isset($sessionMessages['success'])) {
            $messages[] = [
                'type' => 'success',
                'prefix' => 'Success:',
                'text' => $sessionMessages['success']
            ];
        }
        
        if (isset($sessionMessages['error'])) {
            $messages[] = [
                'type' => 'error',
                'prefix' => 'Error:',
                'text' => $sessionMessages['error']
            ];
        }
        
        if (isset($sessionMessages['warning'])) {
            $messages[] = [
                'type' => 'warning',
                'prefix' => 'Warning:',
                'text' => $sessionMessages['warning']
            ];
        }
        
        if (isset($sessionMessages['info'])) {
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Info:',
                'text' => $sessionMessages['info']
            ];
        }
        
        return $messages;
    }
}