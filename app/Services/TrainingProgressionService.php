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

    public function getSuggestionDetails(int $userId, int $exerciseId, Carbon $forDate = null): ?object
    {
        $forDate = $forDate ?? Carbon::now();
        $recentLiftLogs = LiftLog::with('liftSets')
            ->join('exercises', 'lift_logs.exercise_id', '=', 'exercises.id')
            ->where('lift_logs.user_id', $userId)
            ->where('lift_logs.exercise_id', $exerciseId)
            ->where('exercises.is_bodyweight', false)
            ->where('logged_at', '>=', $forDate->copy()->subWeeks(self::LOOKBACK_WEEKS))
            ->orderBy('logged_at', 'desc')
            ->select('lift_logs.*')
            ->get();

        if ($recentLiftLogs->isEmpty()) {
            return null;
        }

        $closestLog = $this->findClosestLiftLog($userId, $exerciseId, $recentLiftLogs);

        $lastWeight = $closestLog ? $closestLog->display_weight : null;
        $targetReps = $closestLog ? $closestLog->display_reps : config('training.defaults.reps', 10);

        $suggestedWeight = $this->suggestNextWeight($userId, $exerciseId, $targetReps, $forDate, $recentLiftLogs);

        $percentageIncrease = null;
        if ($lastWeight > 0 && $suggestedWeight) {
            $percentageIncrease = (($suggestedWeight - $lastWeight) / $lastWeight) * 100;
        }

        return (object)[
            'suggestedWeight' => $suggestedWeight,
            'reps' => $closestLog ? $closestLog->display_reps : config('training.defaults.reps', 10),
            'sets' => $closestLog ? $closestLog->display_rounds : config('training.defaults.sets', 3),
            'lastWeight' => $lastWeight,
            'percentageIncrease' => $percentageIncrease,
        ];
    }

    public function suggestNextWeight(int $userId, int $exerciseId, int $targetReps, Carbon $forDate = null, Collection $recentLiftLogs = null): float|false
    {
        $forDate = $forDate ?? Carbon::now();

        if ($recentLiftLogs === null) {
            $recentLiftLogs = LiftLog::with('liftSets')
                ->join('exercises', 'lift_logs.exercise_id', '=', 'exercises.id')
                ->where('lift_logs.user_id', $userId)
                ->where('lift_logs.exercise_id', $exerciseId)
                ->where('exercises.is_bodyweight', false)
                ->where('logged_at', '>=', $forDate->copy()->subWeeks(self::LOOKBACK_WEEKS))
                ->orderBy('logged_at', 'desc')
                ->select('lift_logs.*')
                ->get();
        }

        if ($recentLiftLogs->isEmpty()) {
            return false;
        }

        $allEstimated1RMs = collect();
        $hasRecentHigherOrEqualReps = false;

        foreach ($recentLiftLogs as $liftLog) {
            foreach ($liftLog->liftSets as $liftSet) {
                if ($liftSet->reps >= $targetReps) {
                    $hasRecentHigherOrEqualReps = true;
                }
                if ($liftSet->weight > 0 && $liftSet->reps > 0) {
                    $estimated1RM = $this->oneRepMaxCalculatorService->calculateOneRepMax($liftSet->weight, $liftSet->reps);
                    if ($estimated1RM !== null) {
                        $allEstimated1RMs->push($estimated1RM);
                    }
                }
            }
        }

        $current1RM = null;
        if ($allEstimated1RMs->isNotEmpty()) {
            $current1RM = $allEstimated1RMs->max();
        }

        if ($current1RM !== null) {
            $predictedWeight = $this->oneRepMaxCalculatorService->getWeightFromOneRepMax($current1RM, $targetReps);
            $finalPredictedWeight = $predictedWeight;
            if ($hasRecentHigherOrEqualReps) {
                $finalPredictedWeight += self::RESOLUTION;
            }
            return ceil($finalPredictedWeight / self::RESOLUTION) * self::RESOLUTION;
        } else {
            return false;
        }
    }

    public function suggestNextRepCount(int $userId, int $exerciseId): int
    {
        $closestLog = $this->findClosestLiftLog($userId, $exerciseId);
        return $closestLog ? $closestLog->display_reps : config('training.defaults.reps', 10);
    }

    public function suggestNextSetCount(int $userId, int $exerciseId): int
    {
        $closestLog = $this->findClosestLiftLog($userId, $exerciseId);
        return $closestLog ? $closestLog->display_rounds : config('training.defaults.sets', 3);
    }

    public function findClosestLiftLog(int $userId, int $exerciseId, Collection $recentLiftLogs = null): ?LiftLog
    {
        $defaultReps = config('training.defaults.reps', 10);
        $defaultSets = config('training.defaults.sets', 3);

        if ($recentLiftLogs === null) {
            $recentLiftLogs = LiftLog::where('user_id', $userId)
                ->where('exercise_id', $exerciseId)
                ->where('logged_at', '>=', Carbon::now()->subWeeks(self::LOOKBACK_WEEKS))
                ->get();
        }

        if ($recentLiftLogs->isEmpty()) {
            return null;
        }

        $closestLog = null;
        $minDistance = PHP_INT_MAX;

        foreach ($recentLiftLogs as $log) {
            $repsDistance = abs($log->display_reps - $defaultReps);
            $setsDistance = abs($log->display_rounds - $defaultSets);
            $totalDistance = $repsDistance + $setsDistance;

            if ($totalDistance < $minDistance) {
                $minDistance = $totalDistance;
                $closestLog = $log;
            }
        }

        return $closestLog;
    }
}