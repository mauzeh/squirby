<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ProgressionModels\DoubleProgression;
use App\Services\ProgressionModels\LinearProgression;
use Carbon\Carbon;

class TrainingProgressionService
{
    protected OneRepMaxCalculatorService $oneRepMaxCalculatorService;
    protected BandService $bandService;

    public function __construct(OneRepMaxCalculatorService $oneRepMaxCalculatorService, BandService $bandService)
    {
        $this->oneRepMaxCalculatorService = $oneRepMaxCalculatorService;
        $this->bandService = $bandService;
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

        return $this->getSuggestionDetailsWithLog($lastLog, $userId, $exerciseId, $forDate);
    }

    public function getSuggestionDetailsWithLog(LiftLog $lastLog, int $userId, int $exerciseId, Carbon $forDate = null): ?object
    {
        // Use exercise type strategy to get progression suggestions
        $strategy = $lastLog->exercise->getTypeStrategy();
        $supportedProgressionTypes = $strategy->getSupportedProgressionTypes();
        
        // Handle cardio exercises with specific logic
        if (in_array('cardio_progression', $supportedProgressionTypes)) {
            return $this->getCardioProgressionSuggestion($lastLog, $userId, $exerciseId);
        }
        
        // Handle banded exercises with specific logic
        if (in_array('band_progression', $supportedProgressionTypes)) {
            $lastLoggedReps = $lastLog->liftSets->first()->reps ?? 0;
            $lastLoggedBandColor = $lastLog->liftSets->first()->band_color ?? null;

            $maxRepsBeforeBandChange = config('bands.max_reps_before_band_change', 15);
            $defaultRepsOnBandChange = config('bands.default_reps_on_band_change', 8);

            if ($lastLoggedReps < $maxRepsBeforeBandChange) {
                $suggestedReps = min($lastLoggedReps + 1, $maxRepsBeforeBandChange);
                return (object)[
                    'sets' => $lastLog->liftSets->count(),
                    'reps' => $suggestedReps,
                    'band_color' => $lastLoggedBandColor,
                ];
            } else {
                $bandType = $lastLog->exercise->isBandedResistance() ? 'resistance' : 'assistance';
                $nextHarderBand = $this->bandService->getNextHarderBand($lastLoggedBandColor, $bandType);
                if ($nextHarderBand) {
                    return (object)[
                        'sets' => $lastLog->liftSets->count(),
                        'reps' => $defaultRepsOnBandChange,
                        'band_color' => $nextHarderBand,
                    ];
                } else {
                    // If no harder band, suggest same band with max reps
                    return (object)[
                        'sets' => $lastLog->liftSets->count(),
                        'reps' => $maxRepsBeforeBandChange,
                        'band_color' => $lastLoggedBandColor,
                    ];
                }
            }
        }

        $progressionModel = $this->getProgressionModel($lastLog);

        return $progressionModel->suggest($userId, $exerciseId, $forDate);
    }

    private function getProgressionModel(LiftLog $liftLog): \App\Services\ProgressionModels\ProgressionModel
    {
        // Use exercise type strategy to determine appropriate progression model
        $strategy = $liftLog->exercise->getTypeStrategy();
        $supportedProgressionTypes = $strategy->getSupportedProgressionTypes();
        
        // Cardio exercises should not use weight-based progression models
        if (in_array('cardio_progression', $supportedProgressionTypes)) {
            // This should not be reached since cardio is handled separately,
            // but provide a fallback to prevent weight-based progression
            return new LinearProgression($this->oneRepMaxCalculatorService);
        }
        
        // Try to infer progression model from recent workout history first
        $inferredModel = $this->inferProgressionModelFromHistory($liftLog->user_id, $liftLog->exercise_id);
        if ($inferredModel) {
            return $inferredModel;
        }
        
        // For exercises that support both linear and double progression, use rep-range logic
        if (in_array('linear', $supportedProgressionTypes) && in_array('double_progression', $supportedProgressionTypes)) {
            // Use rep-range logic to decide between linear and double progression
            if ($liftLog->display_reps >= 8 && $liftLog->display_reps <= 12) {
                return new DoubleProgression($this->oneRepMaxCalculatorService);
            }
            return new LinearProgression($this->oneRepMaxCalculatorService);
        }
        
        // For exercises that only support linear progression, use LinearProgression
        if (in_array('linear', $supportedProgressionTypes)) {
            return new LinearProgression($this->oneRepMaxCalculatorService);
        }
        
        // For exercises that only support double progression, use DoubleProgression
        if (in_array('double_progression', $supportedProgressionTypes)) {
            return new DoubleProgression($this->oneRepMaxCalculatorService);
        }

        // Fallback to rep-range based selection
        if ($liftLog->display_reps >= 8 && $liftLog->display_reps <= 12) {
            return new DoubleProgression($this->oneRepMaxCalculatorService);
        }

        return new LinearProgression($this->oneRepMaxCalculatorService);
    }

