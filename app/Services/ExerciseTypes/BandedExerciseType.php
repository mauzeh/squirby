<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;

/**
 * Banded Exercise Type Strategy
 * 
 * Handles exercises that use resistance or assistance bands instead of traditional weights.
 * Supports both resistance bands (add difficulty) and assistance bands (reduce difficulty).
 * 
 * Characteristics:
 * - Requires band_color field for all lift logs
 * - Sets weight to 0 (bands don't use traditional weight)
 * - Does not support 1RM calculation (band resistance varies)
 * - Uses band color display formatting
 * - Supports volume and band-specific progression models
 * - Provides band progression suggestions
 * 
 * Band Types:
 * - Resistance: Adds difficulty (e.g., banded squats)
 * - Assistance: Reduces difficulty (e.g., assisted pull-ups)
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Typical usage for exercises like "Banded Squats", "Assisted Pull-ups"
 * $strategy = new BandedExerciseType();
 * $processedData = $strategy->processLiftData([
 *     'weight' => '50', // Will be set to 0
 *     'band_color' => 'blue',
 *     'reps' => '10'
 * ]);
 * // Result: ['weight' => 0, 'band_color' => 'blue', 'reps' => 10]
 */
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
        
        // Validate band_color is present
        if (!isset($processedData['band_color']) || empty($processedData['band_color'])) {
            throw InvalidExerciseDataException::missingField('band_color', $this->getTypeName());
        }
        
        // Validate band_color is valid
        $availableBands = array_keys(config('bands.colors', []));
        if (!in_array($processedData['band_color'], $availableBands)) {
            throw InvalidExerciseDataException::invalidBandColor($processedData['band_color']);
        }
        
        return $processedData;
    }
    
    /**
     * Process exercise data according to banded exercise rules
     */
    public function processExerciseData(array $data): array
    {
        $processedData = $data;
        
        // If a band type is selected, it cannot be a bodyweight exercise
        if (isset($processedData['band_type']) && !empty($processedData['band_type'])) {
            $processedData['is_bodyweight'] = false;
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