<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ingredient::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        // Ensure a Unit exists for the foreign key
        $unit = UnitFactory::new()->create();

        return [
            'name' => $this->faker->word(),
            'calories' => $this->faker->randomFloat(2, 0, 1000),
            'protein' => $this->faker->randomFloat(2, 0, 100),
            'carbs' => $this->faker->randomFloat(2, 0, 100),
            'added_sugars' => $this->faker->randomFloat(2, 0, 50),
            'fats' => $this->faker->randomFloat(2, 0, 100),
            'sodium' => $this->faker->randomFloat(2, 0, 1000),
            'iron' => $this->faker->randomFloat(2, 0, 50),
            'potassium' => $this->faker->randomFloat(2, 0, 2000),
            'base_quantity' => $this->faker->randomFloat(2, 1, 100),
            'base_unit_id' => $unit->id,
            'cost_per_unit' => $this->faker->randomFloat(2, 0.1, 10),
        ];
    }
}
