<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DateNavigationService
{
    /**
     * Get date navigation data for a given date and model
     *
     * @param Carbon $selectedDate The currently selected date
     * @param string $modelClass The model class to check for last record
     * @param int $userId The user ID to filter records
     * @param string $routeName The route name for navigation links
     * @return array Navigation data including dates, today info, and last record date
     */
    public function getNavigationData(Carbon $selectedDate, string $modelClass, int $userId, string $routeName): array
    {
        $today = Carbon::today();
        $todayInRange = false;
        
        // Generate the three-day window (yesterday, selected, tomorrow)
        $navigationDates = [];
        for ($i = -1; $i <= 1; $i++) {
            $date = $selectedDate->copy()->addDays($i);
            $dateString = $date->toDateString();
            
            if ($date->isSameDay($today)) {
                $todayInRange = true;
            }
            
            $navigationDates[] = [
                'date' => $date,
                'dateString' => $dateString,
                'isSelected' => $selectedDate->toDateString() === $dateString,
                'isToday' => $date->isSameDay($today),
                'label' => $date->isSameDay($today) ? 'Today' : $date->format('D M d'),
                'url' => route($routeName, ['date' => $dateString])
            ];
        }
        
        // Get the last record date
        $lastRecordDate = null;
        if (class_exists($modelClass)) {
            $model = new $modelClass;
            
            // Check if model has 'logged_at' or 'date' field
            if (in_array('logged_at', $model->getFillable()) || $model->hasGetMutator('logged_at')) {
                $lastRecord = $modelClass::where('user_id', $userId)
                    ->orderBy('logged_at', 'desc')
                    ->first();
                $lastRecordDate = $lastRecord?->logged_at?->toDateString();
            } elseif (in_array('date', $model->getFillable()) || $model->hasGetMutator('date')) {
                $lastRecord = $modelClass::where('user_id', $userId)
                    ->orderBy('date', 'desc')
                    ->first();
                $lastRecordDate = $lastRecord?->date?->toDateString();
            }
        }
        
        return [
            'selectedDate' => $selectedDate,
            'today' => $today,
            'todayInRange' => $todayInRange,
            'navigationDates' => $navigationDates,
            'lastRecordDate' => $lastRecordDate,
            'routeName' => $routeName,
            'showLastRecordButton' => $lastRecordDate && $selectedDate->toDateString() !== $lastRecordDate,
            'showTodayButton' => !$todayInRange,
            'todayUrl' => route($routeName, ['date' => $today->toDateString()]),
            'lastRecordUrl' => $lastRecordDate ? route($routeName, ['date' => $lastRecordDate]) : null
        ];
    }
    
    /**
     * Parse date from request or return today
     *
     * @param mixed $dateInput Date input from request
     * @return Carbon
     */
    public function parseSelectedDate($dateInput): Carbon
    {
        return $dateInput ? Carbon::parse($dateInput) : Carbon::today();
    }
}