<?php

use App\Models\Workout;
use App\Models\WorkoutSet;
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
        Schema::disableForeignKeyConstraints();

        $workouts = Workout::all();

        foreach ($workouts as $workout) {
            for ($i = 0; $i < $workout->rounds; $i++) {
                WorkoutSet::create([
                    'workout_id' => $workout->id,
                    'weight' => $workout->weight,
                    'reps' => $workout->reps,
                    'notes' => $workout->comments,
                ]);
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse for data migration
    }
};
