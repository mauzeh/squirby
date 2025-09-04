<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

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
        'fiber',
        'calcium',
        'caffeine',
        'base_quantity',
        'base_unit_id',
        'cost_per_unit',
        'user_id',
    ];

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function meals()
    {
        return $this->belongsToMany(Meal::class, 'meal_ingredients')->withPivot('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
