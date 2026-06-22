<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix exercises that were created with exercise_type='regular' but have
     * log_type='weighted-carry' or 'dual-kettlebell'. These should use the
     * static_hold strategy so duration (time field) is displayed instead of reps.
     */
    public function up(): void
    {
        DB::table('exercises')
            ->whereIn('log_type', ['weighted-carry', 'dual-kettlebell'])
            ->where('exercise_type', 'regular')
            ->update(['exercise_type' => 'static_hold']);
    }

    /**
     * Reverse the migration (revert to regular).
     */
    public function down(): void
    {
        DB::table('exercises')
            ->whereIn('log_type', ['weighted-carry', 'dual-kettlebell'])
            ->where('exercise_type', 'static_hold')
            ->update(['exercise_type' => 'regular']);
    }
};
