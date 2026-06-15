<?php

namespace App\Sync\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteBlueprint extends Model
{
    protected $table = 'athlete_blueprints';

    protected $fillable = [
        'user_id',
        'blueprint_data',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'blueprint_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
