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
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lift_log_id')->constrained()->cascadeOnDelete();
            $table->enum('pr_type', ['one_rm', 'volume', 'rep_specific', 'hypertrophy']);
            $table->integer('rep_count')->nullable(); // For rep-specific PRs (e.g., "5 reps")
            $table->decimal('weight', 8, 2)->nullable(); // For hypertrophy PRs (e.g., "best @ 200 lbs")
            $table->decimal('value', 10, 2); // The actual PR value (1RM weight, volume, etc.)
            $table->foreignId('previous_pr_id')->nullable()->constrained('personal_records');
            $table->decimal('previous_value', 10, 2)->nullable();
            $table->timestamp('achieved_at');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for fast queries
            $table->index(['user_id', 'exercise_id', 'pr_type']);
            $table->index(['user_id', 'achieved_at']);
            $table->index(['lift_log_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};
