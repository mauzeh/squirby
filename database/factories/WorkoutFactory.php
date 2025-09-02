<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workout>
 */
class WorkoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exercise_id' => \App\Models\Exercise::factory(),
            'comments' => $this->faker->sentence(),
            'logged_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
