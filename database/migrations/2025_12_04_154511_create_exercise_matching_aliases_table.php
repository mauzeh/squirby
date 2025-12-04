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
        Schema::create('exercise_matching_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->string('alias'); // e.g., "KB Swing", "GHD Situp"
            $table->timestamps();
            
            // Ensure unique aliases per exercise
            $table->unique(['exercise_id', 'alias']);
            
            // Index for fast alias lookups
            $table->index('alias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_matching_aliases');
    }
};
