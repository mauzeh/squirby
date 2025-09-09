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
        // Check if the measurement_logs table exists before renaming
        if (Schema::hasTable('measurement_logs')) {
            Schema::rename('measurement_logs', 'body_logs');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the body_logs table exists before renaming back
        if (Schema::hasTable('body_logs')) {
            Schema::rename('body_logs', 'measurement_logs');
        }
    }
};
