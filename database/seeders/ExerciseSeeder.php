<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class ExerciseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@example.com')->first();

        \App\Models\Exercise::create([
            'user_id' => $adminUser->id,
            'title' => 'Back Squat',
            'description' => 'A compound exercise that targets the muscles of the legs and core.'
        ]);

        \App\Models\Exercise::create([
            'user_id' => $adminUser->id,
            'title' => 'Bench Press',
            'description' => 'A compound exercise that targets the muscles of the upper body, including the chest, shoulders, and triceps.'
        ]);

        \App\Models\Exercise::create([
            'user_id' => $adminUser->id,
            'title' => 'Deadlift',
            'description' => 'A compound exercise that targets the muscles of the back, legs, and grip.'
        ]);

        \App\Models\Exercise::create([
            'user_id' => $adminUser->id,
            'title' => 'Strict Press',
            'description' => 'A compound exercise that targets the shoulders and triceps.'
        ]);

        \App\Models\Exercise::create([
            'user_id' => $adminUser->id,
            'title' => 'Power Clean',
            'description' => 'An explosive deadlift.'
        ]);

        \App\Models\Exercise::create([
            'user_id' => $adminUser->id,
            'title' => 'Half-Kneeling DB Press',
            'description' => 'A unilateral exercise that targets the shoulders and core.'
        ]);

        \App\Models\Exercise::create([
            'user_id' => $adminUser->id,
            'title' => 'Cyclist Squat (Barbell, Front Rack)',
            'description' => 'A squat variation that emphasizes the quadriceps by elevating the heels, performed with a barbell in the front rack position.'
        ]);

    }
}
