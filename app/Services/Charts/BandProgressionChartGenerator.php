<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class BandProgressionChartGenerator implements ChartGeneratorInterface
{
    public function generate(Collection $liftLogs): array
    {
        // For banded exercises, track total reps similar to bodyweight
        // but could be enhanced to show band color progression
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
        return in_array($exerciseType, ['banded', 'volume_progression']);
    }
}