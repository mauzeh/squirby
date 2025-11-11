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
        Schema::table('workout_template_exercises', function (Blueprint $table) {
            $table->dropColumn(['sets', 'reps', 'notes', 'rest_seconds']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_template_exercises', function (Blueprint $table) {
            $table->integer('sets')->after('exercise_id');
            $table->integer('reps')->after('sets');
            $table->text('notes')->nullable()->after('order');
            $table->integer('rest_seconds')->nullable()->after('notes');
        });
    }
};
