<?php

namespace App\Console\Commands;

use App\Sync\Models\PrismExerciseOverlayRecord;
use Illuminate\Console\Command;

/**
 * Import a resolved Prism exercise-overlay payload into prism_exercise_overlay.
 *
 * The payload is produced by the prism repo's cove-exercises-apply.mjs --emit
 * (all library/schema-dependent resolution — slug ids, ALIAS/MERGE target
 * resolution, primary/core movement+muscle splitting — happens there, because
 * only the prism repo has data/exercises.json + data/exercise-schema.json). The
 * file shape is:
 *
 *   {
 *     "gym": "the-cove",
 *     "generatedAt": "...",
 *     "exercises": [ { "exercise_id": "slug", "record": { id, name, aliases,
 *                      primaryMovements, coreMovements, primaryMuscles,
 *                      coreMuscles, equipment, modalities, [video] } }, ... ]
 *   }
 *
 * Idempotent, safe to run twice:
 *  - A row that does NOT yet exist for (gym_id, exercise_id) is INSERTED with the
 *    full incoming record.
 *  - A row that ALREADY exists has its aliases MERGED (union) with the incoming
 *    aliases; every other field (name, movements, muscles, equipment, modalities)
 *    is left untouched, and an existing deleted_at is left untouched (a
 *    deliberately-removed exercise is not resurrected). If the union adds no new
 *    alias, the row is not written.
 *
 * updated_at is Eloquent-managed, so every written row bumps it — which is what
 * the client's /changes cursor needs to pick the change up on its next pull.
 *
 * Usage:
 *   php artisan prism:import-exercise-overlay database/imports/cove-overlay-resolved.json
 *   php artisan prism:import-exercise-overlay <path> --gym=the-cove --dry-run
 */
