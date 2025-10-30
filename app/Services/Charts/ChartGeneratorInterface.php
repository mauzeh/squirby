<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

interface ChartGeneratorInterface
{
    /**
     * Generate chart data for the given lift logs
     */
    public function generate(Collection $liftLogs): array;

    /**
     * Check if this generator supports the given exercise type
     */
    public function supports(string $exerciseType): bool;
}