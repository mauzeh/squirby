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
        if (Schema::hasTable('measurements')) {
            $measurementTypes = \App\Models\MeasurementType::all()->keyBy(function ($type) {
                return $type->name . '_' . $type->default_unit;
            });

            DB::table('measurements')->get()->each(function ($measurement) use ($measurementTypes) {
                $key = $measurement->name . '_' . $measurement->unit;
                if (isset($measurementTypes[$key])) {
                    DB::table('measurements')
                        ->where('id', $measurement->id)
                        ->update(['measurement_type_id' => $measurementTypes[$key]->id]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('measurements')) {
            DB::table('measurements')->update(['measurement_type_id' => null]);
        }
    }
};
