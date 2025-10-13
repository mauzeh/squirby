<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TransformationConfig;
use Carbon\Carbon;
use Database\Seeders\AthleteTransformationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AthleteTransformationSeederIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed basic data needed for transformation seeder including comprehensive ingredients
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UnitSeeder::class,
            \Database\Seeders\GlobalExercisesSeeder::class,
            \Database\Seeders\UserSeeder::class, // Creates admin user
            \Database\Seeders\IngredientSeeder::class, // Creates comprehensive ingredient database
        ]);
    }

    public function test_seeder_runs_successfully_with_default_configuration()
    {
        // Arrange
        $seeder = new AthleteTransformationSeeder();
        
        // Act
        $seeder->run();
        
        // Assert
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        $this->assertNotNull($demoUser);
        $this->assertEquals('Demo Athlete', $demoUser->name);
        
        // Verify data was created
        $this->assertGreaterThan(0, $demoUser->programs()->count());
        $this->assertGreaterThan(0, $demoUser->liftLogs()->count());
        $this->assertGreaterThan(0, $demoUser->foodLogs()->count());
        $this->assertGreaterThan(0, $demoUser->bodyLogs()->count());
        $this->assertGreaterThan(0, $demoUser->ingredients()->count());
        $this->assertGreaterThan(0, $demoUser->measurementTypes()->count());
    }

    public function test_seeder_runs_with_custom_configuration()
    {
        // Arrange
        $config = new TransformationConfig();
        $config->durationWeeks = 8;
        $config->startingWeight = 200.0;
        $config->targetWeight = 180.0;
        $config->startingWaist = 38.0;
        $config->programType = 'powerlifting';
        $config->startDate = Carbon::parse('2025-01-01');
        
        $seeder = new AthleteTransformationSeeder();
        
        // Act
        $seeder->runWithConfig($config);
        
        // Assert
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        $this->assertNotNull($demoUser);
        
        // Verify data was created with custom parameters
        $this->assertGreaterThan(0, $demoUser->programs()->count());
        $this->assertGreaterThan(0, $demoUser->liftLogs()->count());
        $this->assertGreaterThan(0, $demoUser->foodLogs()->count());
        $this->assertGreaterThan(0, $demoUser->bodyLogs()->count());
        
        // Verify dates align with configuration
        $firstLiftLog = $demoUser->liftLogs()->orderBy('logged_at')->first();
        $this->assertTrue($firstLiftLog->logged_at->gte($config->startDate));
        
        $firstBodyLog = $demoUser->bodyLogs()->orderBy('logged_at')->first();
        $this->assertTrue($firstBodyLog->logged_at->gte($config->startDate));
    }

    public function test_seeder_works_with_existing_user()
    {
        // Arrange
        $existingUser = User::factory()->create([
            'name' => 'Test Athlete',
            'email' => 'test.athlete@example.com',
        ]);
        
        // Seed user with basic data
        $userSeederService = new \App\Services\UserSeederService();
        $userSeederService->seedNewUser($existingUser);
        
        $config = new TransformationConfig();
        $config->user = $existingUser;
        $config->durationWeeks = 4;
        
        $seeder = new AthleteTransformationSeeder();
        
        // Act
        $seeder->runWithConfig($config);
        
        // Assert
        $this->assertGreaterThan(0, $existingUser->programs()->count());
        $this->assertGreaterThan(0, $existingUser->liftLogs()->count());
        $this->assertGreaterThan(0, $existingUser->foodLogs()->count());
        $this->assertGreaterThan(0, $existingUser->bodyLogs()->count());
    }

    public function test_seeder_validates_configuration()
    {
        // Arrange
        $config = new TransformationConfig();
        $config->durationWeeks = 0; // Invalid
        
        $seeder = new AthleteTransformationSeeder();
        
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration must be between 1 and 52 weeks.');
        
        $seeder->runWithConfig($config);
    }

    public function test_seeder_validates_weight_configuration()
    {
        // Arrange
        $config = new TransformationConfig();
        $config->startingWeight = 150.0;
        $config->targetWeight = 160.0; // Target higher than starting (invalid for weight loss)
        
        $seeder = new AthleteTransformationSeeder();
        
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Starting weight must be greater than target weight for weight loss transformation.');
        
        $seeder->runWithConfig($config);
    }

    public function test_seeder_creates_required_exercises_if_missing()
    {
        // Arrange
        $seeder = new AthleteTransformationSeeder();
        
        // Act
        $seeder->run();
        
        // Assert
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        
        // Check that required exercises exist (either global or user-specific)
        $requiredExercises = ['Squat', 'Bench Press', 'Deadlift'];
        
        foreach ($requiredExercises as $exerciseTitle) {
            $exerciseExists = \App\Models\Exercise::where('title', $exerciseTitle)
                ->where(function($query) use ($demoUser) {
                    $query->whereNull('user_id')
                          ->orWhere('user_id', $demoUser->id);
                })
                ->exists();
                
            $this->assertTrue($exerciseExists, "Exercise '{$exerciseTitle}' should exist");
        }
    }

    public function test_seeder_creates_progressive_data()
    {
        // Arrange
        $seeder = new AthleteTransformationSeeder();
        
        // Act
        $seeder->run();
        
        // Assert
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        
        // Check that lift logs show progression
        $squatExercise = \App\Models\Exercise::where('title', 'Squat')
            ->where(function($query) use ($demoUser) {
                $query->whereNull('user_id')
                      ->orWhere('user_id', $demoUser->id);
            })
            ->first();
            
        if ($squatExercise) {
            $liftLogs = $demoUser->liftLogs()
                ->where('exercise_id', $squatExercise->id)
                ->with('liftSets')
                ->orderBy('logged_at')
                ->get();
                
            if ($liftLogs->count() >= 2) {
                $firstLog = $liftLogs->first();
                $lastLog = $liftLogs->last();
                
                // Get the weight from the first set of each lift log
                $firstWeight = $firstLog->liftSets->first()?->weight;
                $lastWeight = $lastLog->liftSets->first()?->weight;
                
                // Ensure both logs have weight values in their sets
                $this->assertNotNull($firstWeight, 'First lift log should have sets with weight');
                $this->assertNotNull($lastWeight, 'Last lift log should have sets with weight');
                
                // Weight should increase over time (progressive overload)
                $this->assertGreaterThan($firstWeight, $lastWeight, 
                    "Last weight ({$lastWeight}) should be greater than first weight ({$firstWeight})");
            }
        }
        
        // Check that body measurements show progression
        $weightLogs = $demoUser->bodyLogs()
            ->whereHas('measurementType', function($query) {
                $query->where('name', 'Bodyweight');
            })
            ->orderBy('logged_at')
            ->get();
            
        if ($weightLogs->count() >= 2) {
            $firstWeight = $weightLogs->first();
            $lastWeight = $weightLogs->last();
            
            // Weight should decrease over time (weight loss)
            $this->assertLessThan($firstWeight->value, $lastWeight->value);
        }
    }
}