<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\OneRepMaxCalculatorService;

class WorkoutSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_id',
        'weight',
        'reps',
        'notes',
    ];

    public function workout()
    {
        return $this->belongsTo(Workout::class);
    }

    public function getOneRepMaxAttribute()
    {
        $calculator = new OneRepMaxCalculatorService();
        return $calculator->calculateOneRepMax($this->weight, $this->reps);
    }
}
