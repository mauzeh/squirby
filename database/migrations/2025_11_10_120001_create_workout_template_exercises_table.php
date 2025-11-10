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
        Schema::create('workout_template_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('order');
            $table->timestamps();
            
            $table->unique(['workout_template_id', 'exercise_id'], 'wt_exercise_unique');
            $table->index('workout_template_id', 'wt_exercise_template_idx');
            $table->index('exercise_id', 'wt_exercise_exercise_idx');
            $table->index(['workout_template_id', 'order'], 'wt_exercise_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_template_exercises');
    }
};
