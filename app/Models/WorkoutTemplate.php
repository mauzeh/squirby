<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
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
        return $this->hasMany(WorkoutTemplateExercise::class)->orderBy('order');
    }

    /**
     * Apply this template to a specific date for a user
     */
    public function applyToDate(Carbon $date, User $user): void
    {
        foreach ($this->exercises as $templateExercise) {
            MobileLiftForm::create([
                'user_id' => $user->id,
                'exercise_id' => $templateExercise->exercise_id,
                'date' => $date,
            ]);
        }

        $this->increment('times_used');
    }

    /**
     * Create a copy of this template for another user
     */
    public function duplicate(User $user): self
    {
        $newTemplate = self::create([
            'user_id' => $user->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => false,
            'tags' => $this->tags,
        ]);

        foreach ($this->exercises as $exercise) {
            WorkoutTemplateExercise::create([
                'workout_template_id' => $newTemplate->id,
                'exercise_id' => $exercise->exercise_id,
                'sets' => $exercise->sets,
                'reps' => $exercise->reps,
                'order' => $exercise->order,
                'notes' => $exercise->notes,
                'rest_seconds' => $exercise->rest_seconds,
            ]);
        }

        return $newTemplate;
    }
}
