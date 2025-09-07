<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class IngredientTest extends TestCase
{
    public function test_calories_are_calculated_correctly(): void
    {
        $ingredient = new \App\Models\Ingredient();
        $ingredient->protein = 10;
        $ingredient->carbs = 20;
        $ingredient->fats = 5;

        $this->assertEquals((10 * 4) + (20 * 4) + (5 * 9), $ingredient->calories);
    }
}
