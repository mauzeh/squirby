<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a `log_type` column to exercises table. This stores the Athlete-canonical
     * log type (e.g. 'cardio-calories', 'kettlebell', 'dual-dumbbell') which is more
     * granular than the existing `exercise_type` used for Logger's internal UI.
     */
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('log_type', 50)->nullable()->after('exercise_type');
        });

        // Backfill log_type from exercise_type where we can infer it
        // (Sync resolver will populate the precise value going forward)
        DB::table('exercises')
            ->whereNull('log_type')
            ->where('exercise_type', 'static_hold')
            ->update(['log_type' => 'static-hold']);

        DB::table('exercises')
            ->whereNull('log_type')
            ->where('exercise_type', 'bodyweight')
            ->update(['log_type' => 'bodyweight-reps']);

        DB::table('exercises')
            ->whereNull('log_type')
            ->whereIn('exercise_type', ['banded_resistance', 'banded_assistance'])
            ->update(['log_type' => 'banded']);

        DB::table('exercises')
            ->whereNull('log_type')
            ->where('exercise_type', 'regular')
            ->update(['log_type' => 'barbell']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('log_type');
        });
    }
};
