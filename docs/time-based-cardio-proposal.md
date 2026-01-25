# Time-Based Cardio Tracking Proposal

## ⚠️ DEPENDENCY NOTICE

**This proposal depends on the Time Field Implementation being completed first.**

See: `docs/time-field-implementation-proposal.md`

The time field implementation adds a dedicated `time` column to the `lift_sets` table, which is required for this feature to work properly.

## Overview

Add support for time-based cardio tracking to enable athletes to track both distance-based and time-based cardiovascular performance. This allows tracking sprints, timed runs, and pace improvements.

**Prerequisites:**
- ✅ Time field added to `lift_sets` table
- ✅ Static holds migrated to use time field
- ✅ Time field validation and display methods implemented

## Use Cases

### Sprint Training
- 100m sprint in 13 seconds
- 400m sprint in 58 seconds
- Track improvements in sprint times

### Timed Distance Runs
- 5K run in 22:30 (22 minutes, 30 seconds)
- Mile run in 6:15
- Track pace improvements over time

### Interval Training
- 400m × 8 rounds with times for each interval
- Track consistency and improvement across intervals

### Race Performance
- Marathon (42.2km) in 3:45:00
- Half-marathon in 1:35:20
- Compare race times over seasons

## Current System Analysis

### Existing Field Mapping (Distance-Only Cardio)

| Field | Current Use |
|-------|-------------|
| `reps` | Distance in meters |
| `time` | null (not used) |
| `weight` | Always 0 (forced) |
| `band_color` | Always null |

### After Time Field Implementation

With the new `time` field available, we can now properly store time-based data:

| Field | Distance Cardio | Timed Cardio |
|-------|----------------|--------------|
| `reps` | Distance in meters | Distance in meters (optional) |
| `time` | null | **Duration in seconds** |
| `weight` | 0 | 0 |
| `band_color` | null | null |

## Proposed Solution: Timed Cardio Exercise Type

With the dedicated `time` field now available, we can create a clean `timed_cardio` exercise type.

### Field Mapping

#### Distance-Based Cardio (Existing)
```php
Exercise Type: 'cardio'

[
    'reps' => 5000,      // Distance in meters (required)
    'time' => null,      // Not used
    'weight' => 0,       // Always 0 (forced)
    'sets' => 1,         // Number of rounds
    'band_color' => null
]

Display: "5,000m × 1 round"
```

#### Time-Based Cardio (New)
```php
Exercise Type: 'timed_cardio'

[
    'reps' => 5000,      // Distance in meters (optional, 0 = not tracked)
    'time' => 1350,      // Duration in seconds (required) - 22:30 = 1350s
    'weight' => 0,       // Always 0 (forced)
    'sets' => 1,         // Number of rounds
    'band_color' => null
]

Display: "22:30 × 1 round" or "5,000m in 22:30 × 1 round" (if distance tracked)
```

**Comparison with Static Holds:**
```php
Exercise Type: 'static_hold'

[
    'reps' => 1,         // Always 1 (one hold performed)
    'time' => 30,        // Duration in seconds
    'weight' => 25,      // Optional added weight
    'sets' => 3,         // Number of holds
    'band_color' => null
]

Display: "30s hold +25 lbs × 3 sets"
```

### Key Differences from Static Holds

| Feature | Static Hold | Timed Cardio |
|---------|-------------|--------------|
| **Time range** | 1-300s (5 min max) | 1-86400s (24 hours max) |
| **Reps field** | Always 1 | Distance (optional) |
| **Weight field** | Added weight | Always 0 |
| **Focus** | Hold duration | Activity duration |
| **Can track distance?** | No | Yes (optional) |

## Implementation Details

### 1. Create New TimedCardioExerciseType Class

