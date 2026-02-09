<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PRHighFive extends Model
{
    protected $table = 'pr_high_fives';

    protected $fillable = [
        'user_id',
        'personal_record_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personalRecord(): BelongsTo
    {
        return $this->belongsTo(PersonalRecord::class);
    }
}
