<?php

namespace Tests\Unit\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Program;
use App\Services\MobileEntryLiftLogFormService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for MobileEntryLiftLogFormService core logic
 * 
 * Tests the business logic without route dependencies
 */
class MobileEntryLiftLogFormServiceTest extends TestCase
{
    use RefreshDatabase;

    private MobileEntryLiftLogFormService $service;
    private Carbon $testDate;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new MobileEntryLiftLogFormService();
        $this->testDate = Carbon::parse('2024-01-15');
    }

    #[Test]
    public function it_returns_null_when_no_last_session_exists()
    {
        $exercise = Exercise::factory()->create();
        $user = \App\Models\User::factory()->create();
        
        $lastSession = $this->service->getLastSessionData(
            $exercise->id, 
            $this->testDate, 
            $user->id
        );
        
        $this->assertNull($lastSession);
    }

    #[Test]
    public function it_retrieves_last_session_data_correctly()
    {
        $exercise = Exercise::factory()->create();
        $user = \App\Models\User::factory()->create();
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->subDays(2),
            'comments' => 'Great session'
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 225,
            'reps' => 5
        ]);

        $lastSession = $this->service->getLastSessionData(
            $exercise->id, 
            $this->testDate, 
            $user->id
        );
        
        $this->assertIsArray($lastSession);
        $this->assertEquals(225, $lastSession['weight']);
        $this->assertEquals(5, $lastSession['reps']);
        $this->assertEquals(2, $lastSession['sets']);
        $this->assertEquals('Jan 13', $lastSession['date']);
        $this->assertEquals('Great session', $lastSession['comments']);
    }

    #[Test]
    public function it_ignores_future_sessions_when_getting_last_session()
    {
        $exercise = Exercise::factory()->create();
        $user = \App\Models\User::factory()->create();
        
        // Create a future session
        $futureLiftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->addDays(1)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $futureLiftLog->id,
            'weight' => 300,
            'reps' => 1
        ]);

        $lastSession = $this->service->getLastSessionData(
            $exercise->id, 
            $this->testDate, 
            $user->id
        );
        
        $this->assertNull($lastSession);
    }

    #[Test]
    public function it_calculates_default_weight_for_bodyweight_exercises()
    {
        $exercise = Exercise::factory()->create(['is_bodyweight' => true]);
        
        // Without last session
        $weight = $this->service->getDefaultWeight($exercise, null);
        $this->assertEquals(0, $weight);
        
        // With last session
        $lastSession = ['weight' => 25];
        $weight = $this->service->getDefaultWeight($exercise, $lastSession);
        $this->assertEquals(25, $weight);
    }

    #[Test]
    public function it_calculates_default_weight_with_progression()
    {
        $exercise = Exercise::factory()->create(['is_bodyweight' => false]);
        
        $lastSession = ['weight' => 225];
        $weight = $this->service->getDefaultWeight($exercise, $lastSession);
        
        $this->assertEquals(230, $weight); // 225 + 5 progression
    }

    #[Test]
    public function it_uses_canonical_name_defaults_for_common_exercises()
    {
        $testCases = [
            'bench_press' => 135,
            'squat' => 185,
            'deadlift' => 225,
            'overhead_press' => 95,
            'barbell_row' => 115,
            'unknown_exercise' => 95
        ];
        
        foreach ($testCases as $canonicalName => $expectedWeight) {
            $exercise = Exercise::factory()->create([
                'is_bodyweight' => false,
                'canonical_name' => $canonicalName === 'unknown_exercise' ? null : $canonicalName
            ]);
            
            $weight = $this->service->getDefaultWeight($exercise, null);
            $this->assertEquals($expectedWeight, $weight, "Failed for {$canonicalName}");
        }
    }

    #[Test]
    public function it_generates_empty_messages_when_no_data_available()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create(['is_bodyweight' => false]);
        $program->exercise = $exercise;
        
        $messages = $this->service->generateFormMessages($program, null);
        
        $this->assertEmpty($messages);
    }

    #[Test]
    public function it_generates_last_session_message()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create(['is_bodyweight' => false]);
        $program->exercise = $exercise;
        
        $lastSession = [
            'weight' => 225,
            'reps' => 5,
            'sets' => 3,
            'date' => 'Jan 13'
        ];
        
        $messages = $this->service->generateFormMessages($program, $lastSession);
        
        $this->assertCount(2, $messages); // Last session + suggestion
        
        $lastSessionMessage = $messages[0];
        $this->assertEquals('info', $lastSessionMessage['type']);
        $this->assertEquals('Last session (Jan 13):', $lastSessionMessage['prefix']);
        $this->assertEquals('225 lbs × 5 reps × 3 sets', $lastSessionMessage['text']);
        
        $suggestionMessage = $messages[1];
        $this->assertEquals('tip', $suggestionMessage['type']);
        $this->assertEquals('Suggestion:', $suggestionMessage['prefix']);
        $this->assertEquals('Try 230 lbs today', $suggestionMessage['text']);
    }

    #[Test]
    public function it_generates_program_comments_message()
    {
        $program = Program::factory()->create(['comments' => 'Focus on form today']);
        $exercise = Exercise::factory()->create(['is_bodyweight' => false]);
        $program->exercise = $exercise;
        
        $messages = $this->service->generateFormMessages($program, null);
        
        $this->assertCount(1, $messages);
        
        $message = $messages[0];
        $this->assertEquals('tip', $message['type']);
        $this->assertEquals('Program notes:', $message['prefix']);
        $this->assertEquals('Focus on form today', $message['text']);
    }

    #[Test]
    public function it_does_not_suggest_progression_for_bodyweight_exercises()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create(['is_bodyweight' => true]);
        $program->exercise = $exercise;
        
        $lastSession = [
            'weight' => 25,
            'reps' => 10,
            'sets' => 3,
            'date' => 'Jan 13'
        ];
        
        $messages = $this->service->generateFormMessages($program, $lastSession);
        
        $this->assertCount(1, $messages); // Only last session, no suggestion
        $this->assertEquals('info', $messages[0]['type']);
    }

    #[Test]
    public function it_calculates_lift_summary_with_no_logs()
    {
        $user = \App\Models\User::factory()->create();
        
        $summary = $this->service->generateLiftSummary($user->id, $this->testDate);
        
        $this->assertEquals([
            'values' => [
                'total' => 0,
                'completed' => 0,
                'average' => 0,
                'today' => 0
            ],
            'labels' => [
                'total' => 'Total Weight (lbs)',
                'completed' => 'Exercises',
                'average' => 'Avg Intensity %',
                'today' => 'Sets Today'
            ],
            'ariaLabels' => [
                'section' => 'Lift session summary'
            ]
        ], $summary);
    }

    #[Test]
    public function it_calculates_lift_summary_with_logs()
    {
        $user = \App\Models\User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        // First exercise: 2 sets
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => $this->testDate
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        // Second exercise: 1 set
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'logged_at' => $this->testDate
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog2->id,
            'weight' => 135,
            'reps' => 10
        ]);

        $summary = $this->service->generateLiftSummary($user->id, $this->testDate);
        
        // Total weight: (225*5 + 225*5 + 135*10) = 3600
        // Exercises: 2 unique
        // Sets: 3 total
        // Average intensity: min(100, 3600/3/10) = 100
        
        $this->assertEquals(3600, $summary['values']['total']);
        $this->assertEquals(2, $summary['values']['completed']);
        $this->assertEquals(100, $summary['values']['average']);
        $this->assertEquals(3, $summary['values']['today']);
    }

    #[Test]
    public function it_handles_empty_lift_sets_in_summary()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // Create lift log without sets
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate
        ]);

        $summary = $this->service->generateLiftSummary($user->id, $this->testDate);
        
        $this->assertEquals(0, $summary['values']['total']);
        $this->assertEquals(1, $summary['values']['completed']); // Still counts as completed exercise
        $this->assertEquals(0, $summary['values']['average']);
        $this->assertEquals(0, $summary['values']['today']);
    }

    #[Test]
    public function it_calculates_average_intensity_correctly()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate
        ]);
        
        // Create a set that would result in average intensity > 100
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 500,
            'reps' => 10
        ]);

        $summary = $this->service->generateLiftSummary($user->id, $this->testDate);
        
        // Should cap at 100
        $this->assertEquals(100, $summary['values']['average']);
    }
}