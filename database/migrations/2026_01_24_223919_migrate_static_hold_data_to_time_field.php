<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find all static hold exercises
        $staticHoldExercises = DB::table('exercises')
            ->where('exercise_type', 'static_hold')
            ->pluck('id');
        
        if ($staticHoldExercises->isEmpty()) {
            return;
        }
        
        // Get all lift logs for static hold exercises
        $staticHoldLiftLogs = DB::table('lift_logs')
            ->whereIn('exercise_id', $staticHoldExercises)
            ->pluck('id');
        
        if ($staticHoldLiftLogs->isEmpty()) {
            return;
        }
        
        // Copy reps (duration) to time field for static hold sets
        DB::table('lift_sets')
            ->whereIn('lift_log_id', $staticHoldLiftLogs)
            ->update([
                'time' => DB::raw('reps'),  // Copy reps value to time
                'reps' => 1,                 // Set reps to 1 (one hold)
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find all static hold exercises
        $staticHoldExercises = DB::table('exercises')
            ->where('exercise_type', 'static_hold')
            ->pluck('id');
        
        if ($staticHoldExercises->isEmpty()) {
            return;
        }
        
        // Get all lift logs for static hold exercises
        $staticHoldLiftLogs = DB::table('lift_logs')
            ->whereIn('exercise_id', $staticHoldExercises)
            ->pluck('id');
        
        if ($staticHoldLiftLogs->isEmpty()) {
            return;
        }
        
        // Restore reps from time field
        DB::table('lift_sets')
            ->whereIn('lift_log_id', $staticHoldLiftLogs)
            ->update([
                'reps' => DB::raw('time'),  // Copy time value back to reps
                'time' => null,             // Clear time field
            ]);
    }
};
