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
        Schema::create('pr_detection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lift_log_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->json('pr_types_detected'); // Array of PR type strings: ["weight", "volume"]
            $table->json('calculation_snapshot'); // Full calculation context for debugging
            $table->string('trigger_event'); // 'created' or 'updated'
            $table->timestamp('detected_at');
            
            $table->index(['lift_log_id', 'detected_at']);
            $table->index(['user_id', 'exercise_id', 'detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_detection_logs');
    }
};
