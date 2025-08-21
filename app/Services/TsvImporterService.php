<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\Ingredient;
use Carbon\Carbon;

class TsvImporterService
{
    public function import(string $tsvData, string $date): array
    {
        $date = Carbon::parse($date);
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $notFound = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = str_getcsv($row, "\t");

            if (count($columns) < 5) {
                continue;
            }

            $ingredient = Ingredient::where('name', $columns[2])->first();

            if ($ingredient) {
                $loggedAt = Carbon::parse($date->format('Y-m-d') . ' ' . $columns[1]);

                DailyLog::create([
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $ingredient->base_unit_id,
                    'quantity' => $columns[4],
                    'logged_at' => $loggedAt,
                    'notes' => $columns[3],
                ]);
                $importedCount++;
            } else {
                $notFound[] = $columns[2];
            }
        }

        return [
            'importedCount' => $importedCount,
            'notFound' => $notFound,
        ];
    }
}
