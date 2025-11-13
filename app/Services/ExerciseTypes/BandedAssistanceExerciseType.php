<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;

/**
 * Banded Assistance Exercise Type Strategy
 * 
 * Handles exercises that use assistance bands to reduce difficulty of the movement.
 * Assistance bands provide support throughout the range of motion,
 * making the exercise easier to perform.
 * 
 * Characteristics:
 * - Requires band_color field for all lift logs
 * - Sets weight to 0 (bands don't use traditional weight)
 * - Does not support 1RM calculation (band assistance varies)
 * - Uses band color display formatting with "assistance" indicator
 * - Supports band-specific progression models
 * - Provides band progression suggestions (opposite direction from resistance)
 * 
 * Examples: Assisted pull-ups, assisted dips, assisted pistol squats
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Typical usage for exercises like "Assisted Pull-ups"
 * $strategy = new BandedAssistanceExerciseType();
 * $processedData = $strategy->processLiftData([
 *     'weight' => '50', // Will be set to 0
 *     'band_color' => 'blue',
 *     'reps' => '10'
 * ]);
 * // Result: ['weight' => 0, 'band_color' => 'blue', 'reps' => 10]
 */
class BandedAssistanceExerciseType extends BaseExerciseType
{
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'banded_assistance';
    }
    
    /**
     * Process lift data according to banded assistance exercise rules
     */
    public function processLiftData(array $data): array
    {
        // For banded assistance exercises, set weight to 0 and ensure band_color is set
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
     * Process exercise data according to banded assistance exercise rules
     */
    public function processExerciseData(array $data): array
    {
        $processedData = $data;
        
        // For banded assistance exercises, ensure exercise_type is set correctly
        $processedData['exercise_type'] = 'banded_assistance';
        
        // Banded assistance exercises are not bodyweight exercises
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
     * Format weight display for banded assistance exercises
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        $bandColor = $liftLog->display_weight; // For banded exercises, display_weight returns band_color
        
        if (empty($bandColor) || $bandColor === 'N/A') {
            return 'Band: N/A';
        }
        
        // Capitalize the band color for display with assistance indicator
        return 'Band: ' . ucfirst($bandColor) . ' assistance';
    }
    
    /**
     * Format progression suggestion for banded assistance exercises
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
            // For assistance bands, progression means less assistance (lower order)
            $nextBand = $this->getNextBand($bandColor);
            if ($nextBand) {
                return "Try {$nextBand} band with {$defaultReps} reps";
            } else {
                // If no lighter band available, suggest trying without assistance
                return "Try without assistance band";
            }
        }
        
        return null;
    }
    
    /**
     * Format table cell display for banded assistance exercises
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
     * Get exercise type display name and icon for banded assistance exercises
     */
    public function getTypeDisplayInfo(): array
    {
        return [
            'icon' => 'fas fa-circle',
            'name' => 'Assistance Band'
        ];
    }
    
    /**
     * Format mobile summary display for banded assistance exercises
     * Shows band color with assistance indicator instead of weight
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
     * Format success message description for banded assistance exercises
     * Shows band color with assistance indicator instead of weight
     */
    public function formatSuccessMessageDescription(?float $weight, int $reps, int $rounds, ?string $bandColor = null): string
    {
        $bandDisplay = $bandColor ? ucfirst($bandColor) . ' band' : 'Band';
        return $bandDisplay . ' × ' . $reps . ' reps × ' . $rounds . ' sets';
    }
    
    /**
     * Get progression suggestion for banded assistance exercises
     * Implements band-specific progression logic (less assistance = harder)
     */
    public function getProgressionSuggestion(\App\Models\LiftLog $lastLog, int $userId, int $exerciseId, ?\Carbon\Carbon $forDate = null): ?object
    {
        $lastLoggedReps = $lastLog->liftSets->first()->reps ?? 0;
        $lastLoggedBandColor = $lastLog->liftSets->first()->band_color ?? null;

        $maxRepsBeforeBandChange = config('bands.max_reps_before_band_change', 15);
        $defaultRepsOnBandChange = config('bands.default_reps_on_band_change', 8);

        // If reps are below threshold, suggest increasing reps
        if ($lastLoggedReps < $maxRepsBeforeBandChange) {
            $suggestedReps = min($lastLoggedReps + 1, $maxRepsBeforeBandChange);
            return (object)[
                'sets' => $lastLog->liftSets->count(),
                'reps' => $suggestedReps,
                'band_color' => $lastLoggedBandColor,
            ];
        }
        
        // If reps are at threshold, suggest progressing to lighter band (less assistance)
        $bandService = app(\App\Services\BandService::class);
        $nextLighterBand = $bandService->getNextHarderBand($lastLoggedBandColor, 'assistance');
        
        if ($nextLighterBand) {
            return (object)[
                'sets' => $lastLog->liftSets->count(),
                'reps' => $defaultRepsOnBandChange,
                'band_color' => $nextLighterBand,
            ];
        }
        
        // If no lighter band available (already at no assistance), suggest bodyweight
        return (object)[
            'sets' => $lastLog->liftSets->count(),
            'reps' => $defaultRepsOnBandChange,
            'band_color' => null, // null means no assistance = bodyweight
        ];
    }
    
    /**
     * Get the next band in progression order (less assistance - lower order)
     */
    private function getNextBand(string $currentBand): ?string
    {
        $bandConfig = config('bands.colors', []);
        $currentOrder = $bandConfig[$currentBand]['order'] ?? 0;
        
        // For assistance bands, progression means less assistance (lower order number)
        foreach ($bandConfig as $color => $config) {
            if ($config['order'] === $currentOrder - 1) {
                return $color;
            }
        }
        
        return null;
    }
    
    /**
     * Format suggestion text for banded assistance exercises
     */
    public function formatSuggestionText(object $suggestion): ?string
    {
        if (!isset($suggestion->band_color) || !isset($suggestion->reps)) {
            return null;
        }
        
        $sets = $suggestion->sets ?? 3;
        return 'Suggested: ' . ucfirst($suggestion->band_color) . ' band × ' . $suggestion->reps . ' reps × ' . $sets . ' sets';
    }
}