```php
<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Models\User;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;

/**
 * Timed Cardio Exercise Type Strategy
 * 
 * Handles time-based cardiovascular exercises where the focus is on speed/pace
 * rather than distance. Examples include sprint times, race times, and timed intervals.
 * 
 * Characteristics:
 * - Reps field stores time duration in seconds (follows Static Hold pattern)
 * - Weight is always 0 (forced)
 * - Sets field stores number of rounds/intervals
 * - Does not support 1RM calculation
 * - Uses time-based display formatting
 * - Validates time within reasonable bounds (1s - 24 hours)
 * 
 * @package App\Services\ExerciseTypes
 * @since 1.0.0
 * 
 * @example
 * // Sprint training
 * $strategy = new TimedCardioExerciseType();
 * $processedData = $strategy->processLiftData([
 *     'reps' => '13',      // Time in seconds (13s sprint)
 *     'sets' => '8',       // Number of sprints
 *     'weight' => '0',     // Always 0
 * ]);
 * 
 * @example
 * // 5K time trial
 * $processedData = $strategy->processLiftData([
 *     'reps' => '1350',    // 22:30 = 1350 seconds
 *     'sets' => '1',
 * ]);
 */
class TimedCardioExerciseType extends BaseExerciseType
{
    /**
     * Minimum time in seconds (1 second)
     */
    private const MIN_TIME = 1;
    
    /**
     * Maximum time in seconds (24 hours = 86400 seconds)
     */
    private const MAX_TIME = 86400;
    
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string
    {
        return 'timed_cardio';
    }
    
    /**
     * Process lift data according to timed cardio exercise rules
     */
    public function processLiftData(array $data): array
    {
        $processedData = $data;
        
        // Force weight to 0 for timed cardio exercises
        $processedData['weight'] = 0;
        
        // Nullify band_color for timed cardio exercises
        $processedData['band_color'] = null;
        
        // Validate time (stored in reps field)
        if (!isset($processedData['reps'])) {
            throw InvalidExerciseDataException::missingField('reps', $this->getTypeName());
        }
        
        if (!is_numeric($processedData['reps'])) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'time must be a number');
        }
        
        $time = (int) $processedData['reps'];
        
        if ($time < self::MIN_TIME) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'time must be at least ' . self::MIN_TIME . ' second');
        }
        
        if ($time > self::MAX_TIME) {
            throw InvalidExerciseDataException::forField('reps', $this->getTypeName(), 'time cannot exceed ' . self::MAX_TIME . ' seconds (24 hours)');
        }
        
        $processedData['reps'] = $time;
        
        return $processedData;
    }
    
    /**
     * Process exercise data according to timed cardio exercise rules
     */
    public function processExerciseData(array $data): array
    {
        $processedData = $data;
        
        // For timed cardio exercises, ensure exercise_type is set correctly
        $processedData['exercise_type'] = 'timed_cardio';
        
        // Timed cardio exercises are not bodyweight exercises and don't use bands
        $processedData['is_bodyweight'] = false;
        
        return $processedData;
    }
    
    /**
     * Format weight display for timed cardio exercises
     * 
     * For timed cardio exercises, we display time instead of weight.
     * The time is stored in the reps field (following Static Hold pattern).
     */
    public function formatWeightDisplay(LiftLog $liftLog): string
    {
        $time = $liftLog->display_reps;
        
        if (!is_numeric($time) || $time <= 0) {
            return '0s';
        }
        
        return $this->formatTime($time);
    }
    
    /**
     * Format time in seconds to a readable format
     * 
     * @param int $seconds Time in seconds (whole seconds only)
     * @return string Formatted time (e.g., "12s", "22:30", "3:45:00")
     */
    private function formatTime(int $seconds): string
    {
        if ($seconds < 60) {
            // Under 1 minute: "12s"
            return $seconds . 's';
        }
        
        if ($seconds < 3600) {
            // Under 1 hour: "22:30" (MM:SS)
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return sprintf('%d:%02d', $minutes, $secs);
        }
        
        // Over 1 hour: "3:45:00" (H:MM:SS)
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    
    /**
     * Format complete timed cardio display showing time and rounds
     */
    public function formatCompleteDisplay(LiftLog $liftLog): string
    {
        $time = $liftLog->display_reps;
        $rounds = $liftLog->display_rounds;
        
        if (!is_numeric($time) || $time <= 0) {
            $time = 0;
        }
        
        $time = (int) $time;
        
        if (!is_numeric($rounds) || $rounds <= 0) {
            $rounds = 1;
        }
        
        $timeDisplay = $this->formatTime($time);
        $roundsText = $rounds == 1 ? 'round' : 'rounds';
        
        return "{$timeDisplay} × {$rounds} {$roundsText}";
    }
    
    /**
     * Format progression suggestion for timed cardio exercises
     * 
     * Timed cardio progression logic:
     * - Suggest reducing time by 2-5% (getting faster)
     * - For very short times (< 60s): suggest 1-2 second improvement
     * - For longer times: suggest percentage-based improvement
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string
    {
        $time = $liftLog->display_reps;
        $rounds = $liftLog->liftSets->count();
        
        if (!is_numeric($time) || $time <= 0) {
            return null;
        }
        
        if ($time < 60) {
            // For sprints, suggest 1-2 second improvement
            $targetTime = max(1, $time - 1);
            $targetDisplay = $this->formatTime($targetTime);
            return "Try {$targetDisplay} × {$rounds} rounds";
        } else {
            // For longer times, suggest 2% improvement
            $targetTime = $time * 0.98;
            $targetDisplay = $this->formatTime($targetTime);
            return "Try {$targetDisplay} × {$rounds} rounds";
        }
    }
    
    /**
     * Get form field definitions for timed cardio exercises
     * Timed cardio exercises only show time (reps) field, never weight
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
                'defaultValue' => $defaults['reps'] ?? 60,
                'increment' => $increments['reps'],
                'min' => self::MIN_TIME,
                'max' => self::MAX_TIME,
            ]
        ];
    }
    
    /**
     * Format logged item display message for timed cardio exercises
     * Uses time-appropriate terminology (time × rounds)
     */
    public function formatLoggedItemDisplay(LiftLog $liftLog): string
    {
        return $this->formatCompleteDisplay($liftLog);
    }
    
    /**
     * Format form message display for timed cardio exercises
     * Uses time-appropriate terminology (time × rounds)
     */
    public function formatFormMessageDisplay(array $lastSession): string
    {
        $time = $lastSession['reps'] ?? 0;
        $rounds = $lastSession['sets'] ?? 1;
        
        // Format time directly
        if (!is_numeric($time) || $time <= 0) {
            $timeDisplay = '0s';
        } else {
            $timeDisplay = $this->formatTime($time);
        }
        
        $roundsText = $rounds == 1 ? 'round' : 'rounds';
        
        return "{$timeDisplay} × {$rounds} {$roundsText}";
    }
    
    /**
     * Format table cell display for timed cardio exercises
     * Returns the complete time display as primary text only
     */
    public function formatTableCellDisplay(LiftLog $liftLog): array
    {
        // For timed cardio, we show the complete display as the primary text only
        return [
            'primary' => $this->formatCompleteDisplay($liftLog)
        ];
    }
    
    /**
     * Format 1RM table cell display for timed cardio exercises
     * Timed cardio exercises don't support 1RM calculation
     */
    public function format1RMTableCellDisplay(LiftLog $liftLog): string
    {
        return 'N/A (Timed Cardio)';
    }
    
    /**
     * Get exercise type display name and icon for timed cardio exercises
     */
    public function getTypeDisplayInfo(): array
    {
        return [
            'icon' => 'fas fa-stopwatch',
            'name' => 'Timed Cardio'
        ];
    }
    
    /**
     * Get chart title for timed cardio exercises
     */
    public function getChartTitle(): string
    {
        return 'Time Progress';
    }
    
    /**
     * Format mobile summary display for timed cardio exercises
     * Timed cardio exercises don't show weight and use time-specific formatting
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
     * Format success message description for timed cardio exercises
     * Uses time and rounds terminology
     */
    public function formatSuccessMessageDescription(?float $weight, int $reps, int $rounds, ?string $bandColor = null): string
    {
        // For timed cardio, reps represents time in seconds
        $time = $reps;
        
        $timeDisplay = $this->formatTime($time);
        $roundsText = $rounds == 1 ? 'round' : 'rounds';
        
        return "{$timeDisplay} × {$rounds} {$roundsText}";
    }
    
    /**
     * Get progression suggestion for timed cardio exercises
     * Implements time-based progression logic (getting faster)
     */
    public function getProgressionSuggestion(\App\Models\LiftLog $lastLog, int $userId, int $exerciseId, ?\Carbon\Carbon $forDate = null): ?object
    {
        $lastTime = $lastLog->display_reps; // reps field stores time in seconds
        $lastRounds = $lastLog->liftSets->count();
        
        // Validate that we have valid timed cardio data
        if (!is_numeric($lastTime) || $lastTime <= 0) {
            // No valid history, provide sensible defaults
            return $this->getDefaultTimedCardioSuggestion();
        }
        
        // For sprints (< 60s): suggest 1-2 second improvement
        if ($lastTime < 60) {
            $targetTime = max(1, $lastTime - 1);
            
            return (object)[
                'sets' => $lastRounds,
                'reps' => $targetTime, // time stored in reps field
                'weight' => 0, // always 0 for timed cardio
                'band_color' => null, // not applicable for timed cardio
            ];
        }
        
        // For longer times: suggest 2% improvement
        $targetTime = $lastTime * 0.98;
        
        return (object)[
            'sets' => $lastRounds,
            'reps' => round($targetTime),
            'weight' => 0, // always 0 for timed cardio
            'band_color' => null, // not applicable for timed cardio
        ];
    }
    
    /**
     * Provide sensible default timed cardio suggestions when no history exists
     */
    private function getDefaultTimedCardioSuggestion(): object
    {
        return (object)[
            'sets' => 1, // 1 round
            'reps' => 60, // 60 seconds (1 minute)
            'weight' => 0, // always 0 for timed cardio
            'band_color' => null, // not applicable for timed cardio
        ];
    }
    
    // ========================================================================
    // PR DETECTION METHODS
    // ========================================================================
    
    /**
     * Get supported PR types for timed cardio exercises
     * 
     * Timed cardio exercises support:
     * - TIME: Fastest time (lower is better)
     */
    public function getSupportedPRTypes(): array
    {
        return [
            \App\Enums\PRType::TIME,
        ];
    }
    
    /**
     * Calculate current metrics from a lift log
     * 
     * For timed cardio:
     * - best_time: Fastest single time (min reps field = time in seconds)
     * - total_time: Sum of all times across sets
     */
    public function calculateCurrentMetrics(LiftLog $liftLog): array
    {
        $bestTime = PHP_FLOAT_MAX;
        $totalTime = 0;
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->reps > 0) { // reps = time in seconds
                $bestTime = min($bestTime, $set->reps);
                $totalTime += $set->reps;
            }
        }
        
        return [
            'best_time' => $bestTime === PHP_FLOAT_MAX ? 0 : $bestTime,
            'total_time' => $totalTime,
        ];
    }
    
    /**
     * Compare current metrics to previous logs and detect PRs
     * 
     * For timed cardio:
     * - TIME PR: Fastest time (lower is better)
     */
    public function compareToPrevious(array $currentMetrics, \Illuminate\Database\Eloquent\Collection $previousLogs, LiftLog $currentLog): array
    {
        $prs = [];
        
        // If no previous logs, first time is a PR
        if ($previousLogs->isEmpty()) {
            if ($currentMetrics['best_time'] > 0) {
                $prs[] = [
                    'type' => 'time',
                    'value' => $currentMetrics['best_time'],
                    'previous_value' => null,
                    'previous_lift_log_id' => null,
                ];
            }
            
            return $prs;
        }
        
        // Check TIME PR (fastest time - lower is better)
        $bestTimeResult = $this->getBestTime($previousLogs);
        if ($bestTimeResult['value'] > 0 && $currentMetrics['best_time'] < $bestTimeResult['value']) {
            $prs[] = [
                'type' => 'time',
                'value' => $currentMetrics['best_time'],
                'previous_value' => $bestTimeResult['value'],
                'previous_lift_log_id' => $bestTimeResult['lift_log_id'],
            ];
        }
        
        return $prs;
    }
    
    /**
     * Format PR display for beaten PRs table
     */
    public function formatPRDisplay(\App\Models\PersonalRecord $pr, LiftLog $liftLog): array
    {
        return match($pr->pr_type) {
            'time' => [
                'label' => 'Best Time',
                'value' => $pr->previous_value ? $this->formatTime($pr->previous_value) : '—',
                'comparison' => $this->formatTime($pr->value),
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
            'time' => [
                'label' => 'Best Time',
                'value' => $this->formatTime($pr->value),
                'is_current' => $isCurrent,
            ],
            default => [
                'label' => ucfirst(str_replace('_', ' ', $pr->pr_type)),
                'value' => (string)$pr->value,
                'is_current' => $isCurrent,
            ],
        ];
    }
    
    // ========================================================================
    // HELPER METHODS
    // ========================================================================
    
    /**
     * Get best (fastest) time from previous logs
     */
    private function getBestTime(\Illuminate\Database\Eloquent\Collection $logs): array
    {
        $bestTime = PHP_FLOAT_MAX;
        $liftLogId = null;
        
        foreach ($logs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->reps > 0 && $set->reps < $bestTime) {
                    $bestTime = $set->reps;
                    $liftLogId = $log->id;
                }
            }
        }
        
        return [
            'value' => $bestTime === PHP_FLOAT_MAX ? 0 : $bestTime,
            'lift_log_id' => $liftLogId
        ];
    }
}
```

