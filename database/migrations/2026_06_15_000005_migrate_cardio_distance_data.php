<?php

use App\Models\LiftSet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        LiftSet::query()
            ->whereHas('liftLog.exercise', function ($query) {
                $query->where('exercise_type', 'cardio');
            })
            ->whereNotNull('reps')
            ->each(function (LiftSet $set) {
                $set->update([
                    'distance' => (float) $set->reps,
                    'distance_unit' => 'm',
                    'reps' => null,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        LiftSet::query()
            ->whereHas('liftLog.exercise', function ($query) {
                $query->where('exercise_type', 'cardio');
            })
            ->whereNotNull('distance')
            ->each(function (LiftSet $set) {
                $set->update([
                    'reps' => (int) $set->distance,
                    'distance' => null,
                    'distance_unit' => null,
                ]);
            });
    }
};
