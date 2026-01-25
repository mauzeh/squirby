<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\OneRepMaxCalculatorService;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LiftSet extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['lift_log_id', 'weight', 'reps', 'time', 'notes', 'band_color'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    protected $table = 'lift_sets';

    protected $fillable = [
        'lift_log_id',
        'weight',
        'reps',
        'time',
        'notes',
        'band_color',
    ];

    protected $casts = [
        'weight' => 'float',
        'reps' => 'integer',
        'time' => 'integer',
        'band_color' => 'string',
    ];

    public function liftLog()
    {
        return $this->belongsTo(LiftLog::class);
    }

    public function getOneRepMaxAttribute()
    {
        $calculator = new OneRepMaxCalculatorService();
        return $calculator->calculateOneRepMax($this->weight, $this->reps);
    }
}