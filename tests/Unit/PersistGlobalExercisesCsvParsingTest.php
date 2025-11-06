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
        // Create a mock command that doesn't use output methods
        $command = new class extends PersistGlobalExercises {
            public function warn($string, $verbosity = null) {
                // Override to prevent output issues in tests
            }
        };
        
        $csvContent = "title,description,canonical_name,exercise_type\n";
        $csvContent .= "\"Push Up\",\"Basic bodyweight exercise\",push_up,bodyweight\n";
        $csvContent .= "\"Band Row\",\"Rowing with resistance band\",band_row,banded_resistance\n";
        $csvContent .= "\"Assisted Squat\",\"Squat with band assistance\",assisted_squat,banded_assistance\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            $this->assertCount(3, $result);
            
            // Verify each entry has all required fields
            foreach ($result as $canonicalName => $data) {
                $this->assertArrayHasKey('title', $data);
                $this->assertArrayHasKey('description', $data);
                $this->assertArrayHasKey('canonical_name', $data);
                $this->assertArrayHasKey('exercise_type', $data);
                $this->assertArrayHasKey('line_number', $data);
            }
            
            // Verify specific data
            $this->assertEquals('Push Up', $result['push_up']['title']);
            $this->assertEquals('bodyweight', $result['push_up']['exercise_type']);
            $this->assertEquals('banded_resistance', $result['band_row']['exercise_type']);
            $this->assertEquals('banded_assistance', $result['assisted_squat']['exercise_type']);
            
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
        
        // CSV missing exercise_type column
        $csvContent = "title,description,canonical_name\n";
        $csvContent .= "\"Push Up\",\"Basic exercise\",push_up\n";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv');
        file_put_contents($tempFile, $csvContent);
        
        try {
            $result = $this->callPrivateMethod($command, 'parseExistingCsv', [$tempFile]);
            
            // Should still parse but with default exercise_type
            $this->assertCount(1, $result);
            $this->assertArrayHasKey('push_up', $result);
            $this->assertEquals('regular', $result['push_up']['exercise_type']);
            
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
        
        $csvContent = "title,description,canonical_name,exercise_type\n";
        $csvContent .= "\"Valid Exercise\",\"Has canonical name\",valid_exercise,bodyweight\n";
        $csvContent .= "\"Invalid Exercise\",\"No canonical name\",,regular\n";
        $csvContent .= "\"Another Valid\",\"Also has canonical name\",another_valid,banded_resistance\n";
        
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
        
        $csvContent = "title,description,canonical_name,exercise_type\n";
        $csvContent .= "\"Exercise, with comma\",\"Description, with comma\",exercise_comma,bodyweight\n";
        $csvContent .= "\"Normal Exercise\",\"Normal description\",normal_exercise,banded_resistance\n";
        
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
            'canonical_name' => 'test_exercise',
            'exercise_type' => 'banded_resistance'
        ];
        
        $result = $this->callPrivateMethod($command, 'prepareExerciseForCsv', [$exercise]);
        
        $this->assertEquals([
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'canonical_name' => 'test_exercise',
            'exercise_type' => 'banded_resistance'
        ], $result);
    }

    public function test_prepare_exercise_for_csv_handles_null_values()
    {
        $command = $this->getCommand();
        
        // Test with null values
        $exercise = (object) [
            'title' => 'Test Exercise',
            'description' => null,
            'canonical_name' => 'test_exercise',
            'exercise_type' => 'regular'
        ];
        
        $result = $this->callPrivateMethod($command, 'prepareExerciseForCsv', [$exercise]);
        
        $this->assertEquals([
            'title' => 'Test Exercise',
            'description' => '',
            'canonical_name' => 'test_exercise',
            'exercise_type' => 'regular'
        ], $result);
    }
}