### 2. Configuration

Add to `config/exercise_types.php`:

```php
'timed_cardio' => [
    'class' => \App\Services\ExerciseTypes\TimedCardioExerciseType::class,
    'validation' => [
        'reps' => 'required|integer|min:1|max:86400', // Time in seconds (whole seconds)
        'weight' => 'nullable|numeric|in:0', // Must be 0
    ],
    'chart_type' => 'time_progression',
    'supports_1rm' => false,
    'form_fields' => ['reps'], // Only time, no weight
    'progression_types' => ['time_improvement'],
    'display_format' => 'time_rounds',
    'field_labels' => [
        'reps' => 'Time (seconds):',
        'sets' => 'Rounds:',
    ],
    'field_increments' => [
        'reps' => 1, // 1 second increments
        'sets' => 1,
    ],
    'field_mins' => [
        'reps' => 1,
        'sets' => 1,
    ],
    'field_maxes' => [
        'reps' => 86400, // 24 hours max
        'sets' => 20,
    ],
],
```

## PR Detection for Time-Based Cardio

### Supported PR Types

**Distance Mode:**
- ENDURANCE: Furthest distance covered
- DENSITY: Best distance per round

**Time Mode:**
- TIME: Fastest time (already exists in PRType enum!)
- PACE: Best pace (time per distance unit)

