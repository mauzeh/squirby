<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use App\View\Components\ExerciseFormComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseFormComponentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function component_shows_exercise_type_field_for_new_exercise()
    {
        $component = new ExerciseFormComponent();
        
        $this->assertTrue($component->shouldShowField('exercise_type'));
    }

    /** @test */
    public function component_shows_exercise_type_field_for_existing_exercise()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $component = new ExerciseFormComponent($exercise);
        
        $this->assertTrue($component->shouldShowField('exercise_type'));
    }

    /** @test */
    public function component_returns_correct_exercise_types()
    {
        $component = new ExerciseFormComponent();
        $exerciseTypes = $component->getExerciseTypes();
        
        $expectedTypes = [
            'regular' => 'Regular',
            'cardio' => 'Cardio',
            'bodyweight' => 'Bodyweight',
            'banded' => 'Banded'
        ];
        
        $this->assertEquals($expectedTypes, $exerciseTypes);
    }

    /** @test */
    public function component_includes_exercise_type_in_form_fields_for_new_exercise()
    {
        $component = new ExerciseFormComponent();
        
        $this->assertContains('exercise_type', $component->formFields);
    }

    /** @test */
    public function component_includes_exercise_type_in_form_fields_for_existing_exercise()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'cardio']);
        $component = new ExerciseFormComponent($exercise);
        
        $this->assertContains('exercise_type', $component->formFields);
    }

    /** @test */
    public function component_handles_null_exercise_parameter()
    {
        $component = new ExerciseFormComponent(null);
        
        $this->assertTrue($component->shouldShowField('exercise_type'));
        $this->assertInstanceOf(Exercise::class, $component->exercise);
        $this->assertFalse($component->exercise->exists);
    }

    /** @test */
    public function component_maintains_other_form_fields()
    {
        $component = new ExerciseFormComponent();
        
        $expectedFields = ['title', 'description', 'exercise_type', 'band_type', 'is_bodyweight'];
        
        foreach ($expectedFields as $field) {
            $this->assertContains($field, $component->formFields);
        }
    }

    /** @test */
    public function component_returns_correct_band_types()
    {
        $component = new ExerciseFormComponent();
        $bandTypes = $component->getBandTypes();
        
        $expectedBandTypes = [
            '' => 'None',
            'resistance' => 'Resistance',
            'assistance' => 'Assistance'
        ];
        
        $this->assertEquals($expectedBandTypes, $bandTypes);
    }

    /** @test */
    public function component_handles_exercise_with_all_exercise_types()
    {
        $exerciseTypes = ['regular', 'cardio', 'bodyweight', 'banded'];
        
        foreach ($exerciseTypes as $type) {
            $exercise = Exercise::factory()->create(['exercise_type' => $type]);
            $component = new ExerciseFormComponent($exercise);
            
            $this->assertTrue($component->shouldShowField('exercise_type'));
            $this->assertEquals($exercise, $component->exercise);
        }
    }
}