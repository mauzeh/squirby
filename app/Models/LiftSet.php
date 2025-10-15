<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\OneRepMaxCalculatorService;

class LiftSet extends Model
{
    use HasFactory;

    protected $table = 'lift_sets';

    protected $fillable = [
        'lift_log_id',
        'weight',
        'reps',
        'notes',
        'band_color',
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