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

    protected static function booted()
    {
        static::saved(function ($workout) {
            // Auto-sync exercises when wod_parsed is set or updated
            if ($workout->isWod() && $workout->wod_parsed) {
                $workout->syncWodExercises();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'name', 'description', 'notes', 'is_public', 'times_used'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'notes',
        'is_public',
        'times_used',
        'wod_syntax',
        'wod_parsed',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'times_used' => 'integer',
        'wod_parsed' => 'array',
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
     * Check if this is a WOD (has syntax)
     */
    public function isWod(): bool
    {
        return !empty($this->wod_syntax);
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
            'wod_syntax' => $this->wod_syntax,
            'wod_parsed' => $this->wod_parsed,
            'is_public' => false,
        ]);

        // Exercises will be auto-synced via model event

        return $newWorkout;
    }

    /**
     * Sync WOD exercises to workout_exercises table
     * Extracts loggable exercises from parsed WOD and creates/updates workout_exercises records
     */
    public function syncWodExercises(): void
    {
        if (!isset($this->wod_parsed['blocks'])) {
            return;
        }

        // Delete existing workout exercises for this WOD
        $this->exercises()->delete();

        $order = 1;
        foreach ($this->wod_parsed['blocks'] as $block) {
            if (!isset($block['exercises'])) {
                continue;
            }

            foreach ($block['exercises'] as $exerciseData) {
                // Handle special formats (nested exercises)
                if ($exerciseData['type'] === 'special_format' && isset($exerciseData['exercises'])) {
                    foreach ($exerciseData['exercises'] as $nestedExercise) {
                        if ($nestedExercise['type'] === 'exercise' && !empty($nestedExercise['loggable'])) {
                            $this->createWorkoutExercise($nestedExercise, $order++);
                        }
                    }
                    continue;
                }

                // Handle regular exercises
                if ($exerciseData['type'] === 'exercise' && !empty($exerciseData['loggable'])) {
                    $this->createWorkoutExercise($exerciseData, $order++);
                }
            }
        }
    }

    /**
     * Helper method to create a workout exercise
     */
    private function createWorkoutExercise(array $exerciseData, int $order): void
    {
        // Find or create the exercise
        $exercise = Exercise::where('title', $exerciseData['name'])
            ->availableToUser($this->user_id)
            ->first();

        if (!$exercise) {
            // Create new exercise for this user
            $exercise = Exercise::create([
                'title' => $exerciseData['name'],
                'user_id' => $this->user_id,
            ]);
        }

        // Create workout exercise with scheme
        WorkoutExercise::create([
            'workout_id' => $this->id,
            'exercise_id' => $exercise->id,
            'order' => $order,
            'scheme' => $exerciseData['scheme'] ?? null,
        ]);
    }
}
