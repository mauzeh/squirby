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
        
        // Check if the file exists before trying to load it
        if (!file_exists($jsonPath)) {
            // Skip seeding if the intelligence data file doesn't exist
            return;
        }
        
        $intelligenceData = json_decode(file_get_contents($jsonPath), true);

        if (!$intelligenceData) {
            // Skip if the JSON is invalid or empty
            return;
        }

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