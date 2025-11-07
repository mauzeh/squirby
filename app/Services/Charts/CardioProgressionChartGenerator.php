<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class CardioProgressionChartGenerator implements ChartGeneratorInterface
{
    public function generate(Collection $liftLogs): array
    {
        $distanceData = $liftLogs->map(function ($liftLog) {
            // For cardio exercises, calculate total distance (distance per round × number of rounds)
            $distancePerRound = $liftLog->display_reps;
            $rounds = $liftLog->liftSets->count();
            $totalDistance = $distancePerRound * $rounds;
            
            return [
                'x' => $liftLog->logged_at->toIso8601String(),
                'y' => $totalDistance,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Total Distance (m)',
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