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

    public function calculateProteinForQuantity(float $quantity, Unit $unit)
    {
        // Convert the provided quantity to the ingredient's base unit
        $convertedQuantity = ($quantity * $unit->conversion_factor) / $this->baseUnit->conversion_factor;

        // Calculate protein based on the ingredient's base quantity and converted quantity
        return $this->protein * ($convertedQuantity / $this->base_quantity);
    }

    public function calculateCarbsForQuantity(float $quantity, Unit $unit)
    {
        // Convert the provided quantity to the ingredient's base unit
        $convertedQuantity = ($quantity * $unit->conversion_factor) / $this->baseUnit->conversion_factor;

        // Calculate carbs based on the ingredient's base quantity and converted quantity
        return $this->carbs * ($convertedQuantity / $this->base_quantity);
    }

    public function calculateFatsForQuantity(float $quantity, Unit $unit)
    {
        // Convert the provided quantity to the ingredient's base unit
        $convertedQuantity = ($quantity * $unit->conversion_factor) / $this->baseUnit->conversion_factor;

        // Calculate fats based on the ingredient's base quantity and converted quantity
        return $this->fats * ($convertedQuantity / $this->base_quantity);
    }
}