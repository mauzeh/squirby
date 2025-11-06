<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add exercise_type column as nullable initially
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('exercise_type', 50)->nullable()->after('description');
            $table->index('exercise_type', 'idx_exercises_exercise_type');
        });

        // Step 2: Populate exercise_type field based on existing data
        $this->populateExerciseTypes();

        // Step 3: Make the field non-nullable after population
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('exercise_type', 50)->nullable(false)->change();
        });

        // Step 4: Remove legacy columns
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn(['is_bodyweight', 'band_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back legacy columns
        Schema::table('exercises', function (Blueprint $table) {
            $table->boolean('is_bodyweight')->default(false)->after('description');
            $table->string('band_type', 50)->nullable()->after('is_bodyweight');
        });

        // Populate legacy columns from exercise_type
        $this->restoreLegacyFields();

        // Drop the new column and index
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });
    }

    /**
     * Populate exercise_type field based on existing is_bodyweight and band_type fields
     * Migration logic follows requirements 3.1, 3.2, 3.3, 3.4, 3.5
     */
    private function populateExerciseTypes(): void
    {
        // Priority order for type assignment:
        // 1. Banded resistance exercises (band_type = 'resistance')
        // 2. Banded assistance exercises (band_type = 'assistance') 
        // 3. Bodyweight exercises (is_bodyweight = true AND band_type IS NULL)
        // 4. Regular exercises (everything else)

        // Step 1: Banded resistance exercises
        $resistanceCount = DB::table('exercises')
            ->where('band_type', 'resistance')
            ->update(['exercise_type' => 'banded_resistance']);

        // Step 2: Banded assistance exercises  
        $assistanceCount = DB::table('exercises')
            ->where('band_type', 'assistance')
            ->update(['exercise_type' => 'banded_assistance']);

        // Step 3: Bodyweight exercises (only if not already assigned a banded type)
        $bodyweightCount = DB::table('exercises')
            ->where('is_bodyweight', true)
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'bodyweight']);

        // Step 4: Regular exercises (everything else that hasn't been assigned)
        $regularCount = DB::table('exercises')
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'regular']);
    }

    /**
     * Restore legacy fields from exercise_type for rollback
     */
    private function restoreLegacyFields(): void
    {
        // Restore is_bodyweight field
        DB::table('exercises')
            ->where('exercise_type', 'bodyweight')
            ->update(['is_bodyweight' => true]);

        // Restore band_type field
        DB::table('exercises')
            ->where('exercise_type', 'banded_resistance')
            ->update(['band_type' => 'resistance']);

        DB::table('exercises')
            ->where('exercise_type', 'banded_assistance')
            ->update(['band_type' => 'assistance']);
    }
};
