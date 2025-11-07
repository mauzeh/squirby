<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;

/**
 * Static Hold Exercise Type Strategy
 * 
 * Handles static hold exercises commonly used in gymnastics and calisthenics training.
 * Examples include L-sits, planches, front levers, handstands, and hollow body holds.
 * 
 * Characteristics:
 * - Reps field stores hold duration in seconds (1-300 seconds / 5 minutes max)
 * - Weight field stores optional added weight (weighted vests, etc.)
 * - Sets field stores number of holds performed
 * - Does not support 1RM calculation (not applicable to isometric holds)
 * - Uses duration-based display formatting
 * - Progression focuses on increasing duration or adding weight
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Typical usage for exercises like "L-sit", "Planche Hold", "Front Lever"
 * $strategy = new StaticHoldExerciseType();
 * $processedData = $strategy->processLiftData([
 *     'reps' => '30',      // Hold duration in seconds
 *     'sets' => '3',       // Number of holds
 *     'weight' => '0',     // No added weight (bodyweight only)
 *     'band_color' => 'red' // Will be nullified
 * ]);
 * // Result: ['reps' => 30, 'sets' => 3, 'weight' => 0, 'band_color' => null]
 * 
 * @example
 * // Display formatting
 * $display = $strategy->formatWeightDisplay($liftLog);
 * // Result: "30s hold" or "30s hold +25 lbs" (if weighted)
 */
class StaticHoldExerciseType extends BaseExerciseType
{
    /**
     * Minimum hold duration in seconds (1 second)
     */
    private const MIN_DURATION = 1;
    
