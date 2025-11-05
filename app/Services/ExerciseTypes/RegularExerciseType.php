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
        
        // For regular exercises, ensure is_bodyweight is false
        $processedData['is_bodyweight'] = false;
        
        // For regular exercises, ensure band_type is null
        $processedData['band_type'] = null;
        
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
        
        $unit = config('exercise_types.display.weight_unit', 'lbs');
        
        // Format as whole number if it's a whole number, otherwise show decimal
        $formattedWeight = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
        
        return $formattedWeight . ' ' . $unit;
    }
}