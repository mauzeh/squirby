<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\Ingredient;
use Illuminate\Http\Request;

class MealController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $meals = Meal::all();
        return view('meals.index', compact('meals'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $ingredients = Ingredient::orderBy('name')->get();
        return view('meals.create', compact('ingredients'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:meals,name',
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $meal = Meal::create(['name' => $request->name]);

        foreach ($request->ingredients as $item) {
            $meal->ingredients()->attach($item['ingredient_id'], ['quantity' => $item['quantity']]);
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
        $meal->load('ingredients');
        $ingredients = Ingredient::orderBy('name')->get();
        return view('meals.edit', compact('meal', 'ingredients'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Meal $meal)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:meals,name,' . $meal->id,
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $meal->update(['name' => $request->name]);

        $syncData = [];
        foreach ($request->ingredients as $item) {
            $syncData[$item['ingredient_id']] = ['quantity' => $item['quantity']];
        }
        $meal->ingredients()->sync($syncData);

        return redirect()->route('meals.index')->with('success', 'Meal updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Meal $meal)
    {
        $meal->delete();
        return redirect()->route('meals.index')->with('success', 'Meal deleted successfully.');
    }
}