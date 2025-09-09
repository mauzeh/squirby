<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiftSet>
 */
class LiftSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lift_log_id' => \App\Models\LiftLog::factory(),
            'weight' => $this->faker->randomFloat(2, 10, 300),
            'reps' => $this->faker->numberBetween(1, 20),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}