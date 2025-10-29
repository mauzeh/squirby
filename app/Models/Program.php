<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $casts = [
        'date' => 'date',
    ];

    protected $fillable = [
        'user_id',
        'exercise_id',
        'date',
        'sets',
        'reps',
        'comments',
        'priority',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * Check if this program was completed (has corresponding lift logs on the same date)
     * 
     * @return bool
     */
    public function isCompleted()
    {
        // If completion status is already loaded via withCompletionStatus scope, use it
        if (isset($this->attributes['is_completed'])) {
            return (bool) $this->attributes['is_completed'];
        }
        
        return LiftLog::where('user_id', $this->user_id)
            ->where('exercise_id', $this->exercise_id)
            ->whereDate('logged_at', $this->date)
            ->exists();
    }

    /**
     * Get the lift logs that correspond to this program
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLiftLogs()
    {
        return LiftLog::where('user_id', $this->user_id)
            ->where('exercise_id', $this->exercise_id)
            ->whereDate('logged_at', $this->date)
            ->with(['liftSets'])
            ->get();
    }

    /**
     * Scope to get only completed programs
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->whereHas('exercise', function ($exerciseQuery) {
            $exerciseQuery->whereExists(function ($liftLogQuery) {
                $liftLogQuery->select(\DB::raw(1))
                    ->from('lift_logs')
                    ->whereColumn('lift_logs.user_id', 'programs.user_id')
                    ->whereColumn('lift_logs.exercise_id', 'programs.exercise_id')
                    ->whereRaw('DATE(lift_logs.logged_at) = DATE(programs.date)');
            });
        });
    }

    /**
     * Scope to get only incomplete programs
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncomplete($query)
    {
        return $query->whereDoesntHave('exercise', function ($exerciseQuery) {
            $exerciseQuery->whereExists(function ($liftLogQuery) {
                $liftLogQuery->select(\DB::raw(1))
                    ->from('lift_logs')
                    ->whereColumn('lift_logs.user_id', 'programs.user_id')
                    ->whereColumn('lift_logs.exercise_id', 'programs.exercise_id')
                    ->whereRaw('DATE(lift_logs.logged_at) = DATE(programs.date)');
            });
        });
    }

    /**
     * Scope to add completion status as a computed column
     * This helps avoid N+1 queries when checking isCompleted() on multiple programs
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCompletionStatus($query)
    {
        return $query->addSelect([
            '*',
            'is_completed' => \DB::table('lift_logs')
                ->select(\DB::raw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END'))
                ->whereColumn('lift_logs.user_id', 'programs.user_id')
                ->whereColumn('lift_logs.exercise_id', 'programs.exercise_id')
                ->whereRaw('DATE(lift_logs.logged_at) = DATE(programs.date)')
        ]);
    }
}