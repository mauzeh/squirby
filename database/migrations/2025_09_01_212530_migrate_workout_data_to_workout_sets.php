<?php

use App\Models\LiftLog;
use App\Models\LiftSet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Check if the table exists (it might have been renamed already)
        if (Schema::hasTable('lift_logs')) {
            $liftLogs = LiftLog::all();

            foreach ($liftLogs as $liftLog) {
                // Check if the rounds column exists (this migration might have already run)
                if (isset($liftLog->rounds)) {
                    for ($i = 0; $i < $liftLog->rounds; $i++) {
                        LiftSet::create([
                            'lift_log_id' => $liftLog->id,
                            'weight' => $liftLog->weight,
                            'reps' => $liftLog->reps,
                            'notes' => $liftLog->comments,
                        ]);
                    }
                }
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse for data migration
    }
};
