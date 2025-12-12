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
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->foreignId('workout_id')->nullable()->after('user_id')->constrained('workouts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->dropForeign(['workout_id']);
            $table->dropColumn('workout_id');
        });
    }
};
