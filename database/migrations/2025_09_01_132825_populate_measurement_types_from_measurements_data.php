<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\MeasurementType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('measurements')) {
            $measurementNames = DB::table('measurements')->distinct()->pluck('name', 'unit');

            foreach ($measurementNames as $unit => $name) {
                MeasurementType::create([
                    'name' => $name,
                    'default_unit' => $unit,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible.
    }
};
