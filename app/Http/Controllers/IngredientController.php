<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Unit;
use App\Services\IngredientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    protected IngredientService $ingredientService;
    
    public function __construct(IngredientService $ingredientService)
    {
        $this->ingredientService = $ingredientService;
    }
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
    public function create(Request $request)
    {
        $prefilledName = $request->query('name', '');
        
        $units = Unit::all();
        $unitOptions = $this->ingredientService->buildUnitOptions($units);
        
        $data = [
            'components' => [
                $this->ingredientService->generateCreateFormComponent($prefilledName),
                $this->ingredientService->buildFormComponent($unitOptions, null, $prefilledName),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'protein' => 'required|numeric|min:0',
            'carbs' => 'required|numeric|min:0',
            'added_sugars' => 'nullable|numeric|min:0',
            'fats' => 'required|numeric|min:0',
            'sodium' => 'nullable|numeric|min:0',
            'iron' => 'nullable|numeric|min:0',
            'potassium' => 'nullable|numeric|min:0',
            'fiber' => 'nullable|numeric|min:0',
            'calcium' => 'nullable|numeric|min:0',
            'caffeine' => 'nullable|numeric|min:0',
            'base_quantity' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:units,id',
            'cost_per_unit' => 'required|numeric|min:0',
        ]);

        $data = $request->except('calories');
        Ingredient::create(array_merge($data, ['user_id' => auth()->id()]));

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
        $unitOptions = $this->ingredientService->buildUnitOptions($units);
        
        $data = [
            'components' => [
                $this->ingredientService->generateEditFormComponent($ingredient),
                $this->ingredientService->buildFormComponent($unitOptions, $ingredient),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
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
            'protein' => 'required|numeric|min:0',
            'carbs' => 'required|numeric|min:0',
            'added_sugars' => 'nullable|numeric|min:0',
            'fats' => 'required|numeric|min:0',
            'sodium' => 'nullable|numeric|min:0',
            'iron' => 'nullable|numeric|min:0',
            'potassium' => 'nullable|numeric|min:0',
            'fiber' => 'nullable|numeric|min:0',
            'calcium' => 'nullable|numeric|min:0',
            'caffeine' => 'nullable|numeric|min:0',
            'base_quantity' => 'required|numeric|min:0.01',
            'base_unit_id' => 'required|exists:units,id',
            'cost_per_unit' => 'required|numeric|min:0',
        ]);

        $data = $request->except('calories');
        $ingredient->update($data);

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