<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

class LiftLogService
{
    /**
     * Preload bodyweight measurements to avoid N+1 queries in OneRepMaxCalculatorService
     */
    public function preloadBodyweightMeasurements(Collection $liftLogs, int $userId): void
    {
        // Get all unique dates from lift logs
        $dates = $liftLogs->pluck('logged_at')->map(function($date) {
            return $date->toDateString();
        })->unique()->values();

        if ($dates->isEmpty()) {
            return;
        }

        // Get all bodyweight measurements up to the latest date
        $latestDate = $dates->max();
        $bodyweightMeasurements = \App\Models\BodyLog::where('user_id', $userId)
            ->whereHas('measurementType', function ($query) {
                $query->where('name', 'Bodyweight');
            })
            ->whereDate('logged_at', '<=', $latestDate)
            ->orderBy('logged_at', 'desc')
            ->get()
            ->keyBy(function($measurement) {
                return $measurement->logged_at->toDateString();
            });

        // Cache bodyweight measurements on each lift log to avoid repeated queries
        foreach ($liftLogs as $liftLog) {
            $logDate = $liftLog->logged_at->toDateString();
            
            // Find the most recent bodyweight measurement on or before this date
            $bodyweightMeasurement = null;
            foreach ($bodyweightMeasurements as $measurementDate => $measurement) {
                if ($measurementDate <= $logDate) {
                    $bodyweightMeasurement = $measurement;
                    break;
                }
            }
            
            // Cache the bodyweight value on the lift log
            $liftLog->cached_bodyweight = $bodyweightMeasurement ? $bodyweightMeasurement->value : 0;
        }
    }
}