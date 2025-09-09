<?php

namespace App\Services;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\BodyLog;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

class TsvImporterService
{
    public function importFoodLogs(string $tsvData, string $date, int $userId): array
    {
        $date = Carbon::parse($date);
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $notFound = [];
        $invalidRows = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = array_map('trim', str_getcsv($row, "\t"));

            if (count($columns) < 5) {
                $invalidRows[] = $row;
                continue;
            }

            $ingredient = Ingredient::where('name', $columns[2])->first();

            if ($ingredient) {
                $loggedAt = $this->parseDate($date->format('Y-m-d') . ' ' . $columns[1]);

                if (!$loggedAt) {
                    $invalidRows[] = $row;
                    continue;
                }

                FoodLog::create([
                    'user_id' => $userId,
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
            'invalidRows' => $invalidRows,
        ];
    }

    public function importWorkouts(string $tsvData, string $date, int $userId): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $notFound = [];
        $invalidRows = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = array_map('trim', str_getcsv($row, "\t"));

            if (count($columns) < 6) {
                $invalidRows[] = $row;
                continue;
            }

            $exercise = \App\Models\Exercise::where('user_id', $userId)->whereRaw('LOWER(title) = ?', [strtolower($columns[2])])->first();

            if ($exercise) {
                $loggedAt = $this->parseDate($columns[0] . ' ' . $columns[1]);

                if (!$loggedAt) {
                    $invalidRows[] = $row;
                    continue;
                }

                $workout = \App\Models\Workout::create([
                    'user_id' => $userId,
                    'exercise_id' => $exercise->id,
                    'comments' => isset($columns[6]) ? $columns[6] : null,
                    'logged_at' => $loggedAt,
                ]);

                // Create WorkoutSet records based on rounds
                $weight = $columns[3];
                $reps = $columns[4];
                $rounds = $columns[5];
                $notes = isset($columns[6]) ? $columns[6] : null;

                for ($i = 0; $i < $rounds; $i++) {
                    $workout->workoutSets()->create([
                        'weight' => $weight,
                        'reps' => $reps,
                        'notes' => $notes,
                    ]);
                }
                $importedCount++;
            } else {
                $notFound[] = $columns[2];
            }
        }

        return [
            'importedCount' => $importedCount,
            'notFound' => $notFound,
            'invalidRows' => $invalidRows,
        ];
    }

    private function parseDate(string $dateString): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::createFromFormat('m/d/Y H:i', $dateString);
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::createFromFormat('Y-m-d H:i', $dateString);
            } catch (\Exception $e) {
                return null;
            }
        }
    }

    public function importMeasurements(string $tsvData, int $userId): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $invalidRows = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = array_map('trim', str_getcsv($row, "\t"));

            if (count($columns) < 5) {
                $invalidRows[] = $row;
                continue;
            }

            $measurementType = \App\Models\MeasurementType::firstOrCreate([
                'name' => $columns[2],
                'default_unit' => $columns[4],
            ]);

            $loggedAt = $this->parseDate($columns[0] . ' ' . $columns[1]);

            if (!$loggedAt) {
                $invalidRows[] = $row;
                continue;
            }

            BodyLog::create([
                'user_id' => $userId,
                'measurement_type_id' => $measurementType->id,
                'value' => $columns[3],
                'comments' => $columns[5] ?? null,
                'logged_at' => $loggedAt,
            ]);
            $importedCount++;
        }

        return [
            'importedCount' => $importedCount,
            'invalidRows' => $invalidRows,
        ];
    }
}
