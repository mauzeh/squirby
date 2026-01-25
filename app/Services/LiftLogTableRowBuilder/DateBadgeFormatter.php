<?php

namespace App\Services\LiftLogTableRowBuilder;

use App\Models\LiftLog;

/**
 * Formats date badges for lift logs
 */
class DateBadgeFormatter
{
    /**
     * Format a date badge for a lift log
     * 
     * @return array{text: string, color: string}
     */
    public static function format(LiftLog $liftLog): array
    {
        $appTz = config('app.timezone');
        $loggedDate = $liftLog->logged_at->copy()->setTimezone($appTz);
        
        if ($loggedDate->isToday()) {
            return ['text' => 'Today', 'color' => 'success'];
        }
        
        if ($loggedDate->isYesterday()) {
            return ['text' => 'Yesterday', 'color' => 'warning'];
        }
        
        if ($loggedDate->isFuture()) {
            return ['text' => $loggedDate->format('n/j/y'), 'color' => 'neutral'];
        }
        
        // Past date - calculate days ago
        $now = now($appTz);
        $daysDiff = $loggedDate->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());
        
        if ($daysDiff <= 7) {
            return ['text' => $daysDiff . ' days ago', 'color' => 'neutral'];
        }
        
        return ['text' => $loggedDate->format('n/j/y'), 'color' => 'neutral'];
    }
}
