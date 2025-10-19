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
            'is_bodyweight' => true,
            'band_type' => null
        ]);
        
        $csvData = [
            'title' => 'Push-Up',
            'description' => 'A basic push up',
            'is_bodyweight' => '1',
            'band_type' => ''
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertArrayHasKey('title', $differences);
        $this->assertEquals('Push Up', $differences['title']['database']);
        $this->assertEquals('Push-Up', $differences['title']['csv']);
        $this->assertTrue($differences['title']['changed']);
    }

    public function test_compare_exercises_handles_boolean_conversion()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise([
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'is_bodyweight' => false,
            'band_type' => null
        ]);
        
        $csvData = [
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'is_bodyweight' => '1',
            'band_type' => ''
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertArrayHasKey('is_bodyweight', $differences);
        $this->assertEquals('0', $differences['is_bodyweight']['database']);
        $this->assertEquals('1', $differences['is_bodyweight']['csv']);
    }

    public function test_compare_exercises_handles_band_type_differences()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise([
            'title' => 'Band Pull',
            'description' => 'Pull with resistance band',
            'is_bodyweight' => false,
            'band_type' => 'resistance'
        ]);
        
        $csvData = [
            'title' => 'Band Pull',
            'description' => 'Pull with resistance band',
            'is_bodyweight' => '0',
            'band_type' => ''
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertArrayHasKey('band_type', $differences);
        $this->assertEquals('resistance', $differences['band_type']['database']);
        $this->assertEquals('', $differences['band_type']['csv']);
    }

    public function test_compare_exercises_returns_empty_when_identical()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise([
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'is_bodyweight' => true,
            'band_type' => null
        ]);
        
        $csvData = [
            'title' => 'Push Up',
            'description' => 'A basic push up',
            'is_bodyweight' => '1',
            'band_type' => ''
        ];
        
        $differences = $this->callPrivateMethod($command, 'compareExercises', [$exercise, $csvData]);
        
        $this->assertEmpty($differences);
    }

    public function test_parse_existing_csv_with_band_type_column()
    {
        $command = $this->getCommand();
        
        // Create temporary CSV content with band_type column
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Push Up\",\"Basic push up\",1,push_up,\n";
        $csvContent .= "\"Band Pull\",\"Pull with resistance band\",0,band_pull,resistance\n";
        $csvContent .= "\"Assisted Pull Up\",\"Pull up with assistance\",1,assisted_pull_up,assistance\n";
        
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
            $this->assertEquals('1', $result['push_up']['is_bodyweight']);
            $this->assertEquals('', $result['push_up']['band_type']);
            
            // Verify band_pull entry
            $this->assertArrayHasKey('band_pull', $result);
            $this->assertEquals('Band Pull', $result['band_pull']['title']);
            $this->assertEquals('resistance', $result['band_pull']['band_type']);
            
            // Verify assisted_pull_up entry
            $this->assertArrayHasKey('assisted_pull_up', $result);
            $this->assertEquals('assistance', $result['assisted_pull_up']['band_type']);
            
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
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Valid Exercise\",\"Valid description\",1,valid_exercise,\n";
        $csvContent .= "\"Missing Columns\",\"Only two columns\"\n"; // Missing columns
        $csvContent .= "\"Empty Canonical\",\"Empty canonical name\",0,,\n"; // Empty canonical_name
        $csvContent .= "\"Another Valid\",\"Another description\",0,another_valid,resistance\n";
        
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