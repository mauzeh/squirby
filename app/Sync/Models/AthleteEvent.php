<?php

namespace App\Sync\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteEvent extends Model
{
    protected $table = 'athlete_events';

    protected $fillable = [
        'user_id',
        'device_id',
        'event_data',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
