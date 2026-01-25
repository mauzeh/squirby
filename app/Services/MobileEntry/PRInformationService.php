<?php

namespace App\Services\MobileEntry;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Services\ExerciseAliasService;
use App\Services\ComponentBuilder;
use Carbon\Carbon;

class PRInformationService
{
    protected ExerciseAliasService $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Generate PR info components for a collection of lift logs
     * Creates one PR info card per unique exercise
     * 
     * @param \Illuminate\Database\Eloquent\Collection $liftLogs Collection of lift logs
     * @param Carbon $beforeDate Only consider logs before this date
     * @return array Array of PR info components
     */
    public function generatePRInfoForLogs($liftLogs, Carbon $beforeDate): array
    {
        $components = [];
        
        // Group logs by exercise
        $logsByExercise = $liftLogs->groupBy('exercise_id');
        
        foreach ($logsByExercise as $exerciseId => $exerciseLogs) {
            $exercise = $exerciseLogs->first()->exercise;
            
            // Get PR information for this exercise
            $prInfo = $this->getPRInformation($exerciseId, $exerciseLogs->first()->user_id, $beforeDate);
            
            // Only add component if there's PR data
            if (!empty($prInfo)) {
                $displayName = $this->aliasService->getDisplayName($exercise, $exerciseLogs->first()->user);
                $components[] = ComponentBuilder::prInfo($displayName . ' - Previous Records', $prInfo);
            }
        }
        
        return $components;
    }

    /**
     * Get PR information for a specific exercise
     * 
     * @param int $exerciseId
     * @param int $userId
     * @param Carbon $beforeDate
     * @return array Array of PR records
     */
    private function getPRInformation(int $exerciseId, int $userId, Carbon $beforeDate): array
    {
        $exercise = Exercise::find($exerciseId);
        if (!$exercise) {
            return [];
        }
        
        $strategy = $exercise->getTypeStrategy();
        
        // Only exercises that support 1RM calculation can have PRs
        if (!$strategy->canCalculate1RM()) {
            return [];
        }
        
        // Get all previous lift logs for this exercise
        $previousLogs = LiftLog::where('exercise_id', $exerciseId)
            ->where('user_id', $userId)
            ->where('logged_at', '<', $beforeDate->toDateString())
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        if ($previousLogs->isEmpty()) {
            return [];
        }
        
        $records = [];
        
        // Get 1RM PR
        $oneRMResult = $this->getMax1RMWithLog($previousLogs);
        if ($oneRMResult['value'] > 0) {
            $records[] = [
                'type' => 'one_rm',
                'label' => '1RM',
                'value' => number_format($oneRMResult['value'], 1) . ' lbs',
                'date' => $oneRMResult['date'],
                'lift_log_id' => $oneRMResult['lift_log_id']
            ];
        }
        
        // Get Volume PR
        $volumeResult = $this->getMaxVolumeWithLog($previousLogs);
        if ($volumeResult['value'] > 0) {
            $records[] = [
                'type' => 'volume',
                'label' => 'Volume',
                'value' => number_format($volumeResult['value'], 0) . ' lbs',
                'date' => $volumeResult['date'],
                'lift_log_id' => $volumeResult['lift_log_id']
            ];
        }
        
        // Get rep-specific PRs (for reps 1-10)
        for ($reps = 1; $reps <= 10; $reps++) {
            $repResult = $this->getMaxWeightForRepsWithLog($previousLogs, $reps);
            if ($repResult['weight'] > 0) {
                $records[] = [
                    'type' => 'rep_specific',
                    'label' => $reps . ' Rep' . ($reps > 1 ? 's' : ''),
                    'value' => number_format($repResult['weight'], 1) . ' lbs',
                    'date' => $repResult['date'],
                    'lift_log_id' => $repResult['lift_log_id']
                ];
            }
        }
        
        return $records;
    }
    
    /**
     * Get maximum 1RM from previous logs with lift log ID
     * 
     * @param \Illuminate\Database\Eloquent\Collection $previousLogs
     * @return array ['value' => float, 'date' => string, 'lift_log_id' => int]
     */
    private function getMax1RMWithLog($previousLogs): array
    {
        $max1RM = 0;
        $maxDate = null;
        $maxLiftLogId = null;
        
        foreach ($previousLogs as $log) {
            $strategy = $log->exercise->getTypeStrategy();
            
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $max1RM) {
                            $max1RM = $estimated1RM;
                            $maxDate = $log->logged_at->format('M j, Y');
                            $maxLiftLogId = $log->id;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        return [
            'value' => $max1RM,
            'date' => $maxDate ?? '',
            'lift_log_id' => $maxLiftLogId
        ];
    }
    
    /**
     * Get maximum volume from previous logs with lift log ID
     * 
     * @param \Illuminate\Database\Eloquent\Collection $previousLogs
     * @return array ['value' => float, 'date' => string, 'lift_log_id' => int]
     */
    private function getMaxVolumeWithLog($previousLogs): array
    {
        $maxVolume = 0;
        $maxDate = null;
        $maxLiftLogId = null;
        
        foreach ($previousLogs as $log) {
            $totalVolume = 0;
            
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    $totalVolume += ($set->weight * $set->reps);
                }
            }
            
            if ($totalVolume > $maxVolume) {
                $maxVolume = $totalVolume;
                $maxDate = $log->logged_at->format('M j, Y');
                $maxLiftLogId = $log->id;
            }
        }
        
        return [
            'value' => $maxVolume,
            'date' => $maxDate ?? '',
            'lift_log_id' => $maxLiftLogId
        ];
    }
    
    /**
     * Get maximum weight for a specific rep count with lift log ID
     * 
     * @param \Illuminate\Database\Eloquent\Collection $previousLogs
     * @param int $targetReps
     * @return array ['weight' => float, 'date' => string, 'lift_log_id' => int]
     */
    private function getMaxWeightForRepsWithLog($previousLogs, int $targetReps): array
    {
        $maxWeight = 0;
        $maxDate = null;
        $maxLiftLogId = null;
        
        foreach ($previousLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->reps === $targetReps && $set->weight > $maxWeight) {
                    $maxWeight = $set->weight;
                    $maxDate = $log->logged_at->format('M j, Y');
                    $maxLiftLogId = $log->id;
                }
            }
        }
        
        return [
            'weight' => $maxWeight,
            'date' => $maxDate ?? '',
            'lift_log_id' => $maxLiftLogId
        ];
    }
}