**Time + Distance Mode:**
- TIME: Fastest time for specific distance
- PACE: Best pace (seconds per km)

### PR Detection Implementation

```php
public function getSupportedPRTypes(): array
{
    return [
        PRType::TIME,      // Fastest time
        PRType::ENDURANCE, // Furthest distance
        PRType::DENSITY,   // Best pace/efficiency
    ];
}

public function calculateCurrentMetrics(LiftLog $liftLog): array
{
    $distance = 0;
    $time = 0;
    $rounds = 0;
    
    foreach ($liftLog->liftSets as $set) {
        $distance += $set->reps;
        $time += $set->weight;
        $rounds++;
    }
    
    $metrics = [
        'total_distance' => $distance,
        'total_time' => $time,
        'rounds' => $rounds,
    ];
    
    // Calculate pace (seconds per km) if both distance and time are tracked
    if ($distance > 0 && $time > 0) {
        $metrics['pace'] = ($time / $distance) * 1000; // seconds per km
    }
    
    // Calculate density (distance per round)
    if ($rounds > 0 && $distance > 0) {
        $metrics['density'] = $distance / $rounds;
    }
    
    return $metrics;
}

public function compareToPrevious(array $currentMetrics, Collection $previousLogs, LiftLog $currentLog): array
{
    $prs = [];
    
    if ($previousLogs->isEmpty()) {
        // First time logging this exercise
        if ($currentMetrics['total_time'] > 0) {
            $prs[] = [
                'type' => 'time',
                'value' => $currentMetrics['total_time'],
                'previous_value' => null,
                'previous_lift_log_id' => null,
            ];
        }
        
        if ($currentMetrics['total_distance'] > 0) {
            $prs[] = [
                'type' => 'endurance',
                'value' => $currentMetrics['total_distance'],
                'previous_value' => null,
                'previous_lift_log_id' => null,
            ];
        }
        
        return $prs;
    }
    
    // Check TIME PR (fastest time)
    if ($currentMetrics['total_time'] > 0) {
        $bestTimeResult = $this->getBestTime($previousLogs);
        if ($bestTimeResult['value'] > 0 && $currentMetrics['total_time'] < $bestTimeResult['value']) {
            $prs[] = [
                'type' => 'time',
                'value' => $currentMetrics['total_time'],
                'previous_value' => $bestTimeResult['value'],
                'previous_lift_log_id' => $bestTimeResult['lift_log_id'],
            ];
        }
    }
    
    // Check ENDURANCE PR (furthest distance)
    if ($currentMetrics['total_distance'] > 0) {
        $bestDistanceResult = $this->getBestDistance($previousLogs);
        if ($currentMetrics['total_distance'] > $bestDistanceResult['value']) {
            $prs[] = [
                'type' => 'endurance',
                'value' => $currentMetrics['total_distance'],
                'previous_value' => $bestDistanceResult['value'],
                'previous_lift_log_id' => $bestDistanceResult['lift_log_id'],
            ];
        }
    }
    
    // Check DENSITY PR (best pace)
    if (isset($currentMetrics['pace'])) {
        $bestPaceResult = $this->getBestPace($previousLogs);
        if ($bestPaceResult['value'] > 0 && $currentMetrics['pace'] < $bestPaceResult['value']) {
            $prs[] = [
                'type' => 'density',
                'value' => $currentMetrics['pace'],
                'previous_value' => $bestPaceResult['value'],
                'previous_lift_log_id' => $bestPaceResult['lift_log_id'],
            ];
        }
    }
    
    return $prs;
}

private function getBestTime(Collection $logs): array
{
    $bestTime = PHP_FLOAT_MAX;
    $liftLogId = null;
    
    foreach ($logs as $log) {
        $totalTime = $log->liftSets->sum('weight');
        if ($totalTime > 0 && $totalTime < $bestTime) {
            $bestTime = $totalTime;
            $liftLogId = $log->id;
        }
    }
    
    return [
        'value' => $bestTime === PHP_FLOAT_MAX ? 0 : $bestTime,
        'lift_log_id' => $liftLogId
    ];
}

private function getBestDistance(Collection $logs): array
{
    $bestDistance = 0;
    $liftLogId = null;
    
    foreach ($logs as $log) {
        $totalDistance = $log->liftSets->sum('reps');
        if ($totalDistance > $bestDistance) {
            $bestDistance = $totalDistance;
            $liftLogId = $log->id;
        }
    }
    
    return ['value' => $bestDistance, 'lift_log_id' => $liftLogId];
}

private function getBestPace(Collection $logs): array
{
    $bestPace = PHP_FLOAT_MAX;
    $liftLogId = null;
    
    foreach ($logs as $log) {
        $totalDistance = $log->liftSets->sum('reps');
        $totalTime = $log->liftSets->sum('weight');
        
        if ($totalDistance > 0 && $totalTime > 0) {
            $pace = ($totalTime / $totalDistance) * 1000; // seconds per km
            if ($pace < $bestPace) {
                $bestPace = $pace;
                $liftLogId = $log->id;
            }
        }
    }
    
    return [
        'value' => $bestPace === PHP_FLOAT_MAX ? 0 : $bestPace,
        'lift_log_id' => $liftLogId
    ];
}
```

