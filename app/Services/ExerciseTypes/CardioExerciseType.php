<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;

/**
 * Cardio Exercise Type Strategy
 * 
 * Handles distance-based cardiovascular exercises like running, cycling, and rowing.
 * Uses the existing database schema by mapping reps to distance (in meters) and
 * sets to rounds, while always setting weight to 0.
 * 
 * Characteristics:
 * - Weight is always 0 (forced)
 * - Band color is always null (not applicable)
 * - Reps field represents distance in meters
 * - Sets field represents rounds/intervals
 * - Does not support 1RM calculation
 * - Uses cardio-specific display formatting
 * - Validates distance within reasonable bounds (50m - 50km)
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Typical usage for exercises like "Run", "Cycle", "Row"
 * $strategy = new CardioExerciseType();
 * $processedData = $strategy->processLiftData([
 *     'reps' => '500',     // Distance in meters
 *     'sets' => '7',       // Number of rounds
 *     'weight' => '25',    // Will be forced to 0
 *     'band_color' => 'red' // Will be nullified
 * ]);
 * // Result: ['reps' => 500, 'sets' => 7, 'weight' => 0, 'band_color' => null]
 * 
 * @example
 * // Display formatting
 * $display = $strategy->formatWeightDisplay($liftLog);
 * // Result: "500m"
 */
class CardioExerciseType extends BaseExerciseType
{
    /**
     * Minimum distance in meters (50m)
     */
    private const MIN_DISTANCE = 50;
    
    /**
     * Maximum distance in meters (50km = 50,000m)
     */
    private const MAX_DISTANCE = 50000;
    
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'cardio';
    }
    
    /**
     * Process lift data according to cardio exercise rules
     * 
     * For cardio exercises:
     * - Weight is always forced to 0
     * - Band color is always nullified
     * - Reps field represents distance in meters and must be validated
     * - Distance must be between 50m and 50km
     */
    public function processLiftData(array $data): array
    {
        $processedData = $data;
        
        // Force weight to 0 for cardio exercises
        $processedData['weight'] = 0;
        
        // Nullify band_color for cardio exercises
        $processedData['band_color'] = null;
        
        // Validate distance (stored in reps field)
        if (!isset($processedData['reps'])) {
            throw InvalidExerciseDataException::missingField('reps', $this->getTypeName());
        }
        
        if (!is_numeric($processedData['reps'])) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'distance must be a number');
        }
        
        $distance = (int) $processedData['reps'];
        
        if ($distance < self::MIN_DISTANCE) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'distance must be at least ' . self::MIN_DISTANCE . ' meters');
        }
        
        if ($distance > self::MAX_DISTANCE) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'distance cannot exceed ' . self::MAX_DISTANCE . ' meters');
        }
        
        $processedData['reps'] = $distance;
        
        return $processedData;
    }
    
    /**
     * Process exercise data according to cardio exercise rules
     */
    public function processExerciseData(array $data): array
    {
        $processedData = $data;
        
        // For cardio exercises, ensure exercise_type is set correctly
        $processedData['exercise_type'] = 'cardio';
        
        // Cardio exercises are not bodyweight exercises and don't use bands
        $processedData['is_bodyweight'] = false;
        
        return $processedData;
    }
    
    /**
     * Format weight display for cardio exercises
     * 
     * For cardio exercises, we display distance instead of weight.
     * The distance is stored in the reps field.
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        $distance = $liftLog->display_reps;
        
        if (!is_numeric($distance) || $distance <= 0) {
            return '0m';
        }
        
        // Handle edge cases for very short or very long distances
        if ($distance < 100) {
            // For very short distances, show with decimal if needed
            return number_format($distance, 0) . 'm';
        } elseif ($distance >= 10000) {
            // For distances 10km and above, show in km format
            $kilometers = $distance / 1000;
            return number_format($kilometers, 1) . 'km';
        } else {
            // Standard format for distances between 100m and 10km
            return number_format($distance, 0) . 'm';
        }
    }
    
    /**
     * Format complete cardio display showing distance and rounds
     * 
     * Returns a formatted string like "500m × 7 rounds" for cardio exercises.
     * This provides cardio-appropriate terminology instead of weight/reps/sets.
     */
    public function formatCompleteDisplay(LiftLog $liftLog): string
    {
        $distance = $liftLog->display_reps;
        $rounds = $liftLog->display_rounds;
        
        if (!is_numeric($distance) || $distance <= 0) {
            $distance = 0;
        }
        
        if (!is_numeric($rounds) || $rounds <= 0) {
            $rounds = 1;
        }
        
        $distanceDisplay = $this->formatWeightDisplay($liftLog);
        $roundsText = $rounds == 1 ? 'round' : 'rounds';
        
        return "{$distanceDisplay} × {$rounds} {$roundsText}";
    }
    
    /**
     * Format progression suggestion for cardio exercises
     * 
     * Cardio progression logic:
     * - For distances < 1000m: suggest increasing distance by 50-100m
     * - For distances >= 1000m: suggest adding additional rounds
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        $distance = $liftLog->display_reps;
        $rounds = $liftLog->liftSets->count();
        
        if (!is_numeric($distance) || $distance <= 0) {
            return null;
        }
        
        if ($distance < 1000) {
            // For shorter distances, suggest increasing distance
            $increment = $distance < 500 ? 50 : 100;
            $newDistance = $distance + $increment;
            return "Try {$newDistance}m × {$rounds} rounds";
        } else {
            // For longer distances, suggest adding rounds
            $newRounds = $rounds + 1;
            return "Try {$distance}m × {$newRounds} rounds";
        }
    }
}