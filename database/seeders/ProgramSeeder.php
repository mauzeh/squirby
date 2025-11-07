<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use Carbon\Carbon;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            // Or create a user if you want
            return;
        }

        $backSquat = Exercise::where('title', 'Back Squat')->where('user_id', $user->id)->first();
        $benchPress = Exercise::where('title', 'Bench Press')->where('user_id', $user->id)->first();

        if ($backSquat) {
            Program::create([
                'user_id' => $user->id,
                'exercise_id' => $backSquat->id,
                'date' => Carbon::tomorrow(),
                'sets' => 5,
                'reps' => 3,
                'weight' => null, // Or a specific weight
            ]);
        }

        if ($benchPress) {
            Program::create([
                'user_id' => $user->id,
                'exercise_id' => $benchPress->id,
                'date' => Carbon::tomorrow(),
                'sets' => 5,
                'reps' => 3,
                'weight' => null, // Or a specific weight
            ]);
        }
    }
}