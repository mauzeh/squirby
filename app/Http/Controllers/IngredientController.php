<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ingredients = Ingredient::with('baseUnit')->orderBy('name')->get();
        return view('ingredients.index', compact('ingredients'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $units = Unit::all();
        return view('ingredients.create', compact('units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'calories' => 'required|numeric|min:0',
            'protein' => 'required|numeric|min:0',
            'carbs' => 'required|numeric|min:0',
            'added_sugars' => 'required|numeric|min:0',
            'fats' => 'required|numeric|min:0',
            'sodium' => 'required|numeric|min:0',
            'iron' => 'required|numeric|min:0',
            'potassium' => 'required|numeric|min:0',
            'base_quantity' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:units,id',
        ]);

        Ingredient::create($request->all());

        return redirect()->route('ingredients.index')
                         ->with('success', 'Ingredient created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Ingredient $ingredient)
    {
        return view('ingredients.show', compact('ingredient'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ingredient $ingredient)
    {
        $units = Unit::all();
        return view('ingredients.edit', compact('ingredient', 'units'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ingredient $ingredient)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'calories' => 'required|numeric|min:0',
            'protein' => 'required|numeric|min:0',
            'carbs' => 'required|numeric|min:0',
            'added_sugars' => 'required|numeric|min:0',
            'fats' => 'required|numeric|min:0',
            'sodium' => 'required|numeric|min:0',
            'iron' => 'required|numeric|min:0',
            'potassium' => 'required|numeric|min:0',
            'base_quantity' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:units,id',
        ]);

        $ingredient->update($request->all());

        return redirect()->route('ingredients.index')
                         ->with('success', 'Ingredient updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ingredient $ingredient)
    {
        $ingredient->delete();

        return redirect()->route('ingredients.index')
                         ->with('success', 'Ingredient deleted successfully.');
    }
}