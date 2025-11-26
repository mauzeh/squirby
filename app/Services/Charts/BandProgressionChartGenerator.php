<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class BandProgressionChartGenerator implements ChartGeneratorInterface
{
    use HasTrendLine;

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
        })->values()->toArray();

        $datasets = [
            [
                'label' => 'Total Reps',
                'data' => $volumeData,
                'backgroundColor' => 'rgba(153, 102, 255, 0.5)',
                'borderColor' => 'rgba(153, 102, 255, 1)',
                'borderWidth' => 2,
                'fill' => false
            ]
        ];

        // Add trend line if we have at least 2 data points
        if (count($volumeData) >= 2) {
            $trendLineData = $this->calculateTrendLine($volumeData);
            if (!empty($trendLineData)) {
                $datasets[] = $this->createTrendLineDataset($trendLineData);
            }
        }

        return ['datasets' => $datasets];
    }

    public function supports(string $exerciseType): bool
    {
        return in_array($exerciseType, ['banded', 'volume_progression', 'band_progression']);
    }
}