<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

/**
 * Hold Duration Progression Chart Generator
 * 
 * Generates chart data for static hold exercises showing total time under tension.
 * Calculates volume as: duration × sets (total seconds held per workout)
 */
class HoldDurationProgressionChartGenerator implements ChartGeneratorInterface
{
    public function generate(Collection $liftLogs): array
    {
        $volumeData = $liftLogs->map(function ($liftLog) {
            // For static holds: reps = duration in seconds
            // Volume = duration × number of sets (total time under tension)
            $duration = $liftLog->liftSets->first()->reps ?? 0;
            $sets = $liftLog->liftSets->count();
            $totalDuration = $duration * $sets;
            
            return [
                'x' => $liftLog->logged_at->toIso8601String(),
                'y' => $totalDuration,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Total Duration (seconds)',
                    'data' => $volumeData->values()->toArray(),
                    'backgroundColor' => 'rgba(153, 102, 255, 0.5)',
                    'borderColor' => 'rgba(153, 102, 255, 1)',
                    'borderWidth' => 2,
                    'fill' => false
                ]
            ]
        ];
    }

    public function supports(string $exerciseType): bool
    {
        return $exerciseType === 'hold_duration_progression';
    }
}
