<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ExerciseIntelligence extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['exercise_id', 'canonical_name', 'muscle_data', 'primary_mover', 'largest_muscle', 'movement_archetype', 'category', 'difficulty_level', 'recovery_hours'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    protected $table = 'exercise_intelligence';

    protected $fillable = [
        'exercise_id',
        'canonical_name',
        'muscle_data',
        'primary_mover',
        'largest_muscle',
        'movement_archetype',
        'category',
        'difficulty_level',
        'recovery_hours',
    ];

    protected $casts = [
        'muscle_data' => 'array',
        'difficulty_level' => 'integer',
        'recovery_hours' => 'integer',
    ];

    /**
     * Get the exercise that owns this intelligence data.
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * Scope to filter intelligence data for global exercises only.
     */
    public function scopeForGlobalExercises($query)
    {
        return $query->whereHas('exercise', function ($q) {
            $q->whereNull('user_id');
        });
    }

    /**
     * Scope to filter by movement archetype.
     */
    public function scopeByMovementArchetype($query, string $archetype)
    {
        return $query->where('movement_archetype', $archetype);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get muscles that are primary movers from the muscle data.
     */
    public function getPrimaryMoverMuscles(): array
    {
        if (!isset($this->muscle_data['muscles'])) {
            return [];
        }

        return collect($this->muscle_data['muscles'])
            ->filter(fn($muscle) => $muscle['role'] === 'primary_mover')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get muscles that are synergists from the muscle data.
     */
    public function getSynergistMuscles(): array
    {
        if (!isset($this->muscle_data['muscles'])) {
            return [];
        }

        return collect($this->muscle_data['muscles'])
            ->filter(fn($muscle) => $muscle['role'] === 'synergist')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get muscles that are stabilizers from the muscle data.
     */
    public function getStabilizerMuscles(): array
    {
        if (!isset($this->muscle_data['muscles'])) {
            return [];
        }

        return collect($this->muscle_data['muscles'])
            ->filter(fn($muscle) => $muscle['role'] === 'stabilizer')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get muscles with isotonic contraction type from the muscle data.
     */
    public function getIsotonicMuscles(): array
    {
        if (!isset($this->muscle_data['muscles'])) {
            return [];
        }

        return collect($this->muscle_data['muscles'])
            ->filter(fn($muscle) => $muscle['contraction_type'] === 'isotonic')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get muscles with isometric contraction type from the muscle data.
     */
    public function getIsometricMuscles(): array
    {
        if (!isset($this->muscle_data['muscles'])) {
            return [];
        }

        return collect($this->muscle_data['muscles'])
            ->filter(fn($muscle) => $muscle['contraction_type'] === 'isometric')
            ->pluck('name')
            ->toArray();
    }
}