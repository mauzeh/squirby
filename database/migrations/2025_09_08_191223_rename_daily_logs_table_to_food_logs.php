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
        // Check if daily_logs table exists before renaming
        if (Schema::hasTable('daily_logs')) {
            Schema::rename('daily_logs', 'food_logs');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if food_logs table exists before renaming back
        if (Schema::hasTable('food_logs')) {
            Schema::rename('food_logs', 'daily_logs');
        }
    }
};
