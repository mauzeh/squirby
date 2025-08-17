<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\Ingredient;
use Carbon\Carbon;

class NutritionService
{
    public function calculateDailyTotals($logs)
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

        foreach ($logs as $log) {
            foreach ($macroNutrients as $nutrient) {
                $totals[$nutrient] += $this->calculateTotalMacro($log->ingredient, $nutrient, $log->quantity);
            }
            $totals['cost'] += $this->calculateCostForQuantity($log->ingredient, $log->quantity);
        }

        return $totals;
    }

    public function calculateTotalMacro(Ingredient $ingredient, string $nutrient, float $quantity)
    {
        if ($nutrient === 'calories') {
            return (
                (9 * $ingredient->fats) +
                (4 * $ingredient->carbs) +
                (4 * $ingredient->protein)
            ) * ($quantity / $ingredient->base_quantity);
        }

        $nutrientProperties = [
            'protein', 'carbs', 'added_sugars', 'fats', 'sodium', 'iron', 'potassium'
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
