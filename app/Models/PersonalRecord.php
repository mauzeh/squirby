<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'lift_log_id',
        'pr_type',
        'rep_count',
        'weight',
        'value',
        'previous_pr_id',
        'previous_value',
        'achieved_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'previous_value' => 'decimal:2',
        'weight' => 'decimal:2',
        'achieved_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    public function liftLog()
    {
        return $this->belongsTo(LiftLog::class);
    }

    public function previousPR()
    {
        return $this->belongsTo(PersonalRecord::class, 'previous_pr_id');
    }

    public function supersededBy()
    {
        return $this->hasOne(PersonalRecord::class, 'previous_pr_id');
    }

    public function highFives()
    {
        return $this->hasMany(PRHighFive::class, 'personal_record_id');
    }

    public function comments()
    {
        return $this->hasMany(PRComment::class, 'personal_record_id');
    }

    public function reads()
    {
        return $this->hasMany(PersonalRecordRead::class, 'personal_record_id');
    }

    public function readBy()
    {
        return $this->belongsToMany(User::class, 'personal_record_reads')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->whereDoesntHave('supersededBy');
    }

    public function scopeForExercise($query, $exerciseId)
    {
        return $query->where('exercise_id', $exerciseId);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('pr_type', $type);
    }

    // Helper methods
    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    public function markAsReadBy(User $user): void
    {
        PersonalRecordRead::firstOrCreate([
            'user_id' => $user->id,
            'personal_record_id' => $this->id,
        ], [
            'read_at' => now(),
        ]);
    }
}
