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
        Schema::dropIfExists('mobile_lift_forms');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('mobile_lift_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->timestamps();
            
            // Index for common queries
            $table->index(['user_id', 'date']);
        });
    }
};
