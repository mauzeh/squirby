<?php

namespace App\Sync\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthletePreference extends Model
{
    protected $table = 'athlete_preferences';

    protected $fillable = [
        'user_id',
        'preferences_data',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'preferences_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
