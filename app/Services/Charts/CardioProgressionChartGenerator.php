<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class CardioProgressionChartGenerator implements ChartGeneratorInterface
{
    use HasTrendLine;

    public function generate(Collection $liftLogs): array
    {
        $distanceData = $liftLogs->map(function ($liftLog) {
            // For cardio exercises, calculate total distance
            $totalDistance = $liftLog->liftSets->sum('distance');
            
            return [
                'x' => $liftLog->logged_at->toIso8601String(),
                'y' => (float) $totalDistance,
            ];
        })->values()->toArray();

        $datasets = [
            [
                'label' => 'Total Distance (m)',
                'data' => $distanceData,
                'backgroundColor' => 'rgba(255, 159, 64, 0.5)',
                'borderColor' => 'rgba(255, 159, 64, 1)',
                'borderWidth' => 2,
                'fill' => false
            ]
        ];

        // Add trend line if we have at least 2 data points
        if (count($distanceData) >= 2) {
            $trendLineData = $this->calculateTrendLine($distanceData);
            if (!empty($trendLineData)) {
                $datasets[] = $this->createTrendLineDataset($trendLineData);
            }
        }

        return ['datasets' => $datasets];
    }

    public function supports(string $exerciseType): bool
    {
        return in_array($exerciseType, ['cardio_progression', 'cardio']);
    }
}