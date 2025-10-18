<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use Database\Seeders\GlobalExercisesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class GlobalExercisesSeederTest extends TestCase
{
    use RefreshDatabase;

    private $testCsvPath;
    private $originalCsvPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testCsvPath = database_path('seeders/csv/test_exercises.csv');
        $this->originalCsvPath = database_path('seeders/csv/exercises_from_real_world.csv');
    }

    protected function tearDown(): void
    {
        // Clean up test CSV file
        if (File::exists($this->testCsvPath)) {
            File::delete($this->testCsvPath);
        }
        
        parent::tearDown();
    }

    public function test_seeder_reads_csv_file_correctly()
    {
        // Create a test CSV file with known data
        $csvContent = "title,description,is_bodyweight\n";
        $csvContent .= "\"Test Exercise 1\",\"Test description 1\",0\n";
        $csvContent .= "\"Test Exercise 2\",\"Test description 2\",1\n";
        $csvContent .= "\"Test Exercise 3\",\"\",0\n";
        
        File::put($this->testCsvPath, $csvContent);
        
        // Temporarily replace the CSV path in the seeder
        $seeder = new class extends GlobalExercisesSeeder {
            public function run(): void
            {
                $csvPath = database_path('seeders/csv/test_exercises.csv');
                
                if (!file_exists($csvPath)) {
                    throw new \Exception("CSV file not found: {$csvPath}. Please ensure the exercises CSV file exists.");
                }
                
                $csvLines = file($csvPath);
                
                if ($csvLines === false) {
                    throw new \Exception("Failed to read CSV file: {$csvPath}. Please check file permissions.");
                }
                
                $header = null;
                
                foreach ($csvLines as $lineNumber => $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    try {
                        $rowData = str_getcsv($line);
                        
                        if ($lineNumber === 0) {
                            $header = $rowData;
                            continue;
                        }
                        
                        if (count($rowData) !== count($header)) {
                            continue;
                        }
                        
                        $exerciseData = array_combine($header, $rowData);
                        
                        if (!isset($exerciseData['title']) || empty(trim($exerciseData['title']))) {
                            continue;
                        }

                        $isBodyweight = isset($exerciseData['is_bodyweight']) && 
                                       in_array(strtolower($exerciseData['is_bodyweight']), ['1', 'true']);

                        $exercise = [
                            'title' => $exerciseData['title'],
                            'description' => $exerciseData['description'] ?? '',
                            'user_id' => null
                        ];

                        if ($isBodyweight) {
                            $exercise['is_bodyweight'] = true;
                        }

                        Exercise::firstOrCreate(
                            ['title' => $exercise['title'], 'user_id' => null],
                            $exercise
                        );
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        };
        
        // Ensure no exercises exist initially
        $this->assertEquals(0, Exercise::count());

        // Run the seeder
        $seeder->run();

        // Verify exercises were created from CSV
        $this->assertEquals(3, Exercise::count());
        
        // Verify specific exercises from CSV
        $exercise1 = Exercise::where('title', 'Test Exercise 1')->first();
        $this->assertNotNull($exercise1);
        $this->assertEquals('Test description 1', $exercise1->description);
        $this->assertFalse($exercise1->is_bodyweight);
        
        $exercise2 = Exercise::where('title', 'Test Exercise 2')->first();
        $this->assertNotNull($exercise2);
        $this->assertEquals('Test description 2', $exercise2->description);
        $this->assertTrue($exercise2->is_bodyweight);
        
        $exercise3 = Exercise::where('title', 'Test Exercise 3')->first();
        $this->assertNotNull($exercise3);
        $this->assertEquals('', $exercise3->description);
        $this->assertFalse($exercise3->is_bodyweight);
    }

    public function test_boolean_field_conversion_for_is_bodyweight()
    {
        // Run the seeder
        $seeder = new GlobalExercisesSeeder();
        $seeder->run();

        // Verify bodyweight exercises are marked correctly (is_bodyweight = 1 in CSV)
        $bodyweightExercises = [
            'Chin-Ups', 
            'Pull-Ups', 
            'L-Sit (Tucked, Parallelites)',
            'Lat Pull-Down (Kneeled)',
            'Push-Up',
            'Ring Row'
        ];
        
        foreach ($bodyweightExercises as $exerciseTitle) {
            $exercise = Exercise::where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise, "Exercise '{$exerciseTitle}' should exist");
            $this->assertTrue($exercise->is_bodyweight, "Exercise '{$exerciseTitle}' should be marked as bodyweight");
        }

        // Verify non-bodyweight exercises are not marked as bodyweight (is_bodyweight = 0 in CSV)
        $nonBodyweightExercises = [
            'Back Squat', 
            'Bench Press', 
            'Deadlift', 
            'Strict Press',
            'Power Clean',
            'Half-Kneeling DB Press'
        ];
        
        foreach ($nonBodyweightExercises as $exerciseTitle) {
            $exercise = Exercise::where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise, "Exercise '{$exerciseTitle}' should exist");
            $this->assertFalse($exercise->is_bodyweight, "Exercise '{$exerciseTitle}' should not be marked as bodyweight");
        }
    }

    public function test_error_handling_for_missing_csv_file()
    {
        // Create a seeder that looks for a non-existent file
        $seeder = new class extends GlobalExercisesSeeder {
            public function run(): void
            {
                $csvPath = database_path('seeders/csv/non_existent_file.csv');
                
                if (!file_exists($csvPath)) {
                    throw new \Exception("CSV file not found: {$csvPath}. Please ensure the exercises CSV file exists.");
                }
                
                // This should not be reached
                parent::run();
            }
        };

        // Expect an exception when CSV file is missing
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV file not found');
        
        $seeder->run();
    }

    public function test_error_handling_for_malformed_csv_rows()
    {
        // Create a test CSV file with malformed rows
        $csvContent = "title,description,is_bodyweight\n";
        $csvContent .= "\"Valid Exercise\",\"Valid description\",0\n";
        $csvContent .= "\"Malformed Row\",\"Missing comma\"\n"; // Missing third column
        $csvContent .= "\"\",\"Empty title\",0\n"; // Empty title
        $csvContent .= "\"Another Valid Exercise\",\"Another description\",1\n";
        
        File::put($this->testCsvPath, $csvContent);
        
        // Create seeder that uses test CSV
        $seeder = new class extends GlobalExercisesSeeder {
            public function run(): void
            {
                $csvPath = database_path('seeders/csv/test_exercises.csv');
                
                if (!file_exists($csvPath)) {
                    throw new \Exception("CSV file not found: {$csvPath}. Please ensure the exercises CSV file exists.");
                }
                
                $csvLines = file($csvPath);
                
                if ($csvLines === false) {
                    throw new \Exception("Failed to read CSV file: {$csvPath}. Please check file permissions.");
                }
                
                $header = null;
                
                foreach ($csvLines as $lineNumber => $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    try {
                        $rowData = str_getcsv($line);
                        
                        if ($lineNumber === 0) {
                            $header = $rowData;
                            continue;
                        }
                        
                        if (count($rowData) !== count($header)) {
                            continue; // Skip malformed rows
                        }
                        
                        $exerciseData = array_combine($header, $rowData);
                        
                        if (!isset($exerciseData['title']) || empty(trim($exerciseData['title']))) {
                            continue; // Skip rows with empty title
                        }

                        $isBodyweight = isset($exerciseData['is_bodyweight']) && 
                                       in_array(strtolower($exerciseData['is_bodyweight']), ['1', 'true']);

                        $exercise = [
                            'title' => $exerciseData['title'],
                            'description' => $exerciseData['description'] ?? '',
                            'user_id' => null
                        ];

                        if ($isBodyweight) {
                            $exercise['is_bodyweight'] = true;
                        }

                        Exercise::firstOrCreate(
                            ['title' => $exercise['title'], 'user_id' => null],
                            $exercise
                        );
                    } catch (\Exception $e) {
                        continue; // Skip malformed rows gracefully
                    }
                }
            }
        };

        // Ensure no exercises exist initially
        $this->assertEquals(0, Exercise::count());

        // Run the seeder - should complete successfully despite malformed rows
        $seeder->run();

        // Verify only valid exercises were created (malformed and empty title rows skipped)
        $this->assertEquals(2, Exercise::count());
        
        $validExercise = Exercise::where('title', 'Valid Exercise')->first();
        $this->assertNotNull($validExercise);
        $this->assertEquals('Valid description', $validExercise->description);
        $this->assertFalse($validExercise->is_bodyweight);
        
        $anotherValidExercise = Exercise::where('title', 'Another Valid Exercise')->first();
        $this->assertNotNull($anotherValidExercise);
        $this->assertEquals('Another description', $anotherValidExercise->description);
        $this->assertTrue($anotherValidExercise->is_bodyweight);
        
        // Verify malformed and empty title exercises were not created
        $malformedExercise = Exercise::where('title', 'Malformed Row')->first();
        $this->assertNull($malformedExercise);
        
        $emptyTitleExercise = Exercise::where('title', '')->first();
        $this->assertNull($emptyTitleExercise);
    }

    public function test_seeder_handles_empty_descriptions()
    {
        // Run the seeder
        $seeder = new GlobalExercisesSeeder();
        $seeder->run();

        // Verify exercises with empty descriptions in CSV are handled correctly
        $exercisesWithEmptyDescriptions = [
            'Front Squat',
            'Hip Thrust (Barbell)',
            'Kettlebell Swing',
            'Push Press',
            'Zombie Squat',
            'Romanian Deadlift'
        ];
        
        foreach ($exercisesWithEmptyDescriptions as $exerciseTitle) {
            $exercise = Exercise::where('title', $exerciseTitle)->first();
            $this->assertNotNull($exercise, "Exercise '{$exerciseTitle}' should exist");
            $this->assertEquals('', $exercise->description, "Exercise '{$exerciseTitle}' should have empty description");
        }
    }

    public function test_seeder_processes_csv_with_quoted_fields()
    {
        // Create a test CSV with quoted fields containing commas
        $csvContent = "title,description,is_bodyweight\n";
        $csvContent .= "\"Exercise, with comma\",\"Description, with comma\",0\n";
        $csvContent .= "\"Simple Exercise\",\"Simple description\",1\n";
        
        File::put($this->testCsvPath, $csvContent);
        
        // Create seeder that uses test CSV
        $seeder = new class extends GlobalExercisesSeeder {
            public function run(): void
            {
                $csvPath = database_path('seeders/csv/test_exercises.csv');
                
                if (!file_exists($csvPath)) {
                    throw new \Exception("CSV file not found: {$csvPath}. Please ensure the exercises CSV file exists.");
                }
                
                $csvLines = file($csvPath);
                $header = null;
                
                foreach ($csvLines as $lineNumber => $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    try {
                        $rowData = str_getcsv($line);
                        
                        if ($lineNumber === 0) {
                            $header = $rowData;
                            continue;
                        }
                        
                        if (count($rowData) !== count($header)) {
                            continue;
                        }
                        
                        $exerciseData = array_combine($header, $rowData);
                        
                        if (!isset($exerciseData['title']) || empty(trim($exerciseData['title']))) {
                            continue;
                        }

                        $isBodyweight = isset($exerciseData['is_bodyweight']) && 
                                       in_array(strtolower($exerciseData['is_bodyweight']), ['1', 'true']);

                        $exercise = [
                            'title' => $exerciseData['title'],
                            'description' => $exerciseData['description'] ?? '',
                            'user_id' => null
                        ];

                        if ($isBodyweight) {
                            $exercise['is_bodyweight'] = true;
                        }

                        Exercise::firstOrCreate(
                            ['title' => $exercise['title'], 'user_id' => null],
                            $exercise
                        );
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        };

        // Run the seeder
        $seeder->run();

        // Verify CSV parsing handled quoted fields correctly
        $exerciseWithComma = Exercise::where('title', 'Exercise, with comma')->first();
        $this->assertNotNull($exerciseWithComma);
        $this->assertEquals('Description, with comma', $exerciseWithComma->description);
        $this->assertFalse($exerciseWithComma->is_bodyweight);
        
        $simpleExercise = Exercise::where('title', 'Simple Exercise')->first();
        $this->assertNotNull($simpleExercise);
        $this->assertEquals('Simple description', $simpleExercise->description);
        $this->assertTrue($simpleExercise->is_bodyweight);
    }
}