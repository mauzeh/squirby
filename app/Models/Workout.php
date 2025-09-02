<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workout extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_id',
        'weight',
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
        if ($this->workoutSets->isEmpty()) {
            return 0;
        }

        // Check for uniformity
        $isUniform = true;
        $firstSet = $this->workoutSets->first();
        foreach ($this->workoutSets as $set) {
            if ($set->weight !== $firstSet->weight || $set->reps !== $firstSet->reps) {
                $isUniform = false;
                break;
            }
        }

        if ($isUniform) {
            // Use the old calculation if uniform
            return $firstSet->weight * (1 + (0.0333 * $firstSet->reps));
        } else {
            // If not uniform, use the first set's data (as per current implementation)
            return $firstSet->weight * (1 + (0.0333 * $firstSet->reps));
        }
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
        if ($this->workoutSets->isEmpty()) {
            return 0;
        }

        return $this->workoutSets->max(function ($workoutSet) {
            return $workoutSet->one_rep_max;
        });
    }
}
