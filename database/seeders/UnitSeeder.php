<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Unit::create(['name' => 'grams', 'abbreviation' => 'g', 'conversion_factor' => 1.0]);
        Unit::create(['name' => 'kilograms', 'abbreviation' => 'kg', 'conversion_factor' => 1000.0]);
        Unit::create(['name' => 'pounds', 'abbreviation' => 'lbs', 'conversion_factor' => 453.592]);
        Unit::create(['name' => 'ounces', 'abbreviation' => 'oz', 'conversion_factor' => 28.3495]);
        Unit::create(['name' => 'cups', 'abbreviation' => 'cup', 'conversion_factor' => 240.0]); // Assuming 1 cup of water = 240g
        Unit::create(['name' => 'tablespoons', 'abbreviation' => 'tbsp', 'conversion_factor' => 15.0]); // Assuming 1 tbsp of water = 15g
        Unit::create(['name' => 'teaspoons', 'abbreviation' => 'tsp', 'conversion_factor' => 5.0]); // Assuming 1 tsp of water = 5g
        Unit::create(['name' => 'milliliters', 'abbreviation' => 'ml', 'conversion_factor' => 1.0]);
        Unit::create(['name' => 'liters', 'abbreviation' => 'l', 'conversion_factor' => 1000.0]);
        Unit::create(['name' => 'pieces', 'abbreviation' => 'pc', 'conversion_factor' => 1.0]);
    }
}
