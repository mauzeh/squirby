<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Admin');

        $athlete = User::create([
            'name' => 'Athlete User',
            'email' => 'athlete@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $athlete->assignRole('Athlete');

        $nutritionNinja = User::create([
            'name' => 'Nutrition Ninja User',
            'email' => 'nutrition.ninja@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $nutritionNinja->assignRole('Nutrition Ninja');
    }
}