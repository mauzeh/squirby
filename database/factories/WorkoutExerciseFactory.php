<?php

namespace Database\Factories;

use App\Models\WorkoutExercise;
use App\Models\Workout;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutExerciseFactory extends Factory
{
    protected $model = WorkoutExercise::class;

    public function definition()
    {
        return [
            'workout_id' => Workout::factory(),
            'exercise_id' => Exercise::factory(),
            'order' => 1,
        ];
    }
}
