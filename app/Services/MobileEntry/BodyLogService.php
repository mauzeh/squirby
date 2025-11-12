<?php

namespace App\Services\MobileEntry;

use App\Models\BodyLog;
use App\Models\MeasurementType;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\MobileEntry\MobileEntryBaseService;
use App\Services\ComponentBuilder as C;

class BodyLogService extends MobileEntryBaseService
{


    /**
     * Generate logged items data for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateLoggedItems($userId, Carbon $selectedDate)
    {
        $logs = BodyLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->with(['measurementType'])
            ->orderBy('logged_at', 'desc')
            ->get();

        $itemsBuilder = C::items()
            ->confirmMessage('deleteItem', 'Are you sure you want to delete this measurement? This action cannot be undone.')
            ->confirmMessage('removeForm', 'Are you sure you want to remove this measurement from today\'s tracking?');
        
        $hasItems = false;
        foreach ($logs as $log) {
            if (!$log->measurementType) {
                continue;
            }

            $valueText = $log->value . ' ' . $log->measurementType->default_unit;

            $itemsBuilder->item(
                $log->id,
                $log->measurementType->name,
                null,
                route('body-logs.edit', ['body_log' => $log->id]),
                route('body-logs.destroy', ['body_log' => $log->id])
            )
            ->message('success', $valueText, 'Logged:')
            ->freeformText($log->comments ?? '')
            ->deleteParams([
                'redirect_to' => 'mobile-entry-measurements',
                'date' => $selectedDate->toDateString()
            ])
            ->add();
            
            $hasItems = true;
        }

        // Only include empty message when there are no items
        if (!$hasItems) {
            $itemsBuilder->emptyMessage(config('mobile_entry_messages.empty_states.no_measurements_logged', 'No measurements logged yet today!'));
        }

        return $itemsBuilder->build()['data'];
    }





    /**
     * Generate forms based on user's measurement types (only show unlogged ones)
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateForms($userId, Carbon $selectedDate)
    {
        $forms = [];
        
        // Get all measurement types for this user
        $measurementTypes = MeasurementType::where('user_id', $userId)
            ->orderBy('name', 'asc')
            ->get();
        
        // Get logged measurement type IDs for today to exclude them
        $loggedMeasurementTypeIds = BodyLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->pluck('measurement_type_id')
            ->toArray();
        
        foreach ($measurementTypes as $measurementType) {
            // Only show form if this measurement type hasn't been logged today
            if (!in_array($measurementType->id, $loggedMeasurementTypeIds)) {
                $form = $this->generateMeasurementForm($userId, $measurementType->id, $selectedDate);
                if ($form) {
                    $forms[] = $form;
                }
            }
        }
        
        return $forms;
    }

    /**
     * Generate a form for a specific measurement type
     * 
     * @param int $userId
     * @param int $measurementTypeId
     * @param Carbon $selectedDate
     * @return array|null
     */
    public function generateMeasurementForm($userId, $measurementTypeId, Carbon $selectedDate)
    {
        $measurementType = MeasurementType::where('id', $measurementTypeId)
            ->where('user_id', $userId)
            ->first();
            
        if (!$measurementType) {
            return null;
        }
        
        // Get last session data for this measurement type
        $lastSession = $this->getLastMeasurementSession($measurementType->id, $selectedDate, $userId);
        
        // Generate form ID
        $formId = 'measurement-type-' . $measurementType->id;
        
        // Determine default value (no today's log since we only show forms for unlogged measurements)
        $defaultValue = $lastSession['value'] ?? $this->getDefaultValue($measurementType->name);
        
        // Generate messages based on last session (no today's log)
        $messages = $this->generateMeasurementFormMessages($measurementType, $lastSession, null);
        
        // Determine increment based on measurement type
        $increment = $this->getValueIncrement($measurementType->name, $defaultValue);

        // Build form using ComponentBuilder
        $formBuilder = C::form($formId, $measurementType->name)
            ->type('warning')
            ->formAction(route('body-logs.store'));
        
        // Add messages
        foreach ($messages as $message) {
            $formBuilder->message($message['type'], $message['text'], $message['prefix'] ?? null);
        }
        
        // Build and customize form data
        $formData = $formBuilder->build();
        $formData['data']['deleteAction'] = null; // No delete action since this measurement hasn't been logged yet
        $formData['data']['numericFields'] = [
            [
                'id' => $formId . '-value',
                'name' => 'value',
                'label' => 'Value (' . $measurementType->default_unit . '):',
                'defaultValue' => round($defaultValue, 2),
                'increment' => $increment,
                'step' => 'any',
                'min' => 0,
                'max' => 1000,
                'ariaLabels' => [
                    'decrease' => 'Decrease value',
                    'increase' => 'Increase value'
                ]
            ],
            [
                'id' => $formId . '-comments',
                'name' => 'comments',
                'label' => 'Notes:',
                'type' => 'textarea',
                'placeholder' => 'Any additional notes...',
                'defaultValue' => '',
                'ariaLabels' => [
                    'field' => 'Notes'
                ]
            ]
        ];
        $formData['data']['buttons'] = [
            'decrement' => '-',
            'increment' => '+',
            'submit' => 'Log ' . $measurementType->name
        ];
        $formData['data']['ariaLabels'] = [
            'section' => $measurementType->name . ' entry'
        ];
        $formData['data']['hiddenFields'] = [
            'measurement_type_id' => $measurementType->id,
            'logged_at' => now()->format('H:i'),
            'date' => $selectedDate->toDateString(),
            'redirect_to' => 'mobile-entry-measurements'
        ];
        // Completion status (always pending since we only show unlogged measurements)
        $formData['data']['isCompleted'] = false;
        $formData['data']['completionStatus'] = 'pending';
        
        return $formData['data'];
    }

