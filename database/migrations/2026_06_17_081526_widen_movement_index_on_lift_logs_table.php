<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Athlete app assigns user-added movements a movementIndex starting
     * at 900 (900 + position). The previous unsignedTinyInteger column maxed
     * at 255, causing SQLSTATE[22003] overflows on sync. Widen to
     * unsignedSmallInteger (max 65535) to accommodate.
     */
    public function up(): void
    {
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('block_index')->nullable()->change();
            $table->unsignedSmallInteger('movement_index')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->unsignedTinyInteger('block_index')->nullable()->change();
            $table->unsignedTinyInteger('movement_index')->nullable()->change();
        });
    }
};
