<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserSeederService;
use Illuminate\Console\Command;

class SeedExistingUsersWithIngredients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:seed-ingredients {--force : Force seeding even if user already has ingredients}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed existing users with default ingredients and measurement types';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');
        $seederService = new UserSeederService();
        
        // Get all users except admin
        $users = User::where('email', '!=', 'admin@example.com')->get();
        
        if ($users->isEmpty()) {
            $this->info('No non-admin users found to seed.');
            return;
        }
        
        $this->info("Found {$users->count()} users to process...");
        
        foreach ($users as $user) {
            $hasIngredients = $user->ingredients()->exists();
            $hasMeasurementTypes = $user->measurementTypes()->exists();
            
            if (!$force && $hasIngredients && $hasMeasurementTypes) {
                $this->line("Skipping {$user->name} ({$user->email}) - already has data");
                continue;
            }
            
            $this->info("Seeding {$user->name} ({$user->email})...");
            
            try {
                $seederService->seedNewUser($user);
                $this->info("✅ Successfully seeded {$user->name}");
            } catch (\Exception $e) {
                $this->error("❌ Failed to seed {$user->name}: " . $e->getMessage());
            }
        }
        
        $this->info('Seeding complete!');
    }
}
