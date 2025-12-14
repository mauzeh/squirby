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
            // Auto-sync exercises when wod_syntax changes
            if (!empty($workout->wod_syntax) && $workout->isDirty('wod_syntax')) {
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
    ];

    protected $casts = [
        'is_public' => 'boolean',
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
     * Create a copy of this workout for another user
     */
    public function duplicate(User $user): self
    {
        $newWorkout = self::create([
            'user_id' => $user->id,
            'name' => $this->name,
            'description' => $this->description,
            'wod_syntax' => $this->wod_syntax,
            'is_public' => false,
        ]);

        // Exercises will be auto-synced via model event

        return $newWorkout;
    }

    /**
     * Parse WOD syntax on-demand
     */
    public function getParsedWod(): ?array
    {
        if (empty($this->wod_syntax)) {
            return null;
        }

        try {
            $parser = app(\App\Services\WodParser::class);
            return $parser->parse($this->wod_syntax);
        } catch (\Exception $e) {
            \Log::error('Failed to parse WOD syntax for workout ' . $this->id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync WOD exercises to workout_exercises table
     * Extracts loggable exercises from parsed WOD and creates/updates workout_exercises records
     */
    public function syncWodExercises(): void
    {
        $parsed = $this->getParsedWod();
        
        if (!$parsed || !isset($parsed['blocks'])) {
            return;
        }

        // Delete existing workout exercises for this WOD
        $this->exercises()->delete();

        $order = 1;
        foreach ($parsed['blocks'] as $block) {
            if (!isset($block['exercises'])) {
                continue;
            }

            foreach ($block['exercises'] as $exerciseData) {
                // Handle special formats (nested exercises)
                if ($exerciseData['type'] === 'special_format' && isset($exerciseData['exercises'])) {
                    foreach ($exerciseData['exercises'] as $nestedExercise) {
                        if ($nestedExercise['type'] === 'exercise') {
                            $this->createWorkoutExercise($nestedExercise, $order++);
                        }
                    }
                    continue;
                }

                // Handle regular exercises - all exercises are now loggable
                if ($exerciseData['type'] === 'exercise') {
                    $this->createWorkoutExercise($exerciseData, $order++);
                }
            }
        }
    }

    /**
     * Helper method to create a workout exercise
     * Only links to existing exercises - does NOT auto-create them
     * Uses ExerciseMatchingService to find exercises by name or alias
     */
    private function createWorkoutExercise(array $exerciseData, int $order): void
    {
        // Use the matching service to find exercise by name or alias
        $matchingService = app(\App\Services\ExerciseMatchingService::class);
        $exercise = $matchingService->findBestMatch($exerciseData['name'], $this->user_id);

        if (!$exercise) {
            // Exercise doesn't exist - skip it
            // User should create exercises explicitly or use the alias system
            return;
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
