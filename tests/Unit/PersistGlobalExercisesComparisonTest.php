<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\PersistGlobalExercises;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PersistGlobalExercisesComparisonTest extends TestCase
{
    use RefreshDatabase;

    private function getCommand()
    {
        return new PersistGlobalExercises();
    }

    private function callPrivateMethod($object, $method, $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function test_compare_exercises_detects_title_differences()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise([
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'exercise_type' => 'bodyweight'
        ]);
        
        $csvData = [
            'title' => 'Push-Up',
            'description' => 'A basic push up',
            'exercise_type' => 'regular'
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertArrayHasKey('title', $differences);
        $this->assertEquals('Push Up', $differences['title']['database']);
        $this->assertEquals('Push-Up', $differences['title']['csv']);
        $this->assertTrue($differences['title']['changed']);
    }

    public function test_compare_exercises_handles_exercise_type_differences()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise([
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'exercise_type' => 'bodyweight'
        ]);
        
        $csvData = [
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'exercise_type' => 'regular'
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertArrayHasKey('exercise_type', $differences);
        $this->assertEquals('bodyweight', $differences['exercise_type']['database']);
        $this->assertEquals('regular', $differences['exercise_type']['csv']);
        $this->assertTrue($differences['exercise_type']['changed']);
    }

    public function test_compare_exercises_handles_banded_exercise_type_differences()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise([
            'title' => 'Band Pull',
            'description' => 'Pull with resistance band',
            'exercise_type' => 'banded_resistance'
        ]);
        
        $csvData = [
            'title' => 'Band Pull',
            'description' => 'Pull with resistance band',
            'exercise_type' => 'regular'
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertArrayHasKey('exercise_type', $differences);
        $this->assertEquals('banded_resistance', $differences['exercise_type']['database']);
        $this->assertEquals('regular', $differences['exercise_type']['csv']);
        $this->assertTrue($differences['exercise_type']['changed']);
    }

    public function test_compare_exercises_returns_empty_when_identical()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise([
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'exercise_type' => 'bodyweight'
        ]);
        
        $csvData = [
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'exercise_type' => 'bodyweight'
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertEmpty($differences);
    }

    public function test_parse_existing_csv_with_exercise_type_column()
    {
        $command = $this->getCommand();
        
        // Create temporary CSV content with exercise_type column
        $csvContent = "title,description,canonical_name,exercise_type\n";
        $csvContent .= "\"Push Up\",\"Basic push up\",push_up,bodyweight\n";
        $csvContent .= "\"Band Pull\",\"Pull with resistance band\",band_pull,banded_resistance\n";
        $csvContent .= "\"Assisted Pull Up\",\"Pull up with assistance\",assisted_pull_up,banded_assistance\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            // Verify structure
            $this->assertIsArray($result);
            $this->assertCount(3, $result);
            
            // Verify push_up entry
            $this->assertArrayHasKey('push_up', $result);
            $this->assertEquals('Push Up', $result['push_up']['title']);
            $this->assertEquals('Basic push up', $result['push_up']['description']);
            $this->assertEquals('bodyweight', $result['push_up']['exercise_type']);
            
            // Verify band_pull entry
            $this->assertArrayHasKey('band_pull', $result);
            $this->assertEquals('Band Pull', $result['band_pull']['title']);
            $this->assertEquals('banded_resistance', $result['band_pull']['exercise_type']);
            
            // Verify assisted_pull_up entry
            $this->assertArrayHasKey('assisted_pull_up', $result);
            $this->assertEquals('banded_assistance', $result['assisted_pull_up']['exercise_type']);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_existing_csv_handles_malformed_entries()
    {
        // Create a mock command that doesn't use output methods
        $command = new class extends PersistGlobalExercises {
            public function warn($string, $verbosity = null) {
                // Override to prevent output issues in tests
            }
            
            public function error($string, $verbosity = null) {
                // Override to prevent output issues in tests
            }
        };
        
        // Create CSV with malformed entries
        $csvContent = "title,description,canonical_name,exercise_type\n";
        $csvContent .= "\"Valid Exercise\",\"Valid description\",valid_exercise,bodyweight\n";
        $csvContent .= "\"Missing Columns\",\"Only two columns\"\n"; // Missing columns
        $csvContent .= "\"Empty Canonical\",\"Empty canonical name\",,regular\n"; // Empty canonical_name
        $csvContent .= "\"Another Valid\",\"Another description\",another_valid,banded_resistance\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            // Debug what we actually got
            // dd($result);
            
            // Should only have valid entries (the empty canonical_name row should be skipped)
            $this->assertCount(2, $result);
            $this->assertArrayHasKey('valid_exercise', $result);
            $this->assertArrayHasKey('another_valid', $result);
            
            // Malformed entries should be skipped (no key for empty canonical_name)
            
        } finally {
            unlink($tempFile);
        }
    }
}