    /**
     * Get last session data for a measurement type
     * 
     * @param int $measurementTypeId
     * @param Carbon $beforeDate
     * @param int $userId
     * @return array|null
     */
    public function getLastMeasurementSession($measurementTypeId, Carbon $beforeDate, $userId)
    {
        $lastLog = BodyLog::where('user_id', $userId)
            ->where('measurement_type_id', $measurementTypeId)
            ->where('logged_at', '<', $beforeDate->toDateString())
            ->with(['measurementType'])
            ->orderBy('logged_at', 'desc')
            ->first();
        
        if (!$lastLog) {
            return null;
        }
        
        return [
            'value' => $lastLog->value,
            'unit' => $lastLog->measurementType->default_unit,
            'date' => $lastLog->logged_at->format('M j'),
            'comments' => $lastLog->comments
        ];
    }

    /**
     * Generate messages for a measurement form based on last session and today's log
     * 
     * @param \App\Models\MeasurementType $measurementType
     * @param array|null $lastSession
     * @param \App\Models\BodyLog|null $todayLog
     * @return array
     */
    public function generateMeasurementFormMessages($measurementType, $lastSession, $todayLog)
    {
        $messages = [];
        
        // If already logged today, show current value
        if ($todayLog) {
            $messages[] = [
                'type' => 'success',
                'prefix' => 'Today\'s value:',
                'text' => $todayLog->value . ' ' . $measurementType->default_unit
            ];
        }
        
        // Add last session info if available and not today
        if ($lastSession && !$todayLog) {
            $messageText = $lastSession['value'] . ' ' . $lastSession['unit'];
            
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Last logged (' . $lastSession['date'] . '):',
                'text' => $messageText
            ];
        }
        
        // Add last session comments if available
        if ($lastSession && !empty($lastSession['comments']) && !$todayLog) {
            $messages[] = [
                'type' => 'neutral',
                'prefix' => 'Last notes:',
                'text' => $lastSession['comments']
            ];
        }
        
