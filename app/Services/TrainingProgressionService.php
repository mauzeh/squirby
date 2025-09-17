<?php

namespace App\Services;

use App\Models\LiftLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrainingProgressionService
{
    const RESOLUTION = 5.0;
    const LOOKBACK_WEEKS = 2;

    protected OneRepMaxCalculatorService $oneRepMaxCalculatorService;

    public function __construct(OneRepMaxCalculatorService $oneRepMaxCalculatorService)
    {
        $this->oneRepMaxCalculatorService = $oneRepMaxCalculatorService;
    }

    public function suggestNextWeight(int $userId, int $exerciseId, int $targetReps, Carbon $forDate = null): float|false
    {
        $forDate = $forDate ?? Carbon::now(); // Use provided date or default to now

        // 1. Retrieve all relevant LiftLogs with their sets
        $recentLiftLogs = LiftLog::with('liftSets')
            ->join('exercises', 'lift_logs.exercise_id', '=', 'exercises.id')
            ->where('lift_logs.user_id', $userId)
            ->where('lift_logs.exercise_id', $exerciseId)
            ->where('exercises.is_bodyweight', false) // Filter on the exercises table
            ->where('logged_at', '>=', $forDate->copy()->subWeeks(self::LOOKBACK_WEEKS))
            ->orderBy('logged_at', 'desc')
            ->select('lift_logs.*') // Select lift_logs columns to avoid ambiguity
            ->get();

        $allEstimated1RMs = collect();
        $hasRecentHigherOrEqualReps = false;

        // 2. Calculate Estimated 1RM for Each Set
        foreach ($recentLiftLogs as $liftLog) {
            foreach ($liftLog->liftSets as $liftSet) {
                // Check if any historical set has reps >= targetReps
                if ($liftSet->reps >= $targetReps) {
                    $hasRecentHigherOrEqualReps = true;
                }

                // Only calculate 1RM if weight and reps are valid
                if ($liftSet->weight > 0 && $liftSet->reps > 0) {
                    $estimated1RM = $this->oneRepMaxCalculatorService->calculateOneRepMax($liftSet->weight, $liftSet->reps);
                    if ($estimated1RM !== null) { // Ensure 1RM calculation was successful
                        $allEstimated1RMs->push($estimated1RM);
                    }
                }
            }
        }

        $current1RM = null;
        if ($allEstimated1RMs->isNotEmpty()) {
            // For simplicity, take the highest estimated 1RM from recent history
            $current1RM = $allEstimated1RMs->max();
        }

        // 3. Predict Target Weight from Current 1RM
        if ($current1RM !== null) {
            $predictedWeight = $this->oneRepMaxCalculatorService->getWeightFromOneRepMax($current1RM, $targetReps);

            $finalPredictedWeight = $predictedWeight;

            // Apply the resolution only if there's recent history with higher or equal reps.
            // This prevents adding resolution when the user is attempting a rep count
            // significantly higher than their recent performance, where a direct increment
            // might not be appropriate.
            //
            // Examples:
            // - Target Reps: 5
            //   Historical Sets: (100 lbs x 5 reps), (90 lbs x 6 reps) -> hasRecentHigherOrEqualReps is TRUE. RESOLUTION applied.
            // - Target Reps: 5
            //   Historical Sets: (100 lbs x 3 reps), (90 lbs x 5 reps) -> hasRecentHigherOrEqualReps is TRUE. RESOLUTION applied.
            // - Target Reps: 5
            //   Historical Sets: (100 lbs x 8 reps), (90 lbs x 10 reps) -> hasRecentHigherOrEqualReps is TRUE. RESOLUTION applied.
            // - Target Reps: 10
            //   Historical Sets: (100 lbs x 8 reps), (90 lbs x 5 reps) -> hasRecentHigherOrEqualReps is FALSE. RESOLUTION NOT applied.
            if ($hasRecentHigherOrEqualReps) {
                $finalPredictedWeight += self::RESOLUTION;
            }

            // Round to the nearest multiple of RESOLUTION, rounded to the lowest ceiling
            return ceil($finalPredictedWeight / self::RESOLUTION) * self::RESOLUTION;
        } else {
            // If no history to determine 1RM, return false
            return false;
        }
    }

    public function suggestNextRepCount(int $userId, int $exerciseId): int
    {
        $mostRecentLiftLog = LiftLog::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->orderBy('logged_at', 'desc')
            ->first();

        return $mostRecentLiftLog ? $mostRecentLiftLog->display_reps : config('training.defaults.reps', 10);
    }

    public function suggestNextSetCount(int $userId, int $exerciseId): int
    {
        $mostRecentLiftLog = LiftLog::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->orderBy('logged_at', 'desc')
            ->first();

        return $mostRecentLiftLog ? $mostRecentLiftLog->display_rounds : config('training.defaults.sets', 3);
    }
}