<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('comment');
            $table->timestamps();
            
            $table->index(['personal_record_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_comments');
    }
};
