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
        // Cache the result to avoid recalculating
        if (!isset($this->attributes['cached_one_rep_max'])) {
            try {
                $calculator = new OneRepMaxCalculatorService();
                $this->attributes['cached_one_rep_max'] = $calculator->getLiftLogOneRepMax($this);
            } catch (\Exception $e) {
                $this->attributes['cached_one_rep_max'] = 0;
            }
        }
        return $this->attributes['cached_one_rep_max'];
    }

    public function getDisplayRepsAttribute()
    {
        // Use relationLoaded to check if liftSets are already loaded
        if ($this->relationLoaded('liftSets')) {
            return $this->liftSets->first()->reps ?? 0;
        }
        
        // Fallback to a single query if not loaded
        return $this->liftSets()->first()->reps ?? 0;
    }

    public function getDisplayRoundsAttribute()
    {
        // Use relationLoaded to check if liftSets are already loaded
        if ($this->relationLoaded('liftSets')) {
            return $this->liftSets->count();
        }
        
        // Fallback to a count query if not loaded
        return $this->liftSets()->count();
    }

    public function getDisplayWeightAttribute()
    {
        // Check if exercise is loaded to avoid N+1
        if (!$this->relationLoaded('exercise')) {
            $this->load('exercise');
        }
        
        if ($this->exercise->isBandedResistance() || $this->exercise->isBandedAssistance()) {
            if ($this->relationLoaded('liftSets')) {
                return $this->liftSets->first()->band_color ?? 'N/A';
            }
            return $this->liftSets()->first()->band_color ?? 'N/A';
        }
        
        if ($this->relationLoaded('liftSets')) {
            return $this->liftSets->first()->weight ?? 0;
        }
        return $this->liftSets()->first()->weight ?? 0;
    }

    public function getBestOneRepMaxAttribute()
    {
        // Cache the result to avoid recalculating
        if (!isset($this->attributes['cached_best_one_rep_max'])) {
            try {
                $calculator = new OneRepMaxCalculatorService();
                $this->attributes['cached_best_one_rep_max'] = $calculator->getBestLiftLogOneRepMax($this);
            } catch (\Exception $e) {
                $this->attributes['cached_best_one_rep_max'] = 0;
            }
        }
        return $this->attributes['cached_best_one_rep_max'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}