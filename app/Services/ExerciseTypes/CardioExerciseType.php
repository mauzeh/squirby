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
    
    /**
     * Get form field definitions for cardio exercises
     * Cardio exercises only show distance (reps) field, never weight
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
                'defaultValue' => $defaults['reps'] ?? 500,
                'increment' => $increments['reps'],
                'min' => self::MIN_DISTANCE,
                'max' => self::MAX_DISTANCE,
            ]
        ];
    }
    
    /**
     * Format logged item display message for cardio exercises
     * Uses cardio-appropriate terminology (distance × rounds)
     */
    public function formatLoggedItemDisplay(LiftLog $liftLog): string
    {
        return $this->formatCompleteDisplay($liftLog);
    }
    
    /**
     * Format form message display for cardio exercises
     * Uses cardio-appropriate terminology (distance × rounds)
     */
    public function formatFormMessageDisplay(array $lastSession): string
    {
        $distance = $lastSession['reps'] ?? 0;
        $rounds = $lastSession['sets'] ?? 1;
        
        // Format distance directly
        if (!is_numeric($distance) || $distance <= 0) {
            $distanceDisplay = '0m';
        } elseif ($distance < 100) {
            $distanceDisplay = number_format($distance, 0) . 'm';
        } elseif ($distance >= 10000) {
            $kilometers = $distance / 1000;
            $distanceDisplay = number_format($kilometers, 1) . 'km';
        } else {
            $distanceDisplay = number_format($distance, 0) . 'm';
        }
        
        $roundsText = $rounds == 1 ? 'round' : 'rounds';
        
        return "{$distanceDisplay} × {$rounds} {$roundsText}";
    }
    
    /**
     * Format table cell display for cardio exercises
     * Returns the complete cardio display as primary text only
     */
    public function formatTableCellDisplay(LiftLog $liftLog): array
    {
        // For cardio, we show the complete display as the primary text only
        return [
            'primary' => $this->formatCompleteDisplay($liftLog)
        ];
    }
    
    /**
     * Format 1RM table cell display for cardio exercises
     * Cardio exercises don't support 1RM calculation
     */
    public function format1RMTableCellDisplay(LiftLog $liftLog): string
    {
        return 'N/A (Cardio)';
    }
    
    /**
     * Get exercise type display name and icon for cardio exercises
     */
    public function getTypeDisplayInfo(): array
    {
        return [
            'icon' => 'fas fa-running',
            'name' => 'Cardio'
        ];
    }
    
    /**
     * Get chart title for cardio exercises
     */
    public function getChartTitle(): string
    {
        return 'Distance Progress';
    }
    
    /**
     * Format mobile summary display for cardio exercises
     * Cardio exercises don't show weight and use cardio-specific formatting
     */
    public function formatMobileSummaryDisplay(LiftLog $liftLog): array
    {
        return [
            'weight' => '',
            'repsSets' => $this->formatCompleteDisplay($liftLog),
            'showWeight' => false
        ];
    }
    
    /**
     * Format success message description for cardio exercises
     * Uses distance and rounds terminology instead of weight/reps/sets
     */
    public function formatSuccessMessageDescription(?float $weight, int $reps, int $rounds, ?string $bandColor = null): string
    {
        // For cardio, reps represents distance and rounds represents rounds
        $distance = $reps;
        
        // Format distance display
        if ($distance < 100) {
            $distanceDisplay = number_format($distance, 0) . 'm';
        } elseif ($distance >= 10000) {
            $kilometers = $distance / 1000;
            $distanceDisplay = number_format($kilometers, 1) . 'km';
        } else {
            $distanceDisplay = number_format($distance, 0) . 'm';
        }
        
        $roundsText = $rounds == 1 ? 'round' : 'rounds';
        
        return "{$distanceDisplay} × {$rounds} {$roundsText}";
    }
    
    /**
     * Get progression suggestion for cardio exercises
     * Implements distance/rounds-based progression logic
     */
    public function getProgressionSuggestion(\App\Models\LiftLog $lastLog, int $userId, int $exerciseId, ?\Carbon\Carbon $forDate = null): ?object
    {
        $lastDistance = $lastLog->display_reps; // reps field stores distance in meters
        $lastRounds = $lastLog->liftSets->count();
        
        // Validate that we have valid cardio data
        if (!is_numeric($lastDistance) || $lastDistance <= 0) {
            // No valid history, provide sensible defaults
            return $this->getDefaultCardioSuggestion();
        }
        
        // For distances < 1000m: suggest increasing distance by 50-100m
        if ($lastDistance < 1000) {
            $increment = $lastDistance < 500 ? 50 : 100;
            $suggestedDistance = $lastDistance + $increment;
            
            // Cap the distance increase to reasonable limits
            $suggestedDistance = min($suggestedDistance, 1500);
            
            return (object)[
                'sets' => $lastRounds,
                'reps' => $suggestedDistance, // distance stored in reps field
                'weight' => 0, // always 0 for cardio
                'band_color' => null, // not applicable for cardio
            ];
        }
        
        // For distances >= 1000m: suggest adding additional rounds
        $suggestedRounds = $lastRounds + 1;
        
        // Cap the rounds to reasonable limits
        $suggestedRounds = min($suggestedRounds, 10);
        
        return (object)[
            'sets' => $suggestedRounds,
            'reps' => $lastDistance, // keep same distance
            'weight' => 0, // always 0 for cardio
            'band_color' => null, // not applicable for cardio
        ];
    }
    
    /**
     * Provide sensible default cardio suggestions when no history exists
     */
    private function getDefaultCardioSuggestion(): object
    {
        return (object)[
            'sets' => 1, // 1 round
            'reps' => 500, // 500m distance
            'weight' => 0, // always 0 for cardio
            'band_color' => null, // not applicable for cardio
        ];
    }
    
    // ========================================================================
    // PR DETECTION METHODS
    // ========================================================================
    
    /**
     * Get supported PR types for cardio exercises
     * 
     * Cardio exercises support:
     * - ENDURANCE: Longest single distance in one round (best individual round)
     * - REP_SPECIFIC: Best distance at specific round counts (e.g., best 5-round total)
     * - VOLUME: Total distance across all rounds in a session
     */
    public function getSupportedPRTypes(): array
    {
        return [
            \App\Enums\PRType::ENDURANCE,
            \App\Enums\PRType::REP_SPECIFIC,
            \App\Enums\PRType::VOLUME,
        ];
    }
    
    /**
     * Calculate current metrics from a lift log
     * 
     * For cardio exercises:
     * - best_distance: Longest single distance in one round (for ENDURANCE PR)
     * - total_distance: Sum of all distances across all rounds (for VOLUME PR)
     * - round_distances: Map of round count => best total distance for that round count (for REP_SPECIFIC PR)
     */
    public function calculateCurrentMetrics(LiftLog $liftLog): array
    {
        $bestDistance = 0;
        $totalDistance = 0;
        $roundCount = 0;
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->reps > 0) { // reps field stores distance in meters
                $distance = $set->reps;
                
                // Track best single distance (ENDURANCE)
                $bestDistance = max($bestDistance, $distance);
                
                // Track total distance (VOLUME)
                $totalDistance += $distance;
                
                // Count rounds
                $roundCount++;
            }
        }
        
        // For REP_SPECIFIC, we track best total distance at specific round counts
        // Only track up to 10 rounds (similar to how regular exercises track up to 10 reps)
        $roundDistances = [];
        if ($roundCount > 0 && $roundCount <= 10) {
            $roundDistances[$roundCount] = $totalDistance;
        }
        
        return [
            'best_distance' => $bestDistance,
            'total_distance' => $totalDistance,
            'round_distances' => $roundDistances,
        ];
    }
    
    /**
     * Compare current metrics to previous logs and detect PRs
     * 
     * For cardio:
     * - ENDURANCE PR: Longest single distance in one round
     * - VOLUME PR: Total distance across all rounds
     * - REP_SPECIFIC PR: Best total distance at specific round counts
     */
    public function compareToPrevious(array $currentMetrics, \Illuminate\Database\Eloquent\Collection $previousLogs, LiftLog $currentLog): array
    {
        $prs = [];
        $distanceTolerance = 1; // 1 meter tolerance for distance comparisons
        
        // If no previous logs, all non-zero metrics are PRs
        if ($previousLogs->isEmpty()) {
            if ($currentMetrics['best_distance'] > 0) {
                $prs[] = [
                    'type' => 'endurance',
                    'value' => $currentMetrics['best_distance'],
                    'previous_value' => null,
                    'previous_lift_log_id' => null,
                ];
            }
            
            if ($currentMetrics['total_distance'] > 0) {
                $prs[] = [
                    'type' => 'volume',
                    'value' => $currentMetrics['total_distance'],
                    'previous_value' => null,
                    'previous_lift_log_id' => null,
                ];
            }
            
            foreach ($currentMetrics['round_distances'] as $rounds => $distance) {
                if ($distance > 0) {
                    $prs[] = [
                        'type' => 'rep_specific',
                        'rep_count' => $rounds,
                        'value' => $distance,
                        'previous_value' => null,
                        'previous_lift_log_id' => null,
                    ];
                }
            }
            
            return $prs;
        }
        
        // Check ENDURANCE PR (longest single distance)
        $bestDistanceResult = $this->getBestSingleDistance($previousLogs);
        if ($currentMetrics['best_distance'] > $bestDistanceResult['value'] + $distanceTolerance) {
            $prs[] = [
                'type' => 'endurance',
                'value' => $currentMetrics['best_distance'],
                'previous_value' => $bestDistanceResult['value'],
                'previous_lift_log_id' => $bestDistanceResult['lift_log_id'],
            ];
        }
        
        // Check VOLUME PR (total distance)
        $bestVolumeResult = $this->getBestTotalDistance($previousLogs);
        if ($currentMetrics['total_distance'] > $bestVolumeResult['value'] + $distanceTolerance) {
            $prs[] = [
                'type' => 'volume',
                'value' => $currentMetrics['total_distance'],
                'previous_value' => $bestVolumeResult['value'],
                'previous_lift_log_id' => $bestVolumeResult['lift_log_id'],
            ];
        }
        
        // Check REP_SPECIFIC PRs (best total distance at specific round counts)
        foreach ($currentMetrics['round_distances'] as $rounds => $distance) {
            $previousBestResult = $this->getBestDistanceForRounds($previousLogs, $rounds);
            if ($distance > $previousBestResult['distance'] + $distanceTolerance) {
                $prs[] = [
                    'type' => 'rep_specific',
                    'rep_count' => $rounds,
                    'value' => $distance,
                    'previous_value' => $previousBestResult['distance'],
                    'previous_lift_log_id' => $previousBestResult['lift_log_id'],
                ];
            }
        }
        
        return $prs;
    }
    
    /**
     * Format PR display for beaten PRs table
     */
    public function formatPRDisplay(\App\Models\PersonalRecord $pr, LiftLog $liftLog): array
    {
        return match($pr->pr_type) {
            'endurance' => [
                'label' => 'Best Distance',
                'value' => $pr->previous_value ? $this->formatDistance((int)$pr->previous_value) : '—',
                'comparison' => $this->formatDistance((int)$pr->value),
            ],
            'volume' => [
                'label' => 'Total Distance',
                'value' => $pr->previous_value ? $this->formatDistance((int)$pr->previous_value) : '—',
                'comparison' => $this->formatDistance((int)$pr->value),
            ],
            'rep_specific' => [
                'label' => $this->formatRoundLabel($pr->rep_count),
                'value' => $pr->previous_value ? $this->formatDistance((int)$pr->previous_value) : '—',
                'comparison' => $this->formatDistance((int)$pr->value),
            ],
            default => [
                'label' => ucfirst(str_replace('_', ' ', $pr->pr_type)),
                'value' => $pr->previous_value ? (string)$pr->previous_value : '—',
                'comparison' => (string)$pr->value,
            ],
        };
    }
    
    /**
     * Format PR display for current records table
     */
    public function formatCurrentPRDisplay(\App\Models\PersonalRecord $pr, LiftLog $liftLog, bool $isCurrent): array
    {
        return match($pr->pr_type) {
            'endurance' => [
                'label' => 'Best Distance',
                'value' => $this->formatDistance((int)$pr->value),
                'is_current' => $isCurrent,
            ],
            'volume' => [
                'label' => 'Total Distance',
                'value' => $this->formatDistance((int)$pr->value),
                'is_current' => $isCurrent,
            ],
            'rep_specific' => [
                'label' => $this->formatRoundLabel($pr->rep_count),
                'value' => $this->formatDistance((int)$pr->value),
                'is_current' => $isCurrent,
            ],
            default => [
                'label' => ucfirst(str_replace('_', ' ', $pr->pr_type)),
                'value' => (string)$pr->value,
                'is_current' => $isCurrent,
            ],
        };
    }
    
    // ========================================================================
    // HELPER METHODS
    // ========================================================================
    
    /**
     * Get best single distance from previous logs (for ENDURANCE PR)
     */
    private function getBestSingleDistance(\Illuminate\Database\Eloquent\Collection $logs): array
    {
        $bestDistance = 0;
        $liftLogId = null;
        
        foreach ($logs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->reps > $bestDistance) {
                    $bestDistance = $set->reps;
                    $liftLogId = $log->id;
                }
            }
        }
        
        return ['value' => $bestDistance, 'lift_log_id' => $liftLogId];
    }
    
    /**
     * Get best total distance from previous logs (for VOLUME PR)
     */
    private function getBestTotalDistance(\Illuminate\Database\Eloquent\Collection $logs): array
    {
        $bestTotal = 0;
        $liftLogId = null;
        
        foreach ($logs as $log) {
            $total = 0;
            foreach ($log->liftSets as $set) {
                $total += $set->reps;
            }
            
            if ($total > $bestTotal) {
                $bestTotal = $total;
                $liftLogId = $log->id;
            }
        }
        
        return ['value' => $bestTotal, 'lift_log_id' => $liftLogId];
    }
    
    /**
     * Get best total distance for specific round count from previous logs
     */
    private function getBestDistanceForRounds(\Illuminate\Database\Eloquent\Collection $logs, int $targetRounds): array
    {
        $bestDistance = 0;
        $liftLogId = null;
        
        foreach ($logs as $log) {
            $roundCount = $log->liftSets->count();
            
            // Only compare logs with the same number of rounds
            if ($roundCount === $targetRounds) {
                $total = 0;
                foreach ($log->liftSets as $set) {
                    $total += $set->reps;
                }
                
                if ($total > $bestDistance) {
                    $bestDistance = $total;
                    $liftLogId = $log->id;
                }
            }
        }
        
        return ['distance' => $bestDistance, 'lift_log_id' => $liftLogId];
    }
    
    /**
     * Format distance for display (handles meters and kilometers)
     */
    private function formatDistance(int $meters): string
    {
        if ($meters < 100) {
            return number_format($meters, 0) . 'm';
        } elseif ($meters >= 10000) {
            $kilometers = $meters / 1000;
            return number_format($kilometers, 1) . 'km';
        } else {
            return number_format($meters, 0) . 'm';
        }
    }
    
    /**
     * Format round label for PR display
     */
    private function formatRoundLabel(int $rounds): string
    {
        $roundText = $rounds == 1 ? 'Round' : 'Rounds';
        return "{$rounds} {$roundText}";
    }
}