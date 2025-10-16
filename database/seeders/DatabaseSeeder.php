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
            RoleSeeder::class,
            UnitSeeder::class,
            GlobalExercisesSeeder::class,
            ExerciseIntelligenceSeeder::class,
            UserSeeder::class,
            IngredientSeeder::class,
            // Seed existing users with ingredients after IngredientSeeder runs
            ExistingUserIngredientSeeder::class,
            DailyLogSeeder::class,
            MealSeeder::class,
            LiftLogSeeder::class,
            MeasurementSeeder::class,
            ProgramSeeder::class,
        ]);
    }
}
