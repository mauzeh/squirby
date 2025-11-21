<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMergeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_exercise_id',
        'source_exercise_title',
        'target_exercise_id',
        'target_exercise_title',
        'admin_user_id',
        'admin_email',
        'lift_log_ids',
        'lift_log_count',
        'alias_created',
    ];

    protected $casts = [
        'lift_log_ids' => 'array',
        'lift_log_count' => 'integer',
        'alias_created' => 'boolean',
    ];

    /**
     * Get the admin user who performed the merge.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Get the target exercise (if it still exists).
     */
    public function targetExercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'target_exercise_id');
    }
}
