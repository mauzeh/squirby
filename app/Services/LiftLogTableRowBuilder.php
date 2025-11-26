<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ExerciseAliasService;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Shared service for building lift log table rows
 * Used by both lift-logs/index and mobile-entry/lifts
 */
class LiftLogTableRowBuilder
{
    protected ExerciseAliasService $aliasService;

    public function __construct(ExerciseAliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * Build table rows from lift logs collection
     * 
     * @param Collection $liftLogs
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
        
        // Calculate PRs once upfront for all logs
        $prLogIds = $this->calculatePRLogIds($liftLogs);
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
        
        // Always show comments in subitem if they exist
        if (!empty($liftLog->comments)) {
            $row['subItems'] = [[
                'line1' => null,
                'messages' => [[
                    'type' => 'neutral',
                    'prefix' => 'Your notes:',
                    'text' => $liftLog->comments
                ]],
                'actions' => []
            ]];
            $row['collapsible'] = false; // Always show comments
            $row['initialState'] = 'expanded';
        }
        
        return $row;
    }

    /**
     * Calculate which lift logs contain PRs based on estimated 1RM
     * A PR is determined at the time it was achieved - meaning it beat all previous lifts
     * This is done once upfront to avoid N+1 queries
     * 
     * @param Collection $liftLogs
     * @return array Array of lift log IDs that contain PRs
     */
    protected function calculatePRLogIds(Collection $liftLogs): array
    {
        if ($liftLogs->isEmpty()) {
            return [];
        }

        // Only process if this is an exercise that supports 1RM calculation
        $firstLog = $liftLogs->first();
        $strategy = $firstLog->exercise->getTypeStrategy();
        
        if (!$strategy->canCalculate1RM()) {
            return [];
        }

        // Sort logs by date (oldest first) to process chronologically
        $sortedLogs = $liftLogs->sortBy('logged_at');
        
        $prLogIds = [];
        $maxEstimated1RMSoFar = 0;
        $tolerance = 0.1; // Small tolerance for floating point comparison
        
        // Process each log chronologically
        foreach ($sortedLogs as $log) {
            $logMaxEstimated1RM = 0;
            
            // Find the best estimated 1RM in this log
            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    try {
                        $estimated1RM = $strategy->calculate1RM($set->weight, $set->reps, $log);
                        
                        if ($estimated1RM > $logMaxEstimated1RM) {
                            $logMaxEstimated1RM = $estimated1RM;
                        }
                    } catch (\Exception $e) {
                        // Skip sets that can't be calculated
                        continue;
                    }
                }
            }
            
            // If this log's best 1RM beats or ties the previous best, it's a PR
            // Use >= to include ties as PRs
            if ($logMaxEstimated1RM >= $maxEstimated1RMSoFar - $tolerance) {
                $prLogIds[] = $log->id;
                $maxEstimated1RMSoFar = max($maxEstimated1RMSoFar, $logMaxEstimated1RM);
            }
        }
        
        return $prLogIds;
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
