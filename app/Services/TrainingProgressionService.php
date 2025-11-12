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

    /**
     * Format suggestion text for display
     * Delegates to the exercise type strategy for proper formatting
     * 
     * @param int $userId
     * @param int $exerciseId
     * @return string|null Formatted text like "Suggested: 185 lbs × 8 reps × 4 sets"
     */
    public function formatSuggestionText(int $userId, int $exerciseId): ?string
    {
        $suggestion = $this->getSuggestionDetails($userId, $exerciseId);
        
        if (!$suggestion) {
            return null;
        }
        
        // Get the exercise to access its strategy
        $exercise = \App\Models\Exercise::find($exerciseId);
        if (!$exercise) {
            return null;
        }
        
        // Delegate formatting to the exercise type strategy
        $strategy = $exercise->getTypeStrategy();
        return $strategy->formatSuggestionText($suggestion);
    }
}
