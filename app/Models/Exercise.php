<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'canonical_name',
        'is_bodyweight',
        'user_id',
        'band_type',
    ];

    protected $casts = [
        'is_bodyweight' => 'boolean',
        'band_type' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($exercise) {
            if (empty($exercise->canonical_name) && !empty($exercise->title)) {
                $exercise->canonical_name = static::generateUniqueCanonicalName($exercise->title);
            }
        });


    }

    public function liftLogs()
    {
        return $this->hasMany(LiftLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function intelligence()
    {
        return $this->hasOne(ExerciseIntelligence::class);
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

    /**
     * Scope that applies user-specific exercise filtering based on preferences
     */
    public function scopeAvailableToUser($query, $userId = null, $showGlobal = null)
    {
        $userId = $userId ?? Auth::id();
        
        if (!$userId) {
            // If no user, show no exercises at all
            return $query->whereRaw('1 = 0'); // This will return no results
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            // If user doesn't exist, show no exercises
            return $query->whereRaw('1 = 0'); // This will return no results
        }
        
        // Admin users see all exercises regardless of preference
        if ($user->hasRole('Admin')) {
            return $query->orderByRaw('user_id IS NULL ASC');
        }
        
        // Use provided showGlobal parameter or fall back to user preference
        $shouldShowGlobal = $showGlobal !== null ? $showGlobal : $user->shouldShowGlobalExercises();
        
        if ($shouldShowGlobal) {
            // Show global + own exercises
            return $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')        // Global exercises
                  ->orWhere('user_id', $userId); // User's own exercises
            })->orderByRaw('user_id IS NULL ASC');
        } else {
            // Show only user's own exercises
            return $query->where('user_id', $userId)
                        ->orderBy('title', 'asc');
        }
    }

    /**
     * Scope to show all exercises without any filtering (for admin operations)
     */
    public function scopeWithoutUserFiltering($query)
    {
        return $query;
    }

    /**
     * Scope to force showing only global exercises
     */
    public function scopeOnlyGlobal($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope to force showing only user-specific exercises for a given user
     */
    public function scopeOnlyUserSpecific($query, $userId = null)
    {
        $userId = $userId ?? Auth::id();
        return $query->where('user_id', $userId);
    }

    public function scopeWithIntelligence($query)
    {
        return $query->whereHas('intelligence');
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
        // Admin users can edit all exercises
        if ($user->hasRole('Admin')) {
            return true;
        }
        
        // Regular users can only edit global exercises (if admin) or their own exercises
        if ($this->isGlobal()) {
            return false; // Regular users cannot edit global exercises
        }
        return $this->user_id === $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        if ($this->liftLogs()->exists()) {
            return false; // Cannot delete if has lift logs
        }
        
        // Admin users can delete all exercises (if no lift logs)
        if ($user->hasRole('Admin')) {
            return true;
        }
        
        // Regular users can only delete global exercises (if admin) or their own exercises
        if ($this->isGlobal()) {
            return false; // Regular users cannot delete global exercises
        }
        return $this->user_id === $user->id;
    }

    public function hasIntelligence(): bool
    {
        return $this->intelligence !== null;
    }

    /**
     * Generate a unique canonical name from a title
     */
    protected static function generateUniqueCanonicalName(string $title, ?int $excludeId = null): string
    {
        $baseCanonicalName = Str::slug($title, '_');
        $canonicalName = $baseCanonicalName;
        $counter = 1;

        // Keep checking until we find a unique canonical name
        while (static::canonicalNameExists($canonicalName, $excludeId)) {
            $canonicalName = $baseCanonicalName . '_' . $counter;
            $counter++;
        }

        return $canonicalName;
    }

    /**
     * Check if a canonical name already exists
     */
    protected static function canonicalNameExists(string $canonicalName, ?int $excludeId = null): bool
    {
        $query = static::where('canonical_name', $canonicalName);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}