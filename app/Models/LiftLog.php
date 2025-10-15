<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\OneRepMaxCalculatorService;

class LiftLog extends Model
{
    use HasFactory;

    protected $table = 'lift_logs';

    protected $fillable = [
        'exercise_id',
        'comments',
        'logged_at',
        'user_id',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    public function liftSets()
    {
        return $this->hasMany(LiftSet::class);
    }

    public function getOneRepMaxAttribute()
    {
        $calculator = new OneRepMaxCalculatorService();
        return $calculator->getLiftLogOneRepMax($this);
    }

    public function getDisplayRepsAttribute()
    {
        return $this->liftSets->first()->reps ?? 0;
    }

    public function getDisplayRoundsAttribute()
    {
        return $this->liftSets->count();
    }

    public function getDisplayWeightAttribute()
    {
        if ($this->exercise->isBandedResistance() || $this->exercise->isBandedAssistance()) {
            return $this->liftSets->first()->band_color ?? 'N/A';
        }
        return $this->liftSets->first()->weight ?? 0;
    }

    public function getBestOneRepMaxAttribute()
    {
        $calculator = new OneRepMaxCalculatorService();
        return $calculator->getBestLiftLogOneRepMax($this);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}