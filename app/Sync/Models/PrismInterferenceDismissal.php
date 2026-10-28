<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrismInterferenceDismissal extends Model
{
    protected $table = 'prism_interference_dismissals';

    protected $fillable = [
        'gym_id',
        'resolution_key',
        'device_id',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id', 'id');
    }
}
