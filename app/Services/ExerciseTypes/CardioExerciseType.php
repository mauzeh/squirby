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
 * - Weight is always 0 (no weight resistance)
 * - Reps field represents distance in meters
 * - Sets field represents rounds/intervals
 * - Nullifies band_color field (incompatible with cardio exercises)
 * - Does not support 1RM calculation
 * - Uses cardio-specific display formatting
 * - Supports cardio-specific progression models
 * 
 * Distance Validation:
 * - Minimum distance: 50 meters
 * - Maximum distance: 50,000 meters (50km)
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Typical usage for exercises like "Running", "Cycling", "Rowing"
 * $strategy = new CardioExerciseType();
 * $processedData = $strategy->processLiftData([
 *     'reps' => '500', // Distance in meters
 *     'sets' => '7',   // Rounds
 *     'weight' => '25', // Will be forced to 0
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
     * Minimum allowed distance in meters
     */
    public const MIN_DISTANCE = 50;
    
    /**
     * Maximum allowed distance in meters (50km)
     */
    public const MAX_DISTANCE = 50000;
    
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'cardio';
    }
    
    /**
     * Get validation rules for cardio exercises
     */
    public function getValidationRules(?User $user = null): array
    {
        $rules = parent::getValidationRules($user);
        
        // Override with cardio-specific validation
        $rules['reps'] = 'required|integer|min:' . self::MIN_DISTANCE . '|max:' . self::MAX_DISTANCE;
        $rules['weight'] = 'nullable|numeric|in:0';
        $rules['sets'] = 'required|integer|min:1|max:20';
        
        return $rules;
    }
    
    /**
     * Process lift data according to cardio exercise rules
     */
    public function processLiftData(array $data): array
    {
        $processedData = $data;
        
        // Force weight to 0 for cardio exercises
        $processedData['weight'] = 0;
        
        // Nullify band_color for cardio exercises
        $processedData['band_color'] = null;
        
        // Validate distance (reps field)
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
        
        // Ensure cardio exercises are not marked as bodyweight or banded
        $processedData['is_bodyweight'] = false;
        $processedData['band_type'] = null;
        
        return $processedData;
    }
    
    /**
     * Format weight display for cardio exercises
     * Shows distance instead of weight
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        return $this->formatDistance($liftLog->display_reps);
    }
    
    /**
     * Format complete cardio display (e.g., "500m × 7 rounds")
     */
    public function formatCompleteDisplay(LiftLog $liftLog): string
    {
        $distance = $this->formatDistance($liftLog->display_reps);
        $rounds = $liftLog->display_rounds;
        
        // Handle edge case where rounds might be 0 or null
        if (!is_numeric($rounds) || $rounds <= 0) {
            return $distance;
        }
        
        return "{$distance} × {$rounds} " . ($rounds == 1 ? 'round' : 'rounds');
    }
    
    /**
     * Format distance with appropriate units and handle edge cases
     */
    private function formatDistance(mixed $distance): string
    {
        // Handle edge cases for invalid or missing distance
        if (!is_numeric($distance) || $distance <= 0) {
            return '0m';
        }
        
        $distance = (int) $distance;
        
        // Handle edge case for very short distances (< minimum)
        if ($distance < self::MIN_DISTANCE) {
            return number_format($distance, 0) . 'm (below minimum)';
        }
        
        // Handle edge case for very long distances (> maximum)
        if ($distance > self::MAX_DISTANCE) {
            return number_format($distance, 0) . 'm (exceeds maximum)';
        }
        
        // Format distance with appropriate units
        if ($distance >= 1000) {
            // Show in kilometers for distances >= 1km
            $km = $distance / 1000;
            
            // Handle common distances that should be shown as whole kilometers
            if ($km == floor($km)) {
                return number_format($km, 0) . 'km';
            } else {
                // Show one decimal place for fractional kilometers
                return number_format($km, 1) . 'km';
            }
        } else {
            // Show in meters for distances < 1km
            return number_format($distance, 0) . 'm';
        }
    }
    
    /**
     * Format 1RM display for cardio exercises
     * Cardio exercises do not support 1RM calculation
     */
    public function format1RMDisplay(LiftLog $liftLog): string
    {
        // Cardio exercises don't support 1RM - this should not be called
        // but we'll return empty string for safety
        return '';
    }
    
    /**
     * Format progression suggestion for cardio exercises
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        $distance = $liftLog->display_reps; // reps field contains distance
        $rounds = $liftLog->liftSets->count();
        
        if (!is_numeric($distance) || $distance <= 0) {
            return null;
        }
        
        // For moderate distances (< 1000m), suggest increasing distance
        if ($distance < 1000) {
            $increment = $distance < 500 ? 50 : 100;
            $newDistance = $distance + $increment;
            $formattedDistance = $this->formatDistance($newDistance);
            return "Try {$formattedDistance} × {$rounds} " . ($rounds == 1 ? 'round' : 'rounds');
        } else {
            // For longer distances (>= 1000m), suggest adding rounds
            $newRounds = $rounds + 1;
            $formattedDistance = $this->formatDistance($distance);
            return "Try {$formattedDistance} × {$newRounds} " . ($newRounds == 1 ? 'round' : 'rounds');
        }
    }
}