<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Exercise;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ProgramTsvImporterService
{
    public function import(string $tsvContent, int $userId)
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

            // Expected columns: date, exercise_title, sets, reps, priority, comments
            if (count($columns) < 5) {
                $invalidRows[] = $line;
                continue;
            }

            // Find exercise by title for the current user (case-insensitive)
            $exercise = Exercise::where('user_id', $userId)
                                ->whereRaw('LOWER(title) = ?', [strtolower($columns[1])])
                                ->first();

            if (!$exercise) {
                $notFound[] = $columns[1];
                continue;
            }

            $validator = Validator::make([
                'date' => $columns[0],
                'exercise_id' => $exercise->id,
                'sets' => $columns[2] ?? null,
                'reps' => $columns[3] ?? null,
                'priority' => $columns[4] ?? null,
                'comments' => $columns[5] ?? null,
            ], [
                'date' => ['required', 'date'],
                'exercise_id' => ['required', 'exists:exercises,id'],
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
                'date' => Carbon::parse($columns[0]),
                'exercise_id' => $exercise->id,
                'sets' => $columns[2],
                'reps' => $columns[3],
                'priority' => $columns[4] ?? 0,
                'comments' => $columns[5] ?? null,
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