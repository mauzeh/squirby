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
        // Drop the mobile_food_forms table as it's no longer needed
        // Food logging now uses direct navigation like lifts
        Schema::dropIfExists('mobile_food_forms');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the mobile_food_forms table structure for rollback
        Schema::create('mobile_food_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('type'); // 'ingredient' or 'meal'
            $table->unsignedBigInteger('item_id'); // ingredient_id or meal_id
            $table->string('item_name'); // cached name for display
            $table->timestamps();
            
            // Ensure unique combination of user, date, type, and item
            $table->unique(['user_id', 'date', 'type', 'item_id']);
            
            // Index for cleanup queries
            $table->index(['user_id', 'date']);
        });
    }
};