### PR Display Formatting

```php
public function formatPRDisplay(PersonalRecord $pr, LiftLog $liftLog): array
{
    return match($pr->pr_type) {
        'time' => [
            'label' => 'Best Time',
            'value' => $pr->previous_value ? $this->formatTime($pr->previous_value) : '—',
            'comparison' => $this->formatTime($pr->value),
        ],
        'endurance' => [
            'label' => 'Furthest Distance',
            'value' => $pr->previous_value ? $this->formatDistance($pr->previous_value) : '—',
            'comparison' => $this->formatDistance($pr->value),
        ],
        'density' => [
            'label' => 'Best Pace',
            'value' => $pr->previous_value ? $this->formatPace($pr->previous_value) : '—',
            'comparison' => $this->formatPace($pr->value),
        ],
        default => [
            'label' => ucfirst(str_replace('_', ' ', $pr->pr_type)),
            'value' => $pr->previous_value ? (string)$pr->previous_value : '—',
            'comparison' => (string)$pr->value,
        ],
    };
}

private function formatPace(float $secondsPerKm): string
{
    $minutes = floor($secondsPerKm / 60);
    $seconds = $secondsPerKm % 60;
    return sprintf('%d:%02d/km', $minutes, $seconds);
}
```

## Real-World Examples

### Example 1: Sprint Training
```php
Exercise: "100m Sprint"
Type: cardio

Log entry:
- Distance: 100m
- Time: 13s
- Rounds: 8

Display: "100m in 13s × 8 rounds"

PRs detected:
✓ TIME PR: 13s (previous: 14s)
✓ DENSITY PR: 7:30/km pace (previous: 7:55/km)
```

