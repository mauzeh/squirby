<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkoutTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    /**
     * Get the user that owns the template
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the exercises in this template with order
     */
    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'workout_template_exercises')
                    ->withPivot('order')
                    ->withTimestamps()
                    ->orderBy('workout_template_exercises.order');
    }

    /**
     * Scope to get templates for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if the template can be edited by the user
     */
    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Check if the template can be deleted by the user
     */
    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
