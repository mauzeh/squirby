<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\Ingredient;
use App\Models\MeasurementLog;
use Carbon\Carbon;

class TsvImporterService
{
    public function importDailyLogs(string $tsvData, string $date): array
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

    public function importWorkouts(string $tsvData, string $date): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $notFound = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = str_getcsv($row, "\t");

            if (count($columns) < 7) {
                continue;
            }

            $exercise = \App\Models\Exercise::where('title', $columns[2])->first();

            if ($exercise) {
                $loggedAt = Carbon::createFromFormat('Y-m-d H:i', $columns[0] . ' ' . $columns[1]);

                $workout = \App\Models\Workout::create([
                    'exercise_id' => $exercise->id,
                    'comments' => $columns[6],
                    'logged_at' => $loggedAt,
                ]);

                // Create WorkoutSet records based on rounds
                $weight = $columns[3];
                $reps = $columns[4];
                $rounds = $columns[5];
                $notes = $columns[6];

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
        ];
    }

    public function importMeasurements(string $tsvData): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = str_getcsv($row, "\t");

            if (count($columns) < 5) {
                continue;
            }

            $measurementType = \App\Models\MeasurementType::firstOrCreate([
                'name' => $columns[2],
                'default_unit' => $columns[4],
            ]);

            $loggedAt = Carbon::createFromFormat('m/d/Y H:i', $columns[0] . ' ' . $columns[1]);

            \App\Models\MeasurementLog::create([
                'measurement_type_id' => $measurementType->id,
                'value' => $columns[3],
                'comments' => $columns[5] ?? null,
                'logged_at' => $loggedAt,
            ]);
            $importedCount++;
        }

        return [
            'importedCount' => $importedCount,
        ];
    }
}