### Example 2: 5K Time Trial
```php
Exercise: "5K Run"
Type: cardio

Log entry:
- Distance: 5000m
- Time: 1350s (22:30)
- Rounds: 1

Display: "5,000m in 22:30 × 1 round"

PRs detected:
✓ TIME PR: 22:30 (previous: 23:15)
✓ DENSITY PR: 4:30/km pace (previous: 4:39/km)
```

### Example 3: Interval Training
```php
Exercise: "400m Intervals"
Type: cardio

Log entry:
- Distance: 400m per interval
- Time: 75s per interval
- Rounds: 8

Display: "400m in 1:15 × 8 rounds"

PRs detected:
✓ ENDURANCE PR: 3200m total (previous: 2800m)
✓ DENSITY PR: 3:07/km pace (previous: 3:15/km)
```

### Example 4: Distance-Only (Current Behavior)
```php
Exercise: "Long Run"
Type: cardio

Log entry:
- Distance: 15000m
- Time: 0 (not tracked)
- Rounds: 1

Display: "15.0km × 1 round"

PRs detected:
✓ ENDURANCE PR: 15km (previous: 12km)
```

## Migration Strategy

### Backward Compatibility

**Existing cardio logs (weight = 0):**
- Continue to work exactly as before
- Display as distance-only
- No changes needed

