<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ExerciseAliasService;
use App\Services\PRDetectionService;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Shared service for building lift log table rows
 * Used by both lift-logs/index and mobile-entry/lifts
 */
class LiftLogTableRowBuilder
{
    protected ExerciseAliasService $aliasService;
    protected PRDetectionService $prDetectionService;

    public function __construct(
        ExerciseAliasService $aliasService,
        PRDetectionService $prDetectionService
    ) {
        $this->aliasService = $aliasService;
        $this->prDetectionService = $prDetectionService;
    }

    /**
     * Build table rows from lift logs collection
     * 
     * @param Collection $liftLogs The logs to display
     * @param array $options Configuration options
     * @return array
     */
    public function buildRows(Collection $liftLogs, array $options = []): array
    {
        $defaults = [
            'showDateBadge' => true,
            'showCheckbox' => false,
            'showViewLogsAction' => true,
            'showDeleteAction' => false,
            'wrapActions' => true,
            'includeEncouragingMessage' => false,
            'redirectContext' => null,
            'selectedDate' => null,
        ];
        
        $config = array_merge($defaults, $options);
        
        // Calculate PRs once upfront
        // Fetch all historical logs for accurate PR calculation
        if ($liftLogs->isNotEmpty()) {
            $userId = $liftLogs->first()->user_id;
            $exerciseIds = $liftLogs->pluck('exercise_id')->unique();
            
            $logsForPRCalculation = \App\Models\LiftLog::where('user_id', $userId)
                ->whereIn('exercise_id', $exerciseIds)
                ->with(['exercise', 'liftSets'])
                ->orderBy('logged_at', 'asc')
                ->get();
        } else {
            $logsForPRCalculation = $liftLogs;
        }
        
        $prLogIds = $this->prDetectionService->calculatePRLogIds($logsForPRCalculation);
        $config['prLogIds'] = $prLogIds;
        
        return $liftLogs->map(function ($liftLog) use ($config) {
            return $this->buildRow($liftLog, $config);
        })->toArray();
    }

