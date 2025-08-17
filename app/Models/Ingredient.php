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
    ];
}
