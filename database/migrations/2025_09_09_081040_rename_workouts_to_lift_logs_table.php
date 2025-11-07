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
        // Disable foreign key checks to allow table renaming
        Schema::disableForeignKeyConstraints();

        // Rename workouts table to lift_logs
        Schema::rename('workouts', 'lift_logs');

        // Rename workout_sets table to lift_sets
        Schema::rename('workout_sets', 'lift_sets');

        // Update foreign key column name in lift_sets table
        Schema::table('lift_sets', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign('workout_sets_workout_id_foreign');
            
            // Rename the column
            $table->renameColumn('workout_id', 'lift_log_id');
        });

        // Add the new foreign key constraint with the renamed column
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->foreign('lift_log_id')->references('id')->on('lift_logs')->onDelete('cascade');
        });

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks to allow table renaming
        Schema::disableForeignKeyConstraints();

        // Drop the new foreign key constraint
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->dropForeign(['lift_log_id']);
        });

        // Rename the column back
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->renameColumn('lift_log_id', 'workout_id');
        });

        // Add back the original foreign key constraint
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->foreign('workout_id', 'workout_sets_workout_id_foreign')->references('id')->on('workouts')->onDelete('cascade');
        });

        // Rename tables back to original names
        Schema::rename('lift_sets', 'workout_sets');
        Schema::rename('lift_logs', 'workouts');

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }
};
