<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SampleFoodDataService;
use Illuminate\Console\Command;

class CreateSampleFoodData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'food:create-samples {user_id? : The user ID to create samples for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create 50 common ingredients and 5 sample meals for a user';

    protected SampleFoodDataService $sampleFoodDataService;

    public function __construct(SampleFoodDataService $sampleFoodDataService)
    {
        parent::__construct();
        $this->sampleFoodDataService = $sampleFoodDataService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if (!$userId) {
            $users = User::withCount('ingredients')->get();
            
            if ($users->isEmpty()) {
                $this->error('No users found in the database.');
                return 1;
            }

            $this->info('Available users:');
            $this->table(
                ['ID', 'Name', 'Email', 'Existing Ingredients'],
                $users->map(fn ($user) => [$user->id, $user->name, $user->email, $user->ingredients_count])
            );

            $userId = $this->ask('Please enter the ID of the user to create samples for');
        }
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Creating sample food data for {$user->name} ({$user->email})...");

        // Check if user already has ingredients
        if ($user->ingredients()->exists()) {
            if (!$this->confirm('User already has ingredients. Continue anyway?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $result = $this->sampleFoodDataService->createSampleData($user);
            
            $this->info('✅ Sample food data created successfully!');
            $this->info("Created {$result['ingredients']->count()} ingredients and {$result['meals']->count()} meals for {$user->name}");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create sample data: {$e->getMessage()}");
            return 1;
        }
    }
}
