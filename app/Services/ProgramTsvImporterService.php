<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Exercise;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ProgramTsvImporterService
{
    public function import(string $tsvContent, int $userId, Carbon $dateForImport)
    {
        $lines = array_filter(explode("\n", $tsvContent));
        if (empty($lines)) {
            return ['importedCount' => 0, 'notFound' => [], 'invalidRows' => []];
        }

        $importedCount = 0;
        $notFound = [];
        $invalidRows = [];

        foreach ($lines as $lineNumber => $line) {
            $columns = array_map('trim', str_getcsv($line, "\t"));

            // Expected columns: exercise_title, sets, reps, priority, comments
            if (count($columns) < 5) {
                $invalidRows[] = $line;
                continue;
            }

            // Find exercise by title for the current user (case-insensitive)
            $exercise = Exercise::where('user_id', $userId)
                                ->whereRaw('LOWER(title) = ?', [strtolower($columns[0])])
                                ->first();

            if (!$exercise) {
                $notFound[] = $columns[0];
                continue;
            }

            $validator = Validator::make([
                'exercise_id' => $exercise->id,
                'date' => $dateForImport->toDateString(),
                'sets' => $columns[1] ?? null,
                'reps' => $columns[2] ?? null,
                'priority' => $columns[3] ?? null,
                'comments' => $columns[4] ?? null,
            ], [
                'exercise_id' => ['required', 'exists:exercises,id'],
                'date' => ['required', 'date'],
                'sets' => ['required', 'integer', 'min:1'],
                'reps' => ['required', 'integer', 'min:1'],
                'priority' => ['nullable', 'integer', 'min:0'],
                'comments' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $invalidRows[] = $line . ' - ' . implode(", ", $validator->errors()->all());
                continue;
            }

            Program::create([
                'user_id' => $userId,
                'exercise_id' => $exercise->id,
                'date' => $dateForImport,
                'sets' => $columns[1],
                'reps' => $columns[2],
                'priority' => $columns[3] ?? 0,
                'comments' => $columns[4] ?? null,
            ]);

            $importedCount++;
        }

        return [
            'importedCount' => $importedCount,
            'notFound' => $notFound,
            'invalidRows' => $invalidRows,
        ];
    }
}