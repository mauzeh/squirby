<?php

namespace Tests\Helpers;

use App\Events\LiftLogCompleted;
use App\Models\LiftLog;

trait TriggersPRDetection
{
    /**
     * Trigger PR detection for a lift log by dispatching the LiftLogCompleted event.
     * Use this after creating lift logs with factories in tests.
     * 
     * @param LiftLog $liftLog
     * @param bool $isUpdate
     * @return void
     */
    protected function triggerPRDetection(LiftLog $liftLog, bool $isUpdate = false): void
    {
        // Ensure lift sets are loaded
        $liftLog->load(['exercise', 'liftSets']);
        
        // Dispatch the event (uses positional parameters, not named)
        LiftLogCompleted::dispatch($liftLog, $isUpdate);
        
        // Refresh the lift log to get updated PR flags
        $liftLog->refresh();
    }
}
