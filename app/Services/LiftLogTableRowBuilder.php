<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ExerciseAliasService;
use App\Services\PRDetectionService;
use App\Services\Components\Display\PRRecordsTableComponentBuilder;
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
        
        // Build subitem with notes and PR records
        $subItem = [
            'line1' => null,
            'messages' => [],
            'actions' => []
        ];
        
        // Always show comments first
        $notesText = !empty(trim($liftLog->comments)) ? $liftLog->comments : 'N/A';
        $subItem['messages'][] = [
            'type' => 'neutral',
            'prefix' => 'Your notes:',
            'text' => $notesText
        ];
        
        // Add PR records component
        if ($isPR) {
            // For PRs, show what was beaten
            $prRecords = $this->getPRRecordsForBeatenPRs($liftLog);
            if (!empty($prRecords)) {
                $viewLogsUrl = route('exercises.show-logs', $liftLog->exercise);
                
                $builder = (new PRRecordsTableComponentBuilder('Records beaten'))
                    ->records($prRecords)
                    ->beaten()
                    ->footerLink($viewLogsUrl, 'View history');
                
                $subItem['component'] = $builder->build();
            }
        } else {
            // For non-PRs, show current records
            $currentRecords = $this->getCurrentRecordsTable($liftLog);
            if (!empty($currentRecords)) {
                $viewLogsUrl = route('exercises.show-logs', $liftLog->exercise);
                
                $builder = (new PRRecordsTableComponentBuilder('Current records'))
                    ->records($currentRecords)
                    ->current()
                    ->footerLink($viewLogsUrl, 'View history');
                
                $subItem['component'] = $builder->build();
            }
        }
        
        $row['subItems'] = [$subItem];
        $row['collapsible'] = false; // Always show comments
        $row['initialState'] = 'expanded';
        
        return $row;
    }
    
    /**
     * Format weight value for display
     * Shows integer if whole number, otherwise shows decimal
     * 
     * @param float $weight
     * @return string
     */
    protected function formatWeight(float $weight): string
    {
        // Round to 1 decimal place first
        $rounded = round($weight, 1);
        
        // Check if the rounded weight is a whole number
        if ($rounded == floor($rounded)) {
            return number_format($rounded, 0);
        }
        
        return number_format($rounded, 1);
    }
    
    /**
     * Get PR records for beaten PRs in table format
     * 
     * @param LiftLog $liftLog
     * @return array
     */
    protected function getPRRecordsForBeatenPRs(LiftLog $liftLog): array
    {
        // Get all previous lift logs for this exercise
        $previousLogs = \App\Models\LiftLog::where('exercise_id', $liftLog->exercise_id)
            ->where('user_id', $liftLog->user_id)
            ->where('logged_at', '<', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        if ($previousLogs->isEmpty()) {
            return [[
                'label' => 'Achievement',
                'value' => 'First time!'
            ]];
        }
        
        $strategy = $liftLog->exercise->getTypeStrategy();
        if (!$strategy->canCalculate1RM()) {
            return [];
        }
        
        $records = [];
        
        // Check for 1RM PR
        $current1RM = 0;
        $previous1RM = 0;
        $current1RMIsTrueMax = false; // Track if current 1RM is from a 1 rep lift
        $previous1RMIsTrueMax = false; // Track if previous 1RM is from a 1 rep lift
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                try {
                    $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $liftLog);
                    if ($estimated1RM > $current1RM) {
                        $current1RM = $estimated1RM;
                        $current1RMIsTrueMax = ($set->reps === 1);
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
                            $previous1RMIsTrueMax = ($set->reps === 1);
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // Check for rep-specific PRs first to see if we have a 1 rep PR
        $hasOneRepPR = false;
        $oneRepWeight = 0;
        foreach ($liftLog->liftSets as $set) {
            if ($set->reps === 1 && $set->weight > 0) {
                $previousMaxForReps = 0;
                
                foreach ($previousLogs as $log) {
                    foreach ($log->liftSets as $prevSet) {
                        if ($prevSet->reps === 1 && $prevSet->weight > $previousMaxForReps) {
                            $previousMaxForReps = $prevSet->weight;
                        }
                    }
                }
                
                if ($set->weight > $previousMaxForReps + 0.1) {
                    $hasOneRepPR = true;
                    $oneRepWeight = $set->weight;
                    break;
                }
            }
        }
        
        // Only show 1RM if it's different from the 1 Rep weight (i.e., it's an estimated 1RM)
        if ($current1RM > $previous1RM + 0.1) {
            // If we have a 1 rep PR and the 1RM equals the 1 rep weight, skip the 1RM row
            $shouldShow1RM = !($hasOneRepPR && abs($current1RM - $oneRepWeight) < 0.1);
            
            if ($shouldShow1RM) {
                // Use "1RM" if both current and previous are true maxes, otherwise "Est 1RM"
                $label = ($current1RMIsTrueMax && $previous1RMIsTrueMax) ? '1RM' : 'Est 1RM';
                $records[] = [
                    'label' => $label,
                    'value' => sprintf('%s → %s lbs', $this->formatWeight($previous1RM), $this->formatWeight($current1RM))
                ];
            }
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
        
        if ($currentVolume > $previousVolume * 1.01) {
            $records[] = [
                'label' => 'Volume',
                'value' => sprintf('%s → %s lbs', number_format($previousVolume, 0), number_format($currentVolume, 0))
            ];
        }
        
        // Check for rep-specific PRs
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
                    $records[] = [
                        'label' => $repLabel,
                        'value' => sprintf('%s → %s lbs', $this->formatWeight($previousMaxForReps), $this->formatWeight($set->weight))
                    ];
                    break; // Only show one rep-specific PR to keep it clean
                }
            }
        }
        
        return $records;
    }
    
    /**
     * Get current records for an exercise in table format
     * 
     * @param LiftLog $liftLog
     * @return array
     */
    protected function getCurrentRecordsTable(LiftLog $liftLog): array
    {
        // Get all previous lift logs for this exercise (including this one)
        $allLogs = \App\Models\LiftLog::where('exercise_id', $liftLog->exercise_id)
            ->where('user_id', $liftLog->user_id)
            ->where('logged_at', '<=', $liftLog->logged_at)
            ->with('liftSets')
            ->orderBy('logged_at', 'asc')
            ->get();
        
        if ($allLogs->count() <= 1) {
            return []; // First lift, no records to show
        }
        
        $strategy = $liftLog->exercise->getTypeStrategy();
        if (!$strategy->canCalculate1RM()) {
            return [];
        }
        
        $records = [];
        
        // Get best 1RM and current lift's 1RM
        $best1RM = 0;
        $best1RMIsTrueMax = false;
        $current1RM = 0;
        
        foreach ($allLogs as $log) {
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        if ($estimated1RM > $best1RM) {
                            $best1RM = $estimated1RM;
                            $best1RMIsTrueMax = ($set->reps === 1);
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // Calculate current lift's 1RM
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
        
        if ($best1RM > 0) {
            // Use "1RM" if it's a true max, otherwise "Est 1RM"
            $label = $best1RMIsTrueMax ? '1RM' : 'Est 1RM';
            $records[] = [
                'label' => $label,
                'value' => sprintf('%s lbs', $this->formatWeight($best1RM)),
                'comparison' => sprintf('%s lbs', $this->formatWeight($current1RM))
            ];
        }
        
        // Get best Volume and current lift's volume
        $bestVolume = 0;
        $currentVolume = 0;
        
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
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                $currentVolume += ($set->weight * $set->reps);
            }
        }
        
        if ($bestVolume > 0) {
            $records[] = [
                'label' => 'Volume',
                'value' => sprintf('%s lbs', number_format($bestVolume, 0)),
                'comparison' => sprintf('%s lbs', number_format($currentVolume, 0))
            ];
        }
        
        // Get rep-specific records (for reps in current lift)
        $currentReps = $liftLog->liftSets->pluck('reps')->unique()->filter(function($reps) {
            return $reps > 0 && $reps <= 10;
        })->take(2); // Limit to 2 rep counts to keep it clean
        
        foreach ($currentReps as $targetReps) {
            $bestWeightForReps = 0;
            $currentWeightForReps = 0;
            
            foreach ($allLogs as $log) {
                foreach ($log->liftSets as $set) {
                    if ($set->reps === $targetReps && $set->weight > $bestWeightForReps) {
                        $bestWeightForReps = $set->weight;
                    }
                }
            }
            
            foreach ($liftLog->liftSets as $set) {
                if ($set->reps === $targetReps && $set->weight > $currentWeightForReps) {
                    $currentWeightForReps = $set->weight;
                }
            }
            
            if ($bestWeightForReps > 0) {
                $repLabel = $targetReps . ' Rep' . ($targetReps > 1 ? 's' : '');
                $records[] = [
                    'label' => $repLabel,
                    'value' => sprintf('%s lbs', $this->formatWeight($bestWeightForReps)),
                    'comparison' => sprintf('%s lbs', $this->formatWeight($currentWeightForReps))
                ];
            }
        }
        
        return $records;
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
