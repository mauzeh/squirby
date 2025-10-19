<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\PersistGlobalExercises;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PersistGlobalExercisesCsvParsingTest extends TestCase
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

    public function test_parse_existing_csv_with_complete_data()
    {
        $command = $this->getCommand();
        
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Push Up\",\"Basic bodyweight exercise\",1,push_up,\n";
        $csvContent .= "\"Band Row\",\"Rowing with resistance band\",0,band_row,resistance\n";
        $csvContent .= "\"Assisted Squat\",\"Squat with band assistance\",1,assisted_squat,assistance\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            $this->assertCount(3, $result);
            
            // Verify each entry has all required fields
            foreach ($result as $canonicalName => $data) {
                $this->assertArrayHasKey('title', $data);
                $this->assertArrayHasKey('description', $data);
                $this->assertArrayHasKey('is_bodyweight', $data);
                $this->assertArrayHasKey('canonical_name', $data);
                $this->assertArrayHasKey('band_type', $data);
                $this->assertArrayHasKey('line_number', $data);
            }
            
            // Verify specific data
            $this->assertEquals('Push Up', $result['push_up']['title']);
            $this->assertEquals('', $result['push_up']['band_type']);
            $this->assertEquals('resistance', $result['band_row']['band_type']);
            $this->assertEquals('assistance', $result['assisted_squat']['band_type']);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_existing_csv_handles_empty_file()
    {
        // Create a mock command that doesn't use output methods
        $command = new class extends PersistGlobalExercises {
            public function warn($string, $verbosity = null) {
                // Override to prevent output issues in tests
            }
        };
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, '');
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            $this->assertEmpty($result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_existing_csv_handles_missing_required_columns()
    {
        // Create a mock command that doesn't use output methods
        $command = new class extends PersistGlobalExercises {
            public function warn($string, $verbosity = null) {
                // Override to prevent output issues in tests
            }
        };
        
        // CSV missing band_type column
        $csvContent = "title,description,is_bodyweight,canonical_name\n";
        $csvContent .= "\"Push Up\",\"Basic exercise\",1,push_up\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            // Should still parse but with default band_type
            $this->assertCount(1, $result);
            $this->assertArrayHasKey('push_up', $result);
            $this->assertEquals('', $result['push_up']['band_type']);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_existing_csv_skips_rows_without_canonical_name()
    {
        // Create a mock command that doesn't use output methods
        $command = new class extends PersistGlobalExercises {
            public function warn($string, $verbosity = null) {
                // Override to prevent output issues in tests
            }
        };
        
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Valid Exercise\",\"Has canonical name\",1,valid_exercise,\n";
        $csvContent .= "\"Invalid Exercise\",\"No canonical name\",0,,\n";
        $csvContent .= "\"Another Valid\",\"Also has canonical name\",0,another_valid,resistance\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            // Should only have entries with canonical names
            $this->assertCount(2, $result);
            $this->assertArrayHasKey('valid_exercise', $result);
            $this->assertArrayHasKey('another_valid', $result);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_existing_csv_handles_quoted_fields_with_commas()
    {
        $command = $this->getCommand();
        
        $csvContent = "title,description,is_bodyweight,canonical_name,band_type\n";
        $csvContent .= "\"Exercise, with comma\",\"Description, with comma\",1,exercise_comma,\n";
        $csvContent .= "\"Normal Exercise\",\"Normal description\",0,normal_exercise,resistance\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            $this->assertCount(2, $result);
            $this->assertEquals('Exercise, with comma', $result['exercise_comma']['title']);
            $this->assertEquals('Description, with comma', $result['exercise_comma']['description']);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_prepare_exercise_for_csv_handles_all_field_types()
    {
        $command = $this->getCommand();
        
        // Test with all fields populated
        $exercise = (object) [
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'is_bodyweight' => true,
            'canonical_name' => 'test_exercise',
            'band_type' => 'resistance'
        ];
        
        $result = $this->callPrivateMethod($command, 'prepareExerciseForCsv', [$exercise]);
        
        $this->assertEquals([
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'is_bodyweight' => '1',
            'canonical_name' => 'test_exercise',
            'band_type' => 'resistance'
        ], $result);
    }

    public function test_prepare_exercise_for_csv_handles_null_values()
    {
        $command = $this->getCommand();
        
        // Test with null values
        $exercise = (object) [
            'title' => 'Test Exercise',
            'description' => null,
            'is_bodyweight' => false,
            'canonical_name' => 'test_exercise',
            'band_type' => null
        ];
        
        $result = $this->callPrivateMethod($command, 'prepareExerciseForCsv', [$exercise]);
        
        $this->assertEquals([
            'title' => 'Test Exercise',
            'description' => '',
            'is_bodyweight' => '0',
            'canonical_name' => 'test_exercise',
            'band_type' => ''
        ], $result);
    }
}