<?php

namespace Database\Factories;

use App\Models\WorkoutTemplateExercise;
use App\Models\WorkoutTemplate;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutTemplateExerciseFactory extends Factory
{
    protected $model = WorkoutTemplateExercise::class;

    public function definition()
    {
        return [
            'workout_template_id' => WorkoutTemplate::factory(),
            'exercise_id' => Exercise::factory(),
            'order' => 1,
        ];
    }
}
