<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\Ingredient;
use Carbon\Carbon;

class NutritionService
{
    public function calculateDailyTotals($items)
    {
        $totals = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'added_sugars' => 0,
            'fats' => 0,
            'sodium' => 0,
            'iron' => 0,
            'potassium' => 0,
            'cost' => 0,
        ];

        $macroNutrients = ['calories', 'protein', 'carbs', 'added_sugars', 'fats', 'sodium', 'iron', 'potassium'];

        foreach ($items as $item) {
            $ingredient = $item instanceof DailyLog ? $item->ingredient : $item;
            $quantity = $item instanceof DailyLog ? $item->quantity : $item->pivot->quantity;

            foreach ($macroNutrients as $nutrient) {
                $totals[$nutrient] += $this->calculateTotalMacro($ingredient, $nutrient, $quantity);
            }
            $totals['cost'] += $this->calculateCostForQuantity($ingredient, $quantity);
        }

        return $totals;
    }

    public function calculateTotalMacro(Ingredient $ingredient, string $nutrient, float $quantity)
    {
        // A list of fillable properties that are also nutrients
        $nutrientProperties = [
            'calories', 'protein', 'carbs', 'added_sugars', 'fats', 'sodium', 'iron', 'potassium'
        ];

        if (in_array($nutrient, $nutrientProperties)) {
            return $ingredient->$nutrient * ($quantity / $ingredient->base_quantity);
        }

        return 0;
    }

    public function calculateCostForQuantity(Ingredient $ingredient, float $quantity)
    {
        return ($ingredient->cost_per_unit / $ingredient->base_quantity) * $quantity;
    }
}
