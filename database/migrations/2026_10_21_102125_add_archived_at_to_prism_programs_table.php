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
        Schema::table('prism_programs', function (Blueprint $table): void {
            $table->dateTime('archived_at', 6)->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prism_programs', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
