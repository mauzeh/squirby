<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;

class BodyweightExerciseType extends BaseExerciseType
{
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'bodyweight';
    }
    
    /**
     * Process lift data according to bodyweight exercise rules
     */
    public function processLiftData(array $data): array
    {
        // For bodyweight exercises, weight represents extra weight added
        $processedData = $data;
        
        // Ensure weight is numeric (can be 0 for bodyweight only)
        if (!isset($processedData['weight']) || !is_numeric($processedData['weight'])) {
            $processedData['weight'] = 0;
        }
        
        // Nullify band_color for bodyweight exercises
        $processedData['band_color'] = null;
        
        return $processedData;
    }
    
    /**
     * Format weight display for bodyweight exercises
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        $extraWeight = $liftLog->display_weight;
        
        if (!is_numeric($extraWeight) || $extraWeight <= 0) {
            return 'Bodyweight';
        }
        
        $precision = config('exercise_types.display.precision', 1);
        $unit = config('exercise_types.display.weight_unit', 'lbs');
        
        return 'Bodyweight +' . number_format($extraWeight, $precision) . ' ' . $unit;
    }
    
    /**
     * Format 1RM display for bodyweight exercises
     * Includes bodyweight in the calculation
     */
    public function format1RMDisplay(LiftLog $liftLog): string
    {
        if (!$this->canCalculate1RM()) {
            return '';
        }
        
        // For bodyweight exercises, we need to add the user's bodyweight to the extra weight
        // This would require access to the user's current bodyweight
        // For now, we'll use the standard 1RM calculation and note it's extra weight only
        $oneRepMax = $liftLog->one_rep_max;
        
        if ($oneRepMax <= 0) {
            return '';
        }
        
        $precision = config('exercise_types.display.precision', 1);
        $unit = config('exercise_types.display.weight_unit', 'lbs');
        
        // If there's extra weight, show it as "BW + X lbs (1RM)"
        if ($oneRepMax > 0) {
            return 'BW +' . number_format($oneRepMax, $precision) . ' ' . $unit . ' (1RM)';
        }
        
        return 'Bodyweight (1RM)';
    }
    
    /**
     * Format progression suggestion for bodyweight exercises
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        $extraWeight = $liftLog->display_weight;
        $reps = $liftLog->display_reps;
        
        if (!is_numeric($reps)) {
            return null;
        }
        
        // Suggest adding weight if reps are high
        if ($reps >= 12 && (!is_numeric($extraWeight) || $extraWeight <= 0)) {
            return "Consider adding 5-10 lbs extra weight";
        } elseif ($reps >= 15 && is_numeric($extraWeight) && $extraWeight > 0) {
            $nextWeight = $extraWeight + 5;
            return "Try {$nextWeight} lbs extra weight";
        }
        
        return null;
    }
}