<?php

namespace App\Services\MobileEntry;

abstract class MobileEntryBaseService
{
    /**
     * Generate interface messages from session data
     * 
     * @param array $sessionMessages
     * @return array
     */
    public function generateInterfaceMessages($sessionMessages = [])
    {
        $systemMessages = $this->generateSystemMessages($sessionMessages);
        
        // Filter out messages with empty text.
        // Some actions, like adding an existing item, intentionally return an empty success message
        // to provide a smoother user experience. This filtering prevents empty message
        // containers from being rendered in the view.
        $filteredMessages = array_filter($systemMessages, function ($message) {
            return !empty($message['text']);
        });

        return [
            'messages' => array_values($filteredMessages), // Re-index the array
            'hasMessages' => !empty($filteredMessages),
            'messageCount' => count($filteredMessages)
        ];
    }

    /**
     * Generate system messages from session flash data
     * 
     * @param array $sessionMessages
     * @return array
     */
    private function generateSystemMessages($sessionMessages)
    {
        $messages = [];
        
        if (isset($sessionMessages['success']) && !empty($sessionMessages['success'])) {
            $messages[] = [
                'type' => 'success',
                'text' => $sessionMessages['success']
            ];
        }
        
        if (isset($sessionMessages['error']) && !empty($sessionMessages['error'])) {
            $messages[] = [
                'type' => 'error',
                'text' => $sessionMessages['error']
            ];
        }
        
        if (isset($sessionMessages['warning']) && !empty($sessionMessages['warning'])) {
            $messages[] = [
                'type' => 'warning',
                'text' => $sessionMessages['warning']
            ];
        }
        
        if (isset($sessionMessages['info']) && !empty($sessionMessages['info'])) {
            $messages[] = [
                'type' => 'info',
                'text' => $sessionMessages['info']
            ];
        }
        
        return $messages;
    }
}
