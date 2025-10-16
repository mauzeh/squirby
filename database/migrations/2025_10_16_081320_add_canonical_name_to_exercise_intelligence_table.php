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
        Schema::table('exercise_intelligence', function (Blueprint $table) {
            $table->string('canonical_name')->nullable()->after('exercise_id');
            $table->index('canonical_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercise_intelligence', function (Blueprint $table) {
            $table->dropIndex(['canonical_name']);
            $table->dropColumn('canonical_name');
        });
    }
};
