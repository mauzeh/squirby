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
     * Returns formatted text like "Suggested: 185 lbs × 8 reps × 4 sets"
     * 
     * @param int $userId
     * @param int $exerciseId
     * @return string|null
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
        
        $strategy = $exercise->getTypeStrategy();
        $sets = $suggestion->sets ?? 3;
        
        // Format based on exercise type using strategy information
        if (isset($suggestion->band_color)) {
            // Banded exercise suggestion
            return 'Suggested: ' . $suggestion->band_color . ' band × ' . $suggestion->reps . ' reps × ' . $sets . ' sets';
        } elseif (isset($suggestion->suggestedWeight) && $strategy->getTypeName() !== 'bodyweight') {
            // Weighted exercise suggestion
            return 'Suggested: ' . $suggestion->suggestedWeight . ' lbs × ' . $suggestion->reps . ' reps × ' . $sets . ' sets';
        } elseif ($strategy->getTypeName() === 'bodyweight' && isset($suggestion->reps)) {
            // Bodyweight exercise suggestion
            return 'Suggested: ' . $suggestion->reps . ' reps × ' . $sets . ' sets';
        } elseif ($strategy->getTypeName() === 'cardio' && isset($suggestion->reps)) {
            // Cardio exercise suggestion (reps = distance)
            $distance = $suggestion->reps;
            if ($distance >= 10) {
                $distanceText = number_format($distance / 5.28, 1) . ' km';
            } else {
                $distanceText = $distance . ' mi';
            }
            return 'Suggested: ' . $distanceText . ' × ' . $sets . ' rounds';
        }
        
        return null;
    }
}
