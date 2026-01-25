<?php

namespace App\Services\MobileEntry;

use App\Models\LiftLog;
use App\Services\LiftLogTableRowBuilder;
use App\Services\ComponentBuilder;
use Carbon\Carbon;

class LiftLogTableService
{
    protected LiftLogTableRowBuilder $tableRowBuilder;

    public function __construct(LiftLogTableRowBuilder $tableRowBuilder)
    {
        $this->tableRowBuilder = $tableRowBuilder;
    }

    /**
     * Generate logged items table for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateLoggedItems($userId, Carbon $selectedDate)
    {
        $logs = LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->with(['exercise' => function ($query) use ($userId) {
                $query->with(['aliases' => function ($aliasQuery) use ($userId) {
                    $aliasQuery->where('user_id', $userId);
                }]);
            }, 'liftSets'])
            ->orderBy('logged_at', 'desc')
            ->get();

        // Build table rows using shared service
        $rows = $this->tableRowBuilder->buildRows($logs, [
            'showDateBadge' => false, // Don't show date badge on mobile-entry (same day)
            'showCheckbox' => false,
            'showViewLogsAction' => true, // Show view logs action
            'showDeleteAction' => true, // Show delete button on mobile-entry
            'wrapActions' => false, // Keep all 3 buttons on same line
            'includeEncouragingMessage' => true, // Show encouraging messages
            'redirectContext' => 'mobile-entry-lifts',
            'selectedDate' => $selectedDate->toDateString(),
            'showPRRecordsTable' => true, // Show PR records table on mobile-entry/lifts
        ]);

        $tableBuilder = ComponentBuilder::table()
            ->rows($rows)
            ->emptyMessage(config('mobile_entry_messages.empty_states.no_workouts_logged'))
            ->confirmMessage('deleteItem', 'Are you sure you want to delete this lift log entry? This action cannot be undone.')
            ->ariaLabel('Logged workouts')
            ->spacedRows();

        return $tableBuilder->build();
    }
}
