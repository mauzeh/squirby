<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workout extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'notes',
        'is_public',
        'tags',
        'times_used',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'tags' => 'array',
        'times_used' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('order');
    }

    /**
     * Apply this workout to a specific date for a user
     */
    public function applyToDate(Carbon $date, User $user): void
    {
        foreach ($this->exercises as $workoutExercise) {
            MobileLiftForm::create([
                'user_id' => $user->id,
                'exercise_id' => $workoutExercise->exercise_id,
                'date' => $date,
            ]);
        }

        $this->increment('times_used');
    }

    /**
     * Create a copy of this workout for another user
     */
    public function duplicate(User $user): self
    {
        $newWorkout = self::create([
            'user_id' => $user->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => false,
            'tags' => $this->tags,
        ]);

        foreach ($this->exercises as $exercise) {
            WorkoutExercise::create([
                'workout_id' => $newWorkout->id,
                'exercise_id' => $exercise->exercise_id,
                'order' => $exercise->order,
            ]);
        }

        return $newWorkout;
    }
}
