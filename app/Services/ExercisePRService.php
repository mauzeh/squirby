<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ExercisePRService
{
    /**
     * Check if exercise supports PR tracking
     * Only barbell exercises are supported
     * 
     * @param Exercise $exercise
     * @return bool
     */
    public function supportsPRTracking(Exercise $exercise): bool
    {
        return $exercise->exercise_type === 'barbell';
    }

    /**
     * Get PR data for an exercise
     * Returns the heaviest lifts for 1, 2, and 3 rep ranges
     * 
     * @param Exercise $exercise
     * @param User $user
     * @return array|null Returns null if exercise doesn't support PRs
     */
    public function getPRData(Exercise $exercise, User $user): ?array
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

        // Process each target rep range (1, 2, 3)
        foreach ([1, 2, 3] as $targetReps) {
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
     * Get calculator grid data based on PRs
     * Generates a percentage-based weight grid for training
     * 
     * @param array $prData PR data from getPRData()
     * @return array|null Returns null if no valid PR data
     */
    public function getCalculatorGrid(array $prData): ?array
    {
        // Build columns from PR data
        $columns = [];
        
        foreach ([1, 2, 3] as $reps) {
            $key = "rep_{$reps}";
            if (isset($prData[$key]) && $prData[$key] !== null) {
                $weight = $prData[$key]['weight'];
                $oneRepMax = $this->calculate1RM($weight, $reps);
                
                $columns[] = [
                    'label' => "1x{$reps}",
                    'one_rep_max' => $oneRepMax,
                ];
            }
        }

        // If no columns, return null
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
        ];
    }

    /**
     * Calculate 1RM using the Brzycki formula
     * Formula: 1RM = weight × (36 / (37 - reps))
     * 
     * @param float $weight
     * @param int $reps
     * @return float
     */
    protected function calculate1RM(float $weight, int $reps): float
    {
        // For 1 rep, the weight is already the 1RM
        if ($reps === 1) {
            return $weight;
        }

        // Handle edge case where reps >= 37 (would cause division by zero or negative)
        if ($reps >= 37) {
            Log::warning("Invalid reps for 1RM calculation: {$reps}. Using weight as 1RM.");
            return $weight;
        }

        // Brzycki formula
        return $weight * (36 / (37 - $reps));
    }
}
