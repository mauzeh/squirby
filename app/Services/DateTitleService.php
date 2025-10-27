<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Date Title Service
 * 
 * Generates user-friendly date titles with contextual relative descriptions
 * and optional subtitles showing the actual date.
 * 
 * This service provides intelligent date formatting for mobile entry interfaces,
 * making dates more readable and contextual for users.
 */
class DateTitleService
{
    /**
     * Generate a user-friendly date title with main title and optional subtitle
     * 
     * Returns contextual date labels for recent dates, with relative descriptions for dates
     * within the current or adjacent weeks. Always shows the actual date as a subtitle
     * unless the full date is already displayed in the main title.
     * 
     * Examples:
     * - Today: { main: "Today", subtitle: "Jan 15, 2024" }
     * - Yesterday: { main: "Yesterday", subtitle: "Jan 14, 2024" }
     * - Tomorrow: { main: "Tomorrow", subtitle: "Jan 16, 2024" }
     * - Last Monday: { main: "Last Monday", subtitle: "Jan 15, 2024" }
     * - Next Friday: { main: "Next Friday", subtitle: "Jan 19, 2024" }
     * - This Wednesday: { main: "This Wednesday", subtitle: "Jan 17, 2024" }
     * - Distant dates: { main: "Mon, Jan 15, 2024", subtitle: null } (full date already shown)
     * 
     * @param Carbon $selectedDate The date to generate a title for
     * @param Carbon|null $referenceDate The reference date (defaults to today)
     * @return array{main: string, subtitle: string|null}
     */
    public function generateDateTitle(Carbon $selectedDate, ?Carbon $referenceDate = null): array
    {
        $today = $referenceDate ?? Carbon::today();
        $formattedDate = $selectedDate->format('M d, Y'); // Jan 15, 2024
        
        // Handle immediate dates (show actual date as subtitle)
        if ($selectedDate->isSameDay($today)) {
            return ['main' => 'Today', 'subtitle' => $formattedDate];
        } elseif ($selectedDate->isSameDay($today->copy()->subDay())) {
            return ['main' => 'Yesterday', 'subtitle' => $formattedDate];
        } elseif ($selectedDate->isSameDay($today->copy()->addDay())) {
            return ['main' => 'Tomorrow', 'subtitle' => $formattedDate];
        }
        
        // Calculate week boundaries for relative date detection
        $startOfThisWeek = $today->copy()->startOfWeek();
        $endOfThisWeek = $today->copy()->endOfWeek();
        $startOfLastWeek = $startOfThisWeek->copy()->subWeek();
        $endOfLastWeek = $endOfThisWeek->copy()->subWeek();
        $startOfNextWeek = $startOfThisWeek->copy()->addWeek();
        $endOfNextWeek = $endOfThisWeek->copy()->addWeek();
        
        $dayName = $selectedDate->format('l'); // Full day name (Monday, Tuesday, etc.)
        
        // Check if date is in last week
        if ($selectedDate->between($startOfLastWeek, $endOfLastWeek)) {
            return ['main' => "Last {$dayName}", 'subtitle' => $formattedDate];
        }
        
        // Check if date is in next week
        if ($selectedDate->between($startOfNextWeek, $endOfNextWeek)) {
            return ['main' => "Next {$dayName}", 'subtitle' => $formattedDate];
        }
        
        // Check if date is in this week (but not today/yesterday/tomorrow)
        if ($selectedDate->between($startOfThisWeek, $endOfThisWeek)) {
            return ['main' => "This {$dayName}", 'subtitle' => $formattedDate];
        }
        
        // For dates outside the 3-week window, show full date without subtitle
        // (since the full date is already in the main title)
        return ['main' => $selectedDate->format('D, M d, Y'), 'subtitle' => null];
    }
}