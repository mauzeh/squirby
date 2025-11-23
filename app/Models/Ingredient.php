<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'protein', 'carbs', 'added_sugars', 'fats', 'sodium', 'iron', 'potassium', 'fiber', 'calcium', 'caffeine', 'base_quantity', 'base_unit_id', 'cost_per_unit', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    protected $fillable = [
        'name',
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

    protected $attributes = [
        'added_sugars' => 0,
        'sodium' => 0,
        'iron' => 0,
        'potassium' => 0,
        'fiber' => 0,
        'calcium' => 0,
        'caffeine' => 0,
    ];

    public function getCaloriesAttribute()
    {
        return ($this->protein * 4) + ($this->carbs * 4) + ($this->fats * 9);
    }

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

    public function foodLogs()
    {
        return $this->hasMany(FoodLog::class);
    }
}
