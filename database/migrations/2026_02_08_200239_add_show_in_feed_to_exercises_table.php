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
            $table->boolean('show_in_feed')->default(false)->after('user_id');
        });
        
        // Enable show_in_feed for the most commonly logged exercises
        DB::table('exercises')
            ->whereIn('title', [
                'Back Squat',
                'Deadlift',
                'Bench Press',
                'Strict Press',
                'Front Squat',
                'Pull-Up',
                'Snatch',
                'Romanian Deadlift',
                'Push Press',
                'Power Clean',
                'Clean & Jerk',
                'Overhead Squat',
                'Thruster',
                'Clean',
                'Jerk',
            ])
            ->update(['show_in_feed' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('show_in_feed');
        });
    }
};
