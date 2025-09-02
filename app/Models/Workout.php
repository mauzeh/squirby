<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\OneRepMaxCalculatorService;

class Workout extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_id',
        'comments',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    public function workoutSets()
    {
        return $this->hasMany(WorkoutSet::class);
    }

    public function getOneRepMaxAttribute()
    {
        $calculator = new OneRepMaxCalculatorService();
        return $calculator->getWorkoutOneRepMax($this);
    }

    public function getDisplayRepsAttribute()
    {
        $var=true;
        return $this->workoutSets->first()->reps ?? 0;
    }

    public function getDisplayRoundsAttribute()
    {
        return $this->workoutSets->count();
    }

    public function getDisplayWeightAttribute()
    {
        return $this->workoutSets->first()->weight ?? 0;
    }

    public function getBestOneRepMaxAttribute()
    {
        $calculator = new OneRepMaxCalculatorService();
        return $calculator->getBestWorkoutOneRepMax($this);
    }
}
