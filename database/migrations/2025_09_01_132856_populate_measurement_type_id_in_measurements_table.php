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

            \App\Models\Measurement::all()->each(function ($measurement) use ($measurementTypes) {
                $key = $measurement->name . '_' . $measurement->unit;
                if (isset($measurementTypes[$key])) {
                    $measurement->measurement_type_id = $measurementTypes[$key]->id;
                    $measurement->save();
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
            \App\Models\Measurement::query()->update(['measurement_type_id' => null]);
        }
    }
};
