<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ProgressionModels\DoubleProgression;
use App\Services\ProgressionModels\LinearProgression;
use Carbon\Carbon;

class TrainingProgressionService
{
    protected OneRepMaxCalculatorService $oneRepMaxCalculatorService;

    public function __construct(OneRepMaxCalculatorService $oneRepMaxCalculatorService)
    {
        $this->oneRepMaxCalculatorService = $oneRepMaxCalculatorService;
    }

    public function getSuggestionDetails(int $userId, int $exerciseId, Carbon $forDate = null): ?object
    {
        $lastLog = LiftLog::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->orderBy('logged_at', 'desc')
            ->first();

        if (!$lastLog) {
            return null;
        }

        $progressionModel = $this->getProgressionModel($lastLog);

        return $progressionModel->suggest($userId, $exerciseId, $forDate);
    }

    private function getProgressionModel(LiftLog $liftLog): \App\Services\ProgressionModels\ProgressionModel
    {
        if ($liftLog->display_reps >= 8 && $liftLog->display_reps <= 12) {
            return new DoubleProgression($this->oneRepMaxCalculatorService);
        }

        return new LinearProgression($this->oneRepMaxCalculatorService);
    }
}