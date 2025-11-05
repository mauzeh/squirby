<?php

namespace App\Presenters;

use App\Models\LiftLog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
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
     * Format individual lift log for display
     */
    private function formatLiftLog(LiftLog $liftLog): array
    {
        return [
            'id' => $liftLog->id,
            'formatted_date' => $liftLog->logged_at->format('m/d'),
            'exercise_title' => $liftLog->exercise->title,
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
     * Format weight display for lift log using exercise type strategy
     */
    private function formatWeight(LiftLog $liftLog): string
    {
        return $liftLog->exercise->getTypeStrategy()->formatWeightDisplay($liftLog);
    }

    /**
     * Format reps and sets display
     */
    private function formatRepsSets(LiftLog $liftLog): string
    {
        return $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
    }

    /**
     * Format one rep max display using exercise type strategy
     */
    private function format1RM(LiftLog $liftLog): string
    {
        $strategy = $liftLog->exercise->getTypeStrategy();
        
        if (!$strategy->canCalculate1RM()) {
            return 'N/A (' . ucfirst($strategy->getTypeName()) . ')';
        }
        
        return $strategy->format1RMDisplay($liftLog);
    }

    /**
     * Format progression suggestion using exercise type strategy
     */
    private function formatProgression(LiftLog $liftLog): string
    {
        $suggestion = $liftLog->exercise->getTypeStrategy()->formatProgressionSuggestion($liftLog);
        return $suggestion ?? '';
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