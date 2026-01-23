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
                
                // First table: Records beaten (no footer link)
                if (!empty($prRecords)) {
                    $builder = (new PRRecordsTableComponentBuilder('Records beaten:'))
                        ->records($prRecords)
                        ->beaten();
                    
                    $components[] = $builder->build();
                }
                
                // Second table: Current records (with footer link)
                if (!empty($currentRecords)) {
                    $builder = (new PRRecordsTableComponentBuilder('Not beaten:'))
                        ->records($currentRecords)
                        ->current()
                        ->footerLink($viewLogsUrl, 'View history');
                    
                    $components[] = $builder->build();
                }
            } else {
                // For non-PRs, show current records
                $currentRecords = $this->getCurrentRecordsTable($liftLog, $config);
                if (!empty($currentRecords)) {
                    $builder = (new PRRecordsTableComponentBuilder('History:'))
                        ->records($currentRecords)
                        ->current()
                        ->footerLink($viewLogsUrl, 'View history');
                    
                    $components[] = $builder->build();
                }
            }
            
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
        
        // NEW: Use PersonalRecord database records
        $prs = \App\Models\PersonalRecord::where('lift_log_id', $liftLog->id)
            ->get();
        
        $records = [];
        $isBodyweight = $liftLog->exercise->exercise_type === 'bodyweight';
        $hasExtraWeight = $liftLog->liftSets->max('weight') > 0;
        
        // Group PRs by type to avoid duplicates
        $prsByType = $prs->groupBy('pr_type');
        
        // Process 1RM PRs (skip for bodyweight)
        if ($prsByType->has('one_rm') && !$isBodyweight) {
            $oneRmPR = $prsByType['one_rm']->first();
            
            // Check if this is a true 1RM (from a 1 rep lift)
            $hasOneRepSet = $liftLog->liftSets->contains(function ($set) {
                return $set->reps === 1 && $set->weight > 0;
            });
            
            // Only show "Est 1RM" for estimated 1RMs (from multiple reps)
            // For true 1RMs, it will show as a rep-specific PR instead
            if (!$hasOneRepSet) {
                $records[] = [
                    'label' => 'Est 1RM',
                    'value' => $oneRmPR->previous_value ? $this->formatWeight($oneRmPR->previous_value) . ' lbs' : '—',
                    'comparison' => $this->formatWeight($oneRmPR->value) . ' lbs'
                ];
            }
        }
        
        // Process Volume PRs
        // For pure bodyweight (no extra weight), show as "Total Reps"
        if ($prsByType->has('volume')) {
            $volumePR = $prsByType['volume']->first();
            
            if ($isBodyweight && !$hasExtraWeight) {
                // Pure bodyweight: show total reps
                $records[] = [
                    'label' => 'Total Reps',
                    'value' => $volumePR->previous_value ? (int)$volumePR->previous_value . ' reps' : '—',
                    'comparison' => (int)$volumePR->value . ' reps'
                ];
            } else {
                // Weighted: show volume in lbs
                $records[] = [
                    'label' => 'Volume',
                    'value' => $volumePR->previous_value ? number_format($volumePR->previous_value, 0) . ' lbs' : '—',
                    'comparison' => number_format($volumePR->value, 0) . ' lbs'
                ];
            }
        }
        
        // Process Rep-Specific PRs (limit to first one to keep it clean)
        // Only shown for weighted exercises or bodyweight with extra weight
        if ($prsByType->has('rep_specific')) {
            $repPR = $prsByType['rep_specific']->first();
            $repLabel = $repPR->rep_count . ' Rep' . ($repPR->rep_count > 1 ? 's' : '');
            
            $records[] = [
                'label' => $repLabel,
                'value' => ($repPR->previous_value && $repPR->previous_value > 0) ? $this->formatWeight($repPR->previous_value) . ' lbs' : '—',
                'comparison' => $this->formatWeight($repPR->value) . ' lbs'
            ];
        }
        
        // Process Hypertrophy PRs (best at weight) - skip for bodyweight
        if ($prsByType->has('hypertrophy') && !$isBodyweight) {
            $hypertrophyPR = $prsByType['hypertrophy']->first();
            $label = 'Best @ ' . $this->formatWeight($hypertrophyPR->weight) . ' lbs';
            
            $records[] = [
                'label' => $label,
                'value' => $hypertrophyPR->previous_value ? (int)$hypertrophyPR->previous_value . ' reps' : '—',
                'comparison' => (int)$hypertrophyPR->value . ' reps'
            ];
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
        // NEW: Use PersonalRecord database records to get current PRs
        // Get all current (unbeaten) PRs for this exercise
        $currentPRs = \App\Models\PersonalRecord::where('user_id', $liftLog->user_id)
            ->where('exercise_id', $liftLog->exercise_id)
            ->current() // Only unbeaten PRs
            ->get();
        
        if ($currentPRs->isEmpty()) {
            return []; // No PRs yet
        }
        
        $isBodyweight = $liftLog->exercise->exercise_type === 'bodyweight';
        $hasExtraWeight = $liftLog->liftSets->max('weight') > 0;
        $strategy = $liftLog->exercise->getTypeStrategy();
        
        // Skip 1RM calculation for bodyweight exercises
        if (!$isBodyweight && !$strategy->canCalculate1RM()) {
            return [];
        }
        
        $records = [];
        
        // Calculate current lift's values for comparison
        $current1RM = 0;
        $currentVolume = 0;
        $currentTotalReps = 0;
        $currentRepWeights = []; // [reps => weight]
        
        foreach ($liftLog->liftSets as $set) {
            if ($set->weight > 0 && $set->reps > 0) {
                // Calculate 1RM (skip for bodyweight)
                if (!$isBodyweight) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $liftLog);
                        if ($estimated1RM > $current1RM) {
                            $current1RM = $estimated1RM;
                        }
                    } catch (\Exception $e) {
                        // Skip if calculation fails
                    }
                }
                
                // Calculate volume
                $currentVolume += ($set->weight * $set->reps);
                $currentTotalReps += $set->reps;
                
                // Track rep-specific weights
                if (!isset($currentRepWeights[$set->reps]) || $set->weight > $currentRepWeights[$set->reps]) {
                    $currentRepWeights[$set->reps] = $set->weight;
                }
            } elseif ($isBodyweight && $set->reps > 0) {
                // For pure bodyweight (weight = 0), still count reps
                $currentTotalReps += $set->reps;
            }
        }
        
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
            } else {
                // For other types, just track the type
                $beatenPRMap[$pr->pr_type] = true;
            }
        }
        
        // Group PRs by type
        $prsByType = $currentPRs->groupBy('pr_type');
        
        // Process 1RM PRs (only if NOT beaten by this lift, skip for bodyweight)
        if (!$isBodyweight && $prsByType->has('one_rm') && !isset($beatenPRMap['one_rm'])) {
            $oneRmPR = $prsByType['one_rm']->first();
            
            // Check if the PR is a true 1RM (from a 1 rep lift)
            $prLiftLog = \App\Models\LiftLog::find($oneRmPR->lift_log_id);
            $isTrueMax = $prLiftLog && $prLiftLog->liftSets->contains(function ($set) {
                return $set->reps === 1 && $set->weight > 0;
            });
            
            // Check if current lift is also a true 1RM
            $currentIsTrueMax = $liftLog->liftSets->contains(function ($set) {
                return $set->reps === 1 && $set->weight > 0;
            });
            
            // Only show if both are estimated OR if we have a current estimated to compare
            if (!$isTrueMax || !$currentIsTrueMax) {
                $label = $isTrueMax ? '1RM' : 'Est 1RM';
                
                $records[] = [
                    'label' => $label,
                    'value' => sprintf('%s lbs', $this->formatWeight($oneRmPR->value)),
                    'comparison' => sprintf('%s lbs', $this->formatWeight($current1RM))
                ];
            }
        }
        
        // Process Volume PRs (only if NOT beaten by this lift)
        // For pure bodyweight (no extra weight), show as "Total Reps"
        if ($prsByType->has('volume') && !isset($beatenPRMap['volume'])) {
            $volumePR = $prsByType['volume']->first();
            
            if ($isBodyweight && !$hasExtraWeight) {
                // Pure bodyweight: show total reps
                $records[] = [
                    'label' => 'Total Reps',
                    'value' => sprintf('%d reps', (int)$volumePR->value),
                    'comparison' => sprintf('%d reps', $currentTotalReps)
                ];
            } else {
                // Weighted: show volume in lbs
                $records[] = [
                    'label' => 'Volume',
                    'value' => sprintf('%s lbs', number_format($volumePR->value, 0)),
                    'comparison' => sprintf('%s lbs', number_format($currentVolume, 0))
                ];
            }
        }
        
        // Process Rep-Specific PRs (only for reps in current lift, limit to 2, exclude beaten)
        // Only shown for weighted exercises or bodyweight with extra weight
        if ($prsByType->has('rep_specific') && (!$isBodyweight || $hasExtraWeight)) {
            $repPRs = $prsByType['rep_specific']
                ->filter(function ($pr) use ($currentRepWeights, $beatenPRMap) {
                    // Only show if we have this rep count in current lift AND it wasn't beaten
                    return isset($currentRepWeights[$pr->rep_count]) && !isset($beatenPRMap['rep_specific_' . $pr->rep_count]);
                })
                ->take(2);
            
            foreach ($repPRs as $repPR) {
                $repLabel = $repPR->rep_count . ' Rep' . ($repPR->rep_count > 1 ? 's' : '');
                $currentWeight = $currentRepWeights[$repPR->rep_count] ?? 0;
                
                $records[] = [
                    'label' => $repLabel,
                    'value' => sprintf('%s lbs', $this->formatWeight($repPR->value)),
                    'comparison' => sprintf('%s lbs', $this->formatWeight($currentWeight))
                ];
            }
        }
        
        return $records;
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
