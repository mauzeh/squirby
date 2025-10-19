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
}