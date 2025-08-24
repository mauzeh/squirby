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
        Schema::table('workouts', function (Blueprint $table) {
            $table->renameColumn('working_set_weight', 'weight');
            $table->renameColumn('working_set_reps', 'reps');
            $table->renameColumn('working_set_rounds', 'rounds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->renameColumn('weight', 'working_set_weight');
            $table->renameColumn('reps', 'working_set_reps');
            $table->renameColumn('rounds', 'working_set_rounds');
        });
    }
};
