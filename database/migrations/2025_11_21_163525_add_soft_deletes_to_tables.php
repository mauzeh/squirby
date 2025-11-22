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
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('body_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('food_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('meals', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('ingredients', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('lift_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('body_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('food_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('meals', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