    /**
     * Maximum hold duration in seconds (5 minutes = 300 seconds)
     */
    private const MAX_DURATION = 300;
    
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'static_hold';
    }
    
    /**
     * Process lift data according to static hold exercise rules
     * 
     * For static hold exercises:
     * - Reps field represents hold duration in seconds and must be validated
     * - Weight field is optional (0 for bodyweight, or added weight)
     * - Band color is always nullified (not applicable)
     * - Duration must be between 1 second and 5 minutes
     */
    public function processLiftData(array $data): array
    {
        $processedData = $data;
        
        // Nullify band_color for static hold exercises
        $processedData['band_color'] = null;
        
        // Validate duration (stored in reps field)
        if (!isset($processedData['reps'])) {
            throw InvalidExerciseDataException::missingField('reps', $this->getTypeName());
        }
        
        if (!is_numeric($processedData['reps'])) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'hold duration must be a number');
        }
        
        $duration = (int) $processedData['reps'];
        
        if ($duration < self::MIN_DURATION) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'hold duration must be at least ' . self::MIN_DURATION . ' second');
        }
        
        if ($duration > self::MAX_DURATION) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'hold duration cannot exceed ' . self::MAX_DURATION . ' seconds');
        }
        
        $processedData['reps'] = $duration;
        
        // Validate weight if provided
        if (isset($processedData['weight'])) {
            if (!is_numeric($processedData['weight'])) {
                throw InvalidExerciseDataException::invalidWeight($processedData['weight'], $this->getTypeName());
            }
            
            if ($processedData['weight'] < 0) {
                throw InvalidExerciseDataException::forField('weight', $this->getTypeName(), 'weight cannot be negative');
            }
        } else {
            $processedData['weight'] = 0;
        }
        
        return $processedData;
    }
    
    /**
     * Process exercise data according to static hold exercise rules
     */
    public function processExerciseData(array $data): array
    {
        $processedData = $data;
        
        // For static hold exercises, ensure exercise_type is set correctly
        $processedData['exercise_type'] = 'static_hold';
        
        return $processedData;
    }
    
    /**
     * Format weight display for static hold exercises
     * 
     * For static hold exercises, we display duration and optional weight.
     * The duration is stored in the reps field.
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        $duration = $liftLog->display_reps;
        $weight = $liftLog->display_weight;
        
        if (!is_numeric($duration) || $duration <= 0) {
            return '0s hold';
        }
        
        $durationDisplay = $this->formatDuration($duration);
        
        // If there's added weight, include it in the display
        if (is_numeric($weight) && $weight > 0) {
            $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
            return "{$durationDisplay} +{$weightFormatted} lbs";
        }
        
        return $durationDisplay;
    }
    
    /**
     * Format duration in seconds to a readable format
     * 
     * @param int $seconds Duration in seconds
     * @return string Formatted duration (e.g., "30s hold", "1m 30s hold")
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s hold";
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($remainingSeconds === 0) {
            return "{$minutes}m hold";
        }
        
        return "{$minutes}m {$remainingSeconds}s hold";
    }
    
    /**
     * Format complete static hold display showing duration, weight, and sets
     * 
     * Returns a formatted string like "30s hold × 3 sets" or "30s hold +25 lbs × 3 sets"
     */
    public function formatCompleteDisplay(LiftLog $liftLog): string
    {
        $duration = $liftLog->display_reps;
        $weight = $liftLog->display_weight;
        $sets = $liftLog->display_rounds;
        
        if (!is_numeric($duration) || $duration <= 0) {
            $duration = 0;
        }
        
        if (!is_numeric($sets) || $sets <= 0) {
            $sets = 1;
        }
        
        $durationDisplay = $this->formatDuration($duration);
        
        // Add weight if present
        if (is_numeric($weight) && $weight > 0) {
            $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
            $durationDisplay .= " +{$weightFormatted} lbs";
        }
        
        $setsText = $sets == 1 ? 'set' : 'sets';
        
        return "{$durationDisplay} × {$sets} {$setsText}";
    }
    
    /**
     * Format progression suggestion for static hold exercises
     * 
     * Static hold progression logic:
     * - For durations < 60s: suggest increasing duration by 1-2s (very conservative for difficult holds)
     * - For durations >= 60s: suggest adding weight or additional sets
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        $duration = $liftLog->display_reps;
        $weight = $liftLog->display_weight;
        $sets = $liftLog->liftSets->count();
        
        if (!is_numeric($duration) || $duration <= 0) {
            return null;
        }
        
        // For holds under 60 seconds, suggest small duration increases
        if ($duration < 60) {
            // Very conservative progression: 1-2 seconds
            $increment = $duration < 30 ? 1 : 2;
            $newDuration = $duration + $increment;
            $newDurationDisplay = $this->formatDuration($newDuration);
            return "Try {$newDurationDisplay} × {$sets} sets";
        }
        
        // For longer holds (60s+), suggest adding weight or sets
        if (!is_numeric($weight) || $weight == 0) {
            // Suggest adding weight
            $durationDisplay = $this->formatDuration($duration);
            return "Try {$durationDisplay} +5 lbs × {$sets} sets";
        } else {
            // Suggest adding more sets
            $newSets = $sets + 1;
            $durationDisplay = $this->formatDuration($duration);
            $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
            return "Try {$durationDisplay} +{$weightFormatted} lbs × {$newSets} sets";
        }
    }
    
    /**
     * Get form field definitions for static hold exercises
     * Static hold exercises show duration (reps) and optional weight
     */
    public function getFormFieldDefinitions(array $defaults = [], ?User $user = null): array
    {
        $labels = $this->getFieldLabels();
        $increments = $this->getFieldIncrements();
        
        return [
            [
                'name' => 'reps',
                'label' => $labels['reps'],
                'type' => 'numeric',
                'defaultValue' => $defaults['reps'] ?? 30,
                'increment' => $increments['reps'],
                'min' => self::MIN_DURATION,
                'max' => self::MAX_DURATION,
            ],
            [
                'name' => 'weight',
                'label' => $labels['weight'],
                'type' => 'numeric',
                'defaultValue' => $defaults['weight'] ?? 0,
                'increment' => $increments['weight'],
                'min' => 0,
                'max' => 500,
            ]
        ];
    }
    
    /**
     * Format logged item display message for static hold exercises
     * Uses static hold-appropriate terminology (duration × sets)
     */
    public function formatLoggedItemDisplay(LiftLog $liftLog): string
    {
        return $this->formatCompleteDisplay($liftLog);
    }
    
    /**
     * Format form message display for static hold exercises
     * Uses static hold-appropriate terminology (duration × sets)
     */
    public function formatFormMessageDisplay(array $lastSession): string
    {
        $duration = $lastSession['reps'] ?? 0;
        $weight = $lastSession['weight'] ?? 0;
        $sets = $lastSession['sets'] ?? 1;
        
        // Format duration
        if (!is_numeric($duration) || $duration <= 0) {
            $durationDisplay = '0s hold';
        } else {
            $durationDisplay = $this->formatDuration($duration);
        }
        
        // Add weight if present
        if (is_numeric($weight) && $weight > 0) {
            $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
            $durationDisplay .= " +{$weightFormatted} lbs";
        }
        
        $setsText = $sets == 1 ? 'set' : 'sets';
        
        return "{$durationDisplay} × {$sets} {$setsText}";
    }
    
    /**
     * Format table cell display for static hold exercises
     * Returns the complete display as primary text with duration/sets breakdown
     */
    public function formatTableCellDisplay(LiftLog $liftLog): array
    {
        $duration = $liftLog->display_reps;
        $weight = $liftLog->display_weight;
        $sets = $liftLog->display_rounds;
        
        $durationDisplay = $this->formatDuration($duration);
        
        // Add weight if present
        if (is_numeric($weight) && $weight > 0) {
            $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
            $durationDisplay .= " +{$weightFormatted} lbs";
        }
        
        $setsText = "{$sets} " . ($sets == 1 ? 'set' : 'sets');
        
        return [
            'primary' => $durationDisplay,
            'secondary' => $setsText
        ];
    }
    
    /**
     * Format 1RM table cell display for static hold exercises
     * Static hold exercises don't support 1RM calculation
     */
    public function format1RMTableCellDisplay(LiftLog $liftLog): string
    {
        return 'N/A (Static Hold)';
    }
    
    /**
     * Get exercise type display name and icon for static hold exercises
     */
    public function getTypeDisplayInfo(): array
    {
        return [
            'icon' => 'fas fa-hand-paper',
            'name' => 'Static Hold'
        ];
    }
    
    /**
     * Get chart title for static hold exercises
     */
    public function getChartTitle(): string
    {
        return 'Hold Duration Progress';
    }
    
    /**
     * Format mobile summary display for static hold exercises
     * Static hold exercises show duration and sets
     */
    public function formatMobileSummaryDisplay(LiftLog $liftLog): array
    {
        $duration = $liftLog->display_reps;
        $weight = $liftLog->display_weight;
        $sets = $liftLog->display_rounds;
        
        $durationDisplay = $this->formatDuration($duration);
        
        // Add weight if present
        if (is_numeric($weight) && $weight > 0) {
            $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
            $durationDisplay .= " +{$weightFormatted} lbs";
        }
        
        $setsText = "{$sets} " . ($sets == 1 ? 'set' : 'sets');
        
        return [
            'weight' => $durationDisplay,
            'repsSets' => $setsText,
            'showWeight' => true
        ];
    }
    
    /**
     * Format success message description for static hold exercises
     * Uses duration and sets terminology instead of weight/reps/sets
     */
    public function formatSuccessMessageDescription(?float $weight, int $reps, int $rounds, ?string $bandColor = null): string
    {
        // For static holds, reps represents duration in seconds
        $duration = $reps;
        
        $durationDisplay = $this->formatDuration($duration);
        
        // Add weight if present
        if (is_numeric($weight) && $weight > 0) {
            $weightFormatted = $weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1);
            $durationDisplay .= " +{$weightFormatted} lbs";
        }
        
        $setsText = $rounds == 1 ? 'set' : 'sets';
        
        return "{$durationDisplay} × {$rounds} {$setsText}";
    }
    
    /**
     * Get progression suggestion for static hold exercises
     * Implements duration/weight-based progression logic
     */
    public function getProgressionSuggestion(\App\Models\LiftLog $lastLog, int $userId, int $exerciseId, ?\Carbon\Carbon $forDate = null): ?object
    {
        $lastDuration = $lastLog->display_reps; // reps field stores duration in seconds
        $lastWeight = $lastLog->display_weight;
        $lastSets = $lastLog->liftSets->count();
        
        // Validate that we have valid static hold data
        if (!is_numeric($lastDuration) || $lastDuration <= 0) {
            // No valid history, provide sensible defaults
            return $this->getDefaultStaticHoldSuggestion();
        }
        
        // For holds under 60 seconds, suggest small duration increases
        // Don't add weight until they can hold for 60 seconds
        if ($lastDuration < 60) {
            // Very conservative progression: 1-2 seconds
            $increment = $lastDuration < 30 ? 1 : 2;
            $suggestedDuration = min($lastDuration + $increment, self::MAX_DURATION);
            
            return (object)[
                'sets' => $lastSets,
                'reps' => $suggestedDuration, // duration stored in reps field
                'weight' => 0, // No weight until 60s hold
                'band_color' => null, // not applicable for static holds
            ];
        }
        
        // For durations >= 60s: suggest adding weight or sets
        if (!is_numeric($lastWeight) || $lastWeight == 0) {
            // Suggest adding weight
            return (object)[
                'sets' => $lastSets,
                'reps' => $lastDuration,
                'weight' => 5, // Start with 5 lbs
                'band_color' => null,
            ];
        } else {
            // Suggest adding more sets
            $suggestedSets = min($lastSets + 1, 10);
            
            return (object)[
                'sets' => $suggestedSets,
                'reps' => $lastDuration,
                'weight' => $lastWeight,
                'band_color' => null,
            ];
        }
    }
    
    /**
     * Provide sensible default static hold suggestions when no history exists
     */
    private function getDefaultStaticHoldSuggestion(): object
    {
        return (object)[
            'sets' => 3, // 3 sets
            'reps' => 30, // 30 seconds duration
            'weight' => 0, // bodyweight only
            'band_color' => null, // not applicable for static holds
        ];
    }
}
