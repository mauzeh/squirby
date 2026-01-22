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
            $table->boolean('is_pr')->default(false)->index()->after('workout_id');
            $table->integer('pr_count')->default(0)->after('is_pr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->dropColumn(['is_pr', 'pr_count']);
        });
    }
};
