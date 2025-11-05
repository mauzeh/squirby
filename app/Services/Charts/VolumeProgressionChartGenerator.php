<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class VolumeProgressionChartGenerator implements ChartGeneratorInterface
{
    public function generate(Collection $liftLogs): array
    {
        $volumeData = $liftLogs->map(function ($liftLog) {
            $totalReps = $liftLog->liftSets->sum('reps');
            return [
                'x' => $liftLog->logged_at->toIso8601String(),
                'y' => $totalReps,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Total Reps',
                    'data' => $volumeData->values()->toArray(),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'borderWidth' => 2,
                    'fill' => false
                ]
            ]
        ];
    }

    public function supports(string $exerciseType): bool
    {
        return in_array($exerciseType, ['bodyweight', 'bodyweight_progression', 'volume_progression']);
    }
}