<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, clean up duplicate exercises before adding the unique constraint
        $this->removeDuplicateExercises();
        
        Schema::table('exercises', function (Blueprint $table) {
            // Add unique constraint for exercise names within scope (title, user_id)
            // This ensures that:
            // 1. Global exercises (user_id = null) have unique titles
            // 2. User-specific exercises have unique titles per user
            // 3. Users can create exercises with same name as global ones
            $table->unique(['title', 'user_id'], 'unique_exercise_name_per_scope');
        });
    }

    /**
     * Remove duplicate exercises, keeping only the oldest one for each (title, user_id) combination
     */
    private function removeDuplicateExercises(): void
    {
        // Find all duplicate groups
        $duplicateGroups = DB::table('exercises')
            ->select('title', 'user_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('title', 'user_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            // Get all exercises in this duplicate group
            $exercises = DB::table('exercises')
                ->where('title', $group->title)
                ->where('user_id', $group->user_id)
                ->orderBy('created_at', 'asc')
                ->get();

            // Keep the first (oldest) exercise, delete the rest
            $exercisesToDelete = $exercises->slice(1)->pluck('id');
            
            if ($exercisesToDelete->isNotEmpty()) {
                DB::table('exercises')
                    ->whereIn('id', $exercisesToDelete)
                    ->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('unique_exercise_name_per_scope');
        });
    }
};