    private function inferProgressionModelFromHistory(int $userId, int $exerciseId): ?\App\Services\ProgressionModels\ProgressionModel
    {
        $recentLogs = LiftLog::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->orderBy('logged_at', 'desc')
            ->take(2)
            ->get();

        if ($recentLogs->count() < 2) {
            return null; // Not enough data to infer
        }

        $newer = $recentLogs->first();
        $older = $recentLogs->last();

        $weightChange = $newer->display_weight - $older->display_weight;
        $repsChange = $newer->display_reps - $older->display_reps;

        // DoubleProgression pattern: same weight, reps increased OR weight increased with reps reset to lower value
        if ($weightChange == 0 && $repsChange > 0) {
            // Same weight, reps increased - classic DoubleProgression
            return new DoubleProgression($this->oneRepMaxCalculatorService);
        }

        if ($weightChange > 0 && $repsChange < 0 && $newer->display_reps >= 8 && $newer->display_reps <= 12) {
            // Weight increased, reps decreased to 8-12 range - likely DoubleProgression reset
            return new DoubleProgression($this->oneRepMaxCalculatorService);
        }

        // LinearProgression pattern: weight increased, same reps
        if ($weightChange > 0 && $repsChange == 0) {
            return new LinearProgression($this->oneRepMaxCalculatorService);
        }

        return null; // Pattern unclear, use fallback logic
    }

    /**
     * Get cardio-specific progression suggestion
     * 
     * Cardio progression logic:
     * - For distances < 1000m: suggest increasing distance by 50-100m
     * - For distances >= 1000m: suggest adding additional rounds
     * - Handle cases with no cardio history (provide sensible defaults)
     */
    private function getCardioProgressionSuggestion(LiftLog $lastLog, int $userId, int $exerciseId): ?object
    {
        $lastDistance = $lastLog->display_reps; // reps field stores distance in meters
        $lastRounds = $lastLog->liftSets->count();
        
        // Validate that we have valid cardio data
        if (!is_numeric($lastDistance) || $lastDistance <= 0) {
            // No valid history, provide sensible defaults
            return $this->getDefaultCardioSuggestion();
        }
        
        // For distances < 1000m: suggest increasing distance by 50-100m
        if ($lastDistance < 1000) {
            $increment = $lastDistance < 500 ? 50 : 100;
            $suggestedDistance = $lastDistance + $increment;
            
            // Cap the distance increase to reasonable limits
            $suggestedDistance = min($suggestedDistance, 1500);
            
            return (object)[
                'sets' => $lastRounds,
                'reps' => $suggestedDistance, // distance stored in reps field
                'weight' => 0, // always 0 for cardio
                'band_color' => null, // not applicable for cardio
            ];
        }
        
        // For distances >= 1000m: suggest adding additional rounds
        $suggestedRounds = $lastRounds + 1;
        
        // Cap the rounds to reasonable limits
        $suggestedRounds = min($suggestedRounds, 10);
        
        return (object)[
            'sets' => $suggestedRounds,
            'reps' => $lastDistance, // keep same distance
            'weight' => 0, // always 0 for cardio
            'band_color' => null, // not applicable for cardio
        ];
    }
    
    /**
     * Provide sensible default cardio suggestions when no history exists
     */
    private function getDefaultCardioSuggestion(): object
    {
        return (object)[
            'sets' => 1, // 1 round
            'reps' => 500, // 500m distance
            'weight' => 0, // always 0 for cardio
            'band_color' => null, // not applicable for cardio
        ];
    }


}