<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'pr_comment', 'pr_high_five', 'new_pr'
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // Who performed the action
            $table->morphs('notifiable'); // The thing being notified about (PR, comment, etc)
            $table->text('data')->nullable(); // JSON data for additional context
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
