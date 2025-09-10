<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProgramExercise extends Model
{
    protected $table = 'program_exercises';

    protected $fillable = [
        'workout_program_id',
        'exercise_id',
        'sets',
        'reps',
        'notes',
        'exercise_order',
        'exercise_type'
    ];

    protected $casts = [
        'exercise_order' => 'integer',
        'sets' => 'integer',
        'reps' => 'integer'
    ];

    /**
     * Validation rules for the model
     */
    public static function validationRules(): array
    {
        return [
            'workout_program_id' => 'required|exists:workout_programs,id',
            'exercise_id' => 'required|exists:exercises,id',
            'sets' => 'required|integer|min:1|max:20',
            'reps' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:255',
            'exercise_order' => 'required|integer|min:1',
            'exercise_type' => 'required|in:main,accessory'
        ];
    }

    /**
     * Get the next available exercise order for a workout program
     */
    public static function getNextOrderForProgram(int $workoutProgramId): int
    {
        $maxOrder = static::where('workout_program_id', $workoutProgramId)
            ->max('exercise_order');
        
        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Reorder exercises within a program after deletion or reordering
     */
    public static function reorderExercisesForProgram(int $workoutProgramId): void
    {
        $exercises = static::where('workout_program_id', $workoutProgramId)
            ->orderBy('exercise_order')
            ->get();

        foreach ($exercises as $index => $exercise) {
            $exercise->update(['exercise_order' => $index + 1]);
        }
    }

    /**
     * Move this exercise to a new position within the program
     */
    public function moveToPosition(int $newPosition): void
    {
        $currentPosition = $this->exercise_order;
        $workoutProgramId = $this->workout_program_id;

        if ($currentPosition === $newPosition) {
            return;
        }

        // Get all exercises in the program except this one
        $otherExercises = static::where('workout_program_id', $workoutProgramId)
            ->where('id', '!=', $this->id)
            ->orderBy('exercise_order')
            ->get();

        // Temporarily set this exercise to a high order to avoid conflicts
        $this->update(['exercise_order' => 9999]);

        // Adjust positions of other exercises
        if ($newPosition < $currentPosition) {
            // Moving up - shift exercises down
            foreach ($otherExercises as $exercise) {
                if ($exercise->exercise_order >= $newPosition && $exercise->exercise_order < $currentPosition) {
                    $exercise->update(['exercise_order' => $exercise->exercise_order + 1]);
                }
            }
        } else {
            // Moving down - shift exercises up
            foreach ($otherExercises as $exercise) {
                if ($exercise->exercise_order > $currentPosition && $exercise->exercise_order <= $newPosition) {
                    $exercise->update(['exercise_order' => $exercise->exercise_order - 1]);
                }
            }
        }

        // Set this exercise to the new position
        $this->update(['exercise_order' => $newPosition]);
    }

    /**
     * Scope to order exercises by their order within the program
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('exercise_order');
    }

    /**
     * Scope to filter by exercise type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('exercise_type', $type);
    }

    /**
     * Scope to filter by workout program
     */
    public function scopeForProgram(Builder $query, int $workoutProgramId): Builder
    {
        return $query->where('workout_program_id', $workoutProgramId);
    }

    public function workoutProgram(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgram::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
