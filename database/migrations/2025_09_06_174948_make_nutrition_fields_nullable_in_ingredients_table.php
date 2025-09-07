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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->float('added_sugars')->nullable()->change();
            $table->float('sodium')->nullable()->change();
            $table->float('iron')->nullable()->change();
            $table->float('potassium')->nullable()->change();
            $table->float('fiber')->nullable()->change();
            $table->float('calcium')->nullable()->change();
            $table->float('caffeine')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->float('added_sugars')->nullable(false)->change();
            $table->float('sodium')->nullable(false)->change();
            $table->float('iron')->nullable(false)->change();
            $table->float('potassium')->nullable(false)->change();
            $table->float('fiber')->nullable(false)->change();
            $table->float('calcium')->nullable(false)->change();
            $table->float('caffeine')->nullable(false)->change();
        });
    }
};
