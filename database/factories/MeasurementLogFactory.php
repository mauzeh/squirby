<?php

namespace Database\Factories;

use App\Models\MeasurementLog;
use App\Models\User;
use App\Models\MeasurementType;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeasurementLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MeasurementLog::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'measurement_type_id' => MeasurementType::factory(),
            'value' => $this->faker->randomFloat(2, 50, 200),
            'logged_at' => $this->faker->dateTimeThisMonth(),
            'comments' => $this->faker->sentence,
        ];
    }
}
