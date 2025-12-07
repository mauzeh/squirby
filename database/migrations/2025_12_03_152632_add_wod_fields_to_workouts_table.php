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
        Schema::table('workouts', function (Blueprint $table) {
            $table->text('wod_syntax')->nullable()->after('notes');
            $table->json('wod_parsed')->nullable()->after('wod_syntax');
            $table->date('scheduled_date')->nullable()->after('wod_parsed');
            $table->index('scheduled_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn(['wod_syntax', 'wod_parsed', 'scheduled_date']);
        });
    }
};
