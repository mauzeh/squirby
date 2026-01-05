<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\Ingredient;
use App\Services\ComponentBuilder as C;
use Illuminate\Support\Facades\Auth;

class MealIngredientListService
{
    protected $nutritionService;

    public function __construct(NutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    /**
     * Generate ingredient selection list for adding ingredients to an existing meal
     * 
     * @param Meal $meal
     * @param array $options Configuration options
     * @return array Item list component data
     */
    public function generateIngredientSelectionList(Meal $meal, array $options = []): array
    {
        // Default options
        $options = array_merge([
            'initialState' => 'collapsed', // or 'expanded'
        ], $options);

        $userId = Auth::id();
        
        // Get user's ingredients
        $ingredients = Ingredient::where('user_id', $userId)
            ->orderBy('name', 'asc')
            ->get();

        // Get ingredients already in this meal (to exclude from selection list)
        $mealIngredientIds = $meal->ingredients()->pluck('ingredient_id')->toArray();

        $items = [];
        
        foreach ($ingredients as $ingredient) {
            // Skip ingredients already in meal
            if (in_array($ingredient->id, $mealIngredientIds)) {
                continue;
            }
            
            $items[] = [
                'id' => 'ingredient-' . $ingredient->id,
                'name' => $ingredient->name,
                'href' => route('meals.add-ingredient', [
                    $meal->id,
                    'ingredient' => $ingredient->id
                ])
            ];
        }

        $itemListBuilder = C::itemList()
            ->filterPlaceholder('Search ingredients...')
            ->noResultsMessage('No ingredients found.')
            ->initialState($options['initialState']);

        foreach ($items as $item) {
            $itemListBuilder->item(
                $item['id'],
                $item['name'],
                $item['href'],
                'Ingredient',
                'ingredient',
                3
            );
        }

        // Add create form for new ingredients
        // Use base route without query parameters and add redirect params as hidden fields
        $itemListBuilder->createForm(
            route('ingredients.create'),
            'name',
            [
                'redirect_to' => 'meals.edit',
                'meal_id' => $meal->id,
            ],
            'Create "{term}"',
            'GET'
        );

        return $itemListBuilder->build();
    }

    /**
     * Generate quantity form component for adding ingredient to meal
     * 
     * @param Ingredient $ingredient
     * @param Meal $meal
     * @return array Form component data
     */
    public function generateQuantityForm(Ingredient $ingredient, Meal $meal): array
    {
        // Create form without title
        $formBuilder = C::form('quantity-form', '')
            ->formAction(route('meals.store-ingredient', $meal->id));

        // Hidden ingredient ID field
        $formBuilder->hiddenField('ingredient_id', $ingredient->id);

        // Ingredient section with ingredient name as title
        $formBuilder->section($ingredient->name);
        
        // Quantity field with base unit in label
        $quantityLabel = 'Quantity';
        if ($ingredient->baseUnit) {
            $quantityLabel .= ' (' . $ingredient->baseUnit->name . ')';
        }
        $formBuilder->numericField('quantity', $quantityLabel, '', 0.01, 0.01);

        // Submit button
        $formBuilder->submitButton('Add to Meal');

        return $formBuilder->build();
    }

    /**
     * Generate ingredient list table component for meal editing interface
     * 
     * @param Meal $meal
     * @param array $options Configuration options
     * @return array Table component data
     */
    public function generateIngredientListTable(Meal $meal, array $options = []): array
    {
        // Default options
        $options = array_merge([
            'showDeleteButtons' => true,
            'compactMode' => true,
        ], $options);

        // Load ingredients with pivot data
        $meal->load('ingredients');

        if ($meal->ingredients->isEmpty()) {
            return [
                'type' => 'messages',
                'data' => [
                    'messages' => [
                        [
                            'type' => 'info',
                            'text' => 'Add ingredients above to build your meal.'
                        ]
                    ]
                ]
            ];
        }

        $tableBuilder = C::table();

        foreach ($meal->ingredients as $ingredient) {
            $quantity = $ingredient->pivot->quantity;
            $unit = $ingredient->baseUnit ? $ingredient->baseUnit->name : '';
            
            $line1 = $ingredient->name;
            $line2 = $quantity . ' ' . $unit;
            $line3 = null;
            
            $rowBuilder = $tableBuilder->row($ingredient->id, $line1, $line2, $line3);
            
            if ($options['compactMode']) {
                $rowBuilder->compact();
            }
            
            // Add delete button if enabled
            if ($options['showDeleteButtons']) {
                $rowBuilder->formAction(
                    'fa-trash',
                    route('meals.remove-ingredient', [$meal->id, $ingredient->id]),
                    'DELETE',
                    [],
                    'Remove ingredient',
                    'btn-transparent',
                    true
                );
            }
            
            $rowBuilder->add();
        }

        return $tableBuilder
            ->confirmMessage('deleteItem', 'Are you sure you want to remove this ingredient from the meal?')
            ->build();
    }
}