<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exercise_id' => Exercise::factory(),
            'date' => now()->toDateString(),
            'sets' => $this->faker->numberBetween(1, 5),
            'reps' => $this->faker->numberBetween(5, 12),
            'weight' => $this->faker->optional()->randomFloat(2, 0, 200),
        ];
    }
}