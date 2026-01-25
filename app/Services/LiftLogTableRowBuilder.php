<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ExerciseAliasService;
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

    public function __construct(
        ExerciseAliasService $aliasService
    ) {
        $this->aliasService = $aliasService;
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
            'showPRRecordsTable' => false, // Only show on mobile-entry/lifts by default
        ];
        
        $config = array_merge($defaults, $options);
        
        // NEW: Use database PR flags instead of O(n²) calculation
        // The is_pr flag is already set on each lift log by the event system
        // No need to fetch historical logs or calculate PRs!
        
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
        
        // NEW: Check if this lift log is a PR using the database flag
        $isPR = $liftLog->is_pr;
        
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
            $queryParams = [];
            
            // Only add query parameters when redirectContext is 'mobile-entry-lifts'
            if ($config['redirectContext'] === 'mobile-entry-lifts') {
                $queryParams['from'] = $config['redirectContext'];
                
                if ($config['selectedDate']) {
                    $queryParams['date'] = $config['selectedDate'];
                }
            }
            
            if (!empty($queryParams)) {
                $viewLogsUrl .= '?' . http_build_query($queryParams);
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
        $notesText = !empty(trim($liftLog->comments ?? '')) ? $liftLog->comments : 'N/A';
        $subItem['messages'][] = [
            'type' => 'neutral',
            'prefix' => 'Your notes:',
            'text' => $notesText
        ];
        
        // Add PR records component
        if ($config['showPRRecordsTable']) {
            $components = [];
            $viewLogsUrl = route('exercises.show-logs', $liftLog->exercise);
            
            if ($isPR) {
                // For PRs, show TWO tables: what was beaten AND what wasn't
                $prRecords = $this->getPRRecordsForBeatenPRs($liftLog, $config);
                $currentRecords = $this->getCurrentRecordsTable($liftLog, $config);
                
                // First table: Records beaten
                if (!empty($prRecords)) {
                    $builder = (new PRRecordsTableComponentBuilder('Records beaten:'))
                        ->records($prRecords)
                        ->beaten();
                    
                    $components[] = $builder->build();
                }
                
                // Second table: Current records (no footer link anymore)
                if (!empty($currentRecords)) {
                    $builder = (new PRRecordsTableComponentBuilder('Not beaten:'))
                        ->records($currentRecords)
                        ->current();
                    
                    $components[] = $builder->build();
                }
            } else {
                // For non-PRs, show current records
                $currentRecords = $this->getCurrentRecordsTable($liftLog, $config);
                if (!empty($currentRecords)) {
                    $builder = (new PRRecordsTableComponentBuilder('History:'))
                        ->records($currentRecords)
                        ->current();
                    
                    $components[] = $builder->build();
                }
            }
            
            // Always add a third table with just the "View history" link
            $builder = (new PRRecordsTableComponentBuilder(''))
                ->records([]) // Empty records
                ->current()
                ->footerLink($viewLogsUrl, 'View history');
            
            $components[] = $builder->build();
            
            if (!empty($components)) {
                $subItem['components'] = $components;
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
     * Uses PersonalRecord database records instead of calculating on-the-fly
     * 
     * @param LiftLog $liftLog
     * @param array $config
     * @return array
     */
    protected function getPRRecordsForBeatenPRs(LiftLog $liftLog, array $config): array
    {
        // Check if this is the first lift for this exercise
        $isFirstLift = !\App\Models\LiftLog::where('exercise_id', $liftLog->exercise_id)
            ->where('user_id', $liftLog->user_id)
            ->where('id', '!=', $liftLog->id)
            ->exists();
        
        if ($isFirstLift) {
            return [[
                'label' => 'Achievement',
                'value' => 'First time!',
                'comparison' => ''
            ]];
        }
        
        // Use PersonalRecord database records
        $prs = \App\Models\PersonalRecord::where('lift_log_id', $liftLog->id)
            ->get();
        
        if ($prs->isEmpty()) {
            return [];
        }
        
        // Get the exercise type strategy for formatting
        $strategy = $liftLog->exercise->getTypeStrategy();
        
        $records = [];
        
        // Use the strategy's formatPRDisplay method for each PR
        foreach ($prs as $pr) {
            $formatted = $strategy->formatPRDisplay($pr, $liftLog);
            
            // Skip if the strategy returns empty array (e.g., redundant 1RM)
            if (!empty($formatted)) {
                $records[] = $formatted;
            }
        }
        
        return $records;
    }
    
    /**
     * Get current records for an exercise in table format
     * Uses PersonalRecord database records instead of calculating on-the-fly
     * 
     * @param LiftLog $liftLog
     * @param array $config
     * @return array
     */
    protected function getCurrentRecordsTable(LiftLog $liftLog, array $config): array
    {
        // Use PersonalRecord database records to get current PRs
        // Get all current (unbeaten) PRs for this exercise
        $currentPRs = \App\Models\PersonalRecord::where('user_id', $liftLog->user_id)
            ->where('exercise_id', $liftLog->exercise_id)
            ->current() // Only unbeaten PRs
            ->get();
        
        if ($currentPRs->isEmpty()) {
            return []; // No PRs yet
        }
        
        $strategy = $liftLog->exercise->getTypeStrategy();
        
        // Get PRs that were beaten by THIS lift (to exclude from current records)
        $beatenPRs = \App\Models\PersonalRecord::where('lift_log_id', $liftLog->id)
            ->get();
        
        // Create a map of beaten PR types with their specific details
        $beatenPRMap = [];
        foreach ($beatenPRs as $pr) {
            if ($pr->pr_type === 'rep_specific') {
                // For rep-specific, track which rep count was beaten
                $beatenPRMap['rep_specific_' . $pr->rep_count] = true;
            } elseif ($pr->pr_type === 'hypertrophy') {
                // For hypertrophy, track which weight was beaten
                $beatenPRMap['hypertrophy_' . $pr->weight] = true;
            } elseif ($pr->pr_type === 'time') {
                // For time PRs, track the type
                $beatenPRMap['time'] = true;
            } else {
                // For other types, just track the type
                $beatenPRMap[$pr->pr_type] = true;
            }
        }
        
        $records = [];
        
        // Calculate current metrics using the strategy
        $currentMetrics = $strategy->calculateCurrentMetrics($liftLog);
        
        // Filter out beaten PRs and format using strategy
        foreach ($currentPRs as $pr) {
            // Check if this PR was beaten
            $key = $pr->pr_type;
            if ($pr->pr_type === 'rep_specific') {
                $key = 'rep_specific_' . $pr->rep_count;
            } elseif ($pr->pr_type === 'hypertrophy') {
                $key = 'hypertrophy_' . $pr->weight;
            }
            
            if (isset($beatenPRMap[$key])) {
                continue; // Skip beaten PRs
            }
            
            // Use strategy to format the PR display
            $formatted = $strategy->formatCurrentPRDisplay($pr, $liftLog, true);
            
            // Add comparison value based on current metrics
            $comparison = $this->getComparisonValue($pr, $currentMetrics, $liftLog, $strategy);
            if ($comparison !== null) {
                $formatted['comparison'] = $comparison;
                $records[] = $formatted;
            }
        }
        
        return $records;
    }
    
    /**
     * Get comparison value for a PR based on current metrics
     * 
     * @param \App\Models\PersonalRecord $pr
     * @param array $currentMetrics
     * @param LiftLog $liftLog
     * @param \App\Services\ExerciseTypes\ExerciseTypeInterface $strategy
     * @return string|null
     */
    protected function getComparisonValue(\App\Models\PersonalRecord $pr, array $currentMetrics, LiftLog $liftLog, $strategy): ?string
    {
        $isBodyweight = $liftLog->exercise->exercise_type === 'bodyweight';
        $hasExtraWeight = $liftLog->liftSets->max('weight') > 0;
        
        switch ($pr->pr_type) {
            case 'one_rm':
                if (!$isBodyweight && isset($currentMetrics['best_1rm'])) {
                    return sprintf('%s lbs', $this->formatWeight($currentMetrics['best_1rm']));
                }
                return null;
                
            case 'volume':
                if ($isBodyweight && !$hasExtraWeight && isset($currentMetrics['total_reps'])) {
                    return sprintf('%d reps', (int)$currentMetrics['total_reps']);
                } elseif (isset($currentMetrics['total_volume'])) {
                    return sprintf('%s lbs', number_format($currentMetrics['total_volume'], 0));
                }
                return null;
                
            case 'rep_specific':
                if (isset($currentMetrics['rep_weights'][$pr->rep_count])) {
                    $currentWeight = $currentMetrics['rep_weights'][$pr->rep_count];
                    return sprintf('%s lbs', $this->formatWeight($currentWeight));
                }
                return null;
                
            case 'hypertrophy':
                // Not typically shown in current records table
                return null;
                
            case 'time':
                // For static holds - delegate to strategy for proper formatting
                if (isset($currentMetrics['best_hold'])) {
                    // Create a temporary PR object with current value for formatting
                    $tempPR = new \App\Models\PersonalRecord();
                    $tempPR->pr_type = 'time';
                    $tempPR->value = $currentMetrics['best_hold'];
                    $formatted = $strategy->formatCurrentPRDisplay($tempPR, $liftLog, false);
                    return $formatted['value'];
                }
                return null;
                
            default:
                return null;
        }
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
