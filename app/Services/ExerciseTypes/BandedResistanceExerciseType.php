<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;

/**
 * Banded Resistance Exercise Type Strategy
 * 
 * Handles exercises that use resistance bands to add difficulty to the movement.
 * Resistance bands increase the resistance throughout the range of motion,
 * making the exercise more challenging.
 * 
 * Characteristics:
 * - Requires band_color field for all lift logs
 * - Sets weight to 0 (bands don't use traditional weight)
 * - Does not support 1RM calculation (band resistance varies)
 * - Uses band color display formatting
 * - Supports band-specific progression models
 * - Provides band progression suggestions
 * 
 * Examples: Banded squats, banded deadlifts, banded bench press
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Typical usage for exercises like "Banded Squats"
 * $strategy = new BandedResistanceExerciseType();
 * $processedData = $strategy->processLiftData([
 *     'weight' => '50', // Will be set to 0
 *     'band_color' => 'blue',
 *     'reps' => '10'
 * ]);
 * // Result: ['weight' => 0, 'band_color' => 'blue', 'reps' => 10]
 */
class BandedResistanceExerciseType extends BaseExerciseType
{
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'banded_resistance';
    }
    
    /**
     * Process lift data according to banded resistance exercise rules
     */
    public function processLiftData(array $data): array
    {
        // For banded resistance exercises, set weight to 0 and ensure band_color is set
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
     * Process exercise data according to banded resistance exercise rules
     */
    public function processExerciseData(array $data): array
    {
        $processedData = $data;
        
        // For banded resistance exercises, ensure exercise_type is set correctly
        $processedData['exercise_type'] = 'banded_resistance';
        
        // Banded resistance exercises are not bodyweight exercises
        $processedData['is_bodyweight'] = false;
        
        return $processedData;
    }
    
    /**
     * Get raw display weight value from a lift set
     * For banded exercises, returns the band_color field
     */
    public function getRawDisplayWeight($liftSet)
    {
        return $liftSet->band_color ?? 'N/A';
    }
    
    /**
     * Format weight display for banded resistance exercises
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
     * Format progression suggestion for banded resistance exercises
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
            // Suggest progression to next band (higher resistance)
            $nextBand = $this->getNextBand($bandColor);
            if ($nextBand) {
                return "Try {$nextBand} band with {$defaultReps} reps";
            }
        }
        
        return null;
    }
    
    /**
     * Get form field definitions for banded resistance exercises
     * Ensures band_color field comes first, then reps
     */
    public function getFormFieldDefinitions(array $defaults = [], ?User $user = null): array
    {
        $labels = $this->getFieldLabels();
        $increments = $this->getFieldIncrements();
        
        return [
            [
                'name' => 'band_color',
                'label' => $labels['band_color'],
                'type' => 'select',
                'defaultValue' => $defaults['band_color'] ?? 'red',
                'options' => $this->getFieldOptions('band_color'),
            ],
            [
                'name' => 'reps',
                'label' => $labels['reps'],
                'type' => 'numeric',
                'defaultValue' => $defaults['reps'] ?? 5,
                'increment' => $increments['reps'],
                'min' => 1,
                'max' => 100,
            ]
        ];
    }
    
    /**
     * Format table cell display for banded resistance exercises
     * Returns array with primary and secondary text
     */
    public function formatTableCellDisplay(LiftLog $liftLog): array
    {
        $bandDisplay = $this->formatWeightDisplay($liftLog);
        $repsText = $liftLog->display_reps . ' x ' . $liftLog->display_rounds;
        
        return [
            'primary' => $bandDisplay,
            'secondary' => $repsText
        ];
    }
    
    /**
     * Get exercise type display name and icon for banded resistance exercises
     */
    public function getTypeDisplayInfo(): array
    {
        return [
            'icon' => 'fas fa-circle',
            'name' => 'Resistance Band'
        ];
    }
    
    /**
     * Format mobile summary display for banded resistance exercises
     * Shows band color instead of weight
     */
    public function formatMobileSummaryDisplay(LiftLog $liftLog): array
    {
        $weight = $this->formatWeightDisplay($liftLog);
        $repsSets = $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
        
        return [
            'weight' => $weight,
            'repsSets' => $repsSets,
            'showWeight' => true
        ];
    }
    
    /**
     * Format success message description for banded resistance exercises
     * Shows band color instead of weight
     */
    public function formatSuccessMessageDescription(?float $weight, int $reps, int $rounds, ?string $bandColor = null): string
    {
        $bandDisplay = $bandColor ? ucfirst($bandColor) . ' band' : 'Band';
        return $bandDisplay . ' × ' . $reps . ' reps × ' . $rounds . ' sets';
    }
    
    /**
     * Get the next band in progression order (higher resistance)
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