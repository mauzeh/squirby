<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Models\BodyLog;
use Carbon\Carbon;

class OneRepMaxCalculatorService
{
    /**
     * Calculate the 1RM for a single LiftSet.
     *
     * @param float $weight
     * @param int $reps
     * @param bool $isBodyweightExercise
     * @param int|null $userId
     * @param Carbon|null $date
     * @param string|null $bandType
     * @return float
     * @throws NotApplicableException
     */
    public function calculateOneRepMax(float $weight, int $reps, bool $isBodyweightExercise = false, ?int $userId = null, ?Carbon $date = null, ?string $bandType = null): float
    {
        if ($bandType !== null) {
            throw new NotApplicableException('1RM calculation is not applicable for banded exercises.');
        }

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
            }
        }

        $totalWeight = $weight + $bodyweight;

        if ($reps === 1) {
            return $totalWeight;
        }
        return $totalWeight * (1 + (0.0333 * $reps));
    }

    /**
     * Calculate the weight for a given 1RM and reps.
     *
     * @param float $oneRepMax
     * @param int $reps
     * @return float
     */
    public function getWeightFromOneRepMax(float $oneRepMax, int $reps): float
    {
        if ($reps === 0) {
            return 0.0; // Avoid division by zero
        }
        return $oneRepMax / (1 + (0.0333 * $reps));
    }

    /**
     * Get the 1RM for a LiftLog, considering uniformity of sets.
     *
     * @param \App\Models\LiftLog $liftLog
     * @return float
     * @throws NotApplicableException
     */
    public function getLiftLogOneRepMax(LiftLog $liftLog): float
    {
        if ($liftLog->liftSets->isEmpty()) {
            return 0;
        }

        $liftLog->load('exercise');
        if ($liftLog->exercise->band_type !== null) {
            throw new NotApplicableException('1RM calculation is not applicable for banded exercises.');
        }

        // Check for uniformity
        $isUniform = true;
        $firstSet = $liftLog->liftSets->first();
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight !== $firstSet->weight || $set->reps !== $firstSet->reps) {
                $isUniform = false;
                break;
            }
        }

        $isBodyweightExercise = $liftLog->exercise->is_bodyweight ?? false;
        $userId = $liftLog->user_id;
        $date = $liftLog->logged_at; // Assuming lift log has a logged_at date

        if ($isUniform) {
            return $this->calculateOneRepMax($firstSet->weight, $firstSet->reps, $isBodyweightExercise, $userId, $date);
        } else {
            return $this->calculateOneRepMax($firstSet->weight, $firstSet->reps, $isBodyweightExercise, $userId, $date);
        }
    }

    /**
     * Get the best 1RM from all LiftSets of a LiftLog.
     *
     * @param \App\Models\LiftLog $liftLog
     * @return float
     * @throws NotApplicableException
     */
    public function getBestLiftLogOneRepMax(LiftLog $liftLog): float
    {
        if ($liftLog->liftSets->isEmpty()) {
            return 0;
        }

        $liftLog->load('exercise');
        if ($liftLog->exercise->band_type !== null) {
            throw new NotApplicableException('1RM calculation is not applicable for banded exercises.');
        }

        $isBodyweightExercise = $liftLog->exercise->is_bodyweight ?? false;
        $userId = $liftLog->user_id;
        $date = $liftLog->logged_at; // Assuming lift log has a logged_at date

        return $liftLog->liftSets->max(function ($liftSet) use ($isBodyweightExercise, $userId, $date) {
            return $this->calculateOneRepMax($liftSet->weight, $liftSet->reps, $isBodyweightExercise, $userId, $date);
        });
    }
}
