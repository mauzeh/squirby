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
                $table->foreignId('measurement_type_id')->nullable()->constrained('measurement_types')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('measurements')) {
            Schema::table('measurements', function (Blueprint $table) {
                $table->dropForeign(['measurement_type_id']);
                $table->dropColumn('measurement_type_id');
            });
        }
    }
};
