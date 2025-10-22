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
                        'band_color' => 'red',
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
                        'band_color' => 'green',
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
                        'band_color' => 'red',
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
                        'band_color' => 'green',
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

    public function test_validates_band_type_requires_band_color()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Banded Exercise without Color',
                'canonical_name' => 'banded_exercise_without_color',
                'description' => 'Exercise with band_type but no band_color should fail validation',
                'is_bodyweight' => false,
                'band_type' => 'assistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 8,
                        'sets' => 2,
                        'notes' => 'No band color specified - should fail'
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
            ->expectsOutputToContain('has band_type \'assistance\' but lift log(s) #1 are missing band_color')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was NOT created due to validation failure
            $exercise = Exercise::where('canonical_name', 'banded_exercise_without_color')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_validates_empty_band_color_with_band_type()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Banded Exercise with Empty Color',
                'canonical_name' => 'banded_exercise_empty_color',
                'description' => 'Exercise with band_type but empty band_color should fail validation',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        'band_color' => '',
                        'notes' => 'Empty band color should fail validation'
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
            ->expectsOutputToContain('has band_type \'resistance\' but lift log(s) #1 are missing band_color')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify exercise was NOT created due to validation failure
            $exercise = Exercise::where('canonical_name', 'banded_exercise_empty_color')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_validates_band_type_required_when_band_color_provided()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Invalid Banded Exercise',
                'canonical_name' => 'invalid_banded_exercise',
                'description' => 'Exercise with band color but no band type',
                'is_bodyweight' => false,
                // Missing band_type
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        'band_color' => 'red', // Has band color but no band type
                        'notes' => 'Should fail validation'
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
            ->expectsOutputToContain('has lift log(s) #1 with band_color but no valid band_type')
            ->assertExitCode(Command::SUCCESS); // Command succeeds but skips the invalid exercise
            
            // Verify no exercise was created due to validation failure
            $exercise = Exercise::where('canonical_name', 'invalid_banded_exercise')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_validates_band_color_required_when_band_type_provided()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Incomplete Banded Exercise',
                'canonical_name' => 'incomplete_banded_exercise',
                'description' => 'Exercise with band type but no band color',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        // Missing band_color
                        'notes' => 'Should fail validation'
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
            ->expectsOutputToContain('has band_type \'resistance\' but lift log(s) #1 are missing band_color')
            ->assertExitCode(Command::SUCCESS); // Command succeeds but skips the invalid exercise
            
            // Verify no exercise was created due to validation failure
            $exercise = Exercise::where('canonical_name', 'incomplete_banded_exercise')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_validates_multiple_lift_logs_with_mixed_band_colors()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Mixed Band Color Exercise',
                'canonical_name' => 'mixed_band_color_exercise',
                'description' => 'Exercise with some lift logs missing band color',
                'is_bodyweight' => false,
                'band_type' => 'assistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 8,
                        'sets' => 1,
                        'band_color' => 'green',
                        'notes' => 'Has band color'
                    ],
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        // Missing band_color
                        'notes' => 'Missing band color'
                    ],
                    [
                        'weight' => 0,
                        'reps' => 12,
                        'sets' => 1,
                        // Missing band_color
                        'notes' => 'Also missing band color'
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
            ->expectsOutputToContain('lift log(s) #2, 3 are missing band_color')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify no exercise was created due to validation failure
            $exercise = Exercise::where('canonical_name', 'mixed_band_color_exercise')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_allows_non_banded_exercises_without_band_info()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Regular Exercise',
                'canonical_name' => 'regular_exercise_no_bands',
                'description' => 'Exercise without any band information',
                'is_bodyweight' => false,
                // No band_type
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
                        'sets' => 3,
                        // No band_color
                        'notes' => 'Regular weighted exercise'
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
            
            // Verify exercise was created successfully
            $exercise = Exercise::where('canonical_name', 'regular_exercise_no_bands')->first();
            $this->assertNotNull($exercise);
            $this->assertNull($exercise->band_type);
            
            // Verify lift sets were created without band_color
            $liftSets = $exercise->liftLogs()->first()->liftSets;
            foreach ($liftSets as $liftSet) {
                $this->assertNull($liftSet->band_color);
            }
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_validates_invalid_band_type_with_band_color()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Invalid Band Type Exercise',
                'canonical_name' => 'invalid_band_type_exercise',
                'description' => 'Exercise with invalid band type',
                'is_bodyweight' => false,
                'band_type' => 'invalid_type', // Invalid band type
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        'band_color' => 'red',
                        'notes' => 'Should fail validation'
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
            ->expectsOutputToContain('Band type \'invalid_type\' is invalid')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify no exercise was created due to validation failure
            $exercise = Exercise::where('canonical_name', 'invalid_band_type_exercise')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_validates_invalid_band_colors()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Exercise with Invalid Band Color',
                'canonical_name' => 'exercise_invalid_band_color',
                'description' => 'Exercise with invalid band color',
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        'band_color' => 'whatever', // Invalid color
                        'notes' => 'Should fail validation'
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
            
            // Verify no exercise was created due to validation failure
            $exercise = Exercise::where('canonical_name', 'exercise_invalid_band_color')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_validates_multiple_invalid_band_colors()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Exercise with Multiple Invalid Colors',
                'canonical_name' => 'exercise_multiple_invalid_colors',
                'description' => 'Exercise with multiple invalid band colors',
                'is_bodyweight' => false,
                'band_type' => 'assistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 8,
                        'sets' => 1,
                        'band_color' => 'purple', // Invalid color
                        'notes' => 'First invalid color'
                    ],
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        'band_color' => 'yellow', // Invalid color
                        'notes' => 'Second invalid color'
                    ],
                    [
                        'weight' => 0,
                        'reps' => 12,
                        'sets' => 1,
                        'band_color' => 'red', // Valid color
                        'notes' => 'Valid color but exercise still fails'
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
            
            // Verify no exercise was created due to validation failure
            $exercise = Exercise::where('canonical_name', 'exercise_multiple_invalid_colors')->first();
            $this->assertNull($exercise);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_accepts_valid_band_colors_from_config()
    {
        $user = $this->createTestUser();
        
        // Get valid colors from config
        $validColors = array_keys(config('bands.colors', []));
        $this->assertNotEmpty($validColors, 'No valid band colors found in config');
        
        $exercises = [];
        foreach ($validColors as $index => $color) {
            $exercises[] = [
                'exercise' => "Exercise with {$color} band",
                'canonical_name' => "exercise_with_{$color}_band",
                'description' => "Exercise using {$color} band",
                'is_bodyweight' => false,
                'band_type' => 'resistance',
                'lift_logs' => [
                    [
                        'weight' => 0,
                        'reps' => 10,
                        'sets' => 1,
                        'band_color' => $color,
                        'notes' => "Using {$color} band"
                    ]
                ]
            ];
        }
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify all exercises were created successfully
            foreach ($validColors as $color) {
                $exercise = Exercise::where('canonical_name', "exercise_with_{$color}_band")->first();
                $this->assertNotNull($exercise, "Exercise with {$color} band was not created");
                $this->assertEquals('resistance', $exercise->band_type);
                
                // Verify lift sets have correct band color
                $liftSets = $exercise->liftLogs()->first()->liftSets;
                foreach ($liftSets as $liftSet) {
                    $this->assertEquals($color, $liftSet->band_color);
                }
            }
            
        } finally {
            unlink($tempFile);
        }
    }}