class ImportPrismExerciseOverlay extends Command
{
    protected $signature = 'prism:import-exercise-overlay
        {path : Path to the resolved overlay JSON (absolute, or relative to the app base path)}
        {--gym= : Gym id to import into (overrides the payload\'s "gym" field)}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Import a resolved Prism exercise-overlay JSON into prism_exercise_overlay (insert-if-missing, merge aliases on existing)';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or unreadable: {$path}");

            return Command::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            $this->error('Payload is not valid JSON.');

            return Command::FAILURE;
        }

        $gymId = $this->option('gym') ?: ($payload['gym'] ?? null);
        if (! $gymId) {
            $this->error('No gym id: pass --gym= or include a "gym" field in the payload.');

            return Command::FAILURE;
        }

        $exercises = $payload['exercises'] ?? null;
        if (! is_array($exercises) || $exercises === []) {
            $this->error('Payload has no "exercises" array.');

            return Command::FAILURE;
        }

        // Validate every entry up front; refuse a partial import.
        foreach ($exercises as $i => $entry) {
            if (! is_array($entry)
                || ! isset($entry['exercise_id']) || ! is_string($entry['exercise_id']) || $entry['exercise_id'] === ''
                || ! isset($entry['record']) || ! is_array($entry['record'])) {
                $this->error("exercises[{$i}] is malformed (needs a non-empty string exercise_id and an object record).");

                return Command::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info("Gym: {$gymId}");
        $this->info('Entries in payload: '.count($exercises));
        if ($dryRun) {
            $this->warn('DRY RUN — no rows will be written.');
        }

        // Index existing rows for this gym by exercise_id.
        $existing = PrismExerciseOverlayRecord::query()
            ->where('gym_id', $gymId)
            ->get()
            ->keyBy('exercise_id');

        $toInsert = [];           // [exercise_id => record]
        $toUpdate = [];           // [exercise_id => ['model' => ..., 'added' => [new aliases], 'record' => merged record]]
        $unchanged = 0;

        foreach ($exercises as $entry) {
            $exerciseId = $entry['exercise_id'];
            $incoming = $entry['record'];
            $incomingAliases = $this->cleanAliases($incoming['aliases'] ?? []);

            $row = $existing->get($exerciseId);

            if (! $row) {
                // Insert: keep the full incoming record, with cleaned aliases.
                $record = $incoming;
                $record['aliases'] = $incomingAliases;
                $toInsert[$exerciseId] = $record;

                continue;
            }

            // Existing: merge (union) aliases only; leave everything else.
            $current = is_array($row->record) ? $row->record : [];
            $currentAliases = $this->cleanAliases($current['aliases'] ?? []);
            [$merged, $added] = $this->unionAliases($currentAliases, $incomingAliases, $current['name'] ?? $incoming['name'] ?? '');

            if ($added === []) {
                $unchanged++;

                continue;
            }

            $record = $current;
            $record['aliases'] = $merged;
            $toUpdate[$exerciseId] = ['model' => $row, 'added' => $added, 'record' => $record];
        }

        $this->report($toInsert, $toUpdate, $unchanged);

        if ($toInsert === [] && $toUpdate === []) {
            $this->info('Nothing to do — overlay already matches the payload.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            return Command::SUCCESS;
        }

        $inserted = 0;
        foreach ($toInsert as $exerciseId => $record) {
            PrismExerciseOverlayRecord::create([
                'gym_id' => $gymId,
                'exercise_id' => $exerciseId,
                'record' => $record,
                'deleted_at' => null,
            ]);
            $inserted++;
        }

        $updated = 0;
        foreach ($toUpdate as $data) {
            $row = $data['model'];
            $row->record = $data['record'];
            $row->save(); // bumps updated_at
            $updated++;
        }

        $this->newLine();
        $this->info("Done. Inserted {$inserted} new row(s), merged aliases into {$updated} existing row(s), {$unchanged} unchanged.");

        return Command::SUCCESS;
    }

    /**
     * Normalize an aliases list: strings only, trimmed, de-duplicated
     * case-insensitively (first spelling wins).
     *
     * @param  mixed  $aliases
     * @return array<int, string>
     */
    private function cleanAliases($aliases): array
    {
        if (! is_array($aliases)) {
            return [];
        }
        $seen = [];
        $out = [];
        foreach ($aliases as $a) {
            if (! is_string($a)) {
                continue;
            }
            $a = trim($a);
            if ($a === '') {
                continue;
            }
            $k = mb_strtolower($a);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $a;
        }

        return $out;
    }

    /**
     * Union incoming aliases into the current list (case-insensitive), never
     * adding one that equals the record's own name. Returns [merged, added].
     *
     * @param  array<int, string>  $current
     * @param  array<int, string>  $incoming
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function unionAliases(array $current, array $incoming, string $name): array
    {
        $have = [];
        foreach ($current as $a) {
            $have[mb_strtolower($a)] = true;
        }
        $nameKey = mb_strtolower(trim($name));

        $merged = $current;
        $added = [];
        foreach ($incoming as $a) {
            $k = mb_strtolower($a);
            if ($k === $nameKey || isset($have[$k])) {
                continue;
            }
            $have[$k] = true;
            $merged[] = $a;
            $added[] = $a;
        }

        return [$merged, $added];
    }

    /**
     * @param  array<string, array<string, mixed>>  $toInsert
     * @param  array<string, array<string, mixed>>  $toUpdate
     */
    private function report(array $toInsert, array $toUpdate, int $unchanged): void
    {
        $this->newLine();
        $this->info('=== Prism Exercise Overlay Import Plan ===');
        $this->line('Insert (new):        '.count($toInsert));
        $this->line('Merge aliases:       '.count($toUpdate));
        $this->line('Unchanged:           '.$unchanged);

        if ($toInsert !== []) {
            $this->newLine();
            $this->info('New exercises to insert:');
            $rows = [];
            foreach ($toInsert as $exerciseId => $record) {
                $rows[] = [$exerciseId, $record['name'] ?? '', count($record['aliases'] ?? [])];
            }
            $this->table(['exercise_id', 'name', '#aliases'], $rows);
        }

        if ($toUpdate !== []) {
            $this->newLine();
            $this->info('Existing exercises gaining aliases:');
            $rows = [];
            foreach ($toUpdate as $exerciseId => $data) {
                $rows[] = [
                    $exerciseId,
                    $data['record']['name'] ?? '',
                    implode(', ', $data['added']),
                ];
            }
            $this->table(['exercise_id', 'name', 'added aliases'], $rows);
        }
    }
}
