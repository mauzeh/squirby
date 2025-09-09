<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\MeasurementType;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BodyLog>
 */
class BodyLogFactory extends Factory
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
            'measurement_type_id' => MeasurementType::factory(),
            'value' => $this->faker->randomFloat(2, 50, 200),
            'logged_at' => Carbon::now(),
            'comments' => $this->faker->sentence,
        ];
    }
}