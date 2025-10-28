<?php

namespace Tests\Unit\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Program;
use App\Models\User;
use App\Services\MobileEntryLiftLogFormService;
use App\Services\TrainingProgressionService;
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
        
        // Mock the TrainingProgressionService
        $mockProgressionService = $this->createMock(TrainingProgressionService::class);
        $mockProgressionService->method('getSuggestionDetails')->willReturn(null);
        
        $this->service = new MobileEntryLiftLogFormService($mockProgressionService);
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
        $user = \App\Models\User::factory()->create();
        
        $lastSession = ['weight' => 225];
        $weight = $this->service->getDefaultWeight($exercise, $lastSession, $user->id);
        
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
        
        $user = \App\Models\User::factory()->create();
        $messages = $this->service->generateFormMessages($program, $lastSession, $user->id);
        
        $this->assertCount(2, $messages); // Last session + suggestion
        
        $lastSessionMessage = $messages[0];
        $this->assertEquals('info', $lastSessionMessage['type']);
        $this->assertEquals('Last session (Jan 13):', $lastSessionMessage['prefix']);
        $this->assertEquals('225 lbs × 5 reps × 3 sets', $lastSessionMessage['text']);
        
        $suggestionMessage = $messages[1];
        $this->assertEquals('tip', $suggestionMessage['type']);
        $this->assertEquals('Suggestion:', $suggestionMessage['prefix']);
        $this->assertEquals('Try 230 lbs × 5 reps × 3 sets today', $suggestionMessage['text']);
    }

    #[Test]
    public function it_generates_last_session_message_with_comments()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create(['is_bodyweight' => false]);
        $program->exercise = $exercise;
        
        $lastSession = [
            'weight' => 185,
            'reps' => 8,
            'sets' => 4,
            'date' => 'Jan 10',
            'comments' => 'Felt really strong today, good form throughout'
        ];
        
        $user = \App\Models\User::factory()->create();
        $messages = $this->service->generateFormMessages($program, $lastSession, $user->id);
        
        $this->assertCount(3, $messages); // Last session + last notes + suggestion
        
        $lastSessionMessage = $messages[0];
        $this->assertEquals('info', $lastSessionMessage['type']);
        $this->assertEquals('Last session (Jan 10):', $lastSessionMessage['prefix']);
        $this->assertEquals('185 lbs × 8 reps × 4 sets', $lastSessionMessage['text']);
        
        $lastNotesMessage = $messages[1];
        $this->assertEquals('neutral', $lastNotesMessage['type']);
        $this->assertEquals('Last notes:', $lastNotesMessage['prefix']);
        $this->assertEquals('Felt really strong today, good form throughout', $lastNotesMessage['text']);
        
        $suggestionMessage = $messages[2];
        $this->assertEquals('tip', $suggestionMessage['type']);
        $this->assertEquals('Suggestion:', $suggestionMessage['prefix']);
        $this->assertEquals('Try 190 lbs × 8 reps × 4 sets today', $suggestionMessage['text']);
    }

    #[Test]
    public function it_does_not_show_last_notes_when_comments_are_empty()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create(['is_bodyweight' => false]);
        $program->exercise = $exercise;
        
        $lastSession = [
            'weight' => 135,
            'reps' => 10,
            'sets' => 3,
            'date' => 'Jan 8',
            'comments' => '' // Empty comments
        ];
        
        $user = \App\Models\User::factory()->create();
        $messages = $this->service->generateFormMessages($program, $lastSession, $user->id);
        
        $this->assertCount(2, $messages); // Last session + suggestion (no notes message)
        
        // Verify no "Last notes:" message is present
        $notesMessage = collect($messages)->firstWhere('prefix', 'Last notes:');
        $this->assertNull($notesMessage);
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
    public function it_returns_null_for_summary()
    {
        $user = \App\Models\User::factory()->create();
        
        $summary = $this->service->generateSummary($user->id, $this->testDate);
        
        $this->assertNull($summary);
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
        
        // Verify program was created with priority 99 (100 - 1, no existing programs)
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 5,
            'priority' => 99,
            'comments' => null
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
        
        // Verify program was created with priority 99 (100 - 1, no existing programs)
        $exercise = Exercise::where('title', 'Custom Exercise')->first();
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 5,
            'priority' => 99,
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
        
        $roundsField = $form['numericFields'][2];
        $this->assertEquals('rounds', $roundsField['name']);
        $this->assertEquals(4, $roundsField['defaultValue']); // From program
        
        // Check comment field
        $this->assertEquals('comments', $form['commentField']['name']);
        $this->assertEquals('', $form['commentField']['defaultValue']);
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
        $this->assertGreaterThanOrEqual(2, $messages); // At least last session + suggestion
        
        // Find the last session message (might not be first due to system messages)
        $lastSessionMessage = collect($messages)->firstWhere(function ($message) {
            return str_contains($message['prefix'] ?? '', 'Last session');
        });
        $this->assertNotNull($lastSessionMessage);
        $this->assertEquals('info', $lastSessionMessage['type']);
        $this->assertStringContainsString('200 lbs × 6 reps × 3 sets', $lastSessionMessage['text']);
        
        // Find the suggestion message
        $suggestionMessage = collect($messages)->firstWhere(function ($message) {
            return str_contains($message['text'] ?? '', 'Try 205 lbs × 6 reps × 3 sets today');
        });
        $this->assertNotNull($suggestionMessage);
        $this->assertEquals('tip', $suggestionMessage['type']);
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
        
        $roundsField = $form['numericFields'][2];
        $this->assertEquals($formId . '-rounds', $roundsField['id']);
        
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
        $this->assertEquals(0, $weightedWeightField['min']);
        
        // Find bodyweight exercise form
        $bodyweightForm = collect($forms)->firstWhere('title', 'Push-ups');
        $bodyweightWeightField = $bodyweightForm['numericFields'][0];
        
        $this->assertEquals('Added Weight (lbs):', $bodyweightWeightField['label']);
        $this->assertEquals(0, $bodyweightWeightField['defaultValue']);
        $this->assertEquals(2.5, $bodyweightWeightField['increment']);
        $this->assertEquals(0, $bodyweightWeightField['min']);
    }

    #[Test]
    public function it_generates_interface_messages_with_no_session_data()
    {
        $messages = $this->service->generateInterfaceMessages();
        
        $this->assertFalse($messages['hasMessages']);
        $this->assertEquals(0, $messages['messageCount']);
        $this->assertEmpty($messages['messages']);
    }

    #[Test]
    public function it_includes_session_messages_in_interface_messages()
    {
        $sessionMessages = [
            'success' => 'Exercise added successfully!',
            'error' => 'Something went wrong',
            'warning' => 'Please check your form',
            'info' => 'Additional information'
        ];
        
        $messages = $this->service->generateInterfaceMessages($sessionMessages);
        
        $this->assertTrue($messages['hasMessages']);
        $this->assertEquals(4, $messages['messageCount']);
        
        // Check that session messages are included
        $successMessage = collect($messages['messages'])->firstWhere('type', 'success');
        $this->assertNotNull($successMessage);
        $this->assertEquals('Success:', $successMessage['prefix']);
        $this->assertEquals('Exercise added successfully!', $successMessage['text']);
        
        $errorMessage = collect($messages['messages'])->firstWhere('type', 'error');
        $this->assertNotNull($errorMessage);
        $this->assertEquals('Error:', $errorMessage['prefix']);
        $this->assertEquals('Something went wrong', $errorMessage['text']);
    }

    #[Test]
    public function it_includes_redirect_parameters_in_delete_action()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        
        // Create a lift log for the test date
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->setTime(10, 0),
            'comments' => 'Test log'
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 8
        ]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertCount(1, $loggedItems['items']);
        
        $item = $loggedItems['items'][0];
        $deleteAction = $item['deleteAction'];
        
        // Parse the URL to check parameters
        $parsedUrl = parse_url($deleteAction);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        
        $this->assertEquals('mobile-entry-lifts', $queryParams['redirect_to']);
        $this->assertEquals($this->testDate->toDateString(), $queryParams['date']);
        
        // Also verify the route includes the lift log ID
        $this->assertStringContainsString('lift-logs/' . $liftLog->id, $deleteAction);
    }

    #[Test]
    public function it_includes_empty_message_only_when_no_items_exist()
    {
        $user = User::factory()->create();
        
        // Test with no logged items
        $loggedItemsEmpty = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertArrayHasKey('emptyMessage', $loggedItemsEmpty);
        $this->assertEquals('No entries logged yet today!', $loggedItemsEmpty['emptyMessage']);
        $this->assertEmpty($loggedItemsEmpty['items']);
        
        // Test with logged items
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->setTime(10, 0),
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 8
        ]);
        
        $loggedItemsWithData = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertArrayNotHasKey('emptyMessage', $loggedItemsWithData);
        $this->assertNotEmpty($loggedItemsWithData['items']);
        $this->assertCount(1, $loggedItemsWithData['items']);
        
        // Verify the item includes the exercise title
        $item = $loggedItemsWithData['items'][0];
        $this->assertEquals('Bench Press', $item['title']);
    }

    #[Test]
    public function it_includes_confirmation_messages_in_logged_items()
    {
        $user = User::factory()->create();
        
        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertArrayHasKey('confirmMessages', $loggedItems);
        $this->assertArrayHasKey('deleteItem', $loggedItems['confirmMessages']);
        $this->assertArrayHasKey('removeForm', $loggedItems['confirmMessages']);
        
        $this->assertEquals(
            'Are you sure you want to delete this lift log entry? This action cannot be undone.',
            $loggedItems['confirmMessages']['deleteItem']
        );
        
        $this->assertEquals(
            'Are you sure you want to remove this exercise from today\'s program?',
            $loggedItems['confirmMessages']['removeForm']
        );
    }

    #[Test]
    public function it_assigns_lower_priority_to_new_exercises()
    {
        $user = User::factory()->create();
        
        // Create an existing program with priority 50
        $existingExercise = Exercise::factory()->create(['title' => 'Existing Exercise']);
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $existingExercise->id,
            'date' => $this->testDate,
            'priority' => 50
        ]);
        
        // Add a new exercise
        $newExercise = Exercise::factory()->create(['title' => 'New Exercise']);
        $result = $this->service->addExerciseForm($user->id, $newExercise->canonical_name, $this->testDate);
        
        $this->assertTrue($result['success']);
        
        // Verify new exercise gets priority 49 (lower than existing priority 50)
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $newExercise->id,
            'priority' => 49
        ]);
        
        // Add another exercise to verify it gets even lower priority
        $anotherExercise = Exercise::factory()->create(['title' => 'Another Exercise']);
        $result2 = $this->service->addExerciseForm($user->id, $anotherExercise->canonical_name, $this->testDate);
        
        $this->assertTrue($result2['success']);
        
        // Verify it gets priority 48 (lower than the previous minimum of 49)
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $anotherExercise->id,
            'priority' => 48
        ]);
    }

    #[Test]
    public function it_generates_comment_field_for_lift_log_entry()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate,
            'comments' => 'Program notes: focus on form'
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $this->assertCount(1, $forms);
        
        $form = $forms[0];
        $commentField = $form['commentField'];
        
        // Comment field should be for lift-log entry, not program
        $this->assertEquals('comments', $commentField['name']);
        $this->assertEquals('', $commentField['defaultValue']); // Empty for new lift-log entry
        $this->assertEquals('Notes:', $commentField['label']);
        $this->assertEquals('RPE, form notes, how did it feel?', $commentField['placeholder']);
        $this->assertEquals('program-' . $program->id . '-comment', $commentField['id']);
    }

    #[Test]
    public function it_does_not_populate_comment_field_with_program_comments()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Squat']);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate,
            'comments' => 'This is a program comment that should not appear in the form'
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $commentField = $form['commentField'];
        
        // Comment field should start empty, not pre-populated with program comments
        $this->assertEquals('', $commentField['defaultValue']);
        $this->assertEquals('comments', $commentField['name']);
    }

    #[Test]
    public function it_generates_comment_field_with_correct_attributes_for_lift_log()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Deadlift']);
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $commentField = $form['commentField'];
        
        // Verify all comment field attributes are correct for lift-log submission
        $this->assertEquals('comments', $commentField['name']); // Matches LiftLog fillable field
        $this->assertEquals('Notes:', $commentField['label']);
        $this->assertEquals('RPE, form notes, how did it feel?', $commentField['placeholder']);
        $this->assertEquals('', $commentField['defaultValue']); // Empty for new entry
        $this->assertEquals('program-' . $program->id . '-comment', $commentField['id']);
    }

    #[Test]
    public function it_maintains_program_comments_in_messages_but_not_in_comment_field()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Overhead Press']);
        
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $this->testDate,
            'comments' => 'Focus on strict form today'
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        
        // Program comments should appear in messages section
        $programMessage = collect($form['messages'])->firstWhere('prefix', 'Program notes:');
        $this->assertNotNull($programMessage);
        $this->assertEquals('Focus on strict form today', $programMessage['text']);
        
        // But comment field should be empty for lift-log entry
        $commentField = $form['commentField'];
        $this->assertEquals('', $commentField['defaultValue']);
        $this->assertEquals('comments', $commentField['name']);
    }

    #[Test]
    public function it_generates_logged_items_with_no_logs()
    {
        $user = User::factory()->create();
        
        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertArrayHasKey('items', $loggedItems);
        $this->assertArrayHasKey('emptyMessage', $loggedItems);
        $this->assertEmpty($loggedItems['items']);
        $this->assertEquals('No entries logged yet today!', $loggedItems['emptyMessage']);
    }

    #[Test]
    public function it_generates_logged_items_for_regular_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'is_bodyweight' => false,
            'band_type' => null
        ]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->setTime(10, 0),
            'comments' => 'Felt strong today'
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 185,
            'reps' => 5
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 185,
            'reps' => 5
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 185,
            'reps' => 5
        ]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertCount(1, $loggedItems['items']);
        $this->assertArrayNotHasKey('emptyMessage', $loggedItems);
        
        $item = $loggedItems['items'][0];
        $this->assertEquals($liftLog->id, $item['id']);
        $this->assertEquals('Bench Press', $item['title']);
        $this->assertArrayNotHasKey('value', $item); // No value field
        $this->assertEquals('185 lbs × 3 x 5', $item['message']['text']); // weight × sets x reps in message
        $this->assertEquals('success', $item['message']['type']);
        $this->assertEquals('Completed!', $item['message']['prefix']);
        $this->assertEquals('Felt strong today', $item['freeformText']);
        $this->assertStringContainsString('lift-logs/' . $liftLog->id . '/edit', $item['editAction']);
        $this->assertStringContainsString('lift-logs/' . $liftLog->id, $item['deleteAction']);
    }

    #[Test]
    public function it_generates_logged_items_for_bodyweight_exercise_with_no_added_weight()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Pull-ups',
            'is_bodyweight' => true,
            'band_type' => null
        ]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->setTime(14, 30),
            'comments' => 'Good form throughout'
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0, // No added weight
            'reps' => 8
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 7
        ]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $item = $loggedItems['items'][0];
        $this->assertEquals('Pull-ups', $item['title']);
        $this->assertEquals('BW × 2 x 8', $item['message']['text']); // BW × sets x reps (uses first set reps)
        $this->assertEquals('Good form throughout', $item['freeformText']);
    }

    #[Test]
    public function it_generates_logged_items_for_bodyweight_exercise_with_added_weight()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Weighted Pull-ups',
            'is_bodyweight' => true,
            'band_type' => null
        ]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->setTime(16, 0)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 25, // Added weight
            'reps' => 6
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 25,
            'reps' => 5
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 25,
            'reps' => 4
        ]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $item = $loggedItems['items'][0];
        $this->assertEquals('Weighted Pull-ups', $item['title']);
        $this->assertEquals('BW +25 lbs × 3 x 6', $item['message']['text']); // BW +weight × sets x reps
    }

    #[Test]
    public function it_generates_logged_items_for_band_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Band Pull-aparts',
            'is_bodyweight' => false,
            'band_type' => 'resistance'
        ]);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->setTime(12, 15)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 15,
            'band_color' => 'Red'
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 12,
            'band_color' => 'Red'
        ]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $item = $loggedItems['items'][0];
        $this->assertEquals('Band Pull-aparts', $item['title']);
        $this->assertEquals('Band: Red × 2 x 15', $item['message']['text']); // Band: color × sets x reps
    }

    #[Test]
    public function it_skips_logged_items_with_no_lift_sets()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Empty Log']);
        
        // Create lift log without any sets
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate
        ]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertEmpty($loggedItems['items']);
        $this->assertArrayHasKey('emptyMessage', $loggedItems);
    }

    #[Test]
    public function it_orders_logged_items_by_logged_at_desc()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['title' => 'First Exercise']);
        $exercise2 = Exercise::factory()->create(['title' => 'Second Exercise']);
        
        // Create logs in different order
        $liftLog1 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => $this->testDate->copy()->setTime(10, 0) // Earlier
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'logged_at' => $this->testDate->copy()->setTime(14, 0) // Later
        ]);
        
        // Add sets to both
        LiftSet::factory()->create(['lift_log_id' => $liftLog1->id, 'weight' => 100, 'reps' => 5]);
        LiftSet::factory()->create(['lift_log_id' => $liftLog2->id, 'weight' => 200, 'reps' => 3]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertCount(2, $loggedItems['items']);
        // Should be ordered by logged_at desc (most recent first)
        $this->assertEquals('Second Exercise', $loggedItems['items'][0]['title']); // 14:00
        $this->assertEquals('First Exercise', $loggedItems['items'][1]['title']);  // 10:00
    }

    #[Test]
    public function it_includes_correct_redirect_parameters_in_delete_action()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Test Exercise']);
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate
        ]);
        
        LiftSet::factory()->create(['lift_log_id' => $liftLog->id, 'weight' => 150, 'reps' => 8]);

        $loggedItems = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $item = $loggedItems['items'][0];
        $deleteAction = $item['deleteAction'];
        
        // Parse the URL to check parameters
        $parsedUrl = parse_url($deleteAction);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        
        $this->assertEquals('mobile-entry-lifts', $queryParams['redirect_to']);
        $this->assertEquals($this->testDate->toDateString(), $queryParams['date']);
    }
}