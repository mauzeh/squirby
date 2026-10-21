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
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id', 'id');
    }
}
