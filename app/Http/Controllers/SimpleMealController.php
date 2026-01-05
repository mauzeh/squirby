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
     * Show the form for creating a new meal (simple name input form)
     */
    public function create()
    {
        $components = [];
        
        // Title
        $components[] = C::title('Create Meal')->build();
        
        // Info message
        $components[] = C::messages()
            ->info('Enter a name for your new meal. You can add ingredients after creating it.')
            ->build();
        
        // Simple form for meal name
        $formBuilder = C::form('create-meal-form', 'Create New Meal')
            ->formAction(route('meals.store'));
        
        $formBuilder->textField('name', 'Meal Name', '', 'Enter meal name');
        
        $formBuilder->textareaField('comments', 'Comments (Optional)', '', 'Optional comments about this meal');
        
        $formBuilder->submitButton('Create Meal', 'btn-primary');
        
        $components[] = $formBuilder->build();
        
        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created meal
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'comments' => 'nullable|string|max:1000',
        ]);

        $meal = Meal::create([
            'name' => $validated['name'],
            'comments' => $validated['comments'] ?? '',
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('meals.edit', $meal)
            ->with('success', 'Meal created successfully! Now add some ingredients.');
    }

    /**
     * Show the form for editing an existing meal (meal builder interface)
     */
    public function edit(Request $request, Meal $meal)
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

        // Load ingredients to check if meal is empty
        $meal->load('ingredients');
        
        // Check if we should expand the list (from "Add ingredients" button OR if meal has no ingredients)
        $shouldExpandList = $request->query('expand') === 'true' || $meal->ingredients->isEmpty();

        // Calculate and display nutritional information if meal has ingredients
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

        // Add Ingredient button - hidden if list should be expanded (either manually or automatically for empty meals)
        $buttonBuilder = C::button('Add Ingredient')
            ->ariaLabel('Add ingredient to meal')
            ->addClass('btn-add-item');
        
        if ($shouldExpandList) {
            $buttonBuilder->initialState('hidden');
        }
        
        $components[] = $buttonBuilder->build();

        // Ingredient selection list - expanded if coming from "Add ingredients" button OR if meal has no ingredients
        $ingredientSelectionList = $this->ingredientListService->generateIngredientSelectionList($meal, [
            'initialState' => $shouldExpandList ? 'expanded' : 'collapsed'
        ]);
        $components[] = $ingredientSelectionList;

        // Current ingredients table - only show if meal has ingredients
        if ($meal->ingredients->isNotEmpty()) {
            $ingredientTable = $this->ingredientListService->generateIngredientListTable($meal);
            $components[] = $ingredientTable;
        }

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
    public function addIngredient(Request $request, Meal $meal)
    {
        // Check authorization
        if ($meal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

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
        
        // Check for duplicates
        if ($meal->ingredients()->where('ingredient_id', $ingredient->id)->exists()) {
            return redirect()
                ->route('meals.edit', $meal->id)
                ->with('warning', 'Ingredient already in meal.');
        }
        
        $components = [];
        
        // Title with back button
        $components[] = C::title('Add ' . $ingredient->name)
            ->subtitle('to ' . $meal->name)
            ->backButton('fa-arrow-left', route('meals.edit', $meal->id), 'Back to meal')
            ->build();
        
        // Add helpful info message
        
        // Add session messages if they exist
        $messagesComponent = C::messagesFromSession();
        if ($messagesComponent) {
            $components[] = $messagesComponent;
        }
        
        // Add validation error messages as individual messages
        if ($errors = session('errors')) {
            $errorMessages = C::messages();
            
            if ($errors->has('ingredient_id')) {
                $errorMessages->error($errors->first('ingredient_id'));
            }
            if ($errors->has('quantity')) {
                $errorMessages->error($errors->first('quantity'));
            }
            
            // Only add if there are validation errors
            if ($errors->has('ingredient_id') || $errors->has('quantity')) {
                $components[] = $errorMessages->build();
            }
        }
        
        // Generate quantity form
        $form = $this->ingredientListService->generateQuantityForm($ingredient, $meal);
        $components[] = $form;
        
        return view('mobile-entry.flexible', ['data' => ['components' => $components]]);
    }

    /**
     * Store ingredient with quantity (meal must already exist)
     */
    public function storeIngredient(Request $request, Meal $meal)
    {
        // Check authorization
        if ($meal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);
        
        $ingredient = Ingredient::where('id', $validated['ingredient_id'])
            ->where('user_id', Auth::id())
            ->first();
        
        if (!$ingredient) {
            return redirect()->back()->with('error', 'Ingredient not found.');
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
        $components = [];
        
        // Title with back button
        $components[] = C::title('Edit ' . $ingredient->name)
            ->subtitle('in ' . $meal->name)
            ->backButton('fa-arrow-left', route('meals.edit', $meal->id), 'Back to meal')
            ->build();
        
        // Add helpful info message
        $components[] = C::messages()
            ->info('Update the quantity of ' . $ingredient->name . ' in your meal. Current amount: ' . $currentQuantity . ' ' . ($ingredient->baseUnit ? $ingredient->baseUnit->name : 'units') . '.')
            ->build();
        
        // Add session messages if they exist
        $messagesComponent = C::messagesFromSession();
        if ($messagesComponent) {
            $components[] = $messagesComponent;
        }
        
        // Add validation error messages as individual messages
        if ($errors = session('errors')) {
            $errorMessages = C::messages();
            
            if ($errors->has('quantity')) {
                $errorMessages->error($errors->first('quantity'));
            }
            
            // Only add if there are validation errors
            if ($errors->has('quantity')) {
                $components[] = $errorMessages->build();
            }
        }
        
        $form = $this->ingredientListService->generateQuantityForm($ingredient, $meal, $currentQuantity);
        $components[] = $form;
        
        return view('mobile-entry.flexible', ['data' => ['components' => $components]]);
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