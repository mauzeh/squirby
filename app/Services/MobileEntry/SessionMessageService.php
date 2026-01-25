<?php

namespace App\Services\MobileEntry;

/**
 * Extracts and formats session messages for display
 * Handles success, error, warning, info messages and validation errors
 */
class SessionMessageService
{
    /**
     * Extract all session messages
     * 
     * @return array Associative array of message types and their content
     */
    public function extract(): array
    {
        $messages = [
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
            'info' => session('info') ?: request()->input('completion_info')
        ];
        
        // Add validation errors if they exist
        if ($errors = session('errors')) {
            $errorMessages = $errors->all();
            if (!empty($errorMessages)) {
                $messages['error'] = implode(' ', $errorMessages);
            }
        }
        
        return $messages;
    }
}
