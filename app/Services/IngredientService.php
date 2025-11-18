<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Unit;
use App\Services\ComponentBuilder as C;

class IngredientService
{
    /**
     * Generate the create form component
     *
     * @param string $prefilledName
     * @return array
     */
    public function generateCreateFormComponent(string $prefilledName = ''): array
    {
        $units = Unit::all();
        $unitOptions = $this->buildUnitOptions($units);
        
        return C::title('Create New Ingredient', 'Enter ingredient information')
            ->backButton('fa-arrow-left', route('ingredients.index'), 'Back to ingredients')
            ->build();
    }
    
    /**
     * Generate the edit form component
     *
     * @param Ingredient $ingredient
     * @return array
     */
    public function generateEditFormComponent(Ingredient $ingredient): array
    {
        $units = Unit::all();
        $unitOptions = $this->buildUnitOptions($units);
        
        return C::title('Edit Ingredient', 'Update ingredient information')
            ->backButton('fa-arrow-left', route('ingredients.index'), 'Back to ingredients')
            ->build();
    }
    
    /**
     * Build the ingredient form component
     *
     * @param array $unitOptions
     * @param Ingredient|null $ingredient
     * @param string $prefilledName
     * @return array
     */
    public function buildFormComponent(array $unitOptions, ?Ingredient $ingredient = null, string $prefilledName = ''): array
    {
        $isEdit = $ingredient !== null;
        
        $formBuilder = C::form('ingredient-form', 'Ingredient Details')
            ->type('primary')
            ->formAction($isEdit ? route('ingredients.update', $ingredient) : route('ingredients.store'));
        
        if ($isEdit) {
            $formBuilder->hiddenField('_method', 'PUT');
            $formBuilder->deleteAction(route('ingredients.destroy', $ingredient));
            $formBuilder->confirmMessage('Are you sure you want to delete this ingredient?');
        }
        
        // Section 1: General Information (required)
        $formBuilder->section('General Information', false, 'expanded')
            ->textField('name', 'Ingredient Name:', $isEdit ? $ingredient->name : $prefilledName, 'e.g., Chicken Breast')
            ->numericField('base_quantity', 'Base Quantity:', $isEdit ? $ingredient->base_quantity : 1, 0.01, 0.01, 9999)
            ->selectField('base_unit_id', 'Base Unit:', $unitOptions, $isEdit ? $ingredient->base_unit_id : ($unitOptions[0]['value'] ?? 1))
            ->numericField('cost_per_unit', 'Cost Per Unit ($):', $isEdit ? $ingredient->cost_per_unit : 0, 0.01, 0, 9999);
        
        // Section 2: Nutritional Information (required macros)
        $formBuilder->section('Nutritional Information', false, 'expanded')
            ->numericField('protein', 'Protein (g):', $isEdit ? $ingredient->protein : 0, 0.1, 0, 999)
            ->numericField('carbs', 'Carbohydrates (g):', $isEdit ? $ingredient->carbs : 0, 0.1, 0, 999)
            ->numericField('fats', 'Fats (g):', $isEdit ? $ingredient->fats : 0, 0.1, 0, 999);
        
        // Section 3: Micronutrients (optional, collapsible)
        $formBuilder->section('Micronutrients (Optional)', true, 'collapsed')
            ->message('tip', 'All fields in this section are optional', 'Optional:')
            ->message('info', 'These help track detailed nutrition information', 'Purpose:')
            ->numericField('fiber', 'Fiber (g):', $isEdit ? ($ingredient->fiber ?? 0) : 0, 0.1, 0, 999)
            ->numericField('added_sugars', 'Added Sugars (g):', $isEdit ? ($ingredient->added_sugars ?? 0) : 0, 0.1, 0, 999)
            ->numericField('sodium', 'Sodium (mg):', $isEdit ? ($ingredient->sodium ?? 0) : 0, 1, 0, 9999)
            ->numericField('calcium', 'Calcium (mg):', $isEdit ? ($ingredient->calcium ?? 0) : 0, 1, 0, 9999)
            ->numericField('iron', 'Iron (mg):', $isEdit ? ($ingredient->iron ?? 0) : 0, 0.1, 0, 999)
            ->numericField('potassium', 'Potassium (mg):', $isEdit ? ($ingredient->potassium ?? 0) : 0, 1, 0, 9999)
            ->numericField('caffeine', 'Caffeine (mg):', $isEdit ? ($ingredient->caffeine ?? 0) : 0, 1, 0, 9999);
        
        $formBuilder->submitButton($isEdit ? 'Update Ingredient' : 'Create Ingredient');
        
        return $formBuilder->build();
    }
    
    /**
     * Build unit options array for select field
     *
     * @param \Illuminate\Database\Eloquent\Collection $units
     * @return array
     */
    public function buildUnitOptions($units): array
    {
        $unitOptions = [];
        foreach ($units as $unit) {
            $unitOptions[] = [
                'value' => $unit->id,
                'label' => $unit->name . ' (' . $unit->abbreviation . ')'
            ];
        }
        return $unitOptions;
    }
}