**New time-based logs (weight > 0):**
- Automatically detected as time-based
- Display includes time
- PR detection includes time-based PRs

### User Experience

**For existing users:**
- No changes to existing cardio exercises
- Can optionally start tracking time by entering it in the form
- Existing distance-only logs remain valid

**For new users:**
- Can choose to track distance, time, or both
- System automatically adapts display and PRs based on what's tracked

## Configuration Updates

```php
// config/exercise_types.php

'cardio' => [
    'class' => \App\Services\ExerciseTypes\CardioExerciseType::class,
    'validation' => [
        'reps' => 'nullable|integer|min:0|max:50000',  // Distance (optional)
        'weight' => 'nullable|numeric|min:0|max:86400', // Time (optional)
    ],
    'chart_type' => 'cardio_progression',
    'supports_1rm' => false,
    'form_fields' => ['reps', 'weight'], // Both distance and time
    'progression_types' => ['cardio_progression'],
    'display_format' => 'distance_time_rounds',
    'field_labels' => [
        'reps' => 'Distance (m):',
        'weight' => 'Time (seconds):',
        'sets' => 'Rounds:',
    ],
    'field_increments' => [
        'reps' => 50,    // 50m increments
        'weight' => 1,   // 1 second increments
        'sets' => 1,
    ],
    'field_mins' => [
        'reps' => 0,
        'weight' => 0,
        'sets' => 1,
    ],
    'field_maxes' => [
        'reps' => 50000,  // 50km max
        'weight' => 86400, // 24 hours max
        'sets' => 20,
    ],
],
```

## Benefits

### For Athletes
- Track sprint times and improvements
- Monitor pace across different distances
- Compare race performances
- Set time-based goals

### For the System
- No schema changes required
- Backward compatible
- Reuses existing fields cleverly
- Extends PR detection naturally

### For Development
- Minimal code changes
- Leverages existing strategy pattern
- Well-tested architecture
- Easy to maintain

## Alternative Considered: Separate Exercise Type

**Option 2: Create `TimedCardioExerciseType`**

Pros:
- Cleaner separation of concerns
- Simpler validation logic per type

