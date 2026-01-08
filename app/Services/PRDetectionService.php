<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PRDetectionService
{
    private const TOLERANCE = 0.1;
    private const MAX_REP_COUNT_FOR_PR = 10;

    /**
     * Check if a single lift log is a PR compared to previous lifts
     * 
     * @param LiftLog $liftLog The lift log to check
     * @param Exercise $exercise The exercise being performed
     * @param User $user The user who performed the lift
     * @return bool True if this lift is a PR
     */
    public function isLiftLogPR(LiftLog $liftLog, Exercise $exercise, User $user): bool
    {
        $strategy = $exercise->getTypeStrategy();
        
        // Only exercises that support 1RM calculation can have PRs
        if (!$strategy->canCalculate1RM()) {
            return false;
        }
        
        // Get all previous lift logs for this exercise (before this one)
        $previousLogs = LiftLog::where('exercise_id', $exercise->id)
            ->where('user_id', $user->id)
            ->where('logged_at', '<', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        return $this->checkIfPRAgainstPreviousLogs($liftLog, $previousLogs, $strategy);
    }

    /**
     * Calculate which lift logs contain PRs from a collection of logs
     * Processes logs chronologically to determine which were PRs "at the time they happened"
     * 
     * @param Collection $liftLogs Collection of lift logs to analyze
     * @return array Array of lift log IDs that contain PRs
     */
    public function calculatePRLogIds(Collection $liftLogs): array
    {
        if ($liftLogs->isEmpty()) {
            return [];
        }

        $prLogIds = [];
        
        // Group logs by exercise to process each exercise independently
        $logsByExercise = $liftLogs->groupBy('exercise_id');
        
        foreach ($logsByExercise as $exerciseId => $exerciseLogs) {
            // Only process if this is an exercise that supports 1RM calculation
            $firstLog = $exerciseLogs->first();
            $strategy = $firstLog->exercise->getTypeStrategy();
            
            if (!$strategy->canCalculate1RM()) {
                continue;
            }

            // Sort logs by date (oldest first) to process chronologically
            $sortedLogs = $exerciseLogs->sortBy('logged_at');
            
            // Process each log chronologically
            foreach ($sortedLogs as $index => $log) {
                // Get all previous logs for this exercise (logs before current one)
                $previousLogs = $sortedLogs->take($index);
                
                // Check if this log was a PR at the time it happened
                if ($this->checkIfPRAgainstPreviousLogs($log, $previousLogs, $strategy)) {
                    $prLogIds[] = $log->id;
                }
            }
        }
        
        return $prLogIds;
    }

    /**
     * Check if a lift log is a PR against a collection of previous logs
     * 
     * @param LiftLog $liftLog The lift log to check
     * @param Collection $previousLogs Previous lift logs to compare against
     * @param mixed $strategy Exercise type strategy for 1RM calculation
     * @return bool True if this lift is a PR
     */
    private function checkIfPRAgainstPreviousLogs(LiftLog $liftLog, Collection $previousLogs, $strategy): bool
    {
        // Calculate the best estimated 1RM from the current log
        $currentBest1RM = 0;
        $isRepSpecificPR = false;
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                try {
                    $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $liftLog);
                    if ($estimated1RM > $currentBest1RM) {
                        $currentBest1RM = $estimated1RM;
                    }
                    
                    // For sets up to 10 reps, check if this is a rep-specific PR
                    if ($set->reps <= self::MAX_REP_COUNT_FOR_PR) {
                        $maxWeightForReps = $this->getMaxWeightForReps($previousLogs, $set->reps);
                        
                        // Check if current weight beats previous max for this rep count
                        if ($set->weight > $maxWeightForReps + self::TOLERANCE) {
                            $isRepSpecificPR = true;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // If this is the first log, it's a PR if it has valid data
        if ($previousLogs->isEmpty()) {
            return $currentBest1RM > 0;
        }
        
        // Find the best estimated 1RM from all previous logs
        $previousBest1RM = $this->getBestEstimated1RM($previousLogs, $strategy);
        
        // This is a PR if EITHER:
        // 1. It's a rep-specific PR (for 1-10 reps)
        // 2. OR it beats the overall estimated 1RM
        $beats1RM = $currentBest1RM > $previousBest1RM + self::TOLERANCE;
        
        return $isRepSpecificPR || $beats1RM;
    }

    /**
     * Get the maximum weight lifted for a specific rep count from previous logs
     * 
     * @param Collection $previousLogs Previous lift logs to search
     * @param int $targetReps The rep count to find max weight for
     * @return float Maximum weight found for the rep count
     */
    private function getMaxWeightForReps(Collection $previousLogs, int $targetReps): float
    {
        $maxWeight = 0;
        
        foreach ($previousLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->reps == $targetReps && $set->weight > $maxWeight) {
                    $maxWeight = $set->weight;
                }
            }
        }
        
        return $maxWeight;
    }

    /**
     * Get the best estimated 1RM from a collection of lift logs
     * 
     * @param Collection $logs Lift logs to analyze
     * @param mixed $strategy Exercise type strategy for 1RM calculation
     * @return float Best estimated 1RM found
     */
    private function getBestEstimated1RM(Collection $logs, $strategy): float
    {
        $best1RM = 0;
        
        foreach ($logs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $best1RM) {
                            $best1RM = $estimated1RM;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        return $best1RM;
    }
}