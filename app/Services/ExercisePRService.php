<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ExercisePRService
{
    protected OneRepMaxCalculatorService $oneRepMaxService;

    public function __construct(OneRepMaxCalculatorService $oneRepMaxService)
    {
        $this->oneRepMaxService = $oneRepMaxService;
    }
    /**
     * Check if exercise supports PR tracking
     * Only regular (weighted) exercises are supported
     * 
     * @param Exercise $exercise
     * @return bool
     */
    public function supportsPRTracking(Exercise $exercise): bool
    {
        return $exercise->exercise_type === 'regular';
    }

    /**
     * Get PR data for an exercise
     * Returns the heaviest lifts for specified rep ranges (default 1-10)
     * 
     * @param Exercise $exercise
     * @param User $user
     * @param int $maxReps Maximum number of reps to track (default 10)
     * @return array|null Returns null if exercise doesn't support PRs
     */
    public function getPRData(Exercise $exercise, User $user, int $maxReps = 10): ?array
    {
        // Check if exercise supports PR tracking
        if (!$this->supportsPRTracking($exercise)) {
            return null;
        }

        // Get all lift logs with sets for this exercise and user
        $liftLogs = $exercise->liftLogs()
            ->where('user_id', $user->id)
            ->with('liftSets')
            ->get();

        // If no lift logs exist, return null
        if ($liftLogs->isEmpty()) {
            return null;
        }

        $prData = [];

        // Process each target rep range (1 through maxReps)
        for ($targetReps = 1; $targetReps <= $maxReps; $targetReps++) {
            $bestForReps = null;
            $maxWeight = 0;

            // Iterate through all lift logs and their sets
            foreach ($liftLogs as $log) {
                foreach ($log->liftSets as $set) {
                    // Skip invalid data
                    if ($set->reps != $targetReps || $set->weight <= 0) {
                        continue;
                    }

                    // Track the highest weight for this rep range
                    if ($set->weight > $maxWeight) {
                        $maxWeight = $set->weight;
                        $bestForReps = [
                            'weight' => $set->weight,
                            'lift_log_id' => $log->id,
                            'date' => $log->logged_at->format('Y-m-d'),
                            'is_estimated' => false,
                        ];
                    }
                }
            }

            $prData["rep_{$targetReps}"] = $bestForReps;
        }

        // If no PR data was found for any rep range, return null
        if (empty(array_filter($prData))) {
            return null;
        }

        return $prData;
    }

    /**
     * Check if PR data is stale (older than 6 months)
     * 
     * @param array $prData PR data from getPRData()
     * @return bool
     */
    public function isPRDataStale(array $prData): bool
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // Check each rep range for recent data
        foreach ([1, 2, 3] as $reps) {
            $key = "rep_{$reps}";
            if (isset($prData[$key]) && $prData[$key] !== null) {
                $prDate = \Carbon\Carbon::parse($prData[$key]['date']);
                
                // If any PR is recent (within 6 months), data is not stale
                if ($prDate->isAfter($sixMonthsAgo)) {
                    return false;
                }
            }
        }
        
        // All PRs are older than 6 months (or no PRs exist)
        return true;
    }

    /**
     * Get estimated 1RM based on best lift across all rep ranges
     * Used when athlete hasn't performed 1-3 rep tests
     * 
     * @param Exercise $exercise
     * @param User $user
     * @return array|null Returns estimated 1RM data or null if no lifts exist
     */
    public function getEstimated1RM(Exercise $exercise, User $user): ?array
    {
        // Check if exercise supports PR tracking
        if (!$this->supportsPRTracking($exercise)) {
            return null;
        }

        // Check if exercise type supports 1RM calculation
        $strategy = $exercise->getTypeStrategy();
        if (!$strategy->canCalculate1RM()) {
            return null;
        }

        // Get all lift logs with sets for this exercise and user
        $liftLogs = $exercise->liftLogs()
            ->where('user_id', $user->id)
            ->with('liftSets')
            ->get();

        // If no lift logs exist, return null
        if ($liftLogs->isEmpty()) {
            return null;
        }

        $bestLift = null;
        $maxEstimated1RM = 0;

        // Find the lift with the highest estimated 1RM
        foreach ($liftLogs as $log) {
            foreach ($log->liftSets as $set) {
                // Skip invalid data or very high rep sets (less reliable for 1RM estimation)
                if ($set->weight <= 0 || $set->reps <= 0) {
                    continue;
                }

                try {
                    $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);

                    if ($estimated1RM > $maxEstimated1RM) {
                        $maxEstimated1RM = $estimated1RM;
                        $bestLift = [
                            'weight' => round($estimated1RM),
                            'lift_log_id' => $log->id,
                            'date' => $log->logged_at->format('Y-m-d'),
                            'is_estimated' => true,
                            'based_on_reps' => $set->reps,
                            'based_on_weight' => $set->weight,
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip sets that can't be calculated
                    continue;
                }
            }
        }

        return $bestLift;
    }

    /**
     * Get calculator grid data based on PRs
     * Generates a percentage-based weight grid for training
     * 
     * @param Exercise $exercise
     * @param array $prData PR data from getPRData()
     * @param array|null $estimated1RM Optional estimated 1RM data
     * @return array|null Returns null if no valid PR data
     */
    public function getCalculatorGrid(Exercise $exercise, array $prData, ?array $estimated1RM = null): ?array
    {
        $strategy = $exercise->getTypeStrategy();
        
        // Build columns from PR data
        $columns = [];
        $isEstimated = false;
        
        foreach ([1, 2, 3] as $reps) {
            $key = "rep_{$reps}";
            if (isset($prData[$key]) && $prData[$key] !== null) {
                $weight = $prData[$key]['weight'];
                // For actual PRs, the weight at that rep range is the 1RM for that rep
                $oneRepMax = ($reps === 1) ? $weight : $weight * (1 + (0.0333 * $reps));
                
                $columns[] = [
                    'label' => "1 × {$reps}",
                    'one_rep_max' => $oneRepMax,
                    'is_estimated' => $prData[$key]['is_estimated'] ?? false,
                ];
            }
        }

        // If no columns from actual PRs, use estimated 1RM
        if (empty($columns) && $estimated1RM !== null) {
            $columns[] = [
                'label' => 'Est. 1RM',
                'one_rep_max' => $estimated1RM['weight'],
                'is_estimated' => true,
            ];
            $isEstimated = true;
        }

        // If still no columns, return null
        if (empty($columns)) {
            return null;
        }

        // Define percentage rows
        $percentages = [100, 95, 90, 85, 80, 75, 70, 65, 60, 55, 50, 45];
        
        // Build rows with calculated weights
        $rows = [];
        foreach ($percentages as $percentage) {
            $weights = [];
            
            foreach ($columns as $column) {
                $calculatedWeight = ($column['one_rep_max'] * $percentage) / 100;
                $weights[] = round($calculatedWeight);
            }
            
            $rows[] = [
                'percentage' => $percentage,
                'weights' => $weights,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'is_estimated' => $isEstimated,
        ];
    }
}