    /**
     * Build a single table row for a lift log
     * 
     * @param LiftLog $liftLog
     * @param array $config
     * @return array
     */
    protected function buildRow(LiftLog $liftLog, array $config): array
    {
        $user = auth()->user();
        $strategy = $liftLog->exercise->getTypeStrategy();
        $displayData = $strategy->formatMobileSummaryDisplay($liftLog);
        
        // Get display name with alias
        $displayName = $this->aliasService->getDisplayName($liftLog->exercise, $user);
        
        // Check if this lift log is a PR (using pre-calculated list)
        $isPR = in_array($liftLog->id, $config['prLogIds'] ?? []);
        
        // Build badges
        $badges = [];
        
        // Date badge (optional)
        if ($config['showDateBadge']) {
            $dateBadge = $this->getDateBadge($liftLog);
            $badges[] = [
                'text' => $dateBadge['text'],
                'colorClass' => $dateBadge['color']
            ];
        }
        
        // PR badge (if this is a PR)
        if ($isPR) {
            $badges[] = [
                'text' => '🏆 PR',
                'colorClass' => 'pr'
            ];
        }
        
        // Reps/sets badge
        $badges[] = [
            'text' => $displayData['repsSets'],
            'colorClass' => 'info'
        ];
        
        // Weight badge (if applicable)
        if ($displayData['showWeight']) {
            $badges[] = [
                            'text' => $displayData['weight'],
                            'colorClass' => 'success',
                            'emphasized' => true            ];
        }
        
        // Build actions
        $actions = [];
        
        // View logs action (optional)
        if ($config['showViewLogsAction']) {
            $viewLogsUrl = route('exercises.show-logs', $liftLog->exercise);
            
            // Add 'from' and 'date' parameters if coming from mobile-entry-lifts
            if ($config['redirectContext'] === 'mobile-entry-lifts') {
                $params = ['from' => 'mobile-entry-lifts'];
                if (isset($config['selectedDate'])) {
                    $params['date'] = $config['selectedDate'];
                }
                $viewLogsUrl .= '?' . http_build_query($params);
            }
            
            $actions[] = [
                'type' => 'link',
                'url' => $viewLogsUrl,
                'icon' => 'fa-chart-line',
                'ariaLabel' => 'View logs',
                'cssClass' => 'btn-info-circle'
            ];
        }
        
        // Edit action
        $editUrl = route('lift-logs.edit', $liftLog);
        if ($config['redirectContext']) {
            $editUrl .= '?' . http_build_query([
                'redirect_to' => $config['redirectContext'],
                'date' => $config['selectedDate'] ?? now()->toDateString()
            ]);
        }
        
        $actions[] = [
            'type' => 'link',
            'url' => $editUrl,
            'icon' => 'fa-pencil',
            'ariaLabel' => 'Edit',
            'cssClass' => 'btn-transparent'
        ];
        
        // Delete action (optional)
        if ($config['showDeleteAction']) {
            $deleteParams = [];
            if ($config['redirectContext']) {
                $deleteParams['redirect_to'] = $config['redirectContext'];
                $deleteParams['date'] = $config['selectedDate'] ?? now()->toDateString();
            }
            
            $actions[] = [
                'type' => 'form',
                'url' => route('lift-logs.destroy', $liftLog),
                'method' => 'DELETE',
                'icon' => 'fa-trash',
                'ariaLabel' => 'Delete',
                'cssClass' => 'btn-transparent', // Subtle styling like edit button
                'requiresConfirm' => true,
                'params' => $deleteParams
            ];
        }
        
        // Build the row
        $row = [
            'id' => $liftLog->id,
            'line1' => $displayName,
            'line2' => null, // Never show comments in line2
            'badges' => $badges,
            'actions' => $actions,
            'checkbox' => $config['showCheckbox'],
            'compact' => true,
            'wrapActions' => $config['wrapActions'],
            'wrapText' => true,
            'cssClass' => $isPR ? 'row-pr' : null,
        ];
        
        // Build subitem messages
        $subItemMessages = [];
        
        // Always show comments first
        $notesText = !empty(trim($liftLog->comments)) ? $liftLog->comments : 'N/A';
        $subItemMessages[] = [
            'type' => 'neutral',
            'prefix' => 'Your notes:',
            'text' => $notesText
        ];
        
        // Add PR information - show what records exist (for non-PRs) or what was beaten (for PRs)
        if ($isPR) {
            // For PRs, show what was beaten
            $prInfo = $this->getPRInfoForLiftLog($liftLog);
            if (!empty($prInfo)) {
                $subItemMessages[] = [
                    'type' => 'success',
                    'prefix' => '🏆 PRs beaten:',
                    'text' => $prInfo
                ];
            }
        } else {
            // For non-PRs, show what the current records are (what they need to beat)
            $recordsInfo = $this->getCurrentRecordsForExercise($liftLog);
            if (!empty($recordsInfo)) {
                $subItemMessages[] = [
                    'type' => 'info',
                    'prefix' => 'Current records:',
                    'text' => $recordsInfo
                ];
            }
        }
        
        $row['subItems'] = [[
            'line1' => null,
            'messages' => $subItemMessages,
            'actions' => []
        ]];
        $row['collapsible'] = false; // Always show comments
        $row['initialState'] = 'expanded';
        
        return $row;
    }
    
    /**
     * Get current records for an exercise (to show what needs to be beaten)
     * 
     * @param LiftLog $liftLog
     * @return string
     */
    protected function getCurrentRecordsForExercise(LiftLog $liftLog): string
    {
        // Get all previous lift logs for this exercise (including this one)
        $allLogs = \App\Models\LiftLog::where('exercise_id', $liftLog->exercise_id)
            ->where('user_id', $liftLog->user_id)
            ->where('logged_at', '<=', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        if ($allLogs->count() <= 1) {
            return ''; // First lift, no records to show
        }
        
        $strategy = $liftLog->exercise->getTypeStrategy();
        if (!$strategy->canCalculate1RM()) {
            return '';
        }
        
        $records = [];
        
        // Get best 1RM
        $best1RM = 0;
        foreach ($allLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $best1RM) {
                            $best1RM = $estimated1RM;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        if ($best1RM > 0) {
            $records[] = sprintf('1RM: %.1f lbs', $best1RM);
        }
        
        // Get best Volume
        $bestVolume = 0;
        foreach ($allLogs as $log) {
            $logVolume = 0;
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    $logVolume += ($set->weight * $set->reps);
                }
            }
            if ($logVolume > $bestVolume) {
                $bestVolume = $logVolume;
            }
        }
        
        if ($bestVolume > 0) {
            $records[] = sprintf('Volume: %s lbs', number_format($bestVolume, 0));
        }
        
        // Get rep-specific records (for reps in current lift)
        $currentReps = $liftLog->liftSets->pluck('reps')->unique()->filter(function($reps) {
            return $reps > 0 && $reps <= 10;
        });
        
        foreach ($currentReps as $targetReps) {
            $bestWeightForReps = 0;
            
            foreach ($allLogs as $log) {
                foreach ($log->liftSets as $set) {
                    if ($set->reps === $targetReps && $set->weight > $bestWeightForReps) {
                        $bestWeightForReps = $set->weight;
                    }
                }
            }
            
            if ($bestWeightForReps > 0) {
                $repLabel = $targetReps . ' Rep' . ($targetReps > 1 ? 's' : '');
                $records[] = sprintf('%s: %.1f lbs', $repLabel, $bestWeightForReps);
            }
        }
        
        if (empty($records)) {
            return '';
        }
        
        return implode(' • ', $records);
    }
    
