<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\LiftSet;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        LiftSet::whereRaw('LOWER(band_color) = ?', ['purple'])->delete();
    }

    /**
     * Reverse the migrations.
     *
     * No-op: deleted legacy purple demo/test rows are intentionally not resurrected.
     */
    public function down(): void
    {
        // No-op
    }
};
