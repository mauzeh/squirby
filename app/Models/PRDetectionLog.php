<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PRDetectionLog extends Model
{
    public $timestamps = false;
    
    protected $table = 'pr_detection_logs';

    protected $fillable = [
        'lift_log_id',
        'user_id',
        'exercise_id',
        'pr_types_detected',
        'calculation_snapshot',
        'trigger_event',
        'detected_at',
    ];

    protected $casts = [
        'pr_types_detected' => 'array',
        'calculation_snapshot' => 'array',
        'detected_at' => 'datetime',
    ];

    public function liftLog(): BelongsTo
    {
        return $this->belongsTo(LiftLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
