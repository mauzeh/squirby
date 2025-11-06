<?php

namespace Tests\Unit\View\Components;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Services\ExerciseTypes\ExerciseTypeInterface;
use App\View\Components\LiftLogFormComponent;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;
use Mockery;

class LiftLogFormComponentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_shows_all_fields_for_new_lift_logs()
    {
        $exercises = new Collection();
        $component = new LiftLogFormComponent(null, $exercises);

        // Essential fields should always be shown
        $this->assertTrue($component->shouldShowField('comments'));
        $this->assertTrue($component->shouldShowField('date'));
        $this->assertTrue($component->shouldShowField('logged_at'));
        $this->assertTrue($component->shouldShowField('rounds'));
        
        // Exercise-specific fields should also be shown for new lift logs
        $this->assertTrue($component->shouldShowField('exercise_id'));
        $this->assertTrue($component->shouldShowField('weight'));
        $this->assertTrue($component->shouldShowField('reps'));
        $this->assertTrue($component->shouldShowField('band_color'));
    }

    public function test_always_shows_essential_fields_for_existing_lift_logs()
    {
        $exercise = Mockery::mock(Exercise::class);
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        
        // Mock strategy to return limited form fields (like regular exercise)
        $strategy->shouldReceive('getFormFields')->andReturn(['weight', 'reps']);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises, '', 'PUT');

        // Essential fields should always be shown regardless of exercise type
        $this->assertTrue($component->shouldShowField('comments'));
        $this->assertTrue($component->shouldShowField('date'));
        $this->assertTrue($component->shouldShowField('logged_at'));
        $this->assertTrue($component->shouldShowField('rounds'));
        
        // Exercise-specific fields should be shown based on strategy
        $this->assertTrue($component->shouldShowField('weight'));
        $this->assertTrue($component->shouldShowField('reps'));
        
        // Fields not in strategy should not be shown (except essential ones)
        $this->assertFalse($component->shouldShowField('band_color'));
        $this->assertFalse($component->shouldShowField('exercise_id'));
    }

    public function test_shows_strategy_specific_fields_for_regular_exercises()
    {
        $exercise = Mockery::mock(Exercise::class);
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        
        $strategy->shouldReceive('getFormFields')->andReturn(['weight', 'reps']);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises, '', 'PUT');

        // Regular exercise fields
        $this->assertTrue($component->shouldShowField('weight'));
        $this->assertTrue($component->shouldShowField('reps'));
        
        // Not regular exercise fields
        $this->assertFalse($component->shouldShowField('band_color'));
    }

    public function test_shows_strategy_specific_fields_for_banded_exercises()
    {
        $exercise = Mockery::mock(Exercise::class);
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        
        $strategy->shouldReceive('getFormFields')->andReturn(['band_color', 'reps']);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises, '', 'PUT');

        // Banded exercise fields
        $this->assertTrue($component->shouldShowField('band_color'));
        $this->assertTrue($component->shouldShowField('reps'));
        
        // Not banded exercise fields
        $this->assertFalse($component->shouldShowField('weight'));
    }

    public function test_shows_strategy_specific_fields_for_bodyweight_exercises()
    {
        $exercise = Mockery::mock(Exercise::class);
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        
        $strategy->shouldReceive('getFormFields')->andReturn(['weight', 'reps']);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises, '', 'PUT');

        // Bodyweight exercise fields (weight for additional weight)
        $this->assertTrue($component->shouldShowField('weight'));
        $this->assertTrue($component->shouldShowField('reps'));
        
        // Not bodyweight exercise fields
        $this->assertFalse($component->shouldShowField('band_color'));
    }

    public function test_initializes_with_default_values_for_new_lift_logs()
    {
        $exercises = new Collection([]);
        $component = new LiftLogFormComponent(null, $exercises, '/test-action', 'POST');

        $this->assertInstanceOf(LiftLog::class, $component->liftLog);
        $this->assertFalse($component->liftLog->exists);
        $this->assertEquals($exercises, $component->exercises);
        $this->assertEquals('/test-action', $component->action);
        $this->assertEquals('POST', $component->method);
        
        // Should have all possible fields for new lift logs
        $expectedFields = ['exercise_id', 'weight', 'band_color', 'reps', 'rounds', 'comments', 'date', 'logged_at'];
        $this->assertEquals($expectedFields, $component->formFields);
    }

    public function test_gets_form_fields_and_validation_rules_from_strategy_for_existing_lift_logs()
    {
        $exercise = Mockery::mock(Exercise::class);
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        
        $expectedFields = ['weight', 'reps'];
        $expectedRules = ['weight' => 'required|numeric', 'reps' => 'required|integer'];
        
        $strategy->shouldReceive('getFormFields')->andReturn($expectedFields);
        $strategy->shouldReceive('getValidationRules')->andReturn($expectedRules);
        
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises, '', 'PUT');

        $this->assertEquals($expectedFields, $component->formFields);
        $this->assertEquals($expectedRules, $component->validationRules);
    }



    public function test_gets_band_colors_from_config()
    {
        // Mock the config
        config(['bands.colors' => [
            'red' => ['resistance' => 10],
            'blue' => ['resistance' => 15],
            'green' => ['resistance' => 20],
        ]]);

        $exercises = new Collection();
        $component = new LiftLogFormComponent(null, $exercises);
        $bandColors = $component->getBandColors();

        $expected = [
            '' => 'Select Band',
            'red' => 'Red',
            'blue' => 'Blue',
            'green' => 'Green',
        ];

        $this->assertEquals($expected, $bandColors);
    }

    public function test_handles_empty_band_colors_config()
    {
        config(['bands.colors' => []]);

        $exercises = new Collection();
        $component = new LiftLogFormComponent(null, $exercises);
        $bandColors = $component->getBandColors();

        $expected = ['' => 'Select Band'];
        $this->assertEquals($expected, $bandColors);
    }

    public function test_detects_banded_exercises_correctly()
    {
        $exercise = Mockery::mock(Exercise::class);
        $exercise->shouldReceive('isBanded')->andReturn(true);
        
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        $strategy->shouldReceive('getFormFields')->andReturn([]);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises);

        $this->assertTrue($component->isCurrentExerciseBanded());
    }

    public function test_returns_false_for_non_banded_exercises()
    {
        $exercise = Mockery::mock(Exercise::class);
        $exercise->shouldReceive('isBanded')->andReturn(false);
        
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        $strategy->shouldReceive('getFormFields')->andReturn([]);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises);

        $this->assertFalse($component->isCurrentExerciseBanded());
    }

    public function test_returns_false_for_new_lift_logs_when_checking_banded()
    {
        $exercises = new Collection();
        $component = new LiftLogFormComponent(null, $exercises);

        $this->assertFalse($component->isCurrentExerciseBanded());
    }

    public function test_gets_current_band_color_from_lift_sets()
    {
        $liftSet = Mockery::mock();
        $liftSet->band_color = 'red';
        
        $liftSets = collect([$liftSet]);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn(null);
        $liftLog->shouldReceive('getAttribute')->with('liftSets')->andReturn($liftSets);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->liftSets = $liftSets;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises);

        $this->assertEquals('red', $component->getCurrentBandColor());
    }

    public function test_returns_null_for_current_band_color_when_no_lift_sets()
    {
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn(null);
        $liftLog->shouldReceive('getAttribute')->with('liftSets')->andReturn(collect());
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->liftSets = collect();

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises);

        $this->assertNull($component->getCurrentBandColor());
    }

    public function test_returns_null_for_current_band_color_for_new_lift_logs()
    {
        $exercises = new Collection();
        $component = new LiftLogFormComponent(null, $exercises);

        $this->assertNull($component->getCurrentBandColor());
    }

    public function test_essential_fields_override_strategy_limitations()
    {
        // This test specifically validates our fix for the issue where
        // essential fields like comments, date, logged_at, rounds were not
        // showing when editing existing lift logs
        
        $exercise = Mockery::mock(Exercise::class);
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        
        // Mock a very restrictive strategy that only returns one field
        $strategy->shouldReceive('getFormFields')->andReturn(['reps']);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $exercises = new Collection();
        $component = new LiftLogFormComponent($liftLog, $exercises, '', 'PUT');

        // Essential fields should ALWAYS be shown, even if not in strategy
        $this->assertTrue($component->shouldShowField('comments'), 'Comments field should always be shown');
        $this->assertTrue($component->shouldShowField('date'), 'Date field should always be shown');
        $this->assertTrue($component->shouldShowField('logged_at'), 'Logged_at field should always be shown');
        $this->assertTrue($component->shouldShowField('rounds'), 'Rounds field should always be shown');
        
        // Strategy field should be shown
        $this->assertTrue($component->shouldShowField('reps'), 'Reps field from strategy should be shown');
        
        // Non-essential, non-strategy fields should not be shown
        $this->assertFalse($component->shouldShowField('weight'), 'Weight field should not be shown when not in strategy');
        $this->assertFalse($component->shouldShowField('band_color'), 'Band_color field should not be shown when not in strategy');
        $this->assertFalse($component->shouldShowField('exercise_id'), 'Exercise_id field should not be shown when not in strategy');
    }

    public function test_exercise_dropdown_visibility_for_new_vs_existing_lift_logs()
    {
        // Test new lift log shows exercise dropdown
        $exercises = new Collection();
        $newComponent = new LiftLogFormComponent(null, $exercises);
        
        $this->assertTrue($newComponent->shouldShowExerciseDropdown(), 'New lift logs should show exercise dropdown');
        $this->assertFalse($newComponent->shouldIncludeHiddenExerciseId(), 'New lift logs should not include hidden exercise_id');
        
        // Test existing lift log shows hidden exercise_id
        $exercise = Mockery::mock(Exercise::class);
        $strategy = Mockery::mock(ExerciseTypeInterface::class);
        $strategy->shouldReceive('getFormFields')->andReturn(['reps']);
        $strategy->shouldReceive('getValidationRules')->andReturn([]);
        $exercise->shouldReceive('getTypeStrategy')->andReturn($strategy);
        
        $liftLog = Mockery::mock(LiftLog::class);
        $liftLog->shouldReceive('getAttribute')->with('exists')->andReturn(true);
        $liftLog->shouldReceive('getAttribute')->with('exercise')->andReturn($exercise);
        $liftLog->shouldReceive('setAttribute')->andReturnSelf();
        $liftLog->exists = true;
        $liftLog->exercise = $exercise;

        $existingComponent = new LiftLogFormComponent($liftLog, $exercises, '', 'PUT');
        
        $this->assertFalse($existingComponent->shouldShowExerciseDropdown(), 'Existing lift logs should not show exercise dropdown');
        $this->assertTrue($existingComponent->shouldIncludeHiddenExerciseId(), 'Existing lift logs should include hidden exercise_id');
    }
}