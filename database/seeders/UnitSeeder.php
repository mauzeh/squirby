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
        Unit::create(['name' => 'grams', 'abbreviation' => 'g']);
        Unit::create(['name' => 'kilograms', 'abbreviation' => 'kg']);
        Unit::create(['name' => 'pounds', 'abbreviation' => 'lbs']);
        Unit::create(['name' => 'ounces', 'abbreviation' => 'oz']);
        Unit::create(['name' => 'cups', 'abbreviation' => 'cup']);
        Unit::create(['name' => 'tablespoons', 'abbreviation' => 'tbsp']);
        Unit::create(['name' => 'teaspoons', 'abbreviation' => 'tsp']);
        Unit::create(['name' => 'milliliters', 'abbreviation' => 'ml']);
        Unit::create(['name' => 'liters', 'abbreviation' => 'l']);
        Unit::create(['name' => 'pieces', 'abbreviation' => 'pc']);
    }
}
