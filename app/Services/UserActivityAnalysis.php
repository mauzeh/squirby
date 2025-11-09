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
        public readonly array $muscleLastWorked = [],
        public readonly array $exerciseLastPerformed = []
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
    public function getDaysSinceLastWorkout(string $muscle): ?float
    {
        // First check if we have actual date data
        if (isset($this->muscleLastWorked[$muscle])) {
            $lastWorkoutDate = $this->muscleLastWorked[$muscle];
            
            if ($lastWorkoutDate instanceof Carbon) {
                $daysSince = $lastWorkoutDate->diffInHours($this->analysisDate) / 24.0;
                
                // Debug logging for pectoralis_major to track the discrepancy
                if ($muscle === 'pectoralis_major') {
                    \Log::info('Pectoralis Major Recovery Calc', [
                        'last_workout' => $lastWorkoutDate->toIso8601String(),
                        'analysis_date' => $this->analysisDate->toIso8601String(),
                        'hours_diff' => $lastWorkoutDate->diffInHours($this->analysisDate),
                        'days_since' => $daysSince,
                    ]);
                }
                
                return $daysSince;
            }
        }
        
        // Fallback to workload-based estimation if date is not available
        $workloadScore = $this->getMuscleWorkloadScore($muscle);
        if ($workloadScore === 0.0) {
            return null; // Never worked in the analysis period
        }
        
        return max(0, intval(round((1.0 - $workloadScore) * 31)));
    }

    /**
     * Get the number of days since an exercise was last performed
     * Returns null if the exercise was never performed in the analysis period
     */
    public function getDaysSinceExercisePerformed(int $exerciseId): ?float
    {
        if (isset($this->exerciseLastPerformed[$exerciseId])) {
            $lastPerformedDate = $this->exerciseLastPerformed[$exerciseId];

            if ($lastPerformedDate instanceof Carbon) {
                return $lastPerformedDate->diffInHours($this->analysisDate) / 24.0;
            }
        }

        return null;
    }
}