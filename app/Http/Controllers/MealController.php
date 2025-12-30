<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\Ingredient;
use App\Models\FoodLog;
use App\Services\NutritionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Services\ComponentBuilder as C;

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
        $components[] = $tableBuilder->build(); // Add the built table to the components array

        $data = [
            'components' => $components,
        ];

        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $ingredients = Ingredient::where('user_id', auth()->id())->orderBy('name')->get();
        return view('meals.create', compact('ingredients'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:meals,name',
            'comments' => 'nullable|string',
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'nullable|exists:ingredients,id',
            'ingredients.*.quantity' => 'nullable|numeric|min:0.01',
        ]);

        $meal = Meal::create(array_merge(['name' => $request->name, 'comments' => $request->comments], ['user_id' => auth()->id()]));

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
        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $meal->load('ingredients');
        $ingredients = Ingredient::where('user_id', auth()->id())->orderBy('name')->get();
        return view('meals.edit', compact('meal', 'ingredients'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Meal $meal)
    {
        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'name' => 'required|string|max:255|unique:meals,name,' . $meal->id,
            'comments' => 'nullable|string',
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'nullable|exists:ingredients,id',
            'ingredients.*.quantity' => 'nullable|numeric|min:0.01',
        ]);

        $meal->update(['name' => $request->name, 'comments' => $request->comments]);

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
        if ($meal->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $meal->delete();
        return redirect()->route('meals.index')->with('success', 'Meal deleted successfully.');
    }
}