<?php

namespace App\Services;

use App\Models\LiftLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeightProgressionService
{
    const DEFAULT_INCREMENT = 5.0;
    const LOOKBACK_WEEKS = 2;

    protected OneRepMaxCalculatorService $oneRepMaxCalculatorService;

    public function __construct(OneRepMaxCalculatorService $oneRepMaxCalculatorService)
    {
        $this->oneRepMaxCalculatorService = $oneRepMaxCalculatorService;
    }

    public function suggestNextWeight(int $userId, int $exerciseId, int $targetReps): float|false
    {
        // 1. Retrieve all relevant LiftLogs with their sets
        $recentLiftLogs = LiftLog::with('liftSets')
            ->join('exercises', 'lift_logs.exercise_id', '=', 'exercises.id')
            ->where('lift_logs.user_id', $userId)
            ->where('lift_logs.exercise_id', $exerciseId)
            ->where('exercises.is_bodyweight', false) // Filter on the exercises table
            ->where('logged_at', '>=', Carbon::now()->subWeeks(self::LOOKBACK_WEEKS))
            ->orderBy('logged_at', 'desc')
            ->select('lift_logs.*') // Select lift_logs columns to avoid ambiguity
            ->get();

        $allEstimated1RMs = collect();

        // 2. Calculate Estimated 1RM for Each Set
        foreach ($recentLiftLogs as $liftLog) {
            foreach ($liftLog->liftSets as $liftSet) {
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

            // Apply the increment to the predicted weight
            // This ensures progression even if the 1RM calculation itself doesn't directly lead to an increase
            return $predictedWeight + self::DEFAULT_INCREMENT;
        } else {
            // If no history to determine 1RM, return false
            return false;
        }
    }
}