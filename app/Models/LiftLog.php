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
        'track',
        'block_index',
        'movement_index',
        'log_type',
        'device_id',
        'source',
        'idempotency_key',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'is_pr' => 'boolean',
        'pr_count' => 'integer',
        'block_index' => 'integer',
        'movement_index' => 'integer',
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

    public function getDisplayDistanceAttribute(): float
    {
        $first = $this->relationLoaded('liftSets') 
            ? $this->liftSets->first() 
            : $this->liftSets()->first();

        return (float) ($first?->distance ?? 0.0);
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

    /**
     * Determine if all sets in this log have the same weight and reps.
     * A log with 0 or 1 sets is always considered uniform.
     */
    public function hasUniformSets(): bool
    {
        $sets = $this->relationLoaded('liftSets')
            ? $this->liftSets
            : $this->liftSets()->get();

        if ($sets->count() <= 1) {
            return true;
        }

        $firstSet = $sets->first();

        return $sets->every(fn($set) =>
            (float) $set->weight === (float) $firstSet->weight &&
            (int) $set->reps === (int) $firstSet->reps
        );
    }

    /**
     * Format a human-readable summary of all sets.
     *
     * Uniform sets:   "3×5 @ 185 lbs"
     * Non-uniform:    "185×5 / 205×3 / 225×1"
     *
     * @param string|null $unit  Override unit label (e.g. after conversion). If null, uses first set's unit.
     */
    public function formatSetsSummary(?string $unit = null): string
    {
        $sets = $this->relationLoaded('liftSets')
            ? $this->liftSets
            : $this->liftSets()->get();

        if ($sets->isEmpty()) {
            return '';
        }

        $firstSet = $sets->first();
        $unitLabel = $unit ?? ($firstSet->unit ?? 'lbs');

        if ($this->hasUniformSets()) {
            $count = $sets->count();
            $reps = (int) $firstSet->reps;
            $weight = $this->formatWeight((float) $firstSet->weight);

            if ($weight > 0) {
                return "{$count}×{$reps} @ {$weight} {$unitLabel}";
            }

            return "{$count}×{$reps}";
        }

        // Non-uniform: show each set
        return $sets->map(function ($set) use ($unitLabel) {
            $weight = $this->formatWeight((float) $set->weight);
            $reps = (int) $set->reps;

            if ($weight > 0) {
                return "{$weight}×{$reps}";
            }

            return "{$reps} reps";
        })->implode(' / ') . " {$unitLabel}";
    }

    /**
     * Format a weight value, removing unnecessary decimals.
     */
    private function formatWeight(float $weight): string
    {
        return $weight == (int) $weight ? (string) (int) $weight : (string) $weight;
    }
}