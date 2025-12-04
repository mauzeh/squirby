<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exercise Matching Alias
 * 
 * Stores alternative names/abbreviations for exercises to improve WOD matching.
 * Unlike user-specific display aliases, these are global and used for matching only.
 * 
 * Examples:
 * - "KB Swing" → Kettlebell Swing
 * - "GHD Situp" → GHD Sit-up
 * - "T2B" → Toes to Bar
 */
class ExerciseMatchingAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_id',
        'alias',
    ];

    /**
     * Get the exercise this alias belongs to
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
