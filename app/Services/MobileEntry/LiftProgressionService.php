<?php

namespace App\Services\MobileEntry;

use App\Models\Exercise;
use App\Models\User;
use App\Services\TrainingProgressionService;

class LiftProgressionService
{
    protected TrainingProgressionService $trainingProgressionService;

    public function __construct(TrainingProgressionService $trainingProgressionService)
    {
        $this->trainingProgressionService = $trainingProgressionService;
    }

    /**
     * Determine default weight for an exercise
     * 
     * @param Exercise $exercise
     * @param array|null $lastSession
     * @param int|null $userId
     * @return float
     */
    public function getDefaultWeight($exercise, $lastSession, $userId = null)
    {
        $strategy = $exercise->getTypeStrategy();
        
        if ($lastSession && $userId) {
            // Use TrainingProgressionService for intelligent progression
            $suggestion = $this->trainingProgressionService->getSuggestionDetails(
                $userId, 
                $exercise->id
            );
            
            if ($suggestion && isset($suggestion->suggestedWeight)) {
                return $suggestion->suggestedWeight;
            }
        }
        
        // If we have a last session, use strategy's progression logic
        if ($lastSession) {
            return $strategy->getDefaultWeightProgression($lastSession['weight'] ?? 0);
        }
        
        // No last session: use strategy's default starting weight
        return $strategy->getDefaultStartingWeight($exercise);
    }

    /**
     * Prepare default values for create mode
     * 
     * @param Exercise $exercise
     * @param array|null $lastSession
     * @param int $userId
     * @param User $user
     * @return array
     */
    public function prepareCreateDefaults(Exercise $exercise, ?array $lastSession, int $userId, User $user): array
    {
        // Get progression suggestion
        $progressionSuggestion = null;
        if ($lastSession) {
            $progressionSuggestion = $this->trainingProgressionService->getSuggestionDetails($userId, $exercise->id);
        }
        
        // Determine default weight, reps, and sets based on user preference
        if ($user->shouldPrefillSuggestedValues()) {
            // Use suggested values from progression service
            $defaultWeight = $this->getDefaultWeight($exercise, $lastSession, $userId);
            $defaultReps = $progressionSuggestion->reps ?? ($lastSession['reps'] ?? 5);
            $defaultSets = $progressionSuggestion->sets ?? ($lastSession['sets'] ?? 3);
        } else {
            // Use last workout values only
            $defaultWeight = $lastSession['weight'] ?? $exercise->getTypeStrategy()->getDefaultStartingWeight($exercise);
            $defaultReps = $lastSession['reps'] ?? 5;
            $defaultSets = $lastSession['sets'] ?? 3;
        }
        
        return [
            'weight' => $defaultWeight,
            'reps' => $defaultReps,
            'sets' => $defaultSets,
            'band_color' => $lastSession['band_color'] ?? 'red',
            'comments' => '',
        ];
    }
}
