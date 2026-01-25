<?php

namespace App\Services\MobileEntry;

use App\Models\LiftLog;
use Carbon\Carbon;

/**
 * Detects if user has PRs today for celebration display
 */
class PRCelebrationService
{
    /**
     * Check if user has any PRs on the given date
     * Uses database is_pr flag for O(1) performance
     * 
     * @param int $userId
     * @param Carbon $date
     * @return bool
     */
    public function hasPRsToday(int $userId, Carbon $date): bool
    {
        return LiftLog::where('user_id', $userId)
            ->whereDate('logged_at', $date->toDateString())
            ->where('is_pr', true)
            ->exists();
    }
}
