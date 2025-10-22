<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\ImportJsonLiftLog;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Command;

class ImportJsonLiftLogBandTypeTest extends TestCase
{
    use RefreshDatabase;

    private function createTestUser(): User
    {
        return User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);
    }

    private function createTestJsonFile(array $exercises): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_json');
        file_put_contents($tempFile, json_encode($exercises));
        return $tempFile;
    }

    public function test_creates_exercise_with_resistance_band_type()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Banded Lat Pulldown',
                'canonical_name' => 'banded_lat_pulldown',
                'description' => 'Lat pulldown with resistance band',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 15,
                        'sets' => 3,
                        'notes' => 'Heavy resistance band'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was created with correct band_type
            $exercise = Exercise::where('canonical_name', 'banded_lat_pulldown')->first();
            $this->assertNotNull($exercise);
            $this->assertEquals('resistance', $exercise->band_type);
            $this->assertEquals($user->id, $exercise->user_id);
            $this->assertTrue($exercise->isBandedResistance());
            $this->assertFalse($exercise->isBandedAssistance());
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_creates_exercise_with_assistance_band_type()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Assisted Pull-Up',
                'canonical_name' => 'assisted_pull_up',
                'description' => 'Pull-up with assistance band',
                'is_bodyweight' => true,
                'band_type' => 'assistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 8,
                        'sets' => 3,
                        'notes' => 'Medium assistance band'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was created with correct band_type
            $exercise = Exercise::where('canonical_name', 'assisted_pull_up')->first();
            $this->assertNotNull($exercise);
            $this->assertEquals('assistance', $exercise->band_type);
            $this->assertEquals($user->id, $exercise->user_id);
            $this->assertTrue($exercise->isBandedAssistance());
            $this->assertFalse($exercise->isBandedResistance());
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_creates_exercise_without_band_type_when_not_provided()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Regular Exercise',
                'canonical_name' => 'regular_exercise',
                'description' => 'Exercise without band type',
                'is_bodyweight' => false,
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
                        'sets' => 3,
                        'notes' => 'Regular weight exercise'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was created without band_type
            $exercise = Exercise::where('canonical_name', 'regular_exercise')->first();
            $this->assertNotNull($exercise);
            $this->assertNull($exercise->band_type);
            $this->assertEquals($user->id, $exercise->user_id);
            $this->assertFalse($exercise->isBandedResistance());
            $this->assertFalse($exercise->isBandedAssistance());
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_ignores_invalid_band_type_values()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Invalid Band Exercise',
                'canonical_name' => 'invalid_band_exercise',
                'description' => 'Exercise with invalid band type',
                'is_bodyweight' => false,
                'band_type' => 'invalid_type',
                'lift_logs' => [
                    [
                        'weight' => 50,
                        'reps' => 12,
                        'sets' => 3,
                        'notes' => 'Should ignore invalid band type'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was created but invalid band_type was ignored
            $exercise = Exercise::where('canonical_name', 'invalid_band_exercise')->first();
            $this->assertNotNull($exercise);
            $this->assertNull($exercise->band_type);
            $this->assertEquals($user->id, $exercise->user_id);
            $this->assertFalse($exercise->isBandedResistance());
            $this->assertFalse($exercise->isBandedAssistance());
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_handles_multiple_exercises_with_different_band_types()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Resistance Band Exercise',
                'canonical_name' => 'resistance_band_exercise',
                'description' => 'Exercise with resistance band',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 15,
                        'sets' => 3,
                        'notes' => 'Resistance band work'
                    ]
                ]
            ],
            [
                'exercise' => 'Assistance Band Exercise',
                'canonical_name' => 'assistance_band_exercise',
                'description' => 'Exercise with assistance band',
                'is_bodyweight' => true,
                'band_type' => 'assistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 8,
                        'sets' => 3,
                        'notes' => 'Assistance band work'
                    ]
                ]
            ],
            [
                'exercise' => 'No Band Exercise',
                'canonical_name' => 'no_band_exercise',
                'description' => 'Exercise without bands',
                'is_bodyweight' => false,
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
                        'sets' => 3,
                        'notes' => 'Regular exercise'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify resistance band exercise
            $resistanceExercise = Exercise::where('canonical_name', 'resistance_band_exercise')->first();
            $this->assertNotNull($resistanceExercise);
            $this->assertEquals('resistance', $resistanceExercise->band_type);
            $this->assertTrue($resistanceExercise->isBandedResistance());
            
            // Verify assistance band exercise
            $assistanceExercise = Exercise::where('canonical_name', 'assistance_band_exercise')->first();
            $this->assertNotNull($assistanceExercise);
            $this->assertEquals('assistance', $assistanceExercise->band_type);
            $this->assertTrue($assistanceExercise->isBandedAssistance());
            
            // Verify no band exercise
            $noBandExercise = Exercise::where('canonical_name', 'no_band_exercise')->first();
            $this->assertNotNull($noBandExercise);
            $this->assertNull($noBandExercise->band_type);
            $this->assertFalse($noBandExercise->isBandedResistance());
            $this->assertFalse($noBandExercise->isBandedAssistance());
            
            // Verify all exercises belong to the user
            $this->assertEquals($user->id, $resistanceExercise->user_id);
            $this->assertEquals($user->id, $assistanceExercise->user_id);
            $this->assertEquals($user->id, $noBandExercise->user_id);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_band_type_preserved_when_exercise_already_exists_globally()
    {
        $user = $this->createTestUser();
        
        // Create a global exercise with resistance band type
        $globalExercise = Exercise::create([
            'title' => 'Global Banded Exercise',
            'canonical_name' => 'global_banded_exercise',
            'description' => 'Global exercise with resistance band',
            'is_bodyweight' => false,
            'band_type' => 'resistance',
            'user_id' => null // Global exercise
        ]);
        
        $exercises = [
            [
                'exercise' => 'Global Banded Exercise',
                'canonical_name' => 'global_banded_exercise',
                'description' => 'Should use existing global exercise',
                'is_bodyweight' => false,
                'band_type' => 'assistance', // Different from global, should be ignored
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 3,
                        'notes' => 'Using existing global exercise'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify the global exercise was used and its band_type wasn't changed
            $exercise = Exercise::where('canonical_name', 'global_banded_exercise')->first();
            $this->assertNotNull($exercise);
            $this->assertEquals('resistance', $exercise->band_type); // Should remain as original
            $this->assertNull($exercise->user_id); // Should remain global
            $this->assertTrue($exercise->isBandedResistance());
            
            // Verify only one exercise exists (no duplicate created)
            $exerciseCount = Exercise::where('canonical_name', 'global_banded_exercise')->count();
            $this->assertEquals(1, $exerciseCount);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_creates_lift_sets_with_band_color()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Banded Exercise with Color',
                'canonical_name' => 'banded_exercise_with_color',
                'description' => 'Exercise with band color tracking',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 12,
                        'sets' => 3,
                        'band_color' => 'red',
                        'notes' => 'Using red resistance band'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was created
            $exercise = Exercise::where('canonical_name', 'banded_exercise_with_color')->first();
            $this->assertNotNull($exercise);
            $this->assertEquals('resistance', $exercise->band_type);
            
            // Verify lift log and sets were created with band_color
            $liftLog = $exercise->liftLogs()->first();
            $this->assertNotNull($liftLog);
            
            $liftSets = $liftLog->liftSets;
            $this->assertCount(3, $liftSets);
            
            // All sets should have the same band_color
            foreach ($liftSets as $liftSet) {
                $this->assertEquals('red', $liftSet->band_color);
                $this->assertEquals(0, $liftSet->weight);
                $this->assertEquals(12, $liftSet->reps);
            }
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_creates_lift_sets_without_band_color_when_not_provided()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Banded Exercise without Color',
                'canonical_name' => 'banded_exercise_without_color',
                'description' => 'Exercise without band color',
                'is_bodyweight' => false,
                'band_type' => 'assistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 8,
                        'sets' => 2,
                        'notes' => 'No band color specified'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was created
            $exercise = Exercise::where('canonical_name', 'banded_exercise_without_color')->first();
            $this->assertNotNull($exercise);
            $this->assertEquals('assistance', $exercise->band_type);
            
            // Verify lift sets were created without band_color
            $liftLog = $exercise->liftLogs()->first();
            $this->assertNotNull($liftLog);
            
            $liftSets = $liftLog->liftSets;
            $this->assertCount(2, $liftSets);
            
            // All sets should have null band_color
            foreach ($liftSets as $liftSet) {
                $this->assertNull($liftSet->band_color);
                $this->assertEquals(0, $liftSet->weight);
                $this->assertEquals(8, $liftSet->reps);
            }
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_ignores_empty_band_color()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Banded Exercise with Empty Color',
                'canonical_name' => 'banded_exercise_empty_color',
                'description' => 'Exercise with empty band color',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        'band_color' => '',
                        'notes' => 'Empty band color should be ignored'
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify lift set was created without band_color (empty string ignored)
            $exercise = Exercise::where('canonical_name', 'banded_exercise_empty_color')->first();
            $liftSet = $exercise->liftLogs()->first()->liftSets()->first();
            
            $this->assertNull($liftSet->band_color);
            
        } finally {
            unlink($tempFile);
        }
    }}
