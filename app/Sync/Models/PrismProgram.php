<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrismProgram extends Model
{
    protected $table = 'prism_programs';

    protected $fillable = [
        'gym_id',
        'program_uuid',
        'name',
        'week_count',
        'metadata',
        'archived_at',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id', 'id');
    }
}
