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
            $table->string('track', 20)->nullable();
            $table->unsignedTinyInteger('block_index')->nullable();
            $table->unsignedTinyInteger('movement_index')->nullable();
            $table->string('log_type', 30)->nullable();
            $table->string('device_id', 36)->nullable();
            $table->string('source', 10)->nullable();
            $table->string('idempotency_key', 36)->nullable();

            $table->index(['user_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'idempotency_key']);
            
            $table->dropColumn([
                'track',
                'block_index',
                'movement_index',
                'log_type',
                'device_id',
                'source',
                'idempotency_key',
            ]);
        });
    }
};
