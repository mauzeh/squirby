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
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('exercise_type')->nullable()->after('band_type');
            $table->index('exercise_type');
        });
        
        // Populate exercise_type for existing exercises based on characteristics
        $this->populateExerciseTypes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['exercise_type']);
            $table->dropColumn('exercise_type');
        });
    }

    /**
     * Populate exercise_type field for existing exercises based on their characteristics
     */
    private function populateExerciseTypes(): void
    {
        // Identify and mark cardio exercises based on keywords
        $cardioKeywords = ['run', 'running', 'cycle', 'cycling', 'row', 'rowing', 'walk', 'walking', 'jog', 'jogging'];
        
        foreach ($cardioKeywords as $keyword) {
            DB::table('exercises')
                ->where('title', 'LIKE', "%{$keyword}%")
                ->whereNull('exercise_type')
                ->update(['exercise_type' => 'cardio']);
        }
        
        // Mark banded exercises (those with band_type set)
        DB::table('exercises')
            ->whereNotNull('band_type')
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'banded']);
        
        // Mark bodyweight exercises (those with is_bodyweight = true)
        DB::table('exercises')
            ->where('is_bodyweight', true)
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'bodyweight']);
        
        // Default remaining exercises to 'regular'
        DB::table('exercises')
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'regular']);
    }
};