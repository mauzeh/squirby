<?php

namespace Database\Factories;

use App\Models\MobileFoodForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MobileFoodForm>
 */
class MobileFoodFormFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MobileFoodForm::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'type' => $this->faker->randomElement(['ingredient', 'meal']),
            'item_id' => $this->faker->numberBetween(1, 100),
            'item_name' => $this->faker->words(2, true),
        ];
    }

    /**
     * Indicate that the form is for an ingredient.
     */
    public function ingredient(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ingredient',
        ]);
    }

    /**
     * Indicate that the form is for a meal.
     */
    public function meal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'meal',
        ]);
    }
}
