<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrismProgramCell extends Model
{
    protected $table = 'prism_program_cells';

    protected $fillable = [
        'gym_id',
        'program_uuid',
        'cell_id',
        'text',
        'device_id',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id', 'id');
    }
}