        // Add helpful tips based on measurement type
        $tips = $this->getMeasurementTips($measurementType->name);
        if ($tips && !$todayLog) {
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Tip:',
                'text' => $tips
            ];
        }
        
        return $messages;
    }

    /**
     * Get default value for a measurement type
     * 
     * @param string $measurementTypeName
     * @return float
     */
    protected function getDefaultValue($measurementTypeName)
    {
        $defaults = [
            'weight' => 150,
            'body fat' => 15,
            'muscle mass' => 120,
            'height' => 70,
            'waist' => 32,
            'chest' => 40,
            'arms' => 14,
            'thighs' => 22,
        ];
        
        $lowerName = strtolower($measurementTypeName);
        
        foreach ($defaults as $key => $value) {
            if (str_contains($lowerName, $key)) {
                return $value;
            }
        }
        
        return 100; // Default fallback
    }

    /**
     * Get appropriate value increment based on measurement type
     * 
     * @param string $measurementTypeName
     * @param float $defaultValue
     * @return float
     */
    protected function getValueIncrement($measurementTypeName, $defaultValue = 1)
    {
        $lowerName = strtolower($measurementTypeName);
        
        // Weight measurements - use 0.1 for precision
        if (str_contains($lowerName, 'weight') || str_contains($lowerName, 'mass')) {
            return 0.1;
        }
        
        // Body fat percentage - use 0.1 for precision
        if (str_contains($lowerName, 'fat') || str_contains($lowerName, '%')) {
            return 0.1;
        }
        
        // Circumference measurements - use 0.1 for precision
        if (str_contains($lowerName, 'waist') || str_contains($lowerName, 'chest') || 
            str_contains($lowerName, 'arm') || str_contains($lowerName, 'thigh') ||
            str_contains($lowerName, 'neck') || str_contains($lowerName, 'hip')) {
            return 0.1;
        }
        
        // Height measurements - use 0.1 for precision
        if (str_contains($lowerName, 'height')) {
            return 0.1;
        }
        
        // Default increment
        return 0.5;
    }

    /**
     * Get helpful tips for measurement types
     * 
     * @param string $measurementTypeName
     * @return string|null
     */
    protected function getMeasurementTips($measurementTypeName)
    {
        $tips = [
            'weight' => 'Weigh yourself at the same time each day, preferably in the morning',
            'body fat' => 'Measure under consistent conditions for accurate tracking',
            'waist' => 'Measure at the narrowest point, usually just above the belly button',
            'chest' => 'Measure around the fullest part of your chest',
            'arms' => 'Measure around the largest part of your upper arm',
            'thighs' => 'Measure around the largest part of your thigh',
        ];
        
        $lowerName = strtolower($measurementTypeName);
        
        foreach ($tips as $key => $tip) {
            if (str_contains($lowerName, $key)) {
                return $tip;
            }
        }
        
        return null;
    }





    /**
     * Generate contextual help messages based on user's current state
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateContextualHelpMessages($userId, Carbon $selectedDate)
    {
        $messages = [];
        
        // Check if user has any measurement types
        $measurementTypeCount = MeasurementType::where('user_id', $userId)->count();
        
        // Check if user has logged anything today
        $loggedCount = BodyLog::where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->count();
        
        // Count remaining (unlogged) measurement types
        $remainingCount = $measurementTypeCount - $loggedCount;
        
        if ($measurementTypeCount === 0) {
            // No measurement types created yet
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Getting started:',
                'text' => 'Create measurement types in the "Types" section to start tracking your progress.'
            ];
        } elseif ($loggedCount === 0) {
            // Has measurement types but hasn't logged anything today - no message needed
        } elseif ($remainingCount > 0) {
            // Has logged some but not all
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Keep going:',
                'text' => "Great progress! You have {$remainingCount} more measurement" . ($remainingCount > 1 ? 's' : '') . " to log."
            ];
        } else {
            // All measurements completed for today
            $messages[] = [
                'type' => 'success',
                'prefix' => 'Complete:',
                'text' => 'All measurements logged for today! Great consistency.'
            ];
        }
        
        return $messages;
    }
}