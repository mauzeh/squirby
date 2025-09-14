<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Models\Unit;
use Illuminate\Support\Facades\Log;

class IngredientTsvProcessorService
{
    public function processTsv(string $tsvData, array $expectedHeader, callable $rowProcessor): array
    {
        $rows = explode("\n", $tsvData);
        $header = array_map('trim', str_getcsv(array_shift($rows), "\t"));
        $processedCount = 0;
        $invalidRows = [];
        $errors = [];

        if ($header !== $expectedHeader) {
            Log::error('Header mismatch:');
            Log::error('Expected: ' . implode(', ', $expectedHeader));
            Log::error('Actual: ' . implode(', ', $header));
            return [
                'processedCount' => 0,
                'invalidRows' => [],
                'errors' => ['Invalid TSV header. Please make sure the columns are in the correct order.']
            ];
        }

        foreach ($rows as $row) {
            if (empty(trim($row))) {
                continue;
            }

            $values = array_map('trim', str_getcsv($row, "\t"));
            if (count($header) !== count($values)) {
                $invalidRows[] = $row;
                continue;
            }

            $rowData = array_combine($header, $values);

            try {
                Log::info('Processing row: ' . json_encode($rowData));
                $rowProcessor($rowData);
                $processedCount++;
            } catch (\Exception $e) {
                $invalidRows[] = $row . ' - ' . $e->getMessage();
            }
        }

        return [
            'processedCount' => $processedCount,
            'invalidRows' => $invalidRows,
            'errors' => $errors,
        ];
    }

    public function getUnitFromAbbreviation(string $abbreviation): ?Unit
    {
        $units = Unit::all()->keyBy('abbreviation');

        $unitMapping = [
            'gram' => 'g',
            'tbsp' => 'tbsp',
            'tsp' => 'tsp',
            'ml' => 'ml',
            'egg (L)' => 'pc',
            'apple (S)' => 'pc',
            'slice' => 'pc',
            'Pita' => 'pc',
            'can' => 'pc',
            'bottle' => 'pc',
            'shot' => 'pc',
            'raspberries' => 'pc',
        ];

        $unitAbbreviation = $unitMapping[$abbreviation] ?? $abbreviation;

        return $units[$unitAbbreviation] ?? null;
    }
}
