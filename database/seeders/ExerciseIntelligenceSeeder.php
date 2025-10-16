<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;

class ExerciseIntelligenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Load intelligence data from JSON file
        $jsonPath = database_path('seeders/json/exercise_intelligence_data.json');
        $intelligenceData = json_decode(file_get_contents($jsonPath), true);

        foreach ($intelligenceData as $canonicalName => $data) {
            // Find the exercise by canonical name
            $exercise = Exercise::where('canonical_name', $canonicalName)
                ->whereNull('user_id')
                ->first();

            if ($exercise) {
                // Create or update the intelligence data
                ExerciseIntelligence::updateOrCreate(
                    ['exercise_id' => $exercise->id],
                    $data
                );
            }
        }
    }
}