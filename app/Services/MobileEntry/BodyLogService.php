<?php

namespace App\Services\MobileEntry;

use App\Models\BodyLog;
use App\Models\MeasurementType;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BodyLogService
{
    /**
     * Generate summary data based on user's body logs for the selected date
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return array
     */
    public function generateSummary($userId, Carbon $selectedDate)
    {
        $bodyLogs = BodyLog::with(['measurementType'])
            ->where('user_id', $userId)
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->get();

        // Count of measurements today
        $entriesCount = $bodyLogs->count();
        
        // Get total measurement types for this user
        $totalMeasurementTypes = MeasurementType::where('user_id', $userId)->count();
        
        // Calculate completion percentage
        $completionPercentage = $totalMeasurementTypes > 0 ? round(($entriesCount / $totalMeasurementTypes) * 100) : 0;
        
        // Get streak (consecutive days with at least one measurement)
        $streak = $this->getConsecutiveDaysStreak($userId, $selectedDate);

        return [
            'values' => [
                'total' => $entriesCount,
                'completed' => $totalMeasurementTypes,
                'average' => $completionPercentage,
                'today' => $streak
            ],
            'labels' => [
                'total' => 'Logged',
                'completed' => 'Total Types',
                'average' => 'Complete %',
                'today' => 'Day Streak'
            ],
            'ariaLabels' => [
                'section' => 'Daily measurements summary'
            ]
        ];
    }

    /**
     * Get consecutive days streak with at least one measurement
     * 
     * @param int $userId
     * @param Carbon $selectedDate
     * @return int
     */
    protected function getConsecutiveDaysStreak($userId, Carbon $selectedDate)
    {
        $streak = 0;
        $currentDate = $selectedDate->copy();
        
        // Check backwards from selected date
        while ($streak < 30) { // Limit to prevent infinite loops
            $hasEntry = BodyLog::where('user_id', $userId)
                ->whereDate('logged_at', $currentDate->toDateString())
                ->exists();
                
            if (!$hasEntry) {
                break;
            }
            
            $streak++;
            $currentDate->subDay();
        }
        
        return $streak;
    }

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

        $items = [];
        foreach ($logs as $log) {
            if (!$log->measurementType) {
                continue;
            }

            $valueText = $log->value . ' ' . $log->measurementType->default_unit;

            $items[] = [
                'id' => $log->id,
                'title' => $log->measurementType->name,
                'editAction' => route('body-logs.edit', ['body_log' => $log->id]),
                'deleteAction' => route('body-logs.destroy', ['body_log' => $log->id]),
                'deleteParams' => [
                    'redirect_to' => 'mobile-entry-measurements',
                    'date' => $selectedDate->toDateString()
                ],
                'message' => [
                    'type' => 'success',
                    'prefix' => 'Logged:',
                    'text' => $valueText
                ],
                'freeformText' => $log->comments
            ];
        }

        $result = [
            'items' => $items,
            'confirmMessages' => [
                'deleteItem' => 'Are you sure you want to delete this measurement? This action cannot be undone.',
                'removeForm' => 'Are you sure you want to remove this measurement from today\'s tracking?'
            ],
            'ariaLabels' => [
                'section' => 'Logged measurements',
                'editItem' => 'Edit logged measurement',
                'deleteItem' => 'Delete logged measurement'
            ]
        ];

        // Only include empty message when there are no items
        if (empty($items)) {
            $result['emptyMessage'] = config('mobile_entry_messages.empty_states.no_measurements_logged', 'No measurements logged yet today!');
        }

        return $result;
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

        return [
            'id' => $formId,
            'type' => 'measurement',
            'title' => $measurementType->name,
            'itemName' => $measurementType->name,
            'formAction' => route('body-logs.store'),
            'deleteAction' => null, // No delete action since this measurement hasn't been logged yet
            'messages' => $messages,
            'numericFields' => [
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
                ]
            ],
            'commentField' => [
                'id' => $formId . '-comments',
                'name' => 'comments',
                'label' => 'Notes:',
                'placeholder' => 'Any additional notes...',
                'defaultValue' => ''
            ],
            'buttons' => [
                'decrement' => '-',
                'increment' => '+',
                'submit' => 'Log ' . $measurementType->name
            ],
            'ariaLabels' => [
                'section' => $measurementType->name . ' entry',
                'deleteForm' => 'Remove this measurement form'
            ],
            // Hidden fields for form submission
            'hiddenFields' => [
                'measurement_type_id' => $measurementType->id,
                'logged_at' => now()->format('H:i'),
                'date' => $selectedDate->toDateString(),
                'redirect_to' => 'mobile-entry-measurements'
            ],
            // Completion status (always pending since we only show unlogged measurements)
            'isCompleted' => false,
            'completionStatus' => 'pending'
        ];
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
     * Create a new measurement type
     * 
     * @param int $userId
     * @param string $measurementTypeName
     * @param Carbon $selectedDate
     * @return array Result with success/error status and message
     */
    public function createMeasurementType($userId, $measurementTypeName, Carbon $selectedDate)
    {
        // Check if measurement type with similar name already exists
        $existingMeasurementType = MeasurementType::where('name', $measurementTypeName)
            ->where('user_id', $userId)
            ->first();
        
        if ($existingMeasurementType) {
            return [
                'success' => false,
                'message' => "Measurement type '{$measurementTypeName}' already exists."
            ];
        }
        
        // Determine default unit based on name
        $defaultUnit = $this->getDefaultUnit($measurementTypeName);
        
        // Create the new measurement type
        $measurementType = MeasurementType::create([
            'name' => $measurementTypeName,
            'user_id' => $userId,
            'default_unit' => $defaultUnit
        ]);
        
        return [
            'success' => true,
            'message' => "Created new measurement type: {$measurementType->name}. You can now log it below."
        ];
    }

    /**
     * Get default unit for a measurement type based on its name
     * 
     * @param string $measurementTypeName
     * @return string
     */
    protected function getDefaultUnit($measurementTypeName)
    {
        $lowerName = strtolower($measurementTypeName);
        
        // Weight-related measurements
        if (str_contains($lowerName, 'weight') || str_contains($lowerName, 'mass')) {
            return 'lbs';
        }
        
        // Percentage measurements
        if (str_contains($lowerName, 'fat') || str_contains($lowerName, '%') || str_contains($lowerName, 'percent')) {
            return '%';
        }
        
        // Length/circumference measurements
        if (str_contains($lowerName, 'waist') || str_contains($lowerName, 'chest') || 
            str_contains($lowerName, 'arm') || str_contains($lowerName, 'thigh') ||
            str_contains($lowerName, 'neck') || str_contains($lowerName, 'hip') ||
            str_contains($lowerName, 'height')) {
            return 'in';
        }
        
        // Default to generic unit
        return 'units';
    }

    /**
     * Generate interface messages from session data
     * 
     * @param array $sessionMessages
     * @return array
     */
    public function generateInterfaceMessages($sessionMessages = [])
    {
        $systemMessages = $this->generateSystemMessages($sessionMessages);
        
        return [
            'messages' => $systemMessages,
            'hasMessages' => !empty($systemMessages),
            'messageCount' => count($systemMessages)
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
        
        if (isset($sessionMessages['success'])) {
            $messages[] = [
                'type' => 'success',
                'prefix' => 'Success:',
                'text' => $sessionMessages['success']
            ];
        }
        
        if (isset($sessionMessages['error'])) {
            $messages[] = [
                'type' => 'error',
                'prefix' => 'Error:',
                'text' => $sessionMessages['error']
            ];
        }
        
        if (isset($sessionMessages['warning'])) {
            $messages[] = [
                'type' => 'warning',
                'prefix' => 'Warning:',
                'text' => $sessionMessages['warning']
            ];
        }
        
        if (isset($sessionMessages['info'])) {
            $messages[] = [
                'type' => 'info',
                'prefix' => 'Info:',
                'text' => $sessionMessages['info']
            ];
        }
        
        return $messages;
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
                'text' => 'Create measurement types like "Weight" or "Body Fat %" to start tracking your progress.'
            ];
        } elseif ($loggedCount === 0) {
            // Has measurement types but hasn't logged anything today
            $messages[] = [
                'type' => 'tip',
                'prefix' => 'Ready to log:',
                'text' => "You have {$measurementTypeCount} measurement type" . ($measurementTypeCount > 1 ? 's' : '') . " ready to log below."
            ];
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