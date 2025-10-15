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
            'truncated_comments' => Str::limit($liftLog->comments, 50),
            'full_comments' => $liftLog->comments,
            'edit_url' => route('lift-logs.edit', $liftLog->id),
            'mobile_summary' => $this->buildMobileSummary($liftLog),
            'raw_lift_log' => $liftLog // Keep reference for components that need it
        ];
    }

    /**
     * Format weight display for lift log
     */
    private function formatWeight(LiftLog $liftLog): string
    {
        if ($liftLog->exercise->is_bodyweight) {
            $weight = 'Bodyweight';
            if ($liftLog->display_weight > 0) {
                $weight .= ' +' . $liftLog->display_weight . ' lbs';
            }
            return $weight;
        }
        
        return $liftLog->display_weight . ' lbs';
    }

    /**
     * Format reps and sets display
     */
    private function formatRepsSets(LiftLog $liftLog): string
    {
        return $liftLog->display_rounds . ' x ' . $liftLog->display_reps;
    }

    /**
     * Format one rep max display
     */
    private function format1RM(LiftLog $liftLog): string
    {
        if (!$liftLog->one_rep_max) {
            return '';
        }

        $oneRM = round($liftLog->one_rep_max) . ' lbs';
        
        if ($liftLog->exercise->is_bodyweight) {
            $oneRM .= ' (est. incl. BW)';
        }
        
        return $oneRM;
    }

    /**
     * Build mobile summary for responsive display
     */
    private function buildMobileSummary(LiftLog $liftLog): string
    {
        $summary = $liftLog->logged_at->format('m/d') . ' - ' . 
                  $this->formatWeight($liftLog) . ' (' . 
                  $this->formatRepsSets($liftLog) . ')';
        
        if ($liftLog->one_rep_max) {
            $summary .= '<br><i>1RM: ' . round($liftLog->one_rep_max) . ' lbs</i>';
        }
        
        return $summary;
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