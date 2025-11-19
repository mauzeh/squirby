<?php

namespace App\Presenters;

use App\Models\LiftLog;
use App\Services\ExerciseTypes\Exceptions\UnsupportedOperationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiftLogTablePresenter
{
    /**
     * Format lift logs collection for table display
     */
    public function formatForTable(Collection|EloquentCollection $liftLogs, bool $hideExerciseColumn = false): array
    {
        return [
            'liftLogs' => $liftLogs->map(function ($liftLog) {
                return $this->formatLiftLog($liftLog);
            }),
            'config' => $this->buildTableConfig($hideExerciseColumn)
        ];
    }
    
    /**
     * Get date badge data for a lift log
     */
    public function getDateBadge(LiftLog $liftLog): array
    {
        $now = now();
        $loggedDate = $liftLog->logged_at;
        $daysDiff = abs($now->diffInDays($loggedDate));
        
        if ($loggedDate->isToday()) {
            return ['text' => 'Today', 'color' => 'success'];
        } elseif ($loggedDate->isYesterday()) {
            return ['text' => 'Yesterday', 'color' => 'warning'];
        } elseif ($daysDiff <= 7) {
            return ['text' => (int) $daysDiff . ' days ago', 'color' => 'neutral'];
        } else {
            return ['text' => $loggedDate->format('n/j'), 'color' => 'neutral'];
        }
    }

    /**
     * Format individual lift log for display
     */
    private function formatLiftLog(LiftLog $liftLog): array
    {
        // Get display name (alias if exists, otherwise title)
        $displayName = $this->getExerciseDisplayName($liftLog->exercise);
        
        return [
            'id' => $liftLog->id,
            'formatted_date' => $liftLog->logged_at->format('m/d'),
            'exercise_title' => $displayName,
            'exercise_url' => route('exercises.show-logs', $liftLog->exercise),
            'formatted_weight' => $this->formatWeight($liftLog),
            'formatted_reps_sets' => $this->formatRepsSets($liftLog),
            'formatted_1rm' => $this->format1RM($liftLog),
            'formatted_progression' => $this->formatProgression($liftLog),
            'truncated_comments' => Str::limit($liftLog->comments, 50),
            'full_comments' => $liftLog->comments,
            'edit_url' => route('lift-logs.edit', $liftLog->id),

            'raw_lift_log' => $liftLog // Keep reference for components that need it
        ];
    }
    
    /**
     * Get display name for exercise (alias if exists, otherwise title)
     */
    private function getExerciseDisplayName($exercise): string
    {
        // Check if aliases are loaded and exist for current user
        if ($exercise->relationLoaded('aliases') && $exercise->aliases->isNotEmpty()) {
            return $exercise->aliases->first()->alias_name;
        }
        
        return $exercise->title;
    }

    /**
     * Format weight display for lift log using exercise type strategy
     */
    private function formatWeight(LiftLog $liftLog): string
    {
        try {
            return $liftLog->exercise->getTypeStrategy()->formatWeightDisplay($liftLog);
        } catch (\Exception $e) {
            Log::warning('Weight formatting failed, using fallback', [
                'lift_log_id' => $liftLog->id,
                'exercise_id' => $liftLog->exercise_id,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to basic weight display
            $weight = $liftLog->display_weight;
            return is_numeric($weight) && $weight > 0 ? $weight . ' lbs' : 'N/A';
        }
    }

    /**
     * Format reps and sets display
     */
    private function formatRepsSets(LiftLog $liftLog): string
    {
        try {
            $strategy = $liftLog->exercise->getTypeStrategy();
            
            // Use cardio-specific formatting for cardio exercises
            if ($strategy->getTypeName() === 'cardio' && method_exists($strategy, 'formatCompleteDisplay')) {
                return $strategy->formatCompleteDisplay($liftLog);
            }
        } catch (\Exception $e) {
            Log::warning('Reps/sets formatting failed, using fallback', [
                'lift_log_id' => $liftLog->id,
                'exercise_id' => $liftLog->exercise_id,
                'error' => $e->getMessage(),
            ]);
        }
        
        // Default formatting for non-cardio exercises
        return $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
    }

    /**
     * Format one rep max display using exercise type strategy
     */
    private function format1RM(LiftLog $liftLog): string
    {
        try {
            $strategy = $liftLog->exercise->getTypeStrategy();
            
            if (!$strategy->canCalculate1RM()) {
                return 'N/A (' . ucfirst($strategy->getTypeName()) . ')';
            }
            
            return $strategy->format1RMDisplay($liftLog);
        } catch (UnsupportedOperationException $e) {
            // Expected exception for unsupported operations
            return 'N/A';
        } catch (\Exception $e) {
            Log::warning('1RM formatting failed, using fallback', [
                'lift_log_id' => $liftLog->id,
                'exercise_id' => $liftLog->exercise_id,
                'error' => $e->getMessage(),
            ]);
            
            return 'N/A';
        }
    }

    /**
     * Format progression suggestion using exercise type strategy
     */
    private function formatProgression(LiftLog $liftLog): string
    {
        try {
            $suggestion = $liftLog->exercise->getTypeStrategy()->formatProgressionSuggestion($liftLog);
            return $suggestion ?? '';
        } catch (\Exception $e) {
            Log::warning('Progression formatting failed, using fallback', [
                'lift_log_id' => $liftLog->id,
                'exercise_id' => $liftLog->exercise_id,
                'error' => $e->getMessage(),
            ]);
            
            return '';
        }
    }



    /**
     * Build table configuration for responsive behavior
     */
    private function buildTableConfig(bool $hideExerciseColumn): array
    {
        return [
            'hideExerciseColumn' => $hideExerciseColumn,
            'dateColumnClass' => $hideExerciseColumn ? '' : 'hide-on-mobile',
            'colspan' => $hideExerciseColumn ? 6 : 7,
            'showMobileSummary' => true
        ];
    }
}