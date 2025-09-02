<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return $this->weight * (1 + (0.0333 * $this->reps));
    }
}
