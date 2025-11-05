<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;

class RegularExerciseType extends BaseExerciseType
{
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'regular';
    }
    
    /**
     * Process lift data according to regular exercise rules
     */
    public function processLiftData(array $data): array
    {
        // For regular exercises, ensure weight is set and band_color is null
        $processedData = $data;
        
        // Ensure weight is present and numeric
        if (!isset($processedData['weight']) || !is_numeric($processedData['weight'])) {
            $processedData['weight'] = 0;
        }
        
        // Nullify band_color for regular exercises
        $processedData['band_color'] = null;
        
        return $processedData;
    }
    
    /**
     * Process exercise data according to regular exercise rules
     */
    public function processExerciseData(array $data): array
    {
        $processedData = $data;
        
        // For regular exercises, ensure both is_bodyweight and band_type are properly set
        if (!isset($processedData['is_bodyweight']) || !$processedData['is_bodyweight']) {
            $processedData['is_bodyweight'] = false;
        }
        
        if (!isset($processedData['band_type']) || empty($processedData['band_type'])) {
            $processedData['band_type'] = null;
        }
        
        return $processedData;
    }
    
    /**
     * Format weight display for regular exercises
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        $weight = $liftLog->display_weight;
        
        if (!is_numeric($weight) || $weight <= 0) {
            return '0 lbs';
        }
        
        $precision = config('exercise_types.display.precision', 1);
        $unit = config('exercise_types.display.weight_unit', 'lbs');
        
        return number_format($weight, $precision) . ' ' . $unit;
    }
}