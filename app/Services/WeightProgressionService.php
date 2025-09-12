<?php

namespace App\Services;

use App\Models\LiftLog;
use Carbon\Carbon;

class WeightProgressionService
{
    // Define a default increment (e.g., 2.5 lbs or 5 lbs)
    // This could eventually be user-configurable or exercise-specific
    const DEFAULT_INCREMENT = 5.0;

    // Define the look-back period for recent history
    const LOOKBACK_WEEKS = 2;

    public function suggestNextWeight(int $userId, int $exerciseId, int $targetReps): float|false
    {
        // 1. Retrieve recent LiftLogs for the exercise and user
        $recentLiftLogs = LiftLog::with('liftSets')
            ->join('exercises', 'lift_logs.exercise_id', '=', 'exercises.id')
            ->where('lift_logs.user_id', $userId)
            ->where('lift_logs.exercise_id', $exerciseId)
            ->where('exercises.is_bodyweight', false) // Filter on the exercises table
            ->where('logged_at', '>=', Carbon::now()->subWeeks(self::LOOKBACK_WEEKS))
            ->orderBy('logged_at', 'desc')
            ->select('lift_logs.*') // Select lift_logs columns to avoid ambiguity
            ->get();

        $lastSuccessfulWeight = null;

        // 2. Identify "Last Successful" Set
        // Find the heaviest weight for the target reps from any set within the recent LiftLogs
        foreach ($recentLiftLogs as $log) {
            $heaviestSetWeight = $log->liftSets()
                                    ->where('reps', $targetReps)
                                    ->max('weight');

            if ($heaviestSetWeight !== null && ($lastSuccessfulWeight === null || $heaviestSetWeight > $lastSuccessfulWeight)) {
                $lastSuccessfulWeight = $heaviestSetWeight;
            }
        }

        // 3. Determine Progression Increment
        if ($lastSuccessfulWeight !== null) {
            // Suggest a small increment
            return $lastSuccessfulWeight + self::DEFAULT_INCREMENT;
        } else {
            // If no recent history, suggest a default starting weight
            // This could be more sophisticated (e.g., exercise-specific defaults)
            return false; // Return false when no weight can be determined
        }
    }
}
