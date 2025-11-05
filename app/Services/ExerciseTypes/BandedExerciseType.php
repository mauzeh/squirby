<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;

class BandedExerciseType extends BaseExerciseType
{
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'banded';
    }
    
    /**
     * Process lift data according to banded exercise rules
     */
    public function processLiftData(array $data): array
    {
        // For banded exercises, set weight to 0 and ensure band_color is set
        $processedData = $data;
        
        // Set weight to 0 for banded exercises
        $processedData['weight'] = 0;
        
        // Ensure band_color is present
        if (!isset($processedData['band_color']) || empty($processedData['band_color'])) {
            // Default to first available band color if not specified
            $availableBands = array_keys(config('bands.colors', []));
            $processedData['band_color'] = $availableBands[0] ?? 'red';
        }
        
        return $processedData;
    }
    
    /**
     * Format weight display for banded exercises
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        $bandColor = $liftLog->display_weight; // For banded exercises, display_weight returns band_color
        
        if (empty($bandColor) || $bandColor === 'N/A') {
            return 'Band: N/A';
        }
        
        // Capitalize the band color for display
        return 'Band: ' . ucfirst($bandColor);
    }
    
    /**
     * Format progression suggestion for banded exercises
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        $bandColor = $liftLog->display_weight;
        $reps = $liftLog->display_reps;
        
        if (empty($bandColor) || !is_numeric($reps)) {
            return null;
        }
        
        $maxReps = config('bands.max_reps_before_band_change', 15);
        $defaultReps = config('bands.default_reps_on_band_change', 8);
        
        // Get band order for progression logic
        $bandConfig = config('bands.colors', []);
        $currentBandOrder = $bandConfig[$bandColor]['order'] ?? 0;
        
        if ($reps >= $maxReps) {
            // Suggest progression to next band
            $nextBand = $this->getNextBand($bandColor);
            if ($nextBand) {
                return "Try {$nextBand} band with {$defaultReps} reps";
            }
        }
        
        return null;
    }
    
    /**
     * Get the next band in progression order
     */
    private function getNextBand(string $currentBand): ?string
    {
        $bandConfig = config('bands.colors', []);
        $currentOrder = $bandConfig[$currentBand]['order'] ?? 0;
        
        foreach ($bandConfig as $color => $config) {
            if ($config['order'] === $currentOrder + 1) {
                return $color;
            }
        }
        
        return null;
    }
}