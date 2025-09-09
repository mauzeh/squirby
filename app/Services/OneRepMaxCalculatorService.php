<?php

namespace App\Services;

use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Models\User;
use App\Models\BodyLog;
use Carbon\Carbon;

class OneRepMaxCalculatorService
{
    /**
     * Calculate the 1RM for a single WorkoutSet.
     *
     * @param float $weight
     * @param int $reps
     * @param bool $isBodyweightExercise
     * @param int|null $userId
     * @param Carbon|null $date
     * @return float
     */
    public function calculateOneRepMax(float $weight, int $reps, bool $isBodyweightExercise = false, ?int $userId = null, ?Carbon $date = null): float
    {
        $bodyweight = 0;
        if ($isBodyweightExercise && $userId && $date) {
            $bodyweightMeasurement = BodyLog::where('user_id', $userId)
                ->whereHas('measurementType', function ($query) {
                    $query->where('name', 'Bodyweight');
                })
                ->whereDate('logged_at', '<=', $date->toDateString())
                ->orderBy('logged_at', 'desc')
                ->first();

            if ($bodyweightMeasurement) {
                $bodyweight = $bodyweightMeasurement->value;
                // Convert bodyweight to kg if it's in lbs and the exercise is in kg context
                // This part needs careful consideration based on how units are handled globally
                // For simplicity, assuming bodyweight is in the same unit as exercise weight or conversion is handled elsewhere
            }
        }

        $totalWeight = $weight + $bodyweight;

        if ($reps === 1) {
            return $totalWeight;
        }
        return $totalWeight * (1 + (0.0333 * $reps));
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

        // Eager load exercise to access is_bodyweight
        $workout->load('exercise');
        $isBodyweightExercise = $workout->exercise->is_bodyweight ?? false;
        $userId = $workout->user_id;
        $date = $workout->logged_at; // Assuming workout has a logged_at date

        if ($isUniform) {
            return $this->calculateOneRepMax($firstSet->weight, $firstSet->reps, $isBodyweightExercise, $userId, $date);
        } else {
            return $this->calculateOneRepMax($firstSet->weight, $firstSet->reps, $isBodyweightExercise, $userId, $date);
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

        // Eager load exercise to access is_bodyweight
        $workout->load('exercise');
        $isBodyweightExercise = $workout->exercise->is_bodyweight ?? false;
        $userId = $workout->user_id;
        $date = $workout->logged_at; // Assuming workout has a logged_at date

        return $workout->workoutSets->max(function ($workoutSet) use ($isBodyweightExercise, $userId, $date) {
            return $this->calculateOneRepMax($workoutSet->weight, $workoutSet->reps, $isBodyweightExercise, $userId, $date);
        });
    }
}
