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
        Schema::create('prism_exercise_overlay', function (Blueprint $table) {
            $table->id();
            $table->string('gym_id');
            $table->string('exercise_id');
            $table->json('record')->nullable();
            $table->string('device_id', 36)->nullable();
            $table->timestamp('deleted_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
            $table->unique(['gym_id', 'exercise_id']);
            $table->index(['gym_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prism_exercise_overlay');
    }
};
