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
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->unsignedSmallInteger('calories')->nullable();
            $table->decimal('distance', 8, 2)->nullable();
            $table->string('distance_unit', 5)->nullable();
            $table->integer('reps')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lift_sets', function (Blueprint $table) {
            $table->dropColumn([
                'calories',
                'distance',
                'distance_unit',
            ]);
            $table->integer('reps')->nullable(false)->change();
        });
    }
};
