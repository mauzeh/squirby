<?php

namespace App\Services;

use App\Models\Workout;
use App\Models\WorkoutSet;

class OneRepMaxCalculatorService
{
    /**
     * Calculate the 1RM for a single WorkoutSet.
     *
     * @param float $weight
     * @param int $reps
     * @return float
     */
    public function calculateOneRepMax(float $weight, int $reps): float
    {
        return $weight * (1 + (0.0333 * $reps));
    }

    /**
     * Get the 1RM for a Workout, considering uniformity of sets.
     *
     * @param \App\Models\Workout $workout
     * @return float
     */
    public function getWorkoutOneRepMax(Workout $workout): float
    {
        if ($workout->workoutSets->isEmpty()) {
            return 0;
        }

        // Check for uniformity
        $isUniform = true;
        $firstSet = $workout->workoutSets->first();
        foreach ($workout->workoutSets as $set) {
            if ($set->weight !== $firstSet->weight || $set->reps !== $firstSet->reps) {
                $isUniform = false;
                break;
            }
        }

        if ($isUniform) {
            // Use the old calculation if uniform
            return $this->calculateOneRepMax($firstSet->weight, $firstSet->reps);
        } else {
            // If not uniform, use the first set's data (as per current implementation)
            return $this->calculateOneRepMax($firstSet->weight, $firstSet->reps);
        }
    }

    /**
     * Get the best 1RM from all WorkoutSets of a Workout.
     *
     * @param \App\Models\Workout $workout
     * @return float
     */
    public function getBestWorkoutOneRepMax(Workout $workout): float
    {
        if ($workout->workoutSets->isEmpty()) {
            return 0;
        }

        return $workout->workoutSets->max(function ($workoutSet) {
            return $this->calculateOneRepMax($workoutSet->weight, $workoutSet->reps);
        });
    }
}
