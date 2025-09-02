<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            UnitSeeder::class,
            IngredientSeeder::class,
            DailyLogSeeder::class,
            MealSeeder::class,
            ExerciseSeeder::class,
            WorkoutSeeder::class,
            MeasurementSeeder::class,
        ]);
    }
}
