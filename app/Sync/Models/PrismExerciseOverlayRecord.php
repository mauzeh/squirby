<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrismExerciseOverlayRecord extends Model
{
    protected $table = 'prism_exercise_overlay';

    protected $fillable = [
        'gym_id',
        'exercise_id',
        'record',
        'device_id',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'record' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id', 'id');
    }
}
