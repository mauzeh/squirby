<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkoutProgramFactory extends Factory
{
    protected $model = WorkoutProgram::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'name' => $this->faker->words(3, true),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}