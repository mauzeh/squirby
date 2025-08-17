<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'calories',
        'protein',
        'carbs',
        'added_sugars',
        'fats',
        'sodium',
        'iron',
        'potassium',
        'base_quantity',
        'base_unit_id',
        'cost_per_unit',
    ];

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function calculateTotalMacro(string $nutrient, float $quantity)
    {
        if ($nutrient === 'calories') {
            return (
                (9 * $this->fats) +
                (4 * $this->carbs) +
                (4 * $this->protein)
            ) * ($quantity / $this->base_quantity);
        }

        // A list of fillable properties that are also nutrients
        $nutrientProperties = [
            'protein', 'carbs', 'added_sugars', 'fats', 'sodium', 'iron', 'potassium'
        ];

        if (in_array($nutrient, $nutrientProperties)) {
            return $this->$nutrient * ($quantity / $this->base_quantity);
        }

        return 0;
    }

    public function calculateCostForQuantity(float $quantity)
    {
        return ($this->cost_per_unit / $this->base_quantity) * $quantity;
    }
}