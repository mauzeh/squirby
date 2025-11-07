<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class CardioProgressionChartGenerator implements ChartGeneratorInterface
{
    public function generate(Collection $liftLogs): array
    {
        $distanceData = $liftLogs->map(function ($liftLog) {
            // For cardio exercises, distance is stored in the reps field
            $distance = $liftLog->display_reps;
            return [
                'x' => $liftLog->logged_at->toIso8601String(),
                'y' => $distance,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Distance (m)',
                    'data' => $distanceData->values()->toArray(),
                    'backgroundColor' => 'rgba(255, 159, 64, 0.5)',
                    'borderColor' => 'rgba(255, 159, 64, 1)',
                    'borderWidth' => 2,
                    'fill' => false
                ]
            ]
        ];
    }

    public function supports(string $exerciseType): bool
    {
        return in_array($exerciseType, ['cardio_progression', 'cardio']);
    }
}