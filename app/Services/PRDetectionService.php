<?php

namespace App\Services;

use App\Enums\PRType;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PRDetectionService
{
    private const TOLERANCE = 0.1;
    private const VOLUME_TOLERANCE_PERCENT = 0.01; // 1% relative tolerance for volume PRs
    private const MAX_REP_COUNT_FOR_PR = 10;

    /**
     * Store the last calculation snapshot for debugging purposes
     * This is populated during PR detection and can be retrieved for logging
     */
    private ?array $lastCalculationSnapshot = null;

    /**
     * Check if a single lift log is a PR compared to previous lifts
     * Returns bitwise flags indicating which types of PRs were achieved
     * 
     * BACKWARD COMPATIBLE: Returns int that evaluates to true/false in boolean context
     * - 0 (PRType::NONE) = no PR = false
     * - >0 (any PR flags) = PR achieved = true
     * 
     * @param LiftLog $liftLog The lift log to check
     * @param Exercise $exercise The exercise being performed
     * @param User $user The user who performed the lift
     * @return int Bitwise flags of PR types (0 if no PR)
     */
    public function isLiftLogPR(LiftLog $liftLog, Exercise $exercise, User $user): int
    {
        $strategy = $exercise->getTypeStrategy();
        
        // Only exercises that support 1RM calculation can have PRs
        if (!$strategy->canCalculate1RM()) {
            $this->lastCalculationSnapshot = [
                'can_calculate_1rm' => false,
                'reason' => 'Exercise type does not support 1RM calculation',
            ];
            return PRType::NONE->value;
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
     * Get the last calculation snapshot for logging purposes
     * Returns null if no calculation has been performed yet
     * 
     * @return array|null
     */
    public function getLastCalculationSnapshot(): ?array
    {
        return $this->lastCalculationSnapshot;
    }

    /**
     * Calculate which lift logs contain PRs from a collection of logs
     * Processes logs chronologically to determine which were PRs "at the time they happened"
     * 
     * @param Collection $liftLogs Collection of lift logs to analyze
     * @return array Array of lift log IDs that contain PRs (any type)
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
                
                // Check if this log was a PR at the time it happened (returns flags)
                $prFlags = $this->checkIfPRAgainstPreviousLogs($log, $previousLogs, $strategy);
                if ($prFlags > 0) {
                    $prLogIds[] = $log->id;
                }
            }
        }
        
        return $prLogIds;
    }

    /**
     * Check if a lift log is a PR against a collection of previous logs
     * Returns bitwise flags indicating which types of PRs were achieved
     * 
     * @param LiftLog $liftLog The lift log to check
     * @param Collection $previousLogs Previous lift logs to compare against
     * @param mixed $strategy Exercise type strategy for 1RM calculation
     * @return int Bitwise flags of PR types (0 if no PR)
     */
    private function checkIfPRAgainstPreviousLogs(LiftLog $liftLog, Collection $previousLogs, $strategy): int
    {
        $prFlags = PRType::NONE->value;
        
        // Calculate the best estimated 1RM from the current log
        $currentBest1RM = 0;
        $currentTotalVolume = 0;
        $currentSets = [];
        $repSpecificPRs = [];
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                // Calculate volume for this set
                $currentTotalVolume += ($set->weight * $set->reps);
                
                try {
                    $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $liftLog);
                    if ($estimated1RM > $currentBest1RM) {
                        $currentBest1RM = $estimated1RM;
                    }
                    
                    $currentSets[] = [
                        'weight' => $set->weight,
                        'reps' => $set->reps,
                        'estimated_1rm' => $estimated1RM,
                    ];
                    
                    // For sets up to 10 reps, check if this is a rep-specific PR
                    if ($set->reps <= self::MAX_REP_COUNT_FOR_PR) {
                        $maxWeightForReps = $this->getMaxWeightForReps($previousLogs, $set->reps);
                        
                        // Check if current weight beats previous max for this rep count
                        if ($set->weight > $maxWeightForReps + self::TOLERANCE) {
                            $prFlags |= PRType::REP_SPECIFIC->value;
                            $repSpecificPRs[] = [
                                'reps' => $set->reps,
                                'weight' => $set->weight,
                                'previous_max' => $maxWeightForReps,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // Initialize snapshot data
        $snapshot = [
            'current_lift' => [
                'lift_log_id' => $liftLog->id,
                'logged_at' => $liftLog->logged_at->toIso8601String(),
                'sets' => $currentSets,
                'best_1rm' => $currentBest1RM,
                'total_volume' => $currentTotalVolume,
            ],
            'previous_logs_count' => $previousLogs->count(),
            'previous_bests' => [],
            'pr_reasons' => [],
            'why_not_pr' => [],
        ];
        
        // If this is the first log, it's a PR if it has valid data
        if ($previousLogs->isEmpty()) {
            if ($currentBest1RM > 0) {
                $prFlags |= PRType::ONE_RM->value;
                $snapshot['pr_reasons']['one_rm'] = 'First lift for this exercise';
            }
            if ($currentTotalVolume > 0) {
                $prFlags |= PRType::VOLUME->value;
                $snapshot['pr_reasons']['volume'] = 'First lift for this exercise';
            }
            
            $snapshot['is_first_log'] = true;
            $snapshot['pr_types_detected'] = PRType::toArray($prFlags);
            $this->lastCalculationSnapshot = $snapshot;
            
            return $prFlags;
        }
        
        // Check for 1RM PR
        $previousBest1RM = $this->getBestEstimated1RM($previousLogs, $strategy);
        $snapshot['previous_bests']['one_rm'] = [
            'value' => $previousBest1RM,
            'tolerance' => self::TOLERANCE,
        ];
        
        if ($currentBest1RM > $previousBest1RM + self::TOLERANCE) {
            $prFlags |= PRType::ONE_RM->value;
            $snapshot['pr_reasons']['one_rm'] = sprintf(
                '%.2f > %.2f + %.2f',
                $currentBest1RM,
                $previousBest1RM,
                self::TOLERANCE
            );
        } else {
            $snapshot['why_not_pr']['one_rm'] = sprintf(
                '%.2f <= %.2f + %.2f',
                $currentBest1RM,
                $previousBest1RM,
                self::TOLERANCE
            );
        }
        
        // Check for Volume PR (total weight lifted in a single session for this exercise)
        // Use percentage-based tolerance that scales with workout volume
        $previousBestVolume = $this->getBestVolume($previousLogs);
        $volumeTolerance = $previousBestVolume * self::VOLUME_TOLERANCE_PERCENT;
        
        $snapshot['previous_bests']['volume'] = [
            'value' => $previousBestVolume,
            'tolerance_percent' => self::VOLUME_TOLERANCE_PERCENT * 100,
            'tolerance_absolute' => $volumeTolerance,
        ];
        
        if ($currentTotalVolume > $previousBestVolume + $volumeTolerance) {
            $prFlags |= PRType::VOLUME->value;
            $snapshot['pr_reasons']['volume'] = sprintf(
                '%.2f > %.2f + %.2f',
                $currentTotalVolume,
                $previousBestVolume,
                $volumeTolerance
            );
        } else {
            $snapshot['why_not_pr']['volume'] = sprintf(
                '%.2f <= %.2f + %.2f',
                $currentTotalVolume,
                $previousBestVolume,
                $volumeTolerance
            );
        }
        
        // Add rep-specific PR info if any
        if (!empty($repSpecificPRs)) {
            $snapshot['pr_reasons']['rep_specific'] = $repSpecificPRs;
        }
        
        $snapshot['pr_types_detected'] = PRType::toArray($prFlags);
        $this->lastCalculationSnapshot = $snapshot;
        
        return $prFlags;
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

    /**
     * Get the best total volume from a collection of lift logs
     * Volume = sum of (weight × reps) for all sets in a single session
     * 
     * @param Collection $logs Lift logs to analyze
     * @return float Best total volume found
     */
    private function getBestVolume(Collection $logs): float
    {
        $bestVolume = 0;
        
        foreach ($logs as $log) {
            $logVolume = 0;
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    $logVolume += ($set->weight * $set->reps);
                }
            }
            if ($logVolume > $bestVolume) {
                $bestVolume = $logVolume;
            }
        }
        
        return $bestVolume;
    }
}