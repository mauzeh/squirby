<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $fillable = ['name', 'comments'];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'meal_ingredients')->withPivot('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
