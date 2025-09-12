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
        if (count($lines) <= 1) { // Only header or empty content
            return ['success' => false, 'message' => 'TSV content is empty or only contains headers.'];
        }

        $header = array_map('trim', explode("\t", array_shift($lines)));
        $expectedHeaders = ['exercise_title', 'sets', 'reps', 'priority', 'comments'];

        // Basic header validation
        if (array_diff($expectedHeaders, $header)) {
            return ['success' => false, 'message' => 'Missing required headers. Expected: ' . implode(', ', $expectedHeaders)];
        }

        $importedCount = 0;
        $errors = [];

        foreach ($lines as $lineNumber => $line) {
            $data = array_map('trim', explode("\t", $line));
            if (count($data) !== count($header)) {
                $errors[] = "Line " . ($lineNumber + 2) . ": Column count mismatch.";
                continue;
            }

            $rowData = array_combine($header, $data);

            // Find exercise by title for the current user
            $exercise = Exercise::where('user_id', $userId)
                                ->where('title', $rowData['exercise_title'])
                                ->first();

            if (!$exercise) {
                $errors[] = "Line " . ($lineNumber + 2) . ": Exercise '" . $rowData['exercise_title'] . "' not found for user.";
                continue;
            }

            $validator = Validator::make([
                'exercise_id' => $exercise->id,
                'date' => $dateForImport->toDateString(),
                'sets' => $rowData['sets'] ?? null,
                'reps' => $rowData['reps'] ?? null,
                'priority' => $rowData['priority'] ?? null,
                'comments' => $rowData['comments'] ?? null,
            ], [
                'exercise_id' => ['required', 'exists:exercises,id'],
                'date' => ['required', 'date'],
                'sets' => ['required', 'integer', 'min:1'],
                'reps' => ['required', 'integer', 'min:1'],
                'priority' => ['nullable', 'integer', 'min:0'],
                'comments' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Line " . ($lineNumber + 2) . ": " . implode(", ", $validator->errors()->all());
                continue;
            }

            Program::create([
                'user_id' => $userId,
                'exercise_id' => $exercise->id,
                'date' => $dateForImport,
                'sets' => $rowData['sets'],
                'reps' => $rowData['reps'],
                'priority' => $rowData['priority'] ?? 0,
                'comments' => $rowData['comments'] ?? null,
            ]);

            $importedCount++;
        }

        if (count($errors) > 0) {
            return ['success' => false, 'message' => 'Import completed with errors.', 'errors' => $errors, 'imported_count' => $importedCount];
        } else {
            return ['success' => true, 'message' => 'Successfully imported ' . $importedCount . ' program entries.'];
        }
    }
}
