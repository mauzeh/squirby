<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\ImportJsonLiftLog;
use App\Models\User;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class ImportJsonLiftLogOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_displays_detailed_lift_log_information()
    {
        // Create a user
        $user = User::factory()->create(['email' => 'test@example.com']);
        
        // Create a global exercise
        Exercise::create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'description' => 'Barbell bench press',
            'is_bodyweight' => false,
            'user_id' => null // Global exercise
        ]);

        // Create a temporary JSON file
        $jsonData = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'description' => 'Barbell bench press',
                'is_bodyweight' => false,
                'lift_logs' => [
                    [
                        'weight' => 225,
                        'reps' => 5,
                        'sets' => 3,
                        'notes' => 'First set'
                    ],
                    [
                        'weight' => 215,
                        'reps' => 6,
                        'sets' => 2,
                        'notes' => 'Second set'
                    ]
                ]
            ]
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'lift_log_test');
        file_put_contents($tempFile, json_encode($jsonData));

        try {
            // Run the command and verify it completes successfully
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15 10:30:00'
            ])->assertExitCode(0);

            // Verify the data was imported correctly
            $this->assertDatabaseHas('lift_logs', [
                'user_id' => $user->id,
                'logged_at' => '2024-01-15 10:30:00'
            ]);

            // Verify lift sets were created
            $this->assertDatabaseHas('lift_sets', [
                'weight' => 225,
                'reps' => 5
            ]);

            $this->assertDatabaseHas('lift_sets', [
                'weight' => 215,
                'reps' => 6
            ]);

        } finally {
            // Clean up temp file
            unlink($tempFile);
        }
    }

    public function test_command_displays_exercise_created_message_for_new_exercises()
    {
        // Create a user
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Create JSON data with a new exercise
        $jsonData = [
            [
                'exercise' => 'New Exercise',
                'canonical_name' => 'new_exercise',
                'description' => 'A new exercise',
                'is_bodyweight' => false,
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
                        'sets' => 1
                    ]
                ]
            ]
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'lift_log_test');
        file_put_contents($tempFile, json_encode($jsonData));

        try {
            // Run the command with create-exercises flag
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--create-exercises' => true,
                '--date' => '2024-01-15'
            ])
            ->expectsOutputToContain('✓ Imported: New Exercise')
            ->expectsOutputToContain('→ 100lbs × 10 reps × 1 sets on 2024-01-15 00:00:00')
            ->expectsOutputToContain('Total lift logs imported: 1')
            ->assertExitCode(0);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_command_shows_correct_totals_with_multiple_exercises()
    {
        // Create a user
        $user = User::factory()->create(['email' => 'test@example.com']);
        
        // Create global exercises
        Exercise::create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'description' => 'Barbell bench press',
            'is_bodyweight' => false,
            'user_id' => null
        ]);

        Exercise::create([
            'title' => 'Squat',
            'canonical_name' => 'squat',
            'description' => 'Barbell squat',
            'is_bodyweight' => false,
            'user_id' => null
        ]);

        // Create JSON data with multiple exercises
        $jsonData = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'lift_logs' => [
                    ['weight' => 225, 'reps' => 5, 'sets' => 1],
                    ['weight' => 215, 'reps' => 6, 'sets' => 1]
                ]
            ],
            [
                'exercise' => 'Squat',
                'canonical_name' => 'squat',
                'lift_logs' => [
                    ['weight' => 315, 'reps' => 3, 'sets' => 3]
                ]
            ]
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'lift_log_test');
        file_put_contents($tempFile, json_encode($jsonData));

        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com'
            ])
            ->expectsOutputToContain('Exercises imported: 2')
            ->expectsOutputToContain('Total lift logs imported: 3')
            ->expectsOutputToContain('Exercises skipped: 0')
            ->assertExitCode(0);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_command_shows_skipped_duplicates_in_output()
    {
        // Create a user
        $user = User::factory()->create(['email' => 'test@example.com']);
        
        // Create a global exercise
        $exercise = Exercise::create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'description' => 'Barbell bench press',
            'is_bodyweight' => false,
            'user_id' => null
        ]);

        // Create existing lift log data
        $date = Carbon::parse('2024-01-15');
        $command = new ImportJsonLiftLog();
        $exerciseData = [
            'exercise' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'lift_logs' => [
                ['weight' => 225, 'reps' => 5, 'sets' => 1]
            ]
        ];
        
        // Import once to create existing data
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $date]);

        // Create JSON data with the same exercise/lift log
        $jsonData = [$exerciseData];

        $tempFile = tempnam(sys_get_temp_dir(), 'lift_log_test');
        file_put_contents($tempFile, json_encode($jsonData));

        try {
            // Run command and choose to skip duplicates
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])
            ->expectsChoice('What would you like to do?', 'Skip duplicates and import new ones only', [
                'Skip duplicates and import new ones only',
                'Overwrite existing lift logs',
                'Cancel import'
            ])
            ->expectsOutputToContain('⚠ Skipped duplicate: Bench Press')
            ->expectsOutputToContain('Exercises imported: 0')
            ->expectsOutputToContain('Total lift logs imported: 0')
            ->expectsOutputToContain('Exercises skipped: 1')
            ->assertExitCode(0);

        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Call a private method on an object
     */
    private function callPrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}