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
        Schema::create('exercise_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_exercise_id');
            $table->string('source_exercise_title');
            $table->unsignedBigInteger('target_exercise_id');
            $table->string('target_exercise_title');
            $table->unsignedBigInteger('admin_user_id');
            $table->string('admin_email');
            $table->json('lift_log_ids')->nullable();
            $table->integer('lift_log_count')->default(0);
            $table->boolean('alias_created')->default(false);
            $table->timestamps();

            // Indexes for common queries
            $table->index('source_exercise_id');
            $table->index('target_exercise_id');
            $table->index('admin_user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_merge_logs');
    }
};