Cons:
- Users need to choose between two cardio types
- Can't easily switch between tracking modes
- More configuration to maintain
- Less flexible for mixed tracking

**Recommendation:** Option 1 (dual-mode) is better because it's more flexible and user-friendly.

## Implementation Checklist

- [ ] Update `CardioExerciseType::processLiftData()` to support time field
- [ ] Add `getCardioMode()` helper method
- [ ] Update `formatWeightDisplay()` to show time
- [ ] Add `formatTime()` helper method
- [ ] Update `formatCompleteDisplay()` for time display
- [ ] Update `getFormFieldDefinitions()` to include time field
- [ ] Update `getProgressionSuggestion()` for time-based progression
- [ ] Implement `getSupportedPRTypes()` to include TIME, ENDURANCE, DENSITY
- [ ] Implement `calculateCurrentMetrics()` for time/distance/pace
- [ ] Implement `compareToPrevious()` for time-based PRs
- [ ] Update `formatPRDisplay()` for time-based PR formatting
- [ ] Add `formatPace()` helper method
- [ ] Update configuration in `config/exercise_types.php`
- [ ] Write comprehensive tests for time-based cardio
- [ ] Update documentation
- [ ] Test backward compatibility with existing cardio logs

## Testing Strategy

### Unit Tests
- Time validation (1s - 24 hours)
- Mode detection logic
- Time formatting (seconds, MM:SS, H:MM:SS)
- Pace calculation
- PR detection for time-based cardio

### Integration Tests
- Creating time-based cardio logs
- Mixed time and distance tracking
- PR detection across different modes
- Backward compatibility with distance-only logs

### Feature Tests
- Complete workout flow with time tracking
- PR notifications for time-based PRs
- Display formatting in tables and charts
- Progression suggestions for time-based cardio

## Conclusion

Time-based cardio tracking is enabled by the dedicated `time` field implementation. By using the `time` field for duration and optionally tracking distance in `reps`, we get:

- ✅ **Proper semantics** - time stored in time field, distance in reps field
- ✅ **No schema changes** needed (time field already added)
- ✅ **Full backward compatibility** with existing distance-based cardio
- ✅ **Flexible tracking** - time-only OR time + distance
- ✅ **Comprehensive PR detection** - TIME PRs and PACE PRs
- ✅ **Intelligent progression** - suggests faster times (2-5% improvement)

### Comparison: Distance vs Time Cardio

| Feature | Distance Cardio (`cardio`) | Time Cardio (`timed_cardio`) |
|---------|---------------------------|------------------------------|
| **Focus** | How far? | How fast? |
| **Reps field** | Distance in meters | Distance in meters (optional) |
| **Time field** | null | Duration in seconds |
| **Weight field** | Always 0 | Always 0 |
| **Display** | "5,000m × 3 rounds" | "22:30 × 3 rounds" or "5,000m in 22:30" |
| **PRs** | ENDURANCE (furthest) | TIME (fastest), PACE (best pace) |
| **Progression** | Increase distance or rounds | Decrease time (get faster) |
| **Examples** | Long runs, cycling distance | Sprint times, race times |

### Field Usage Consistency

With the time field implementation, all exercise types now have clear, semantic field usage:

```php
// Regular: weight + reps
'regular' => ['weight' => 135, 'reps' => 8, 'time' => null]

// Bodyweight: extra weight + reps
'bodyweight' => ['weight' => 25, 'reps' => 10, 'time' => null]

// Static Hold: extra weight + time
'static_hold' => ['weight' => 25, 'reps' => 1, 'time' => 30]

// Distance Cardio: distance only
'cardio' => ['weight' => 0, 'reps' => 5000, 'time' => null]

// Timed Cardio: time + optional distance
'timed_cardio' => ['weight' => 0, 'reps' => 5000, 'time' => 1350]
```

This is a clean, extensible implementation that leverages the new time field infrastructure.

### Implementation Order

1. ✅ **First**: Implement time field (see `time-field-implementation-proposal.md`)
2. ⏳ **Then**: Implement timed cardio (this proposal)

**Risk Level**: Low (builds on proven time field implementation)
**Value**: High (unlocks speed/pace tracking for athletes)
