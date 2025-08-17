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
        // Calculate calories based on the ingredient's base quantity and logged quantity
        return (
            (9 * $this->fats) +
            (4 * $this->carbs) +
            (4 * $this->protein)
        ) * ($quantity / $this->base_quantity);
    }

    public function calculateProteinForQuantity(float $quantity, Unit $unit)
    {
        // Calculate protein based on the ingredient's base quantity and logged quantity
        return $this->protein * ($quantity / $this->base_quantity);
    }

    public function calculateCarbsForQuantity(float $quantity, Unit $unit)
    {
        // Calculate carbs based on the ingredient's base quantity and logged quantity
        return $this->carbs * ($quantity / $this->base_quantity);
    }

    public function calculateFatsForQuantity(float $quantity, Unit $unit)
    {
        // Calculate fats based on the ingredient's base quantity and logged quantity
        return $this->fats * ($quantity / $this->base_quantity);
    }

    public function calculateAddedSugarsForQuantity(float $quantity, Unit $unit)
    {
        // Calculate added sugars based on the ingredient's base quantity and logged quantity
        return $this->added_sugars * ($quantity / $this->base_quantity);
    }

    public function calculateSodiumForQuantity(float $quantity, Unit $unit)
    {
        // Calculate sodium based on the ingredient's base quantity and logged quantity
        return $this->sodium * ($quantity / $this->base_quantity);
    }

    public function calculateIronForQuantity(float $quantity, Unit $unit)
    {
        // Calculate iron based on the ingredient's base quantity and logged quantity
        return $this->iron * ($quantity / $this->base_quantity);
    }

    public function calculatePotassiumForQuantity(float $quantity, Unit $unit)
    {
        // Calculate potassium based on the ingredient's base quantity and logged quantity
        return $this->potassium * ($quantity / $this->base_quantity);
    }
}