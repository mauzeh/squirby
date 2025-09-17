<?php

namespace App\Services\ProgressionModels;

use App\Models\LiftLog;
use App\Services\OneRepMaxCalculatorService;
use Carbon\Carbon;

class DoubleProgression implements ProgressionModel
{
    const RESOLUTION = 5.0;
    const MIN_REPS = 8;
    const MAX_REPS = 12;

    protected OneRepMaxCalculatorService $oneRepMaxCalculatorService;

    public function __construct(OneRepMaxCalculatorService $oneRepMaxCalculatorService)
    {
        $this->oneRepMaxCalculatorService = $oneRepMaxCalculatorService;
    }

    public function suggest(int $userId, int $exerciseId, Carbon $forDate = null): ?object
    {
        $forDate = $forDate ?? Carbon::now();
        $lastLog = LiftLog::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->orderBy('logged_at', 'desc')
            ->first();

        if (!$lastLog) {
            return null;
        }

        $lastWeight = $lastLog->display_weight;
        $lastReps = $lastLog->display_reps;

        $suggestedWeight = $lastWeight;
        $suggestedReps = $lastReps + 1;

        if ($lastReps >= self::MAX_REPS) {
            $suggestedWeight = $lastWeight + self::RESOLUTION;
            $suggestedReps = self::MIN_REPS;
        }

        return (object)[
            'suggestedWeight' => $suggestedWeight,
            'reps' => $suggestedReps,
            'sets' => $lastLog->display_rounds,
            'lastWeight' => $lastWeight,
            'lastReps' => $lastReps,
            'lastSets' => $lastLog->display_rounds,
        ];
    }
}