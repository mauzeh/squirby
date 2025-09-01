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
        if (Schema::hasTable('measurements')) {
            Schema::table('measurements', function (Blueprint $table) {
                $table->dropColumn(['name', 'unit']);
                $table->foreignId('measurement_type_id')->nullable(false)->change();
            });

            Schema::rename('measurements', 'measurement_logs');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('measurement_logs')) {
            Schema::rename('measurement_logs', 'measurements');

            Schema::table('measurements', function (Blueprint $table) {
                $table->string('name');
                $table->string('unit');
                $table->foreignId('measurement_type_id')->nullable()->change();
            });
        }
    }
};
