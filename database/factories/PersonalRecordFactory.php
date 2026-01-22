<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonalRecord>
 */
class PersonalRecordFactory extends Factory
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
            'lift_log_id' => LiftLog::factory(),
            'pr_type' => fake()->randomElement(['one_rm', 'volume', 'rep_specific', 'hypertrophy']),
            'rep_count' => null,
            'weight' => null,
            'value' => fake()->randomFloat(2, 100, 500),
            'previous_pr_id' => null,
            'previous_value' => null,
            'achieved_at' => now(),
        ];
    }

    /**
     * Indicate that this is a one rep max PR.
     */
    public function oneRepMax(): static
    {
        return $this->state(fn (array $attributes) => [
            'pr_type' => 'one_rm',
            'rep_count' => null,
            'weight' => null,
        ]);
    }

    /**
     * Indicate that this is a volume PR.
     */
    public function volume(): static
    {
        return $this->state(fn (array $attributes) => [
            'pr_type' => 'volume',
            'rep_count' => null,
            'weight' => null,
            'value' => fake()->randomFloat(2, 1000, 10000),
        ]);
    }

    /**
     * Indicate that this is a rep-specific PR.
     */
    public function repSpecific(int $reps = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'pr_type' => 'rep_specific',
            'rep_count' => $reps,
            'weight' => null,
        ]);
    }

    /**
     * Indicate that this is a hypertrophy PR.
     */
    public function hypertrophy(float $weight = 200.00): static
    {
        return $this->state(fn (array $attributes) => [
            'pr_type' => 'hypertrophy',
            'rep_count' => null,
            'weight' => $weight,
            'value' => fake()->numberBetween(8, 15), // Reps achieved at that weight
        ]);
    }

    /**
     * Indicate that this PR supersedes a previous PR.
     */
    public function supersedes($previousPR): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_pr_id' => $previousPR->id,
            'previous_value' => $previousPR->value,
        ]);
    }
}
