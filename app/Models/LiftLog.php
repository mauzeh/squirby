<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\OneRepMaxCalculatorService;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LiftLog extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['exercise_id', 'comments', 'logged_at', 'user_id', 'workout_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    protected $table = 'lift_logs';

    protected $fillable = [
        'exercise_id',
        'comments',
        'logged_at',
        'user_id',
        'workout_id',
        'is_pr',
        'pr_count',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($liftLog) {
            // Soft delete all associated LiftSet records
            $liftLog->liftSets()->each(function ($liftSet) {
                $liftSet->delete();
            });
        });
    }

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
        
        // Ensure liftSets are loaded to avoid N+1
        if (!$this->relationLoaded('liftSets')) {
            $this->load('liftSets');
        }
        
        // Get first lift set
        $firstSet = $this->relationLoaded('liftSets') 
            ? $this->liftSets->first() 
            : $this->liftSets()->first();
            
        if (!$firstSet) {
            return 0;
        }
        
        // Delegate to exercise type strategy to get the raw display value
        $strategy = $this->exercise->getTypeStrategy();
        return $strategy->getRawDisplayWeight($firstSet);
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

    public function workout()
    {
        return $this->belongsTo(Workout::class);
    }

    public function personalRecords()
    {
        return $this->hasMany(PersonalRecord::class);
    }

    public function isPR(): bool
    {
        return $this->is_pr;
    }

    public function getPRCount(): int
    {
        return $this->pr_count;
    }
}