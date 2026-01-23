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
        
        // Bodyweight exercises support PRs (volume and rep-specific only)
        // Regular exercises support all PR types (need 1RM calculation)
        $isBodyweight = $exercise->exercise_type === 'bodyweight';
        
        if (!$isBodyweight && !$strategy->canCalculate1RM()) {
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
        
        return $this->checkIfPRAgainstPreviousLogs($liftLog, $previousLogs, $strategy, $isBodyweight);
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
            $firstLog = $exerciseLogs->first();
            $strategy = $firstLog->exercise->getTypeStrategy();
            $isBodyweight = $firstLog->exercise->exercise_type === 'bodyweight';
            
            // Skip exercises that don't support PRs
            if (!$isBodyweight && !$strategy->canCalculate1RM()) {
                continue;
            }

            // Sort logs by date (oldest first) to process chronologically
            $sortedLogs = $exerciseLogs->sortBy('logged_at');
            
            // Process each log chronologically
            foreach ($sortedLogs as $index => $log) {
                // Get all previous logs for this exercise (logs before current one)
                $previousLogs = $sortedLogs->take($index);
                
                // Check if this log was a PR at the time it happened (returns flags)
                $prFlags = $this->checkIfPRAgainstPreviousLogs($log, $previousLogs, $strategy, $isBodyweight);
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
     * @param bool $isBodyweight Whether this is a bodyweight exercise
     * @return int Bitwise flags of PR types (0 if no PR)
     */
    private function checkIfPRAgainstPreviousLogs(LiftLog $liftLog, Collection $previousLogs, $strategy, bool $isBodyweight = false): int
    {
        $prFlags = PRType::NONE->value;
        
        // Calculate the best estimated 1RM from the current log (skip for bodyweight)
        $currentBest1RM = 0;
        $currentTotalVolume = 0;
        $currentTotalReps = 0;
        $currentSets = [];
        $repSpecificPRs = [];
        $hasExtraWeight = false;
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                // Track if any extra weight is used (for bodyweight exercises)
                if ($isBodyweight && $set->weight > 0) {
                    $hasExtraWeight = true;
                }
                
                // Calculate volume for this set
                $currentTotalVolume += ($set->weight * $set->reps);
                $currentTotalReps += $set->reps;
                
                // Only calculate 1RM for non-bodyweight exercises
                if (!$isBodyweight) {
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
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                // For sets up to 10 reps, check if this is a rep-specific PR
                // For bodyweight: only check if extra weight is used
                if ($set->reps <= self::MAX_REP_COUNT_FOR_PR && (!$isBodyweight || $hasExtraWeight)) {
                    $repSpecificResult = $this->getMaxWeightForRepsWithLog($previousLogs, $set->reps);
                    $maxWeightForReps = $repSpecificResult['weight'];
                    
                    // Check if current weight beats previous max for this rep count
                    if ($set->weight > $maxWeightForReps + self::TOLERANCE) {
                        $prFlags |= PRType::REP_SPECIFIC->value;
                        $repSpecificPRs[] = [
                            'reps' => $set->reps,
                            'weight' => $set->weight,
                            'previous_max' => $maxWeightForReps,
                            'previous_lift_log_id' => $repSpecificResult['lift_log_id'],
                        ];
                    }
                }
            } elseif ($isBodyweight && $set->reps > 0) {
                // For pure bodyweight (weight = 0), still count reps
                $currentTotalReps += $set->reps;
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
                'total_reps' => $currentTotalReps,
                'is_bodyweight' => $isBodyweight,
                'has_extra_weight' => $hasExtraWeight,
            ],
            'previous_logs_count' => $previousLogs->count(),
            'previous_bests' => [],
            'pr_reasons' => [],
            'why_not_pr' => [],
        ];
        
        // If this is the first log, it's a PR if it has valid data
        if ($previousLogs->isEmpty()) {
            // 1RM PR only for non-bodyweight exercises
            if (!$isBodyweight && $currentBest1RM > 0) {
                $prFlags |= PRType::ONE_RM->value;
                $snapshot['pr_reasons']['one_rm'] = 'First lift for this exercise';
            }
            
            // Volume PR: use total reps for pure bodyweight, volume for weighted
            if ($isBodyweight && !$hasExtraWeight && $currentTotalReps > 0) {
                $prFlags |= PRType::VOLUME->value;
                $snapshot['pr_reasons']['volume'] = 'First lift for this exercise (total reps)';
            } elseif ($currentTotalVolume > 0) {
                $prFlags |= PRType::VOLUME->value;
                $snapshot['pr_reasons']['volume'] = 'First lift for this exercise';
            }
            
            $snapshot['is_first_log'] = true;
            $snapshot['pr_types_detected'] = PRType::toArray($prFlags);
            $this->lastCalculationSnapshot = $snapshot;
            
            return $prFlags;
        }
        
        // Check for 1RM PR (skip for bodyweight)
        if (!$isBodyweight) {
            $best1RMResult = $this->getBestEstimated1RMWithLog($previousLogs, $strategy);
            $previousBest1RM = $best1RMResult['value'];
            
            $snapshot['previous_bests']['one_rm'] = [
                'value' => $previousBest1RM,
                'lift_log_id' => $best1RMResult['lift_log_id'],
                'tolerance' => self::TOLERANCE,
            ];
            
            if ($currentBest1RM > $previousBest1RM + self::TOLERANCE) {
                $prFlags |= PRType::ONE_RM->value;
                $snapshot['pr_reasons']['one_rm'] = sprintf(
                    '%.2f > %.2f + %.2f (previous: lift #%d)',
                    $currentBest1RM,
                    $previousBest1RM,
                    self::TOLERANCE,
                    $best1RMResult['lift_log_id']
                );
            } else {
                $snapshot['why_not_pr']['one_rm'] = sprintf(
                    '%.2f <= %.2f + %.2f (previous best: lift #%d)',
                    $currentBest1RM,
                    $previousBest1RM,
                    self::TOLERANCE,
                    $best1RMResult['lift_log_id']
                );
            }
        }
        
        // Check for Volume PR
        // For pure bodyweight (no extra weight), use total reps instead of volume
        if ($isBodyweight && !$hasExtraWeight) {
            $bestRepsResult = $this->getBestTotalRepsWithLog($previousLogs);
            $previousBestReps = $bestRepsResult['value'];
            
            $snapshot['previous_bests']['total_reps'] = [
                'value' => $previousBestReps,
                'lift_log_id' => $bestRepsResult['lift_log_id'],
            ];
            
            if ($currentTotalReps > $previousBestReps) {
                $prFlags |= PRType::VOLUME->value;
                $snapshot['pr_reasons']['volume'] = sprintf(
                    '%d reps > %d reps (previous: lift #%d)',
                    $currentTotalReps,
                    $previousBestReps,
                    $bestRepsResult['lift_log_id']
                );
            } else {
                $snapshot['why_not_pr']['volume'] = sprintf(
                    '%d reps <= %d reps (previous best: lift #%d)',
                    $currentTotalReps,
                    $previousBestReps,
                    $bestRepsResult['lift_log_id']
                );
            }
        } else {
            // Use standard volume calculation (weight × reps)
            $bestVolumeResult = $this->getBestVolumeWithLog($previousLogs);
            $previousBestVolume = $bestVolumeResult['value'];
            $volumeTolerance = $previousBestVolume * self::VOLUME_TOLERANCE_PERCENT;
            
            $snapshot['previous_bests']['volume'] = [
                'value' => $previousBestVolume,
                'lift_log_id' => $bestVolumeResult['lift_log_id'],
                'tolerance_percent' => self::VOLUME_TOLERANCE_PERCENT * 100,
                'tolerance_absolute' => $volumeTolerance,
            ];
            
            if ($currentTotalVolume > $previousBestVolume + $volumeTolerance) {
                $prFlags |= PRType::VOLUME->value;
                $snapshot['pr_reasons']['volume'] = sprintf(
                    '%.2f > %.2f + %.2f (previous: lift #%d)',
                    $currentTotalVolume,
                    $previousBestVolume,
                    $volumeTolerance,
                    $bestVolumeResult['lift_log_id']
                );
            } else {
                $snapshot['why_not_pr']['volume'] = sprintf(
                    '%.2f <= %.2f + %.2f (previous best: lift #%d)',
                    $currentTotalVolume,
                    $previousBestVolume,
                    $volumeTolerance,
                    $bestVolumeResult['lift_log_id']
                );
            }
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
     * Returns both the weight and the lift log ID
     * 
     * @param Collection $previousLogs Previous lift logs to search
     * @param int $targetReps The rep count to find max weight for
     * @return array ['weight' => float, 'lift_log_id' => int|null]
     */
    private function getMaxWeightForRepsWithLog(Collection $previousLogs, int $targetReps): array
    {
        $maxWeight = 0;
        $liftLogId = null;
        
        foreach ($previousLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->reps == $targetReps && $set->weight > $maxWeight) {
                    $maxWeight = $set->weight;
                    $liftLogId = $log->id;
                }
            }
        }
        
        return [
            'weight' => $maxWeight,
            'lift_log_id' => $liftLogId,
        ];
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
        return $this->getMaxWeightForRepsWithLog($previousLogs, $targetReps)['weight'];
    }

    /**
     * Get the best estimated 1RM from a collection of lift logs
     * Returns both the 1RM value and the lift log ID
     * 
     * @param Collection $logs Lift logs to analyze
     * @param mixed $strategy Exercise type strategy for 1RM calculation
     * @return array ['value' => float, 'lift_log_id' => int|null]
     */
    private function getBestEstimated1RMWithLog(Collection $logs, $strategy): array
    {
        $best1RM = 0;
        $liftLogId = null;
        
        foreach ($logs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $best1RM) {
                            $best1RM = $estimated1RM;
                            $liftLogId = $log->id;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        return [
            'value' => $best1RM,
            'lift_log_id' => $liftLogId,
        ];
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
        return $this->getBestEstimated1RMWithLog($logs, $strategy)['value'];
    }

    /**
     * Get the best total volume from a collection of lift logs
     * Returns both the volume and the lift log ID
     * Volume = sum of (weight × reps) for all sets in a single session
     * 
     * @param Collection $logs Lift logs to analyze
     * @return array ['value' => float, 'lift_log_id' => int|null]
     */
    private function getBestVolumeWithLog(Collection $logs): array
    {
        $bestVolume = 0;
        $liftLogId = null;
        
        foreach ($logs as $log) {
            $logVolume = 0;
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    $logVolume += ($set->weight * $set->reps);
                }
            }
            if ($logVolume > $bestVolume) {
                $bestVolume = $logVolume;
                $liftLogId = $log->id;
            }
        }
        
        return [
            'value' => $bestVolume,
            'lift_log_id' => $liftLogId,
        ];
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
        return $this->getBestVolumeWithLog($logs)['value'];
    }

    /**
     * Get the best total reps from a collection of lift logs
     * Returns both the total reps and the lift log ID
     * Used for pure bodyweight exercises (no extra weight)
     * 
     * @param Collection $logs Lift logs to analyze
     * @return array ['value' => int, 'lift_log_id' => int|null]
     */
    private function getBestTotalRepsWithLog(Collection $logs): array
    {
        $bestReps = 0;
        $liftLogId = null;
        
        foreach ($logs as $log) {
            $logReps = 0;
            foreach ($log->liftSets as $set) {
                if ($set->reps > 0) {
                    $logReps += $set->reps;
                }
            }
            if ($logReps > $bestReps) {
                $bestReps = $logReps;
                $liftLogId = $log->id;
            }
        }
        
        return [
            'value' => $bestReps,
            'lift_log_id' => $liftLogId,
        ];
    }

    /**
     * Get the best total reps from a collection of lift logs
     * Used for pure bodyweight exercises (no extra weight)
     * 
     * @param Collection $logs Lift logs to analyze
     * @return int Best total reps found
     */
    private function getBestTotalReps(Collection $logs): int
    {
        return $this->getBestTotalRepsWithLog($logs)['value'];
    }

    /**
     * Detect PRs for a lift log and return detailed information for database storage
     * Returns an array of PR records with all necessary data for PersonalRecord model
     * 
     * @param LiftLog $liftLog The lift log to check for PRs
     * @return array Array of PR details, each containing type, value, previous_pr_id, etc.
     */
    public function detectPRsWithDetails(LiftLog $liftLog): array
    {
        $exercise = $liftLog->exercise;
        $strategy = $exercise->getTypeStrategy();
        $isBodyweight = $exercise->exercise_type === 'bodyweight';
        
        // Skip exercises that don't support PRs
        if (!$isBodyweight && !$strategy->canCalculate1RM()) {
            return [];
        }
        
        // Get all previous lift logs for this exercise (before this one)
        $previousLogs = LiftLog::where('exercise_id', $exercise->id)
            ->where('user_id', $liftLog->user_id)
            ->where('logged_at', '<', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        $prs = [];
        $hasExtraWeight = $liftLog->liftSets->max('weight') > 0;
        
        // If this is the first log, it's a PR for applicable types
        if ($previousLogs->isEmpty()) {
            // 1RM PR only for non-bodyweight exercises
            if (!$isBodyweight) {
                $current1RM = $this->getBestEstimated1RM(new Collection([$liftLog]), $strategy);
                if ($current1RM > 0) {
                    $prs[] = [
                        'type' => 'one_rm',
                        'value' => $current1RM,
                        'previous_pr_id' => null,
                        'previous_value' => null,
                    ];
                }
            }
            
            // Volume PR: use total reps for pure bodyweight, volume for weighted
            if ($isBodyweight && !$hasExtraWeight) {
                $currentTotalReps = $liftLog->liftSets->sum('reps');
                if ($currentTotalReps > 0) {
                    $prs[] = [
                        'type' => 'volume',
                        'value' => $currentTotalReps,
                        'previous_pr_id' => null,
                        'previous_value' => null,
                    ];
                }
            } else {
                $currentVolume = $this->getBestVolume(new Collection([$liftLog]));
                if ($currentVolume > 0) {
                    $prs[] = [
                        'type' => 'volume',
                        'value' => $currentVolume,
                        'previous_pr_id' => null,
                        'previous_value' => null,
                    ];
                }
            }
            
            // Add rep-specific PRs for each unique rep count (only if weighted for bodyweight)
            if (!$isBodyweight || $hasExtraWeight) {
                $repCounts = $liftLog->liftSets->pluck('reps')->unique();
                foreach ($repCounts as $reps) {
                    if ($reps <= self::MAX_REP_COUNT_FOR_PR) {
                        $maxWeight = $liftLog->liftSets->where('reps', $reps)->max('weight');
                        if ($maxWeight > 0) {
                            $prs[] = [
                                'type' => 'rep_specific',
                                'rep_count' => $reps,
                                'value' => $maxWeight,
                                'previous_pr_id' => null,
                                'previous_value' => null,
                            ];
                        }
                    }
                }
            }
            
            return $prs;
        }
        
        // Check for 1RM PR (skip for bodyweight)
        if (!$isBodyweight) {
            $current1RM = $this->getBestEstimated1RM(new Collection([$liftLog]), $strategy);
            $best1RMResult = $this->getBestEstimated1RMWithLog($previousLogs, $strategy);
            
            if ($current1RM > $best1RMResult['value'] + self::TOLERANCE) {
                // Find the PersonalRecord ID for the previous 1RM PR
                $previousPRId = null;
                if ($best1RMResult['lift_log_id']) {
                    $previousPR = \App\Models\PersonalRecord::where('lift_log_id', $best1RMResult['lift_log_id'])
                        ->where('pr_type', 'one_rm')
                        ->first();
                    $previousPRId = $previousPR?->id;
                }
                
                $prs[] = [
                    'type' => 'one_rm',
                    'value' => $current1RM,
                    'previous_pr_id' => $previousPRId,
                    'previous_value' => $best1RMResult['value'],
                ];
            }
        }
        
        // Check for Volume PR
        // For pure bodyweight (no extra weight), use total reps instead of volume
        if ($isBodyweight && !$hasExtraWeight) {
            $currentTotalReps = $liftLog->liftSets->sum('reps');
            $bestRepsResult = $this->getBestTotalRepsWithLog($previousLogs);
            
            if ($currentTotalReps > $bestRepsResult['value']) {
                // Find the PersonalRecord ID for the previous total reps PR
                $previousPRId = null;
                if ($bestRepsResult['lift_log_id']) {
                    $previousPR = \App\Models\PersonalRecord::where('lift_log_id', $bestRepsResult['lift_log_id'])
                        ->where('pr_type', 'volume')
                        ->first();
                    $previousPRId = $previousPR?->id;
                }
                
                $prs[] = [
                    'type' => 'volume',
                    'value' => $currentTotalReps,
                    'previous_pr_id' => $previousPRId,
                    'previous_value' => $bestRepsResult['value'],
                ];
            }
        } else {
            // Use standard volume calculation (weight × reps)
            $currentVolume = $this->getBestVolume(new Collection([$liftLog]));
            $bestVolumeResult = $this->getBestVolumeWithLog($previousLogs);
            $volumeTolerance = $bestVolumeResult['value'] * self::VOLUME_TOLERANCE_PERCENT;
            
            if ($currentVolume > $bestVolumeResult['value'] + $volumeTolerance) {
                // Find the PersonalRecord ID for the previous volume PR
                $previousPRId = null;
                if ($bestVolumeResult['lift_log_id']) {
                    $previousPR = \App\Models\PersonalRecord::where('lift_log_id', $bestVolumeResult['lift_log_id'])
                        ->where('pr_type', 'volume')
                        ->first();
                    $previousPRId = $previousPR?->id;
                }
                
                $prs[] = [
                    'type' => 'volume',
                    'value' => $currentVolume,
                    'previous_pr_id' => $previousPRId,
                    'previous_value' => $bestVolumeResult['value'],
                ];
            }
        }
        
        // Check for rep-specific PRs (only if weighted for bodyweight)
        if (!$isBodyweight || $hasExtraWeight) {
            $repCounts = $liftLog->liftSets->pluck('reps')->unique();
            foreach ($repCounts as $reps) {
                if ($reps <= self::MAX_REP_COUNT_FOR_PR) {
                    $currentMaxWeight = $liftLog->liftSets->where('reps', $reps)->max('weight');
                    $previousMaxResult = $this->getMaxWeightForRepsWithLog($previousLogs, $reps);
                    
                    if ($currentMaxWeight > $previousMaxResult['weight'] + self::TOLERANCE) {
                        // Find the PersonalRecord ID for the previous rep-specific PR
                        $previousPRId = null;
                        if ($previousMaxResult['lift_log_id']) {
                            $previousPR = \App\Models\PersonalRecord::where('lift_log_id', $previousMaxResult['lift_log_id'])
                                ->where('pr_type', 'rep_specific')
                                ->where('rep_count', $reps)
                                ->first();
                            $previousPRId = $previousPR?->id;
                        }
                        
                        $prs[] = [
                            'type' => 'rep_specific',
                            'rep_count' => $reps,
                            'value' => $currentMaxWeight,
                            'previous_pr_id' => $previousPRId,
                            'previous_value' => $previousMaxResult['weight'],
                        ];
                    }
                }
            }
        }
        
        // Check for hypertrophy PR (best reps at a given weight)
        // Skip for bodyweight exercises (not meaningful)
        if (!$isBodyweight) {
            $weights = $liftLog->liftSets->pluck('weight')->unique();
            foreach ($weights as $weight) {
                if ($weight > 0) {
                    $currentMaxReps = $liftLog->liftSets->where('weight', $weight)->max('reps');
                    
                    // Find previous best reps at this weight (within tolerance)
                    $previousBestReps = 0;
                    $previousLiftLogId = null;
                    foreach ($previousLogs as $prevLog) {
                        foreach ($prevLog->liftSets as $set) {
                            if (abs($set->weight - $weight) <= 0.5 && $set->reps > $previousBestReps) {
                                $previousBestReps = $set->reps;
                                $previousLiftLogId = $prevLog->id;
                            }
                        }
                    }
                    
                    // Only award hypertrophy PR if there was a previous lift at this weight
                    if ($currentMaxReps > $previousBestReps && $previousBestReps > 0) {
                        // Find the PersonalRecord ID for the previous hypertrophy PR at this weight
                        $previousPRId = null;
                        if ($previousLiftLogId) {
                            $previousPR = \App\Models\PersonalRecord::where('lift_log_id', $previousLiftLogId)
                                ->where('pr_type', 'hypertrophy')
                                ->where('weight', $weight)
                                ->first();
                            $previousPRId = $previousPR?->id;
                        }
                        
                        $prs[] = [
                            'type' => 'hypertrophy',
                            'weight' => $weight,
                            'value' => $currentMaxReps,
                            'previous_pr_id' => $previousPRId,
                            'previous_value' => $previousBestReps,
                        ];
                    }
                }
            }
        }
        
        return $prs;
    }

}
