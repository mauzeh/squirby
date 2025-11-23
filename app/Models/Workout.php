<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Workout extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'name', 'description', 'notes', 'is_public', 'tags', 'times_used'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    
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
     * 
     * Note: This method now just increments the usage counter.
     * Users will click on individual exercises to log them via lift-logs/create.
     */
    public function applyToDate(Carbon $date, User $user): void
    {
        // No longer creates MobileLiftForm records
        // Users navigate directly to lift-logs/create for each exercise
        
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
