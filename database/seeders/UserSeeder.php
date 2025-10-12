<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\UserSeederService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userSeederService = new UserSeederService();
        $adminRole = Role::where('name', 'Admin')->first();
        $athleteRole = Role::where('name', 'Athlete')->first();

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        
        // Seed the admin user with only measurement types (no sample meal)
        $userSeederService->seedAdminUser($admin);
        $admin->roles()->attach($adminRole);

        $athlete = User::create([
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        
        // Seed the athlete user with default data
        $userSeederService->seedNewUser($athlete);
        $athlete->roles()->attach($athleteRole);
    }
}
