<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a nullable tombstone column so a removed cell can be propagated as a
     * soft-delete through the sync delta, mirroring prism_exercise_overlay.
     * Additive + nullable: existing rows are unaffected. The composite unique key
     * (gym_id, program_uuid, cell_id) is intentionally left unchanged so a
     * re-created cell with the same cell_id can clear its tombstone on upsert.
     */
    public function up(): void
    {
        Schema::table('prism_program_cells', function (Blueprint $table) {
            $table->timestamp('deleted_at', 6)->nullable()->after('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prism_program_cells', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
