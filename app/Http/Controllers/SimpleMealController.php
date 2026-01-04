<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\Ingredient;
use App\Services\MealIngredientListService;
use App\Services\NutritionService;
use App\Services\ComponentBuilder as C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimpleMealController extends Controller
{
    protected $ingredientListService;
    protected $nutritionService;

    public function __construct(
        MealIngredientListService $ingredientListService,
        NutritionService $nutritionService
    ) {
        $this->ingredientListService = $ingredientListService;
        $this->nutritionService = $nutritionService;
    }

    /**
     * Display a listing of meals (preserving existing MealController implementation)
     */
    public function index()
    {
        $meals = Meal::with('ingredients.baseUnit')->where('user_id', auth()->id())->get();

        foreach ($meals as $meal) {
            $meal->total_macros = $this->nutritionService->calculateFoodLogTotals($meal->ingredients);
        }

        $components = [
            C::title('Meals List')->build(),
        ];

        // Add session messages if they exist
        $messagesComponent = C::messagesFromSession();
        if ($messagesComponent) {
            $components[] = $messagesComponent;
        }

        $components[] = C::button('Add New Meal')
            ->ariaLabel('Add new meal')
            ->addClass('btn-add-item')
            ->asLink(route('meals.create'))
            ->build();

        $tableBuilder = C::table()
            ->emptyMessage('No meals found. Please add some!')
            ->ariaLabel('List of meals')
            ->spacedRows()
            ->confirmMessage('deleteItem', 'Are you sure you want to delete this meal?');

        if ($meals->isNotEmpty()) {
            foreach ($meals as $meal) {
                $summary = '';
                foreach ($meal->ingredients as $ingredient) {
                    $summary .= $ingredient->pivot->quantity . ' ' . $ingredient->baseUnit->abbreviation . ' ' . $ingredient->name . '<br>';
                }

                $tableBuilder->row(
                    $meal->id,
                    $meal->name,
                    $meal->comments
                )
                ->wrapText()
                ->badge(round($meal->total_macros['calories']) . ' cal', 'info')
                ->badge(round($meal->total_macros['protein']) . 'g P', 'neutral')
                ->badge(round($meal->total_macros['carbs']) . 'g C', 'neutral')
                ->badge(round($meal->total_macros['fats']) . 'g F', 'neutral')
                ->badge('$' . number_format($meal->total_macros['cost'], 2), 'success')
                ->subItem($meal->id, 'Ingredients:', '')
                    ->message('info', $summary)
                    ->add()
                ->linkAction('fa-pencil', route('meals.edit', $meal->id), 'Edit', 'btn-transparent')
                ->formAction('fa-trash', route('meals.destroy', $meal->id), 'DELETE', ['redirect' => 'meals.index'], 'Delete', 'btn-danger btn-transparent', true)
                ->compact()
                ->add();
            }
        }
        $components[] = $tableBuilder->build();

        $data = [
            'components' => $components,
        ];

        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new meal (ingredient selection interface)
     */
    public function create()
    {
        $components = [];
        
        // Title
        $components[] = C::title('Create Meal')->build();
        
        // Info message
        $components[] = C::messages()
            ->info('Select ingredients below to add them to your meal.')
            ->build();
        
        // Ingredient selection list - always expanded on create page
        $ingredientSelectionList = $this->ingredientListService->generateIngredientSelectionListForNew(Auth::id());
        $components[] = $ingredientSelectionList;
        
        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for editing an existing meal (meal builder interface)
     */
    public function edit(Meal $meal)
    {
        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $components = [];
        
        // Title
        $components[] = C::title('Edit Meal: ' . $meal->name)->build();
        
        // Add session messages if they exist
        $messagesComponent = C::messagesFromSession();
        if ($messagesComponent) {
            $components[] = $messagesComponent;
        }

        // Calculate and display nutritional information if meal has ingredients
        $meal->load('ingredients');
        if ($meal->ingredients->isNotEmpty()) {
            $totalMacros = $this->nutritionService->calculateFoodLogTotals($meal->ingredients);
            
            $nutritionComponent = C::messages()
                ->info('Nutritional Information: ' . 
                    round($totalMacros['calories']) . ' cal, ' .
                    round($totalMacros['protein']) . 'g protein, ' .
                    round($totalMacros['carbs']) . 'g carbs, ' .
                    round($totalMacros['fats']) . 'g fat, ' .
                    '$' . number_format($totalMacros['cost'], 2) . ' cost'
                )
                ->build();
            $components[] = $nutritionComponent;
        }

        // Current ingredients table
        $ingredientTable = $this->ingredientListService->generateIngredientListTable($meal);
        $components[] = $ingredientTable;

        // Add ingredient button/section
        $components[] = C::button('Add Ingredient')
            ->ariaLabel('Add ingredient to meal')
            ->addClass('btn-add-item')
            ->asLink(route('meals.add-ingredient', $meal->id))
            ->build();

        // Ingredient selection list (collapsed by default)
        $ingredientSelectionList = $this->ingredientListService->generateIngredientSelectionList($meal, [
            'initialState' => 'collapsed'
        ]);
        $components[] = $ingredientSelectionList;

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Remove the specified meal from storage
     */
    public function destroy(Meal $meal)
    {
        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        
        $meal->delete();
        
        return redirect()->route('meals.index')->with('success', 'Meal deleted successfully.');
    }

    /**
     * Show quantity form for adding an ingredient to a meal
     */
    public function addIngredient(Request $request, Meal $meal = null)
    {
        $ingredientId = $request->input('ingredient');
        
        if (!$ingredientId) {
            return redirect()->back()->with('error', 'No ingredient specified.');
        }
        
        $ingredient = Ingredient::where('id', $ingredientId)
            ->where('user_id', Auth::id())
            ->first();
        
        if (!$ingredient) {
            return redirect()->back()->with('error', 'Ingredient not found.');
        }
        
        // Check for duplicates if meal exists
        if ($meal && $meal->ingredients()->where('ingredient_id', $ingredient->id)->exists()) {
            return redirect()
                ->route('meals.edit', $meal->id)
                ->with('warning', 'Ingredient already in meal.');
        }
        
        // Generate quantity form
        $form = $this->ingredientListService->generateQuantityForm($ingredient, $meal);
        
        return view('mobile-entry.flexible', ['data' => ['components' => [$form]]]);
    }

    /**
     * Store ingredient with quantity (handles meal creation for first ingredient)
     */
    public function storeIngredient(Request $request, Meal $meal = null)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'meal_name' => 'nullable|string|max:255',
        ]);
        
        $ingredient = Ingredient::where('id', $validated['ingredient_id'])
            ->where('user_id', Auth::id())
            ->first();
        
        if (!$ingredient) {
            return redirect()->back()->with('error', 'Ingredient not found.');
        }
        
        // Create meal if it doesn't exist (first ingredient being added)
        if (!$meal) {
            $meal = Meal::create([
                'user_id' => Auth::id(),
                'name' => $validated['meal_name'] ?: 'New Meal',
                'comments' => null,
            ]);
        } else {
            // Check authorization for existing meal
            if ($meal->user_id !== Auth::id()) {
                abort(403, 'Unauthorized action.');
            }
        }
        
        // Check for duplicates
        if ($meal->ingredients()->where('ingredient_id', $ingredient->id)->exists()) {
            return redirect()
                ->route('meals.edit', $meal->id)
                ->with('warning', 'Ingredient already in meal.');
        }
        
        // Add ingredient to meal
        $meal->ingredients()->attach($ingredient->id, ['quantity' => $validated['quantity']]);
        
        return redirect()
            ->route('meals.edit', $meal->id)
            ->with('success', 'Ingredient added!');
    }

    /**
     * Show form for editing existing ingredient quantity
     */
    public function updateQuantity(Request $request, Meal $meal, $ingredientId)
    {
        if ($meal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $ingredient = Ingredient::where('id', $ingredientId)
            ->where('user_id', Auth::id())
            ->first();
        
        if (!$ingredient) {
            return redirect()->back()->with('error', 'Ingredient not found.');
        }

        // Get current quantity from pivot table
        $mealIngredient = $meal->ingredients()->where('ingredient_id', $ingredient->id)->first();
        
        if (!$mealIngredient) {
            return redirect()
                ->route('meals.edit', $meal->id)
                ->with('error', 'Ingredient not found in meal.');
        }

        $currentQuantity = $mealIngredient->pivot->quantity;

        // If this is a POST request, update the quantity
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'quantity' => 'required|numeric|min:0.01',
            ]);

            $meal->ingredients()->updateExistingPivot($ingredient->id, [
                'quantity' => $validated['quantity']
            ]);

            return redirect()
                ->route('meals.edit', $meal->id)
                ->with('success', 'Quantity updated!');
        }

        // Generate quantity form with current value
        $form = $this->ingredientListService->generateQuantityForm($ingredient, $meal, $currentQuantity);
        
        return view('mobile-entry.flexible', ['data' => ['components' => [$form]]]);
    }

    /**
     * Remove ingredient from meal (with meal deletion logic for last ingredient)
     */
    public function removeIngredient(Meal $meal, $ingredientId)
    {
        if ($meal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $ingredient = Ingredient::where('id', $ingredientId)
            ->where('user_id', Auth::id())
            ->first();
        
        if (!$ingredient) {
            return redirect()->back()->with('error', 'Ingredient not found.');
        }

        // Check if ingredient is in the meal
        if (!$meal->ingredients()->where('ingredient_id', $ingredient->id)->exists()) {
            return redirect()
                ->route('meals.edit', $meal->id)
                ->with('error', 'Ingredient not found in meal.');
        }

        // Remove ingredient from meal
        $meal->ingredients()->detach($ingredient->id);

        // Check if this was the last ingredient - if so, delete the meal
        $meal->load('ingredients');
        if ($meal->ingredients->isEmpty()) {
            $meal->delete();
            return redirect()
                ->route('meals.index')
                ->with('success', 'Ingredient removed and meal deleted (no ingredients remaining).');
        }

        return redirect()
            ->route('meals.edit', $meal->id)
            ->with('success', 'Ingredient removed from meal.');
    }
}