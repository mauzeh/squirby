<?php

namespace App\Sync\Actions;

use App\Models\LiftLog;
use App\Models\User;

class DeleteSyncLogAction
{
    /**
     * Execute the action to delete a sync log.
     */
    public function execute(User $user, LiftLog $liftLog): void
    {
        if ($liftLog->user_id !== $user->id) {
            abort(404, 'Log not found.');
        }

        $liftLog->delete();
    }
}
