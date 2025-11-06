<?php

namespace Tests\Unit\Presenters;

use Tests\TestCase;
use App\Presenters\LiftLogTablePresenter;
use App\Models\LiftLog;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogTablePresenterTest extends TestCase
{
    use RefreshDatabase;

    private LiftLogTablePresenter $presenter;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new LiftLogTablePresenter();
        $this->user = User::factory()->create();
    }

    public function test_formats_lift_log_for_table_display()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Back Squat',
            'exercise_type' => 'regular'
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Great workout today with good form',
            'logged_at' => now()->setDate(2024, 1, 15)->setTime(14, 30)
        ]);

        $liftLog->liftSets()->create([
            'weight' => 200,
            'reps' => 5
        ]);

        $result = $this->presenter->formatForTable(collect([$liftLog]));

        $formattedLog = $result['liftLogs']->first();

        $this->assertEquals($liftLog->id, $formattedLog['id']);
        $this->assertEquals('01/15', $formattedLog['formatted_date']);
        $this->assertEquals('Back Squat', $formattedLog['exercise_title']);
        $this->assertEquals('200 lbs', $formattedLog['formatted_weight']);
        $this->assertEquals('1 x 5', $formattedLog['formatted_reps_sets']);
        $this->assertStringContainsString('Great workout today with good form', $formattedLog['truncated_comments']);
        $this->assertEquals('Great workout today with good form', $formattedLog['full_comments']);
    }

    public function test_formats_bodyweight_exercise_correctly()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Pull-ups',
            'exercise_type' => 'bodyweight'
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);

        $liftLog->liftSets()->create([
            'weight' => 25, // Additional weight
            'reps' => 8
        ]);

        $result = $this->presenter->formatForTable(collect([$liftLog]));
        $formattedLog = $result['liftLogs']->first();

        $this->assertEquals('Bodyweight +25 lbs', $formattedLog['formatted_weight']);
    }

    public function test_formats_bodyweight_exercise_without_additional_weight()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight'
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);

        $liftLog->liftSets()->create([
            'weight' => 0, // No additional weight
            'reps' => 10
        ]);

        $result = $this->presenter->formatForTable(collect([$liftLog]));
        $formattedLog = $result['liftLogs']->first();

        $this->assertEquals('Bodyweight', $formattedLog['formatted_weight']);
    }

    public function test_builds_table_config_correctly()
    {
        $result = $this->presenter->formatForTable(collect([]), false);
        $config = $result['config'];

        $this->assertFalse($config['hideExerciseColumn']);
        $this->assertEquals('hide-on-mobile', $config['dateColumnClass']);
        $this->assertEquals(7, $config['colspan']);

        $result = $this->presenter->formatForTable(collect([]), true);
        $config = $result['config'];

        $this->assertTrue($config['hideExerciseColumn']);
        $this->assertEquals('', $config['dateColumnClass']);
        $this->assertEquals(6, $config['colspan']);
    }

    public function test_truncates_long_comments()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $longComment = str_repeat('This is a very long comment. ', 10);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => $longComment
        ]);

        $liftLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5
        ]);

        $result = $this->presenter->formatForTable(collect([$liftLog]));
        $formattedLog = $result['liftLogs']->first();

        $this->assertLessThan(strlen($longComment), strlen($formattedLog['truncated_comments']));
        $this->assertEquals($longComment, $formattedLog['full_comments']);
    }

    public function test_formats_one_rep_max_for_regular_exercise()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);

        $liftLog->liftSets()->create([
            'weight' => 200,
            'reps' => 5
        ]);

        $result = $this->presenter->formatForTable(collect([$liftLog]));
        $formattedLog = $result['liftLogs']->first();

        // The 1RM should be calculated and formatted without bodyweight notation
        $this->assertStringContainsString('lbs', $formattedLog['formatted_1rm']);
        $this->assertStringNotContainsString('(est. incl. BW)', $formattedLog['formatted_1rm']);
    }

    public function test_formats_one_rep_max_for_bodyweight_exercise()
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);

        $liftLog->liftSets()->create([
            'weight' => 25,
            'reps' => 8
        ]);

        $result = $this->presenter->formatForTable(collect([$liftLog]));
        $formattedLog = $result['liftLogs']->first();

        // Bodyweight exercises should not show 1RM calculations
        $this->assertStringContainsString('N/A (Bodyweight)', $formattedLog['formatted_1rm']);
    }

}
