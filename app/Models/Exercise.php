<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Services\ExerciseTypes\ExerciseTypeInterface;

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

    /**
     * Check if this exercise can be merged by an admin
     * Requirements: 2.1, 5.1, 5.5
     */
    public function canBeMergedByAdmin(): bool
    {
        // Only user exercises can be merged (not global exercises)
        if ($this->isGlobal()) {
            return false;
        }

        // Check if there are any compatible global target exercises
        $potentialTargets = static::onlyGlobal()
            ->where('id', '!=', $this->id)
            ->get()
            ->filter(function ($exercise) {
                return $this->isCompatibleForMerge($exercise);
            });

        return $potentialTargets->isNotEmpty();
    }

    /**
     * Check if this exercise is compatible for merging with another exercise
     * Requirements: 5.1, 5.2, 5.3
     */
    public function isCompatibleForMerge(Exercise $target): bool
    {
        // Cannot merge with itself
        if ($this->id === $target->id) {
            return false;
        }

        // For merge compatibility, we need to determine which is source and which is target
        // Only user exercises can be merged into global exercises
        $source = $this->isGlobal() ? $target : $this;
        $globalTarget = $this->isGlobal() ? $this : $target;

        // Source must be user exercise and target must be global
        if ($source->isGlobal() || !$globalTarget->isGlobal()) {
            return false;
        }

        // Both exercises must have the same is_bodyweight value
        if ($source->is_bodyweight !== $globalTarget->is_bodyweight) {
            return false;
        }

        // Band type compatibility: both null, both same value, or one null and one with value
        if ($source->band_type !== null && $globalTarget->band_type !== null) {
            // Both have band types - they must match
            if ($source->band_type !== $globalTarget->band_type) {
                return false;
            }
        }
        // If one is null and the other has a value, they are compatible
        // If both are null, they are compatible

        return true;
    }

    /**
     * Check if the owner of this exercise has global visibility disabled
     * Only applicable for user exercises (not global exercises)
     * Requirements: 5.4
     */
    public function hasOwnerWithGlobalVisibilityDisabled(): bool
    {
        // Global exercises don't have owners
        if ($this->isGlobal()) {
            return false;
        }

        // Load the user relationship if not already loaded
        if (!$this->relationLoaded('user')) {
            $this->load('user');
        }

        // Check if the user has global exercise visibility disabled
        return $this->user && !$this->user->shouldShowGlobalExercises();
    }

    /**
     * Get the exercise type strategy for this exercise
     */
    public function getTypeStrategy(): ExerciseTypeInterface
    {
        return ExerciseTypeFactory::create($this);
    }
}