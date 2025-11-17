<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ExerciseAliasService;
use Illuminate\Support\Collection;

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
            'includeEncouragingMessage' => false,
            'redirectContext' => null,
            'selectedDate' => null,
        ];
        
        $config = array_merge($defaults, $options);
        
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
        
        // Reps/sets badge
        $badges[] = [
            'text' => $displayData['repsSets'],
            'colorClass' => 'neutral'
        ];
        
        // Weight badge (if applicable)
        if ($displayData['showWeight']) {
            $badges[] = [
                'text' => $displayData['weight'],
                'colorClass' => 'dark',
                'emphasized' => true
            ];
        }
        
        // Build actions
        $actions = [];
        
        // View logs action (optional)
        if ($config['showViewLogsAction']) {
            $actions[] = [
                'type' => 'link',
                'url' => route('exercises.show-logs', $liftLog->exercise),
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
            'line2' => $config['includeEncouragingMessage'] ? null : $liftLog->comments, // Show comments in line2 only if not showing messages
            'badges' => $badges,
            'actions' => $actions,
            'checkbox' => $config['showCheckbox'],
            'compact' => true,
            'wrapActions' => true,
            'wrapText' => true,
        ];
        
        // Add encouraging message for mobile-entry context
        if ($config['includeEncouragingMessage']) {
            $messages = [];
            
            // Add comments as neutral message if they exist
            if (!empty($liftLog->comments)) {
                $messages[] = [
                    'type' => 'neutral',
                    'prefix' => 'Your notes:',
                    'text' => $liftLog->comments
                ];
            }
            
            // Add encouraging message
            $messages[] = [
                'type' => 'success',
                'prefix' => $this->getEncouragingPrefix(),
                'text' => $this->getEncouragingMessage($liftLog, $displayData)
            ];
            
            $row['subItems'] = [[
                'line1' => null,
                'messages' => $messages,
                'actions' => []
            ]];
            $row['collapsible'] = false; // Always show the message
            $row['initialState'] = 'expanded';
        }
        
        return $row;
    }

    /**
     * Get date badge data for a lift log
     */
    protected function getDateBadge(LiftLog $liftLog): array
    {
        $now = now();
        $loggedDate = $liftLog->logged_at;
        $daysDiff = abs($now->diffInDays($loggedDate));
        
        if ($loggedDate->isToday()) {
            return ['text' => 'Today', 'color' => 'success'];
        } elseif ($loggedDate->isYesterday()) {
            return ['text' => 'Yesterday', 'color' => 'warning'];
        } elseif ($daysDiff <= 7) {
            return ['text' => (int) $daysDiff . ' days ago', 'color' => 'info'];
        } else {
            return ['text' => $loggedDate->format('n/j'), 'color' => 'neutral'];
        }
    }

    /**
     * Get a random encouraging prefix
     */
    protected function getEncouragingPrefix(): string
    {
        $prefixes = [
            'Great work!',
            'Nice job!',
            'Well done!',
            'Awesome!',
            'Excellent!',
            'Fantastic!',
            'Outstanding!',
            'Impressive!',
            'Strong work!',
            'Keep it up!',
        ];
        
        return $prefixes[array_rand($prefixes)];
    }

    /**
     * Generate an encouraging message based on the workout
     */
    protected function getEncouragingMessage(LiftLog $liftLog, array $displayData): string
    {
        $strategy = $liftLog->exercise->getTypeStrategy();
        $typeName = $strategy->getTypeName();
        
        // Build the core message
        $message = 'You completed ' . $displayData['repsSets'];
        
        if ($displayData['showWeight']) {
            $message .= ' at ' . $displayData['weight'];
        }
        
        // Add type-specific encouragement
        $encouragements = [
            'weighted' => [
                'That weight is no joke!',
                'Your strength is showing!',
                'Building that muscle!',
                'Getting stronger every day!',
            ],
            'bodyweight' => [
                'Mastering your own bodyweight!',
                'Control and strength combined!',
                'Your form is getting better!',
                'Bodyweight mastery in progress!',
            ],
            'banded' => [
                'That resistance is real!',
                'Bands don\'t lie!',
                'Feeling the burn!',
                'Resistance training at its finest!',
            ],
            'cardio' => [
                'Your endurance is improving!',
                'Heart and lungs getting stronger!',
                'Cardio champion!',
                'Building that stamina!',
            ],
        ];
        
        $typeEncouragements = $encouragements[$typeName] ?? $encouragements['weighted'];
        $message .= ' ' . $typeEncouragements[array_rand($typeEncouragements)];
        
        return $message;
    }
}
