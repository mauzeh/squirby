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
            $table->json('meta')->nullable()->after('movement_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
