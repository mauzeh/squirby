<?php

namespace App\Services;

use Carbon\Carbon;

class UserActivityAnalysis
{
    public function __construct(
        public readonly array $muscleWorkload,
        public readonly array $movementArchetypes,
        public readonly array $recentExercises,
        public readonly Carbon $analysisDate,
        public readonly array $muscleLastWorked = []
    ) {}

    /**
     * Get the workload score for a specific muscle (0.0 to 1.0)
     * Higher scores indicate more recent/intense usage
     */
    public function getMuscleWorkloadScore(string $muscle): float
    {
        return $this->muscleWorkload[$muscle] ?? 0.0;
    }

    /**
     * Get the frequency count for a specific movement archetype
     */
    public function getArchetypeFrequency(string $archetype): int
    {
        return $this->movementArchetypes[$archetype] ?? 0;
    }

    /**
     * Check if an exercise was performed recently (within the analysis period)
     */
    public function wasExerciseRecentlyPerformed(int $exerciseId): bool
    {
        return in_array($exerciseId, $this->recentExercises);
    }

    /**
     * Get the number of days since a muscle was last worked
     * Returns null if the muscle was never worked in the analysis period
     */
    public function getDaysSinceLastWorkout(string $muscle): ?int
    {
        // First check if we have actual date data
        if (isset($this->muscleLastWorked[$muscle])) {
            $lastWorkoutDate = $this->muscleLastWorked[$muscle];
            
            if ($lastWorkoutDate instanceof Carbon) {
                return $lastWorkoutDate->diffInDays($this->analysisDate);
            }
        }
        
        // Fallback to workload-based estimation if date is not available
        $workloadScore = $this->getMuscleWorkloadScore($muscle);
        if ($workloadScore === 0.0) {
            return null; // Never worked in the analysis period
        }
        
        return max(0, intval(round((1.0 - $workloadScore) * 31)));
    }
}