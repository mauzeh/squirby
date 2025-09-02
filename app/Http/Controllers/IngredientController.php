<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ingredients = Ingredient::with('baseUnit')->where('user_id', auth()->id())->orderBy('name')->get();
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
            'fiber' => 'required|numeric|min:0',
            'calcium' => 'required|numeric|min:0',
            'caffeine' => 'required|numeric|min:0',
            'base_quantity' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:units,id',
            'cost_per_unit' => 'required|numeric|min:0',
        ]);

        Ingredient::create(array_merge($request->all(), ['user_id' => auth()->id()]));

        return redirect()->route('ingredients.index')
                         ->with('success', 'Ingredient created successfully.');
    }

    

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ingredient $ingredient)
    {
        if ($ingredient->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $units = Unit::all();
        return view('ingredients.edit', compact('ingredient', 'units'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ingredient $ingredient)
    {
        if ($ingredient->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
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
            'fiber' => 'required|numeric|min:0',
            'calcium' => 'required|numeric|min:0',
            'caffeine' => 'required|numeric|min:0',
            'base_quantity' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:units,id',
            'cost_per_unit' => 'required|numeric|min:0',
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
        if ($ingredient->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $ingredient->delete();

        return redirect()->route('ingredients.index')
                         ->with('success', 'Ingredient deleted successfully.');
    }
}