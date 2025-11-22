<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meal extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'comments', 'user_id'];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'meal_ingredients')->withPivot('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
