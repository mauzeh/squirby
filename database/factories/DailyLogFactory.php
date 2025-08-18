<?php

namespace Database\Factories;

use App\Models\DailyLog;
use Database\Factories\IngredientFactory;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DailyLog::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        // Ensure an Ingredient and Unit exist for foreign keys
        $ingredient = IngredientFactory::new()->create();
        $unit = UnitFactory::new()->create();

        return [
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => $this->faker->randomFloat(2, 0.1, 100),
            'logged_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
