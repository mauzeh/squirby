<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IngredientController extends Controller
{
    protected $tsvImporterService;

    public function __construct(\App\Services\TsvImporterService $tsvImporterService)
    {
        $this->tsvImporterService = $tsvImporterService;
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

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'required|string',
        ]);

        $tsvData = trim($validated['tsv_data']);

        if (empty($tsvData)) {
            return redirect()
                ->route('ingredients.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        try {
            $result = $this->tsvImporterService->importIngredients($tsvData, auth()->id());

            // Check if there was an error in the result
            if (isset($result['error'])) {
                return redirect()
                    ->route('ingredients.index')
                    ->with('error', 'Import failed: ' . $result['error']);
            }

            $message = $this->buildImportSuccessMessage($result);
            
            return redirect()
                ->route('ingredients.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()
                ->route('ingredients.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Build detailed import success message with lists of imported, updated, and skipped ingredients.
     */
    private function buildImportSuccessMessage(array $result): string
    {
        $html = "<p>TSV data processed successfully!</p>";

        // Imported ingredients
        if ($result['importedCount'] > 0) {
            $html .= "<p>Imported {$result['importedCount']} new ingredients:</p><ul>";
            foreach ($result['importedIngredients'] as $ingredient) {
                $html .= "<li>" . e($ingredient['name']) . " ({$ingredient['base_quantity']}" . e($ingredient['unit_abbreviation'] ?? '') . ")</li>";
            }
            $html .= "</ul>";
        }

        // Updated ingredients
        if ($result['updatedCount'] > 0) {
            $html .= "<p>Updated {$result['updatedCount']} existing ingredients:</p><ul>";
            foreach ($result['updatedIngredients'] as $ingredient) {
                $changeDetails = [];
                if (isset($ingredient['changes'])) {
                    foreach ($ingredient['changes'] as $field => $change) {
                        // Handle special field formatting
                        if ($field === 'base_unit_id') {
                            $changeDetails[] = "unit: '" . e($change['from']) . "' → '" . e($change['to']) . "'";
                        } else {
                            $changeDetails[] = e($field) . ": '" . e($change['from']) . "' → '" . e($change['to']) . "'";
                        }
                    }
                }
                $changeText = !empty($changeDetails) ? " (" . implode(', ', $changeDetails) . ")" : "";
                $html .= "<li>" . e($ingredient['name']) . $changeText . "</li>";
            }
            $html .= "</ul>";
        }

        // Skipped ingredients
        if ($result['skippedCount'] > 0) {
            $html .= "<p>Skipped {$result['skippedCount']} ingredients:</p><ul>";
            foreach ($result['skippedIngredients'] as $skipped) {
                $html .= "<li>" . e($skipped['name']) . " - " . e($skipped['reason']) . "</li>";
            }
            $html .= "</ul>";
        }

        // Invalid rows
        if (count($result['invalidRows']) > 0) {
            $html .= "<p>Found " . count($result['invalidRows']) . " invalid rows that were skipped.</p>";
        }

        if ($result['importedCount'] === 0 && $result['updatedCount'] === 0) {
            $html .= "<p>No new data was imported or updated - all entries already exist with the same data.</p>";
        }

        return $html;
    }
}