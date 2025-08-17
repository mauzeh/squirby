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

    public function calculateTotalMacro(string $macro, float $quantity)
    {
        if ($macro === 'calories') {
            return (
                (9 * $this->fats) +
                (4 * $this->carbs) +
                (4 * $this->protein)
            ) * ($quantity / $this->base_quantity);
        }

        // A list of fillable properties that are also macros
        // This allows for easy extension if more macros are added in the future
        $macros = [
            'protein', 'carbs', 'added_sugars', 'fats', 'sodium', 'iron', 'potassium'
        ];

        if (in_array($macro, $macros)) {
            return $this->$macro * ($quantity / $this->base_quantity);
        }

        return 0;
    }
}