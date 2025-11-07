<?php

namespace App\Services;

use App\Models\LiftLog;
use Carbon\Carbon;

class TrainingProgressionService
{
    // No dependencies needed - all logic delegated to exercise type strategies
    
    public function getSuggestionDetails(int $userId, int $exerciseId, Carbon $forDate = null): ?object
    {
        $lastLog = LiftLog::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->orderBy('logged_at', 'desc')
            ->first();

        if (!$lastLog) {
            return null;
        }

        return $this->getSuggestionDetailsWithLog($lastLog, $userId, $exerciseId, $forDate);
    }

    public function getSuggestionDetailsWithLog(LiftLog $lastLog, int $userId, int $exerciseId, Carbon $forDate = null): ?object
    {
        // Delegate to exercise type strategy for progression suggestions
        $strategy = $lastLog->exercise->getTypeStrategy();
        
        return $strategy->getProgressionSuggestion($lastLog, $userId, $exerciseId, $forDate);
    }
}
