<?php

namespace App\Services;

use App\Models\LiftLog;
use App\Services\ExerciseAliasService;
use App\Services\LiftLogTableRowBuilder\RowConfig;
use App\Services\LiftLogTableRowBuilder\BadgeCollectionBuilder;
use App\Services\LiftLogTableRowBuilder\ActionCollectionBuilder;
use App\Services\LiftLogTableRowBuilder\NotesMessageFormatter;
use App\Services\LiftLogTableRowBuilder\PRRecordsComponentAssembler;
use Illuminate\Support\Collection;

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
        $config = RowConfig::fromArray($config);
        $displayData = $this->getDisplayData($liftLog);
        
        return [
            'id' => $liftLog->id,
            'line1' => $displayData['displayName'],
            'line2' => null,
            'badges' => $this->buildBadges($liftLog, $displayData, $config),
            'actions' => $this->buildActions($liftLog, $config),
            'checkbox' => $config->showCheckbox,
            'compact' => true,
            'wrapActions' => $config->wrapActions,
            'wrapText' => true,
            'cssClass' => $liftLog->is_pr ? 'row-pr' : null,
            'subItems' => $this->buildSubItems($liftLog, $config),
            'collapsible' => false,
            'initialState' => 'expanded',
        ];
    }
    
    /**
     * Get display data for a lift log
     */
    private function getDisplayData(LiftLog $liftLog): array
    {
        $strategy = $liftLog->exercise->getTypeStrategy();
        $displayData = $strategy->formatMobileSummaryDisplay($liftLog);
        $displayData['displayName'] = $this->aliasService->getDisplayName(
            $liftLog->exercise,
            auth()->user()
        );
        return $displayData;
    }
    
    /**
     * Build badges for a lift log
     */
    private function buildBadges(LiftLog $liftLog, array $displayData, RowConfig $config): array
    {
        $builder = new BadgeCollectionBuilder();
        
        if ($config->showDateBadge) {
            $builder->addDateBadge($liftLog);
        }
        
        if ($liftLog->is_pr) {
            $builder->addPRBadge();
        }
        
        $builder->addRepsBadge($displayData['repsSets']);
        
        if ($displayData['showWeight']) {
            $builder->addWeightBadge($displayData['weight']);
        }
        
        return $builder->build();
    }
    
    /**
     * Build actions for a lift log
     */
    private function buildActions(LiftLog $liftLog, RowConfig $config): array
    {
        $builder = new ActionCollectionBuilder($liftLog, $config);
        
        if ($config->showViewLogsAction) {
            $builder->addViewLogsAction();
        }
        
        $builder->addEditAction();
        
        if ($config->showDeleteAction) {
            $builder->addDeleteAction();
        }
        
        return $builder->build();
    }
    
    /**
     * Build subitems for a lift log
     */
    private function buildSubItems(LiftLog $liftLog, RowConfig $config): array
    {
        $subItem = [
            'line1' => null,
            'messages' => [NotesMessageFormatter::format($liftLog)],
            'actions' => []
        ];
        
        if ($config->showPRRecordsTable) {
            $subItem['components'] = PRRecordsComponentAssembler::assemble($liftLog, $config);
        }
        
        return [$subItem];
    }
}
