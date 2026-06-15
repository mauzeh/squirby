<?php

namespace App\Sync\Actions;

use App\Events\LiftLogCompleted;
use App\Models\LiftLog;
use App\Models\User;
use App\Sync\Services\ExerciseResolverService;
use App\Sync\Services\SetFieldMapper;
use Carbon\Carbon;

class StoreSyncLogAction
{
    public function __construct(
        private ExerciseResolverService $exerciseResolver,
        private SetFieldMapper $setFieldMapper,
    ) {}

    /**
     * Execute the action to store a sync log.
     */
    public function execute(User $user, array $validated, ?string $deviceId): LiftLog
    {
        // Handle idempotency
        if (! empty($validated['idempotency_key'])) {
            $existing = LiftLog::where('user_id', $user->id)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Resolve the exercise
        $exercise = $this->exerciseResolver->resolve(
            $validated['exercise_name'],
            $user,
            $validated['log_type'],
            $validated['canonical_name'] ?? null
        );

        // Parse logged_at date at 12:00:00
        $loggedAt = Carbon::parse($validated['date'])->setTime(12, 0, 0);

        // Create the lift log
        $liftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'comments' => $validated['note'] ?? null,
            'logged_at' => $loggedAt,
            'log_type' => $validated['log_type'],
            'device_id' => $deviceId,
            'source' => 'sync',
            'track' => $validated['track'] ?? null,
            'block_index' => $validated['block_index'] ?? null,
            'movement_index' => $validated['movement_index'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
        ]);

        // Create lift sets
        foreach ($validated['sets'] as $setData) {
            $mapped = $this->setFieldMapper->mapToColumns(
                $validated['log_type'],
                $setData,
                $validated['weight_unit']
            );
            $liftLog->liftSets()->create($mapped);
        }

        // Dispatch completion event for PR detection
        LiftLogCompleted::dispatch($liftLog, false);

        return $liftLog;
    }
}
