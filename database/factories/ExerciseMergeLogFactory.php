<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExerciseMergeLog>
 */
class ExerciseMergeLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_exercise_id' => fake()->numberBetween(1, 1000),
            'source_exercise_title' => fake()->words(3, true),
            'target_exercise_id' => fake()->numberBetween(1, 1000),
            'target_exercise_title' => fake()->words(3, true),
            'admin_user_id' => User::factory(),
            'admin_email' => fake()->safeEmail(),
            'lift_log_ids' => [fake()->numberBetween(1, 100), fake()->numberBetween(101, 200)],
            'lift_log_count' => fake()->numberBetween(1, 50),
            'alias_created' => fake()->boolean(),
        ];
    }
}
