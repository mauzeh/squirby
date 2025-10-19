<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class PersistGlobalExercisesSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private $testCsvPath;
    private $originalCsvPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testCsvPath = database_path('seeders/csv/test_exercises_sync.csv');
        $this->originalCsvPath = database_path('seeders/csv/exercises_from_real_world.csv');
    }

    protected function tearDown(): void
    {
        // Clean up test CSV file
        if (File::exists($this->testCsvPath)) {
            File::delete($this->testCsvPath);
        }
        
        // Clean up any backup files
        $backupFiles = glob($this->testCsvPath . '.backup.*');
        foreach ($backupFiles as $backupFile) {
            File::delete($backupFile);
        }
        
        parent::tearDown();
    }

    public function test_end_to_end_sync_with_mixed_scenarios()
    {
        // Create test exercises in database
        $exercise1 = Exercise::create([
            'title' => 'Push Up',
            'description' => 'Basic bodyweight exercise',
            'canonical_name' => 'push_up',
            'is_bodyweight' => true,
            'band_type' => null,
            'user_id' => null
        ]);

        $exercise2 = Exercise::create([
            'title' => 'Band Row',
            'description' => 'Rowing with resistance band',
            'canonical_name' => 'band_row',
            'is_bodyweight' => false,
            'band_type' => 'resistance',
            'user_id' => null
        ]);

        $exercise3 = Exercise::create([
            'title' => 'New Exercise',
            'description' => 'This is new',
            'canonical_name' => 'new_exercise',
            'is_bodyweight' => true,
            'band_type' => 'assistance',
            'user_id' => null
        ]);

        // Create existing CSV with some outdated data
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Push Up\",\"Old description\",1,push_up,\n"; // Needs update
        $csvContent .= "\"Band Row\",\"Rowing with resistance band\",0,band_row,resistance\n"; // No change
        // new_exercise is missing from CSV
        
        File::put($this->testCsvPath, $csvContent);

        // Temporarily replace the CSV path for testing
        $this->app->bind('App\Console\Commands\PersistGlobalExercises', function () {
            return new class extends \App\Console\Commands\PersistGlobalExercises {
                public function handle()
                {
                    // Override CSV path for testing
                    $csvPath = database_path('seeders/csv/test_exercises_sync.csv');
                    
                    // Only allow this command to run in local environment
                    if (!app()->environment('local')) {
                        $this->error('This command can only be run in local environment for security reasons.');
                        return self::FAILURE;
                    }
                    
                    // File validation
                    if (!file_exists($csvPath)) {
                        $this->error("CSV file not found: {$csvPath}");
                        return self::FAILURE;
                    }
                    
                    // Use reflection to call private methods
                    $reflection = new \ReflectionClass(parent::class);
                    
                    $collectMethod = $reflection->getMethod('collectGlobalExercises');
                    $collectMethod->setAccessible(true);
                    $globalExercises = $collectMethod->invoke($this);
                    
                    $parseMethod = $reflection->getMethod('parseExistingCsv');
                    $parseMethod->setAccessible(true);
                    $csvData = $parseMethod->invoke($this, $csvPath);
                    
                    $identifyMethod = $reflection->getMethod('identifyChanges');
                    $identifyMethod->setAccessible(true);
                    $changes = $identifyMethod->invoke($this, $globalExercises, $csvData);
                    
                    $displayMethod = $reflection->getMethod('displayChangeReport');
                    $displayMethod->setAccessible(true);
                    $displayMethod->invoke($this, $changes);
                    
                    // Auto-confirm for testing
                    $totalChanges = $changes['summary']['updates_count'] + $changes['summary']['new_entries_count'];
                    if ($totalChanges === 0) {
                        $this->info('CSV is already synchronized with database. No changes needed.');
                        return self::SUCCESS;
                    }
                    
                    // Perform synchronization without user confirmation
                    try {
                        $syncMethod = $reflection->getMethod('synchronizeCsv');
                        $syncMethod->setAccessible(true);
                        $syncMethod->invoke($this, $csvPath, $changes, $csvData);
                        
                        $this->info("Successfully synchronized {$totalChanges} changes to CSV file.");
                        return self::SUCCESS;
                    } catch (\Exception $e) {
                        $this->error("Failed to synchronize CSV: {$e->getMessage()}");
                        return self::FAILURE;
                    }
                }
            };
        });

        // Run the command
        $exitCode = Artisan::call('exercises:persist-global');
        
        // Verify command succeeded
        $this->assertEquals(0, $exitCode);
        
        // Verify CSV was updated correctly
        $this->assertTrue(File::exists($this->testCsvPath));
        
        $csvContent = File::get($this->testCsvPath);
        $lines = explode("\n", trim($csvContent));
        
        // Should have header + 3 exercises
        $this->assertCount(4, $lines);
        
        // Parse CSV to verify content
        $header = str_getcsv($lines[0]);
        $this->assertEquals(['title', 'description', 'is_bodyweight', 'canonical_name', 'band_type'], $header);
        
        $exercises = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            $exerciseData = array_combine($header, $row);
            $exercises[$exerciseData['canonical_name']] = $exerciseData;
        }
        
        // Verify push_up was updated
        $this->assertEquals('Basic bodyweight exercise', $exercises['push_up']['description']);
        
        // Verify band_row remained unchanged
        $this->assertEquals('Rowing with resistance band', $exercises['band_row']['description']);
        $this->assertEquals('resistance', $exercises['band_row']['band_type']);
        
        // Verify new_exercise was added
        $this->assertArrayHasKey('new_exercise', $exercises);
        $this->assertEquals('New Exercise', $exercises['new_exercise']['title']);
        $this->assertEquals('assistance', $exercises['new_exercise']['band_type']);
        
        // Verify backup was created
        $backupFiles = glob($this->testCsvPath . '.backup.*');
        $this->assertCount(1, $backupFiles);
    }

    public function test_sync_handles_no_changes_needed()
    {
        // Create exercise in database
        Exercise::create([
            'title' => 'Push Up',
            'description' => 'Basic exercise',
            'canonical_name' => 'push_up',
            'is_bodyweight' => true,
            'band_type' => null,
            'user_id' => null
        ]);

        // Create CSV that matches database exactly
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Push Up\",\"Basic exercise\",1,push_up,\n";
        
        File::put($this->testCsvPath, $csvContent);

        // Override command to use test CSV
        $this->app->bind('App\Console\Commands\PersistGlobalExercises', function () {
            return new class extends \App\Console\Commands\PersistGlobalExercises {
                public function handle()
                {
                    $csvPath = database_path('seeders/csv/test_exercises_sync.csv');
                    
                    if (!app()->environment('local')) {
                        return self::FAILURE;
                    }
                    
                    if (!file_exists($csvPath)) {
                        return self::FAILURE;
                    }
                    
                    $reflection = new \ReflectionClass(parent::class);
                    
                    $collectMethod = $reflection->getMethod('collectGlobalExercises');
                    $collectMethod->setAccessible(true);
                    $globalExercises = $collectMethod->invoke($this);
                    
                    $parseMethod = $reflection->getMethod('parseExistingCsv');
                    $parseMethod->setAccessible(true);
                    $csvData = $parseMethod->invoke($this, $csvPath);
                    
                    $identifyMethod = $reflection->getMethod('identifyChanges');
                    $identifyMethod->setAccessible(true);
                    $changes = $identifyMethod->invoke($this, $globalExercises, $csvData);
                    
                    $totalChanges = $changes['summary']['updates_count'] + $changes['summary']['new_entries_count'];
                    if ($totalChanges === 0) {
                        $this->info('CSV is already synchronized with database. No changes needed.');
                        return self::SUCCESS;
                    }
                    
                    return self::SUCCESS;
                }
            };
        });

        $exitCode = Artisan::call('exercises:persist-global');
        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $this->assertStringContainsString('No changes needed', $output);
    }

    public function test_sync_handles_csv_rewrite_functionality()
    {
        // Create multiple exercises with different band types
        Exercise::create([
            'title' => 'Exercise A',
            'description' => 'Description A',
            'canonical_name' => 'exercise_a',
            'is_bodyweight' => false,
            'band_type' => 'resistance',
            'user_id' => null
        ]);

        Exercise::create([
            'title' => 'Exercise B',
            'description' => 'Description B',
            'canonical_name' => 'exercise_b',
            'is_bodyweight' => true,
            'band_type' => 'assistance',
            'user_id' => null
        ]);

        Exercise::create([
            'title' => 'Exercise C',
            'description' => 'Description C',
            'canonical_name' => 'exercise_c',
            'is_bodyweight' => false,
            'band_type' => null,
            'user_id' => null
        ]);

        // Create CSV with different order and some missing exercises
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Exercise B\",\"Old Description B\",1,exercise_b,assistance\n"; // Needs update
        $csvContent .= "\"Exercise A\",\"Description A\",0,exercise_a,resistance\n"; // No change
        // exercise_c is missing
        
        File::put($this->testCsvPath, $csvContent);

        // Override command
        $this->app->bind('App\Console\Commands\PersistGlobalExercises', function () {
            return new class extends \App\Console\Commands\PersistGlobalExercises {
                public function handle()
                {
                    $csvPath = database_path('seeders/csv/test_exercises_sync.csv');
                    
                    if (!file_exists($csvPath)) {
                        return self::FAILURE;
                    }
                    
                    $reflection = new \ReflectionClass(parent::class);
                    
                    $collectMethod = $reflection->getMethod('collectGlobalExercises');
                    $collectMethod->setAccessible(true);
                    $globalExercises = $collectMethod->invoke($this);
                    
                    $parseMethod = $reflection->getMethod('parseExistingCsv');
                    $parseMethod->setAccessible(true);
                    $csvData = $parseMethod->invoke($this, $csvPath);
                    
                    $identifyMethod = $reflection->getMethod('identifyChanges');
                    $identifyMethod->setAccessible(true);
                    $changes = $identifyMethod->invoke($this, $globalExercises, $csvData);
                    
                    $syncMethod = $reflection->getMethod('synchronizeCsv');
                    $syncMethod->setAccessible(true);
                    $syncMethod->invoke($this, $csvPath, $changes, $csvData);
                    
                    return self::SUCCESS;
                }
            };
        });

        Artisan::call('exercises:persist-global');
        
        // Verify CSV structure and content
        $csvContent = File::get($this->testCsvPath);
        $lines = explode("\n", trim($csvContent));
        
        // Should have header + 3 exercises
        $this->assertCount(4, $lines);
        
        // Parse and verify all exercises are present
        $header = str_getcsv($lines[0]);
        $exercises = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            $exerciseData = array_combine($header, $row);
            $exercises[$exerciseData['canonical_name']] = $exerciseData;
        }
        
        // All exercises should be present
        $this->assertArrayHasKey('exercise_a', $exercises);
        $this->assertArrayHasKey('exercise_b', $exercises);
        $this->assertArrayHasKey('exercise_c', $exercises);
        
        // Verify exercise_b was updated
        $this->assertEquals('Description B', $exercises['exercise_b']['description']);
        
        // Verify exercise_c was added
        $this->assertEquals('Exercise C', $exercises['exercise_c']['title']);
        $this->assertEquals('', $exercises['exercise_c']['band_type']);
    }

    public function test_error_handling_for_missing_csv_file()
    {
        // Don't create CSV file
        
        $exitCode = Artisan::call('exercises:persist-global');
        $this->assertEquals(1, $exitCode); // Command should fail
        
        $output = Artisan::output();
        $this->assertStringContainsString('CSV file not found', $output);
    }

    public function test_error_handling_for_non_local_environment()
    {
        // Temporarily change environment
        $originalEnv = app()->environment();
        app()->instance('env', 'production');
        
        try {
            $exitCode = Artisan::call('exercises:persist-global');
            $this->assertEquals(1, $exitCode);
            
            $output = Artisan::output();
            $this->assertStringContainsString('local environment', $output);
        } finally {
            // Restore environment
            app()->instance('env', $originalEnv);
        }
    }
}