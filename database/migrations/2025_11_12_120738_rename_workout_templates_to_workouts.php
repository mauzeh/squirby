<?php

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
        // Rename workout_templates table to workouts
        Schema::rename('workout_templates', 'workouts');
        
        // Rename workout_template_exercises table to workout_exercises
        Schema::rename('workout_template_exercises', 'workout_exercises');
        
        // Update foreign key column name in workout_exercises table
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->renameColumn('workout_template_id', 'workout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename foreign key column back
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->renameColumn('workout_id', 'workout_template_id');
        });
        
        // Rename tables back
        Schema::rename('workout_exercises', 'workout_template_exercises');
        Schema::rename('workouts', 'workout_templates');
    }
};
