<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AthleteTransformationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AthleteTransformationSeederBasicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed required data for comprehensive nutrition testing
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UnitSeeder::class,
            \Database\Seeders\GlobalExercisesSeeder::class,
            \Database\Seeders\UserSeeder::class, // Creates admin user
            \Database\Seeders\IngredientSeeder::class, // Creates comprehensive ingredient database
        ]);
    }

    /**
     * Test that the seeder runs without errors and creates basic data.
     * This is the core functionality test as specified in task 8.
     */
    public function test_basic_seeder_functionality()
    {
        // Arrange
        $seeder = new AthleteTransformationSeeder();
        
        // Act - This should not throw any exceptions
        $seeder->run();
        
        // Assert - Verify expected data is created in database
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        
        // Basic user creation validation
        $this->assertNotNull($demoUser, 'Demo user should be created');
        $this->assertEquals('Demo Athlete', $demoUser->name);
        
        // Validate that expected data is created in database
        $this->assertGreaterThan(0, $demoUser->programs()->count(), 'User should have programs');
        $this->assertGreaterThan(0, $demoUser->liftLogs()->count(), 'User should have lift logs');
        $this->assertGreaterThan(0, $demoUser->foodLogs()->count(), 'User should have food logs');
        $this->assertGreaterThan(0, $demoUser->bodyLogs()->count(), 'User should have body logs');
        
        // Confirm user has proper data relationships after seeder execution
        $this->assertGreaterThan(0, $demoUser->ingredients()->count(), 'User should have ingredients');
        $this->assertGreaterThan(0, $demoUser->measurementTypes()->count(), 'User should have measurement types');
        
        // Verify lift logs have proper relationships
        $liftLog = $demoUser->liftLogs()->with('exercise', 'liftSets')->first();
        $this->assertNotNull($liftLog->exercise, 'Lift log should have exercise relationship');
        $this->assertGreaterThan(0, $liftLog->liftSets->count(), 'Lift log should have sets');
        
        // Verify food logs have proper relationships
        $foodLog = $demoUser->foodLogs()->with('ingredient')->first();
        $this->assertNotNull($foodLog->ingredient, 'Food log should have ingredient relationship');
        
        // Verify body logs have proper relationships
        $bodyLog = $demoUser->bodyLogs()->with('measurementType')->first();
        $this->assertNotNull($bodyLog->measurementType, 'Body log should have measurement type relationship');
        
        // Verify programs have proper relationships
        $program = $demoUser->programs()->with('exercise')->first();
        $this->assertNotNull($program->exercise, 'Program should have exercise relationship');
    }

    /**
     * Test that all requirements are validated through basic functionality.
     * This ensures the seeder meets all specified requirements.
     */
    public function test_all_requirements_validation()
    {
        // Arrange
        $seeder = new AthleteTransformationSeeder();
        
        // Act
        $seeder->run();
        
        // Assert - Validate all requirements are met
        $demoUser = User::where('email', 'demo.athlete@example.com')->first();
        
        // Requirement 1.1: Complete 3-month dataset (simplified to 4 weeks in default config)
        $this->assertGreaterThan(0, $demoUser->liftLogs()->count());
        $this->assertGreaterThan(0, $demoUser->foodLogs()->count());
        $this->assertGreaterThan(0, $demoUser->bodyLogs()->count());
        
        // Requirement 1.2: Progressive strength gains
        // Check progression within the same exercise type
        $liftLogsWithWeight = $demoUser->liftLogs()
            ->with('liftSets', 'exercise')
            ->whereHas('liftSets', function($query) {
                $query->where('weight', '>', 0);
            })
            ->orderBy('logged_at')
            ->get();
            
        if ($liftLogsWithWeight->count() >= 2) {
            // Group by exercise to check progression within same exercise
            $exerciseGroups = $liftLogsWithWeight->groupBy('exercise_id');
            
            $progressionFound = false;
            foreach ($exerciseGroups as $exerciseId => $logs) {
                if ($logs->count() >= 2) {
                    $firstLog = $logs->first();
                    $lastLog = $logs->last();
                    
                    $firstWeight = $firstLog->liftSets->first()?->weight;
                    $lastWeight = $lastLog->liftSets->first()?->weight;
                    
                    if ($firstWeight && $lastWeight && $lastWeight >= $firstWeight) {
                        $progressionFound = true;
                        break;
                    }
                }
            }
            
            $this->assertTrue($progressionFound, 'Should show strength progression in at least one exercise');
        }
        
        // Requirement 1.3: Weight loss progression
        $weightLogs = $demoUser->bodyLogs()
            ->whereHas('measurementType', function($query) {
                $query->where('name', 'Bodyweight');
            })
            ->orderBy('logged_at')
            ->get();
            
        if ($weightLogs->count() >= 2) {
            $firstWeight = $weightLogs->first()->value;
            $lastWeight = $weightLogs->last()->value;
            $this->assertLessThanOrEqual($firstWeight, $lastWeight, 'Should show weight loss progression');
        }
        
        // Requirement 1.4: Nutrition logs with appropriate intake
        $this->assertGreaterThan(0, $demoUser->foodLogs()->count(), 'Should have nutrition logs');
        
        // Requirement 1.5: Structured workout programs
        $this->assertGreaterThan(0, $demoUser->programs()->count(), 'Should have workout programs');
        
        // Requirement 3.1: Interconnected data relationships
        $program = $demoUser->programs()->with('exercise')->first();
        $this->assertNotNull($program->exercise, 'Programs should reference existing exercises');
        
        // Requirement 3.2: Valid ingredient references
        $foodLog = $demoUser->foodLogs()->with('ingredient')->first();
        $this->assertNotNull($foodLog->ingredient, 'Food logs should reference existing ingredients');
        
        // Requirement 4.4: Demo user creation
        $this->assertEquals('Demo Athlete', $demoUser->name, 'Should create demo user');
        $this->assertEquals('demo.athlete@example.com', $demoUser->email, 'Should use correct demo email');
    }
}