    /**
     * Get PR information for a specific lift log
     * Returns a formatted string describing which PRs were beaten
     * 
     * @param LiftLog $liftLog
     * @return string
     */
    protected function getPRInfoForLiftLog(LiftLog $liftLog): string
    {
        // Get all previous lift logs for this exercise
        $previousLogs = \App\Models\LiftLog::where('exercise_id', $liftLog->exercise_id)
            ->where('user_id', $liftLog->user_id)
            ->where('logged_at', '<', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        if ($previousLogs->isEmpty()) {
            return 'First time logging this exercise!';
        }
        
        $strategy = $liftLog->exercise->getTypeStrategy();
        if (!$strategy->canCalculate1RM()) {
            return '';
        }
        
        $prTypes = [];
        
        // Check for 1RM PR
        $current1RM = 0;
        $previous1RM = 0;
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                try {
                    $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $liftLog);
                    if ($estimated1RM > $current1RM) {
                        $current1RM = $estimated1RM;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        foreach ($previousLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $previous1RM) {
                            $previous1RM = $estimated1RM;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        if ($current1RM > $previous1RM + 0.1) {
            $prTypes[] = sprintf('1RM (%.1f lbs → %.1f lbs)', $previous1RM, $current1RM);
        }
        
        // Check for Volume PR
        $currentVolume = 0;
        $previousVolume = 0;
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                $currentVolume += ($set->weight * $set->reps);
            }
        }
        
        foreach ($previousLogs as $log) {
            $logVolume = 0;
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    $logVolume += ($set->weight * $set->reps);
                }
            }
            if ($logVolume > $previousVolume) {
                $previousVolume = $logVolume;
            }
        }
        
        if ($currentVolume > $previousVolume * 1.01) { // 1% tolerance
            $prTypes[] = sprintf('Volume (%s lbs → %s lbs)', number_format($previousVolume, 0), number_format($currentVolume, 0));
        }
        
        // Check for rep-specific PRs
        $repPRs = [];
        foreach ($liftLog->liftSets as $set) {
            if ($set->reps > 0 && $set->reps <= 10) {
                $previousMaxForReps = 0;
                
                foreach ($previousLogs as $log) {
                    foreach ($log->liftSets as $prevSet) {
                        if ($prevSet->reps === $set->reps && $prevSet->weight > $previousMaxForReps) {
                            $previousMaxForReps = $prevSet->weight;
                        }
                    }
                }
                
                if ($set->weight > $previousMaxForReps + 0.1) {
                    $repLabel = $set->reps . ' Rep' . ($set->reps > 1 ? 's' : '');
                    $repPRs[$set->reps] = sprintf('%s (%.1f lbs → %.1f lbs)', $repLabel, $previousMaxForReps, $set->weight);
                }
            }
        }
        
        // Add rep PRs to the list
        foreach ($repPRs as $repPR) {
            $prTypes[] = $repPR;
        }
        
        if (empty($prTypes)) {
            return '';
        }
        
        return implode(' • ', $prTypes);
    }

    /**
     * Get date badge data for a lift log
     */
    protected function getDateBadge(LiftLog $liftLog): array
    {
        $appTz = config('app.timezone');
        $loggedDate = $liftLog->logged_at->copy()->setTimezone($appTz);
        
        if ($loggedDate->isToday()) {
            return ['text' => 'Today', 'color' => 'success'];
        } elseif ($loggedDate->isYesterday()) {
            return ['text' => 'Yesterday', 'color' => 'warning'];
        } elseif ($loggedDate->isFuture()) {
            // Also format future dates as a standard date
            return ['text' => $loggedDate->format('n/j/y'), 'color' => 'neutral'];
        } else {
            // It's in the past, but not yesterday. Calculate the difference.
            $now = now($appTz);
            // Ensure we get a positive number for past dates: past->diff(now)
            $daysDiff = $loggedDate->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());

            if ($daysDiff <= 7) {
                return ['text' => $daysDiff . ' days ago', 'color' => 'neutral'];
            } else {
                return ['text' => $loggedDate->format('n/j/y'), 'color' => 'neutral'];
            }
        }
    }


}
