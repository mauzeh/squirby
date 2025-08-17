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
    ];

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function calculateCaloriesForQuantity(float $quantity, Unit $unit)
    {
        // Convert the provided quantity to the ingredient's base unit
        $convertedQuantity = ($quantity * $unit->conversion_factor) / $this->baseUnit->conversion_factor;

        // Calculate calories based on the ingredient's base quantity and converted quantity
        return (
            (9 * $this->fats) +
            (4 * $this->carbs) +
            (4 * $this->protein)
        ) * ($convertedQuantity / $this->base_quantity);
    }
}