<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Services\TransformationConfig;
use Database\Seeders\AthleteTransformationSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\GlobalExercisesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AthleteTransformationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed required base data including comprehensive ingredients
        $this->seed([
            RoleSeeder::class,
            UnitSeeder::class,
            GlobalExercisesSeeder::class,
            \Database\Seeders\UserSeeder::class, // Creates admin user
            \Database\Seeders\IngredientSeeder::class, // Creates comprehensive ingredient database
        ]);
    }

    public function test_seeder_runs_without_errors()
    {
        $seeder = new AthleteTransformationSeeder();
        
        // This should not throw any exceptions
        $seeder->run();
        
        // Verify a demo user was created
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        $this->assertNotNull($demoUser);
        $this->assertEquals('Demo Athlete', $demoUser->name);
    }

    public function test_seeder_creates_expected_data_relationships()
    {
        $seeder = new AthleteTransformationSeeder();
        $seeder->run();
        
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        
        // Verify user has measurement types
        $this->assertTrue($demoUser->measurementTypes()->count() > 0);
        
        // Verify user has ingredients
        $this->assertTrue($demoUser->ingredients()->count() > 0);
        
        // Verify user has programs
        $this->assertTrue($demoUser->programs()->count() > 0);
        
        // Verify user has lift logs
        $this->assertTrue($demoUser->liftLogs()->count() > 0);
        
        // Verify user has food logs
        $this->assertTrue($demoUser->foodLogs()->count() > 0);
        
        // Verify user has body logs
        $this->assertTrue($demoUser->bodyLogs()->count() > 0);
    }

    public function test_seeder_works_with_custom_config()
    {
        $config = new TransformationConfig();
        $config->startingWeight = 200.0;
        $config->targetWeight = 180.0;
        $config->startingWaist = 38.0;
        $config->durationWeeks = 8;
        $config->startDate = Carbon::now()->subWeeks(8);
        
        $seeder = new AthleteTransformationSeeder();
        $seeder->runWithConfig($config);
        
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        $this->assertNotNull($demoUser);
        
        // Verify data was created
        $this->assertTrue($demoUser->bodyLogs()->count() > 0);
    }

    public function test_seeder_works_with_existing_user()
    {
        // Create a user first
        $existingUser = User::factory()->create([
            'name' => 'Test Athlete',
            'email' => 'test.athlete@example.com',
        ]);
        
        // Seed the user with basic data
        $userSeederService = new \App\Services\UserSeederService();
        $userSeederService->seedNewUser($existingUser);
        
        $config = new TransformationConfig();
        $config->user = $existingUser;
        
        $seeder = new AthleteTransformationSeeder();
        $seeder->runWithConfig($config);
        
        // Verify the existing user now has transformation data
        $this->assertTrue($existingUser->programs()->count() > 0);
        $this->assertTrue($existingUser->liftLogs()->count() > 0);
        $this->assertTrue($existingUser->foodLogs()->count() > 0);
        $this->assertTrue($existingUser->bodyLogs()->count() > 0);
    }
}