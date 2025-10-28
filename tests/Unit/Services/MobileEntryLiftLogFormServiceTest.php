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
    public function it_calculates_summary_with_no_logs()
    {
        $user = \App\Models\User::factory()->create();
        
        $summary = $this->service->generateSummary($user->id, $this->testDate);
        
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
                'section' => 'Session summary'
            ]
        ], $summary);
    }

    #[Test]
    public function it_calculates_summary_with_logs()
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

        $summary = $this->service->generateSummary($user->id, $this->testDate);
        
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

        $summary = $this->service->generateSummary($user->id, $this->testDate);
        
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

        $summary = $this->service->generateSummary($user->id, $this->testDate);
        
        // Should cap at 100
        $this->assertEquals(100, $summary['values']['average']);
    }

    #[Test]
    public function it_identifies_completed_programs()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);
        
        // Program should not be completed initially
        $this->assertFalse($program->isCompleted());
        
        // Create a lift log for the same exercise and date
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate
        ]);
        
        // Refresh the program to get updated completion status
        $program->refresh();
        
        // Program should now be completed
        $this->assertTrue($program->isCompleted());
    }

    #[Test]
    public function it_calculates_program_completion_stats()
    {
        $user = \App\Models\User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        // Create two programs
        $program1 = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'date' => $this->testDate
        ]);
        
        $program2 = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'date' => $this->testDate
        ]);
        
        // Complete only the first program
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => $this->testDate
        ]);
        
        $stats = $this->service->getProgramCompletionStats($user->id, $this->testDate);
        
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['completed']);
        $this->assertEquals(1, $stats['incomplete']);
        $this->assertEquals(50, $stats['completionPercentage']);
    }

    #[Test]
    public function it_filters_incomplete_programs()
    {
        $user = \App\Models\User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        // Create two programs
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'date' => $this->testDate
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'date' => $this->testDate
        ]);
        
        // Complete only the first program
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => $this->testDate
        ]);
        
        // Get incomplete programs
        $incompletePrograms = $this->service->getIncompletePrograms($user->id, $this->testDate);
        
        $this->assertCount(1, $incompletePrograms);
        $this->assertEquals($exercise2->id, $incompletePrograms->first()->exercise_id);
    }

    #[Test]
    public function it_includes_completion_status_in_forms()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);
        
        // Generate forms - should show as incomplete
        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $this->assertCount(1, $forms);
        $this->assertFalse($forms[0]['isCompleted']);
        $this->assertEquals('pending', $forms[0]['completionStatus']);
        
        // Complete the program
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate
        ]);
        
        // Generate forms again - should be empty since completed programs are excluded by default
        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        $this->assertCount(0, $forms);
        
        // But if we explicitly include completed programs, we should see it
        $formsWithCompleted = $this->service->generateProgramForms($user->id, $this->testDate, true);
        $this->assertCount(1, $formsWithCompleted);
        $this->assertTrue($formsWithCompleted[0]['isCompleted']);
        $this->assertEquals('completed', $formsWithCompleted[0]['completionStatus']);
    }

    #[Test]
    public function it_can_exclude_completed_programs_from_forms()
    {
        $user = \App\Models\User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        // Create two programs
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'date' => $this->testDate
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'date' => $this->testDate
        ]);
        
        // Complete the first program
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => $this->testDate
        ]);
        
        // Exclude completed programs (default behavior)
        $incompleteForms = $this->service->generateProgramForms($user->id, $this->testDate);

        
        // Include completed programs (explicit)
        $allForms = $this->service->generateProgramForms($user->id, $this->testDate, true);
        $this->assertCount(2, $allForms);
    }

    #[Test]
    public function it_excludes_completed_programs_by_default()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // Create a program
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);
        
        // Initially should generate a form (program is incomplete)
        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        $this->assertCount(1, $forms);
        
        // Complete the program by logging it
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate
        ]);
        
        // Now should generate no forms (program is completed and excluded by default)
        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        $this->assertCount(0, $forms);
    }

    #[Test]
    public function it_adds_exercise_form_successfully()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press'
        ]);
        
        $result = $this->service->addExerciseForm($user->id, 'bench_press', $this->testDate);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Added Bench Press to today\'s program.', $result['message']);
        
        // Verify program was created
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 5,
            'priority' => 999,
            'comments' => 'Added manually'
        ]);
        
        // Verify the date matches (using whereDate for proper comparison)
        $program = Program::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->whereDate('date', $this->testDate->toDateString())
            ->first();
        $this->assertNotNull($program);
    }

    #[Test]
    public function it_adds_exercise_form_by_id()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Squat',
            'canonical_name' => null
        ]);
        
        $result = $this->service->addExerciseForm($user->id, (string)$exercise->id, $this->testDate);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Added Squat to today\'s program.', $result['message']);
        
        // Verify program was created
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id
        ]);
        
        // Verify the date matches
        $program = Program::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->whereDate('date', $this->testDate->toDateString())
            ->first();
        $this->assertNotNull($program);
    }

    #[Test]
    public function it_fails_to_add_nonexistent_exercise()
    {
        $user = \App\Models\User::factory()->create();
        
        $result = $this->service->addExerciseForm($user->id, 'nonexistent_exercise', $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Exercise not found or not accessible.', $result['message']);
        
        // Verify no program was created
        $programCount = Program::where('user_id', $user->id)
            ->whereDate('date', $this->testDate->toDateString())
            ->count();
        $this->assertEquals(0, $programCount);
    }

    #[Test]
    public function it_fails_to_add_exercise_when_program_already_exists()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Deadlift',
            'canonical_name' => 'deadlift'
        ]);
        
        // Create existing program
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);
        
        $result = $this->service->addExerciseForm($user->id, 'deadlift', $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Deadlift is already in today\'s program.', $result['message']);
    }

    #[Test]
    public function it_creates_new_exercise_successfully()
    {
        $user = \App\Models\User::factory()->create();
        
        $result = $this->service->createExercise($user->id, 'Custom Exercise', $this->testDate);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Created new exercise: Custom Exercise', $result['message']);
        
        // Verify exercise was created
        $this->assertDatabaseHas('exercises', [
            'title' => 'Custom Exercise',
            'user_id' => $user->id,
            'is_bodyweight' => false,
            'canonical_name' => 'custom_exercise'
        ]);
        
        // Verify program was created
        $exercise = Exercise::where('title', 'Custom Exercise')->first();
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 5,
            'priority' => 999,
            'comments' => 'New exercise created'
        ]);
        
        // Verify the date matches
        $program = Program::where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->whereDate('date', $this->testDate->toDateString())
            ->first();
        $this->assertNotNull($program);
    }

    #[Test]
    public function it_fails_to_create_exercise_with_existing_name()
    {
        $user = \App\Models\User::factory()->create();
        
        // Create existing exercise
        Exercise::factory()->create([
            'title' => 'Existing Exercise',
            'user_id' => $user->id
        ]);
        
        $result = $this->service->createExercise($user->id, 'Existing Exercise', $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Exercise \'Existing Exercise\' already exists.', $result['message']);
        
        // Verify no duplicate exercise was created
        $exerciseCount = Exercise::where('title', 'Existing Exercise')->count();
        $this->assertEquals(1, $exerciseCount);
    }

    #[Test]
    public function it_generates_unique_canonical_names()
    {
        $user = \App\Models\User::factory()->create();
        
        // Create first exercise
        $result1 = $this->service->createExercise($user->id, 'Push Up', $this->testDate);
        $this->assertTrue($result1['success']);
        
        // Create second exercise with same name (should fail due to existing check)
        $result2 = $this->service->createExercise($user->id, 'Push Up', $this->testDate);
        $this->assertFalse($result2['success']);
        
        // But if we create with slightly different name, it should work
        $result3 = $this->service->createExercise($user->id, 'Push Up Variation', $this->testDate);
        $this->assertTrue($result3['success']);
        
        // Verify canonical names are different
        $exercise1 = Exercise::where('title', 'Push Up')->first();
        $exercise3 = Exercise::where('title', 'Push Up Variation')->first();
        
        $this->assertEquals('push_up', $exercise1->canonical_name);
        $this->assertEquals('push_up_variation', $exercise3->canonical_name);
    }

    #[Test]
    public function it_removes_form_successfully()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Test Exercise']);
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);
        
        $formId = 'program-' . $program->id;
        $result = $this->service->removeForm($user->id, $formId);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Removed Test Exercise from today\'s program.', $result['message']);
        
        // Verify program was deleted
        $this->assertDatabaseMissing('programs', [
            'id' => $program->id
        ]);
    }

    #[Test]
    public function it_fails_to_remove_form_with_invalid_format()
    {
        $user = \App\Models\User::factory()->create();
        
        $result = $this->service->removeForm($user->id, 'invalid-format');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid form ID format.', $result['message']);
    }

    #[Test]
    public function it_fails_to_remove_nonexistent_program()
    {
        $user = \App\Models\User::factory()->create();
        
        $result = $this->service->removeForm($user->id, 'program-999');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Program entry not found or not accessible.', $result['message']);
    }

    #[Test]
    public function it_fails_to_remove_other_users_program()
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $program = Program::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);
        
        $formId = 'program-' . $program->id;
        $result = $this->service->removeForm($user1->id, $formId);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Program entry not found or not accessible.', $result['message']);
        
        // Verify program still exists
        $this->assertDatabaseHas('programs', [
            'id' => $program->id
        ]);
    }

    #[Test]
    public function it_respects_user_exercise_visibility_when_adding_form()
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        
        // Create exercise owned by user2
        $exercise = Exercise::factory()->create([
            'title' => 'Private Exercise',
            'user_id' => $user2->id,
            'canonical_name' => 'private_exercise'
        ]);
        
        // User1 should not be able to add user2's private exercise
        $result = $this->service->addExerciseForm($user1->id, 'private_exercise', $this->testDate);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Exercise not found or not accessible.', $result['message']);
    }

    #[Test]
    public function it_handles_exercise_without_canonical_name_in_removal()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Exercise Without Canonical',
            'canonical_name' => null
        ]);
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);
        
        $formId = 'program-' . $program->id;
        $result = $this->service->removeForm($user->id, $formId);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Removed Exercise Without Canonical from today\'s program.', $result['message']);
    }

    #[Test]
    public function it_generates_forms_with_correct_submission_data()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'is_bodyweight' => false
        ]);
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate,
            'sets' => 4,
            'reps' => 8,
            'comments' => 'Test program'
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $this->assertCount(1, $forms);
        
        $form = $forms[0];
        
        // Check form action points to lift-logs.store
        $this->assertStringContainsString('lift-logs', $form['formAction']);
        
        // Check hidden fields contain all necessary data for submission
        $hiddenFields = $form['hiddenFields'];
        $this->assertEquals($exercise->id, $hiddenFields['exercise_id']);
        $this->assertEquals($program->id, $hiddenFields['program_id']);
        $this->assertEquals($this->testDate->toDateString(), $hiddenFields['date']);
        $this->assertEquals('mobile-entry-lifts', $hiddenFields['redirect_to']);
        
        // Check numeric fields have proper names for form submission
        $weightField = $form['numericFields'][0];
        $this->assertEquals('weight', $weightField['name']);
        
        $repsField = $form['numericFields'][1];
        $this->assertEquals('reps', $repsField['name']);
        $this->assertEquals(8, $repsField['defaultValue']); // From program
        
        $setsField = $form['numericFields'][2];
        $this->assertEquals('sets', $setsField['name']);
        $this->assertEquals(4, $setsField['defaultValue']); // From program
        
        // Check comment field
        $this->assertEquals('comment', $form['commentField']['name']);
        $this->assertEquals('Test program', $form['commentField']['defaultValue']);
    }

    #[Test]
    public function it_generates_bodyweight_exercise_forms_correctly()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Pull-ups',
            'is_bodyweight' => true
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate,
            'sets' => 3,
            'reps' => 10
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $weightField = $form['numericFields'][0];
        
        // Bodyweight exercises should have different weight field configuration
        $this->assertEquals('Added Weight (lbs):', $weightField['label']);
        $this->assertEquals(0, $weightField['defaultValue']);
        $this->assertEquals(2.5, $weightField['increment']);
        $this->assertEquals(0, $weightField['min']);
    }

    #[Test]
    public function it_includes_last_session_data_in_form_defaults()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['is_bodyweight' => false]);
        
        // Create a previous lift log
        $previousLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->subDays(3)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 200,
            'reps' => 6
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 200,
            'reps' => 6
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $previousLog->id,
            'weight' => 200,
            'reps' => 6
        ]);
        
        // Create program with specific sets/reps
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate,
            'sets' => 4,
            'reps' => 8
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        
        // Should suggest progression from last session for weight
        $weightField = $form['numericFields'][0];
        $this->assertEquals(205, $weightField['defaultValue']); // 200 + 5 progression
        
        // Should use program values for sets/reps
        $repsField = $form['numericFields'][1];
        $this->assertEquals(8, $repsField['defaultValue']); // From program
        
        $setsField = $form['numericFields'][2];
        $this->assertEquals(4, $setsField['defaultValue']); // From program
        
        // Should include last session message and progression suggestion
        $messages = $form['messages'];
        $this->assertCount(2, $messages); // Last session + suggestion
        
        $lastSessionMessage = $messages[0];
        $this->assertEquals('info', $lastSessionMessage['type']);
        $this->assertStringContainsString('Last session', $lastSessionMessage['prefix']);
        $this->assertStringContainsString('200 lbs × 6 reps × 3 sets', $lastSessionMessage['text']);
        
        $suggestionMessage = $messages[1];
        $this->assertEquals('tip', $suggestionMessage['type']);
        $this->assertStringContainsString('Try 205 lbs today', $suggestionMessage['text']);
    }

    #[Test]
    public function it_generates_forms_with_proper_field_ids()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $formId = 'program-' . $program->id;
        
        // Check that all field IDs are properly prefixed with form ID
        $this->assertEquals($formId, $form['id']);
        
        $weightField = $form['numericFields'][0];
        $this->assertEquals($formId . '-weight', $weightField['id']);
        
        $repsField = $form['numericFields'][1];
        $this->assertEquals($formId . '-reps', $repsField['id']);
        
        $setsField = $form['numericFields'][2];
        $this->assertEquals($formId . '-sets', $setsField['id']);
        
        $commentField = $form['commentField'];
        $this->assertEquals($formId . '-comment', $commentField['id']);
    }

    #[Test]
    public function it_generates_forms_with_completion_status()
    {
        $user = \App\Models\User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        // Create two programs
        $program1 = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'date' => $this->testDate
        ]);
        
        $program2 = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'date' => $this->testDate
        ]);
        
        // Complete the first program
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => $this->testDate
        ]);
        
        // Generate forms including completed ones
        $forms = $this->service->generateProgramForms($user->id, $this->testDate, true);
        
        $this->assertCount(2, $forms);
        
        // First form should be marked as completed
        $completedForm = collect($forms)->firstWhere('hiddenFields.program_id', $program1->id);
        $this->assertTrue($completedForm['isCompleted']);
        $this->assertEquals('completed', $completedForm['completionStatus']);
        
        // Second form should be marked as pending
        $pendingForm = collect($forms)->firstWhere('hiddenFields.program_id', $program2->id);
        $this->assertFalse($pendingForm['isCompleted']);
        $this->assertEquals('pending', $pendingForm['completionStatus']);
    }

    #[Test]
    public function it_handles_forms_with_different_exercise_types()
    {
        $user = \App\Models\User::factory()->create();
        
        // Create different types of exercises
        $weightedExercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'is_bodyweight' => false,
            'canonical_name' => 'bench_press'
        ]);
        
        $bodyweightExercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'is_bodyweight' => true
        ]);
        
        // Create programs for both
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $weightedExercise->id,
            'date' => $this->testDate
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bodyweightExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $this->assertCount(2, $forms);
        
        // Find weighted exercise form
        $weightedForm = collect($forms)->firstWhere('title', 'Bench Press');
        $weightedWeightField = $weightedForm['numericFields'][0];
        
        $this->assertEquals('Weight (lbs):', $weightedWeightField['label']);
        $this->assertEquals(135, $weightedWeightField['defaultValue']); // Default for bench_press
        $this->assertEquals(5, $weightedWeightField['increment']);
        $this->assertEquals(45, $weightedWeightField['min']);
        
        // Find bodyweight exercise form
        $bodyweightForm = collect($forms)->firstWhere('title', 'Push-ups');
        $bodyweightWeightField = $bodyweightForm['numericFields'][0];
        
        $this->assertEquals('Added Weight (lbs):', $bodyweightWeightField['label']);
        $this->assertEquals(0, $bodyweightWeightField['defaultValue']);
        $this->assertEquals(2.5, $bodyweightWeightField['increment']);
        $this->assertEquals(0, $bodyweightWeightField['min']);
    }
}