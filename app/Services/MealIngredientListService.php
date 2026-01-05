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
        $itemListBuilder->createForm(
            route('ingredients.store'),
            'name',
            ['redirect_to' => 'meals.edit', 'meal_id' => $meal->id],
            'Create "{term}"',
            'POST'
        );

        return $itemListBuilder->build();
    }

    /**
     * Generate ingredient selection list for new meal creation (no meal exists yet)
     * 
     * @param int $userId
     * @return array Item list component data
     */
    public function generateIngredientSelectionListForNew(int $userId): array
    {
        // Get user's ingredients
        $ingredients = Ingredient::where('user_id', $userId)
            ->orderBy('name', 'asc')
            ->get();

        $items = [];
        
        foreach ($ingredients as $ingredient) {
            $items[] = [
                'id' => 'ingredient-' . $ingredient->id,
                'name' => $ingredient->name,
                'href' => route('meals.add-ingredient-new', [
                    'ingredient' => $ingredient->id
                ])
            ];
        }

        $itemListBuilder = C::itemList()
            ->filterPlaceholder('Search ingredients...')
            ->noResultsMessage('No ingredients found.')
            ->initialState('expanded');

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
        $itemListBuilder->createForm(
            route('ingredients.store'),
            'name',
            ['redirect_to' => 'meals.create'],
            'Create "{term}"',
            'POST'
        );

        return $itemListBuilder->build();
    }

    /**
     * Generate quantity form component for ingredient quantity input
     * 
     * @param Ingredient $ingredient
     * @param Meal|null $meal
     * @param float|null $currentQuantity
     * @return array Form component data
     */
    public function generateQuantityForm(Ingredient $ingredient, Meal $meal = null, float $currentQuantity = null): array
    {
        $isEditing = $currentQuantity !== null;
        
        // Create title with ingredient name
        if ($isEditing) {
            $title = 'Edit ' . $ingredient->name;
        } else {
            $title = 'Add ' . $ingredient->name;
        }
        
        // Determine form action and method
        if ($meal) {
            if ($isEditing) {
                $action = route('meals.edit-quantity', [$meal->id, $ingredient->id]);
                $method = 'POST';
            } else {
                $action = route('meals.store-ingredient', $meal->id);
                $method = 'POST';
            }
        } else {
            $action = route('meals.store-ingredient-new');
            $method = 'POST';
        }

        $formBuilder = C::form('quantity-form', $title)
            ->formAction($action);

        // Hidden ingredient ID field
        $formBuilder->hiddenField('ingredient_id', $ingredient->id);

        // If creating a new meal, add meal name field
        if (!$meal) {
            $formBuilder->textField('meal_name', 'Meal Name', '', 'Enter meal name');
        }

        // Quantity field with base unit in label
        $quantityLabel = 'Quantity';
        if ($ingredient->baseUnit) {
            $quantityLabel .= ' (' . $ingredient->baseUnit->name . ')';
        }
        $formBuilder->numericField('quantity', $quantityLabel, $currentQuantity ?: '', 0.01, 0.01);

        // Submit button
        $buttonText = $isEditing ? 'Update Quantity' : 'Add to Meal';
        $formBuilder->submitButton($buttonText);

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
            'showEditButtons' => true,
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
                            'text' => 'No ingredients in this meal yet.'
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
            
            // Add edit button if enabled
            if ($options['showEditButtons']) {
                $rowBuilder->linkAction(
                    'fa-edit',
                    route('meals.edit-quantity', [$meal->id, $ingredient->id]),
                    'Edit quantity',
                    'btn-transparent'
                );
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