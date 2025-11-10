<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutTemplateExercise extends Model
{
    protected $fillable = [
        'workout_template_id',
        'exercise_id',
        'sets',
        'reps',
        'order',
        'notes',
        'rest_seconds',
    ];

    protected $casts = [
        'sets' => 'integer',
        'reps' => 'integer',
        'order' => 'integer',
        'rest_seconds' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
