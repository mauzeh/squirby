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
        Schema::create('exercise_intelligence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained('exercises')->onDelete('cascade');
            $table->json('muscle_data');
            $table->string('primary_mover', 100);
            $table->string('largest_muscle', 100);
            $table->enum('movement_archetype', ['push', 'pull', 'squat', 'hinge', 'carry', 'core']);
            $table->enum('category', ['strength', 'cardio', 'mobility', 'plyometric', 'flexibility']);
            $table->tinyInteger('difficulty_level')->unsigned()->check('difficulty_level >= 1 AND difficulty_level <= 5');
            $table->integer('recovery_hours')->unsigned()->default(48);
            $table->timestamps();
            
            // Unique constraint to prevent duplicate intelligence records per exercise
            $table->unique('exercise_id', 'unique_exercise_intelligence');
            
            // Indexes for efficient querying
            $table->index('movement_archetype', 'idx_movement_archetype');
            $table->index('category', 'idx_category');
            $table->index('difficulty_level', 'idx_difficulty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_intelligence');
    }
};
