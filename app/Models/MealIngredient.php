<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MealIngredient extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['meal_id', 'ingredient_id', 'quantity'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    protected $fillable = [
        'meal_id',
        'ingredient_id',
        'quantity',
    ];

    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}