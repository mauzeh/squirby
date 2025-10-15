<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'is_bodyweight',
        'user_id',
        'band_type',
    ];

    protected $casts = [
        'is_bodyweight' => 'boolean',
        'band_type' => 'string',
    ];

    public function liftLogs()
    {
        return $this->hasMany(LiftLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes for querying
    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeUserSpecific($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAvailableToUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')        // Global exercises (available to all users)
              ->orWhere('user_id', $userId); // User's own exercises
        })->orderByRaw('user_id IS NULL ASC'); // Prioritize user exercises (user_id IS NOT NULL) over global exercises (user_id IS NULL)
    }

    // Helper methods
    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    public function isBandedResistance(): bool
    {
        return $this->band_type === 'resistance';
    }

    public function isBandedAssistance(): bool
    {
        return $this->band_type === 'assistance';
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($this->isGlobal()) {
            return $user->hasRole('Admin');
        }
        return $this->user_id === $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        if ($this->liftLogs()->exists()) {
            return false; // Cannot delete if has lift logs
        }
        return $this->canBeEditedBy($user);
    }
}