<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\PersistGlobalExercises;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

class PersistGlobalExercisesChangeIdentificationTest extends TestCase
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

    public function test_identify_changes_categorizes_exercises_correctly()
    {
        $command = $this->getCommand();
        
        // Create test exercises
        $exercise1 = new Exercise();
        $exercise1->title = 'Push Up';
        $exercise1->description = 'A basic push up';
        $exercise1->canonical_name = 'push_up';
        $exercise1->is_bodyweight = true;
        $exercise1->band_type = null;
        
        $exercise2 = new Exercise();
        $exercise2->title = 'Pull Up';
        $exercise2->description = 'A basic pull up';
        $exercise2->canonical_name = 'pull_up';
        $exercise2->is_bodyweight = true;
        $exercise2->band_type = null;
        
        $exercise3 = new Exercise();
        $exercise3->title = 'New Exercise';
        $exercise3->description = 'A new exercise';
        $exercise3->canonical_name = 'new_exercise';
        $exercise3->is_bodyweight = false;
        $exercise3->band_type = 'resistance';
        
        $exercises = collect([$exercise1, $exercise2, $exercise3]);
        
        // CSV data - push_up needs update, pull_up is identical, new_exercise is missing
        $csvData = [
            'push_up' => [
                'title' => 'Push-Up', // Different title
                'description' => 'A basic push up',
                'canonical_name' => 'push_up',
                'is_bodyweight' => '1',
                'band_type' => ''
            ],
            'pull_up' => [
                'title' => 'Pull Up',
                'description' => 'A basic pull up',
                'canonical_name' => 'pull_up',
                'is_bodyweight' => '1',
                'band_type' => ''
            ]
        ];
        
        $changes = $this->callPrivateMethod($command, 'identifyChanges', [$exercises, $csvData]);
        
        // Verify summary counts
        $this->assertEquals(3, $changes['summary']['total_global_exercises']);
        $this->assertEquals(1, $changes['summary']['updates_count']);
        $this->assertEquals(1, $changes['summary']['new_entries_count']);
        $this->assertEquals(1, $changes['summary']['no_change_count']);
        
        // Verify updates_needed
        $this->assertArrayHasKey('push_up', $changes['updates_needed']);
        $this->assertArrayHasKey('title', $changes['updates_needed']['push_up']['differences']);
        $this->assertContains('title', $changes['updates_needed']['push_up']['field_changes']);
        
        // Verify no_change
        $this->assertArrayHasKey('pull_up', $changes['no_change']);
        
        // Verify new_entries
        $this->assertArrayHasKey('new_exercise', $changes['new_entries']);
    }

    public function test_identify_changes_handles_empty_csv()
    {
        $command = $this->getCommand();
        
        $exercise = new Exercise();
        $exercise->title = 'Push Up';
        $exercise->canonical_name = 'push_up';
        $exercise->is_bodyweight = true;
        
        $exercises = collect([$exercise]);
        
        $csvData = [];
        
        $changes = $this->callPrivateMethod($command, 'identifyChanges', [$exercises, $csvData]);
        
        $this->assertEquals(1, $changes['summary']['total_global_exercises']);
        $this->assertEquals(0, $changes['summary']['updates_count']);
        $this->assertEquals(1, $changes['summary']['new_entries_count']);
        $this->assertEquals(0, $changes['summary']['no_change_count']);
        
        $this->assertArrayHasKey('push_up', $changes['new_entries']);
    }
}