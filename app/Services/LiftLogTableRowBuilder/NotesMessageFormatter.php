<?php

namespace App\Services\LiftLogTableRowBuilder;

use App\Models\LiftLog;

/**
 * Formats notes messages for lift logs
 */
class NotesMessageFormatter
{
    /**
     * Format a notes message for a lift log
     * 
     * @return array{type: string, prefix: string, text: string}
     */
    public static function format(LiftLog $liftLog): array
    {
        $notesText = !empty(trim($liftLog->comments ?? '')) 
            ? $liftLog->comments 
            : 'N/A';
            
        return [
            'type' => 'neutral',
            'prefix' => 'Your notes:',
            'text' => $notesText
        ];
    }
}
