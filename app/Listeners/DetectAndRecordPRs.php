<?php

namespace App\Listeners;

use App\Events\LiftLogCompleted;
use App\Services\PRDetectionService;
use App\Models\PersonalRecord;
use Illuminate\Support\Facades\DB;

class DetectAndRecordPRs
{
    public function __construct(
        protected PRDetectionService $prDetectionService
    ) {}
    
    public function handle(LiftLogCompleted $event): void
    {
        DB::transaction(function () use ($event) {
            $liftLog = $event->liftLog;
            
            // Eager load relationships needed for PR detection
            $liftLog->load(['exercise', 'liftSets']);
            
            // If this is an update, delete old PR records
            if ($event->isUpdate) {
                PersonalRecord::where('lift_log_id', $liftLog->id)->delete();
            }
            
            // Detect PRs for this lift log
            $prs = $this->prDetectionService->detectPRsWithDetails($liftLog);
            
            // Create PR records
            foreach ($prs as $pr) {
                PersonalRecord::create([
                    'user_id' => $liftLog->user_id,
                    'exercise_id' => $liftLog->exercise_id,
                    'lift_log_id' => $liftLog->id,
                    'pr_type' => $pr['type'],
                    'rep_count' => $pr['rep_count'] ?? null,
                    'weight' => $pr['weight'] ?? null,
                    'value' => $pr['value'],
                    'previous_pr_id' => $pr['previous_pr_id'] ?? null,
                    'previous_value' => $pr['previous_value'] ?? null,
                    'achieved_at' => $liftLog->logged_at,
                ]);
            }
            
            // Update lift log flags
            $liftLog->update([
                'is_pr' => count($prs) > 0,
                'pr_count' => count($prs),
            ]);
        });
    }
}
