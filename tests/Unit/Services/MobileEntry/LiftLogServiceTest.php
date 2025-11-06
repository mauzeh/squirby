<?php

namespace Tests\Unit\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Program;
use App\Models\User;
use App\Services\MobileEntry\LiftLogService;
use App\Services\TrainingProgressionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for LiftLogService core logic
 * 
 * Tests the business logic without route dependencies
 */
class LiftLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private LiftLogService $service;
    private Carbon $testDate;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the TrainingProgressionService
        $mockProgressionService = $this->createMock(TrainingProgressionService::class);
        $mockProgressionService->method('getSuggestionDetails')->willReturn(null);
        
        // Mock the LiftDataCacheService
        $mockCacheService = $this->createMock(\App\Services\MobileEntry\LiftDataCacheService::class);
        
        // Set up default mock returns for cache service
        $mockCacheService->method('getProgramsWithCompletionStatus')->willReturnCallback(function($userId, $selectedDate, $includeCompleted = false) {
            $query = Program::where('user_id', $userId)
                ->whereDate('date', $selectedDate->toDateString())
                ->with(['exercise'])
                ->withCompletionStatus();
                
            if (!$includeCompleted) {
                $query->incomplete();
            }
            
            return $query->orderBy('priority', 'asc')->get();
        });
        
        $mockCacheService->method('getAllCachedData')->willReturnCallback(function($userId, $selectedDate, $exerciseIds = []) {
            // Get actual last session data for the exercises
            $lastSessionData = [];
            foreach ($exerciseIds as $exerciseId) {
                $lastLog = LiftLog::where('user_id', $userId)
                    ->where('exercise_id', $exerciseId)
                    ->where('logged_at', '<', $selectedDate->toDateString())
                    ->with(['liftSets'])
                    ->orderBy('logged_at', 'desc')
                    ->first();
                
                if ($lastLog && !$lastLog->liftSets->isEmpty()) {
                    $firstSet = $lastLog->liftSets->first();
                    $lastSessionData[$exerciseId] = [
                        'weight' => $firstSet->weight,
                        'reps' => $firstSet->reps,
                        'sets' => $lastLog->liftSets->count(),
                        'date' => $lastLog->logged_at->format('M j'),
                        'comments' => $lastLog->comments,
                        'band_color' => $firstSet->band_color
                    ];
                }
            }
            
            // Get recent exercise IDs (exercises logged within last 30 days)
            $recentExerciseIds = LiftLog::where('user_id', $userId)
                ->where('logged_at', '<', $selectedDate->toDateString())
                ->where('logged_at', '>=', $selectedDate->copy()->subDays(30))
                ->select('exercise_id')
                ->groupBy('exercise_id')
                ->orderByRaw('MAX(logged_at) DESC')
                ->limit(5)
                ->pluck('exercise_id')
                ->toArray();
            
            return [
                'lastSessionData' => $lastSessionData,
                'recentExerciseIds' => $recentExerciseIds,
                'programExerciseIds' => Program::where('user_id', $userId)
                    ->whereDate('date', $selectedDate->toDateString())
                    ->pluck('exercise_id')
                    ->toArray(),
            ];
        });
        
        $mockCacheService->method('determineItemType')->willReturnCallback(function($exercise, $userId, $recentExerciseIds, $programExerciseIds) {
            // Check if exercise is in today's program (using pre-fetched data)
            $inProgram = in_array($exercise->id, $programExerciseIds);

            // Check if exercise is in the top recent exercises
            $isTopRecent = in_array($exercise->id, $recentExerciseIds);

            // Check if it's a user's custom exercise
            $isCustom = $exercise->user_id === $userId;

            // Determine type based on priority: recent > custom > regular > in-program
            if ($isTopRecent) {
                return ['label' => 'Recent', 'cssClass' => 'recent', 'priority' => 1];
            } elseif ($isCustom) {
                return ['label' => 'My Exercise', 'cssClass' => 'custom', 'priority' => 2];
            } elseif ($inProgram) {
                return ['label' => 'In Program', 'cssClass' => 'in-program', 'priority' => 4];
            } else {
                return ['label' => 'Available', 'cssClass' => 'regular', 'priority' => 3];
            }
        });
        
        $mockCacheService->method('clearCacheForUser')->willReturnCallback(function() {});
        
        $this->service = new LiftLogService($mockProgressionService, $mockCacheService);
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
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'bodyweight'
        ]);
        
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
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
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
            'canonical_name' => $canonicalName === 'unknown_exercise' ? null : $canonicalName,
            'exercise_type' => 'regular'
        ]);
            
            $weight = $this->service->getDefaultWeight($exercise, null);
            $this->assertEquals($expectedWeight, $weight, "Failed for {$canonicalName}");
        }
    }

    #[Test]
    public function it_generates_empty_messages_when_no_data_available()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $program->exercise = $exercise;
        
        $messages = $this->service->generateFormMessages($program, null);
        
        // Should have instructional message for new users
        $this->assertCount(1, $messages);
        $this->assertEquals('tip', $messages[0]['type']);
        $this->assertEquals('How to log:', $messages[0]['prefix']);
    }

    #[Test]
    public function it_generates_last_session_message()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
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
        $this->assertEquals('Last workout (Jan 13):', $lastSessionMessage['prefix']);
        $this->assertEquals('225 lbs × 5 reps × 3 sets', $lastSessionMessage['text']);
        
        $suggestionMessage = $messages[1];
        $this->assertEquals('tip', $suggestionMessage['type']);
        $this->assertEquals('Try this:', $suggestionMessage['prefix']);
        $this->assertEquals('230 lbs × 5 reps × 3 sets', $suggestionMessage['text']);
    }

    #[Test]
    public function it_generates_last_session_message_with_comments()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
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
        $this->assertEquals('Last workout (Jan 10):', $lastSessionMessage['prefix']);
        $this->assertEquals('185 lbs × 8 reps × 4 sets', $lastSessionMessage['text']);
        
        $lastNotesMessage = $messages[1];
        $this->assertEquals('neutral', $lastNotesMessage['type']);
        $this->assertEquals('Your last notes:', $lastNotesMessage['prefix']);
        $this->assertEquals('Felt really strong today, good form throughout', $lastNotesMessage['text']);
        
        $suggestionMessage = $messages[2];
        $this->assertEquals('tip', $suggestionMessage['type']);
        $this->assertEquals('Try this:', $suggestionMessage['prefix']);
        $this->assertEquals('190 lbs × 8 reps × 4 sets', $suggestionMessage['text']);
    }

    #[Test]
    public function it_does_not_show_last_notes_when_comments_are_empty()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
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
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        $program->exercise = $exercise;
        
        $messages = $this->service->generateFormMessages($program, null);
        
        $this->assertCount(2, $messages); // Instructional + program notes
        
        // First message should be instructional
        $this->assertEquals('tip', $messages[0]['type']);
        $this->assertEquals('How to log:', $messages[0]['prefix']);
        
        // Second message should be program notes
        $message = $messages[1];
        $this->assertEquals('tip', $message['type']);
        $this->assertEquals('Today\'s focus:', $message['prefix']);
        $this->assertEquals('Focus on form today', $message['text']);
    }

    #[Test]
    public function it_does_not_suggest_progression_for_bodyweight_exercises()
    {
        $program = Program::factory()->create(['comments' => null]);
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'bodyweight'
        ]);
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
        $this->assertEquals('', $result['message']);
        
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
        $this->assertEquals('', $result['message']);
        
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
        $this->assertEquals('Exercise not found. Try searching for a different name or create a new exercise using the "+" button.', $result['message']);
        
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
        $this->assertEquals('Deadlift is already ready to log below. Scroll down to find the entry and enter or modify your workout details.', $result['message']);
    }

    #[Test]
    public function it_creates_new_exercise_successfully()
    {
        $user = \App\Models\User::factory()->create();
        
        $result = $this->service->createExercise($user->id, 'Custom Exercise', $this->testDate);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Created \'Custom Exercise\'! Now scroll down to log your first set - the form is ready with default values you can adjust.', $result['message']);
        
        // Verify exercise was created
        $this->assertDatabaseHas('exercises', [
            'title' => 'Custom Exercise',
            'user_id' => $user->id,
            'canonical_name' => 'custom_exercise',
            'exercise_type' => 'regular'
        ]);
        
        // Verify program was created with priority 99 (100 - 1, no existing programs)
        $exercise = Exercise::where('title', 'Custom Exercise')->first();
        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 5,
            'priority' => 99,
            'comments' => 'New exercise - adjust weight/reps as needed'
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
        $this->assertEquals('\'Existing Exercise\' already exists in your exercise library. Use the search above to find and add it instead.', $result['message']);
        
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
        $this->assertEquals('Removed Test Exercise form. You can add it back anytime using \'Add Exercise\' below.', $result['message']);
        
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
        $this->assertEquals('Unable to remove form - invalid format.', $result['message']);
    }

    #[Test]
    public function it_fails_to_remove_nonexistent_program()
    {
        $user = \App\Models\User::factory()->create();
        
        $result = $this->service->removeForm($user->id, 'program-999');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Exercise form not found. It may have already been removed.', $result['message']);
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
        $this->assertEquals('Exercise form not found. It may have already been removed.', $result['message']);
        
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
        $this->assertEquals('Exercise not found. Try searching for a different name or create a new exercise using the "+" button.', $result['message']);
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
        $this->assertEquals('Removed Exercise Without Canonical form. You can add it back anytime using \'Add Exercise\' below.', $result['message']);
    }

    #[Test]
    public function it_generates_forms_with_correct_submission_data()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'exercise_type' => 'regular'
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
        $user = \App\Models\User::factory()->create([
            'show_extra_weight' => true
        ]);
        $exercise = Exercise::factory()->create([
            'title' => 'Pull-ups',
            'exercise_type' => 'bodyweight'
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
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular'
        ]);
        
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
            return str_contains($message['prefix'] ?? '', 'Last workout');
        });
        $this->assertNotNull($lastSessionMessage);
        $this->assertEquals('info', $lastSessionMessage['type']);
        $this->assertStringContainsString('200 lbs × 6 reps × 3 sets', $lastSessionMessage['text']);
        
        // Find the suggestion message
        $suggestionMessage = collect($messages)->firstWhere(function ($message) {
            return str_contains($message['text'] ?? '', '205 lbs × 6 reps × 3 sets');
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
        $user = \App\Models\User::factory()->create([
            'show_extra_weight' => true
        ]);
        
        // Create different types of exercises
        $weightedExercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'exercise_type' => 'regular'
        ]);
        
        $bodyweightExercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight'
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
        $this->assertEquals('Exercise added successfully!', $successMessage['text']);
        
        $errorMessage = collect($messages['messages'])->firstWhere('type', 'error');
        $this->assertNotNull($errorMessage);
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
        
        // Verify the route includes the lift log ID but no query parameters
        $this->assertStringContainsString('lift-logs/' . $liftLog->id, $item['deleteAction']);
        $this->assertStringNotContainsString('redirect_to', $item['deleteAction']);
        
        // Verify redirect parameters are in separate deleteParams array
        $this->assertArrayHasKey('deleteParams', $item);
        $this->assertEquals('mobile-entry-lifts', $item['deleteParams']['redirect_to']);
        $this->assertEquals($this->testDate->toDateString(), $item['deleteParams']['date']);
    }

    #[Test]
    public function it_includes_empty_message_only_when_no_items_exist()
    {
        $user = User::factory()->create();
        
        // Test with no logged items
        $loggedItemsEmpty = $this->service->generateLoggedItems($user->id, $this->testDate);
        
        $this->assertArrayHasKey('emptyMessage', $loggedItemsEmpty);
        $this->assertEquals('No workouts logged yet! Add exercises above to get started.', $loggedItemsEmpty['emptyMessage']);
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
        $this->assertEquals('How did it feel? Any form notes?', $commentField['placeholder']);
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
        $this->assertEquals('How did it feel? Any form notes?', $commentField['placeholder']);
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
        $programMessage = collect($form['messages'])->firstWhere('prefix', 'Today\'s focus:');
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
        $this->assertEquals('No workouts logged yet! Add exercises above to get started.', $loggedItems['emptyMessage']);
    }

    #[Test]
    public function it_generates_logged_items_for_regular_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'exercise_type' => 'regular'
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
            'exercise_type' => 'bodyweight'
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
        $this->assertEquals('2 x 8', $item['message']['text']); // Just sets x reps for bodyweight with no added weight
        $this->assertEquals('Good form throughout', $item['freeformText']);
    }

    #[Test]
    public function it_generates_logged_items_for_bodyweight_exercise_with_added_weight()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Weighted Pull-ups',
            'exercise_type' => 'bodyweight'
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
        $this->assertEquals('Bodyweight +25 lbs × 3 x 6', $item['message']['text']); // Bodyweight +weight × sets x reps for bodyweight with added weight
    }

    #[Test]
    public function it_generates_logged_items_for_band_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Band Pull-aparts',
            'exercise_type' => 'banded_resistance'
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
        
        // Check that deleteAction is the base route without query parameters
        $this->assertStringContainsString('lift-logs/' . $liftLog->id, $item['deleteAction']);
        $this->assertStringNotContainsString('redirect_to', $item['deleteAction']);
        
        // Check that redirect parameters are in separate deleteParams array
        $this->assertArrayHasKey('deleteParams', $item);
        $this->assertEquals('mobile-entry-lifts', $item['deleteParams']['redirect_to']);
        $this->assertEquals($this->testDate->toDateString(), $item['deleteParams']['date']);
    }

    #[Test]
    public function it_generates_item_selection_list_with_date_parameters()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press'
        ]);
        $exercise2 = Exercise::factory()->create([
            'title' => 'Squats',
            'canonical_name' => 'squats'
        ]);

        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        $this->assertArrayHasKey('items', $itemSelectionList);
        $this->assertArrayHasKey('createForm', $itemSelectionList);
        
        // Check that exercise hrefs include date parameter
        $benchPressItem = collect($itemSelectionList['items'])->firstWhere('name', 'Bench Press');
        $this->assertNotNull($benchPressItem);
        $this->assertStringContainsString('date=' . $this->testDate->toDateString(), $benchPressItem['href']);
        $this->assertStringContainsString('mobile-entry/add-lift-form/bench_press', $benchPressItem['href']);
        
        // Check that createForm includes date in hiddenFields
        $createForm = $itemSelectionList['createForm'];
        $this->assertArrayHasKey('hiddenFields', $createForm);
        $this->assertEquals($this->testDate->toDateString(), $createForm['hiddenFields']['date']);
    }

    #[Test]
    public function it_generates_item_selection_list_with_exercise_by_id_when_no_canonical_name()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Custom Exercise'
        ]);
        
        // Manually set canonical_name to null to bypass the model's automatic generation
        $exercise->update(['canonical_name' => null]);
        $exercise->refresh();

        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        // Should use exercise ID when canonical_name is null
        $customExerciseItem = collect($itemSelectionList['items'])->firstWhere('name', 'Custom Exercise');
        $this->assertNotNull($customExerciseItem);
        $this->assertStringContainsString('mobile-entry/add-lift-form/' . $exercise->id, $customExerciseItem['href']);
        $this->assertStringContainsString('date=' . $this->testDate->toDateString(), $customExerciseItem['href']);
    }

    #[Test]
    public function it_highlights_exercises_in_todays_program()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['title' => 'In Program']);
        $exercise2 = Exercise::factory()->create(['title' => 'Not In Program']);
        
        // Add exercise1 to today's program
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'date' => $this->testDate
        ]);

        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        $inProgramItem = collect($itemSelectionList['items'])->firstWhere('name', 'In Program');
        $notInProgramItem = collect($itemSelectionList['items'])->firstWhere('name', 'Not In Program');
        
        $this->assertEquals('in-program', $inProgramItem['type']['cssClass']);
        $this->assertEquals('In Program', $inProgramItem['type']['label']);
        $this->assertEquals(4, $inProgramItem['type']['priority']);
        
        $this->assertEquals('regular', $notInProgramItem['type']['cssClass']);
        $this->assertEquals('Available', $notInProgramItem['type']['label']);
        $this->assertEquals(3, $notInProgramItem['type']['priority']);
    }

    #[Test]
    public function it_highlights_recently_used_exercises()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['title' => 'Recently Used']);
        $exercise2 = Exercise::factory()->create(['title' => 'Not Recently Used']);
        
        // Create a recent lift log for exercise1 (within 7 days)
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'logged_at' => $this->testDate->copy()->subDays(3)
        ]);

        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        $recentItem = collect($itemSelectionList['items'])->firstWhere('name', 'Recently Used');
        $notRecentItem = collect($itemSelectionList['items'])->firstWhere('name', 'Not Recently Used');
        
        $this->assertEquals('recent', $recentItem['type']['cssClass']);
        $this->assertEquals('Recent', $recentItem['type']['label']);
        $this->assertEquals(1, $recentItem['type']['priority']);
        
        $this->assertEquals('regular', $notRecentItem['type']['cssClass']);
        $this->assertEquals('Available', $notRecentItem['type']['label']);
        $this->assertEquals(3, $notRecentItem['type']['priority']);
    }

    #[Test]
    public function it_sorts_in_program_exercises_last()
    {
        $user = User::factory()->create();
        
        // Create exercises in alphabetical order
        $exerciseA = Exercise::factory()->create(['title' => 'A Regular Exercise']);
        $exerciseB = Exercise::factory()->create(['title' => 'B In Program Exercise']);
        $exerciseC = Exercise::factory()->create(['title' => 'C Regular Exercise']);
        
        // Make exerciseB in-program by adding to program
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exerciseB->id,
            'date' => $this->testDate
        ]);

        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        // In-program exercise should come last despite alphabetical order
        $this->assertEquals('A Regular Exercise', $itemSelectionList['items'][0]['name']);
        $this->assertEquals('C Regular Exercise', $itemSelectionList['items'][1]['name']);
        $this->assertEquals('B In Program Exercise', $itemSelectionList['items'][2]['name']);
        $this->assertEquals('in-program', $itemSelectionList['items'][2]['type']['cssClass']);
        $this->assertEquals('In Program', $itemSelectionList['items'][2]['type']['label']);
        $this->assertEquals(4, $itemSelectionList['items'][2]['type']['priority']);
    }

    #[Test]
    public function it_includes_all_accessible_exercises_in_item_selection_list()
    {
        $user = User::factory()->create();
        
        // Create 25 exercises
        for ($i = 1; $i <= 25; $i++) {
            Exercise::factory()->create([
                'title' => 'Exercise ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'user_id' => $user->id
            ]);
        }

        $itemSelectionList = $this->service->generateItemSelectionList($user->id, $this->testDate);
        
        // Should include all 25 exercises
        $this->assertCount(25, $itemSelectionList['items']);
    }

    #[Test]
    public function it_respects_user_exercise_visibility_in_item_selection()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // Create exercises for different users
        $user1Exercise = Exercise::factory()->create([
            'title' => 'User 1 Exercise',
            'user_id' => $user1->id
        ]);
        
        $user2Exercise = Exercise::factory()->create([
            'title' => 'User 2 Exercise',
            'user_id' => $user2->id
        ]);
        
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null
        ]);

        $itemSelectionList = $this->service->generateItemSelectionList($user1->id, $this->testDate);
        
        $exerciseNames = collect($itemSelectionList['items'])->pluck('name')->toArray();
        
        // User1 should see their own exercise and global exercises
        $this->assertContains('User 1 Exercise', $exerciseNames);
        $this->assertContains('Global Exercise', $exerciseNames);
        
        // User1 should NOT see User2's private exercise
        $this->assertNotContains('User 2 Exercise', $exerciseNames);
    }

    #[Test]
    public function it_includes_date_parameter_in_form_delete_action()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Test Exercise']);
        
        // Create a program for tomorrow
        $tomorrowDate = $this->testDate->copy()->addDay();
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $tomorrowDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $tomorrowDate);
        
        $this->assertCount(1, $forms);
        $form = $forms[0];
        
        // Verify the form has deleteParams with the correct date
        $this->assertArrayHasKey('deleteParams', $form);
        $this->assertArrayHasKey('date', $form['deleteParams']);
        $this->assertEquals($tomorrowDate->toDateString(), $form['deleteParams']['date']);
    }

    #[Test]
    public function it_preserves_selected_date_when_removing_program_form()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Test Exercise']);
        
        // Create a program for tomorrow
        $tomorrowDate = $this->testDate->copy()->addDay();
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $tomorrowDate
        ]);

        // Simulate the controller receiving the date parameter
        $formId = 'program-' . $program->id;
        
        // The removeForm method should work regardless of date
        $result = $this->service->removeForm($user->id, $formId);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Removed Test Exercise form. You can add it back anytime using \'Add Exercise\' below.', $result['message']);
        
        // Verify program was deleted
        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    #[Test]
    public function it_generates_band_color_field_for_banded_exercises()
    {
        $user = User::factory()->create();
        $bandedExercise = Exercise::factory()->create([
            'title' => 'Lat Pull-Down (Banded)',
            'exercise_type' => 'banded_resistance'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bandedExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $this->assertCount(1, $forms);
        $form = $forms[0];
        
        // Should have band_color field instead of weight field
        $fieldNames = collect($form['numericFields'])->pluck('name')->toArray();
        $this->assertContains('band_color', $fieldNames);
        $this->assertNotContains('weight', $fieldNames);
        
        // Check band_color field properties
        $bandColorField = collect($form['numericFields'])->firstWhere('name', 'band_color');
        $this->assertEquals('select', $bandColorField['type']);
        $this->assertEquals('Band Color:', $bandColorField['label']);
        $this->assertEquals('red', $bandColorField['defaultValue']); // Default to red
        
        // Should have options for all configured band colors
        $this->assertCount(3, $bandColorField['options']); // red, blue, green
        $this->assertEquals('red', $bandColorField['options'][0]['value']);
        $this->assertEquals('Red', $bandColorField['options'][0]['label']);
        $this->assertEquals('blue', $bandColorField['options'][1]['value']);
        $this->assertEquals('Blue', $bandColorField['options'][1]['label']);
        $this->assertEquals('green', $bandColorField['options'][2]['value']);
        $this->assertEquals('Green', $bandColorField['options'][2]['label']);
    }

    #[Test]
    public function it_generates_weight_field_for_non_banded_exercises()
    {
        $user = User::factory()->create();
        $regularExercise = Exercise::factory()->create([
            'title' => 'Bench Press'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $regularExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $this->assertCount(1, $forms);
        $form = $forms[0];
        
        // Should have weight field, not band_color field
        $fieldNames = collect($form['numericFields'])->pluck('name')->toArray();
        $this->assertContains('weight', $fieldNames);
        $this->assertNotContains('band_color', $fieldNames);
        
        // Check weight field properties
        $weightField = collect($form['numericFields'])->firstWhere('name', 'weight');
        $this->assertArrayNotHasKey('type', $weightField); // Regular numeric field
        $this->assertEquals('Weight (lbs):', $weightField['label']);
    }

    #[Test]
    public function it_uses_last_session_band_color_as_default()
    {
        $user = User::factory()->create();
        $bandedExercise = Exercise::factory()->create([
            'title' => 'Band Pull-aparts',
            'exercise_type' => 'banded_resistance'
        ]);
        
        // Create last session with blue band
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bandedExercise->id,
            'logged_at' => $this->testDate->copy()->subDays(2)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 12,
            'band_color' => 'blue'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bandedExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $bandColorField = collect($form['numericFields'])->firstWhere('name', 'band_color');
        
        // Should default to blue from last session
        $this->assertEquals('blue', $bandColorField['defaultValue']);
    }

    #[Test]
    public function it_defaults_to_red_band_when_no_last_session()
    {
        $user = User::factory()->create();
        $bandedExercise = Exercise::factory()->create([
            'title' => 'New Band Exercise',
            'exercise_type' => 'banded_resistance'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bandedExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $bandColorField = collect($form['numericFields'])->firstWhere('name', 'band_color');
        
        // Should default to red when no last session
        $this->assertEquals('red', $bandColorField['defaultValue']);
    }

    #[Test]
    public function it_includes_band_color_in_last_session_data()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->subDays(1),
            'comments' => 'Good session'
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 8,
            'band_color' => 'green'
        ]);

        $lastSession = $this->service->getLastSessionData(
            $exercise->id, 
            $this->testDate, 
            $user->id
        );
        
        $this->assertArrayHasKey('band_color', $lastSession);
        $this->assertEquals('green', $lastSession['band_color']);
        $this->assertEquals(135, $lastSession['weight']);
        $this->assertEquals(8, $lastSession['reps']);
    }

    #[Test]
    public function it_handles_null_band_color_in_last_session_data()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => $this->testDate->copy()->subDays(1)
        ]);
        
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 225,
            'reps' => 5,
            'band_color' => null
        ]);

        $lastSession = $this->service->getLastSessionData(
            $exercise->id, 
            $this->testDate, 
            $user->id
        );
        
        $this->assertArrayHasKey('band_color', $lastSession);
        $this->assertNull($lastSession['band_color']);
    }

    #[Test]
    public function it_generates_both_reps_and_sets_fields_for_banded_exercises()
    {
        $user = User::factory()->create();
        $bandedExercise = Exercise::factory()->create([
            'title' => 'Resistance Band Rows',
            'exercise_type' => 'banded_resistance'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bandedExercise->id,
            'date' => $this->testDate,
            'reps' => 12,
            'sets' => 4
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $fieldNames = collect($form['numericFields'])->pluck('name')->toArray();
        
        // Should have all three fields: band_color, reps, rounds (sets)
        $this->assertContains('band_color', $fieldNames);
        $this->assertContains('reps', $fieldNames);
        $this->assertContains('rounds', $fieldNames);
        
        // Check that reps and sets use program defaults
        $repsField = collect($form['numericFields'])->firstWhere('name', 'reps');
        $setsField = collect($form['numericFields'])->firstWhere('name', 'rounds');
        
        $this->assertEquals(12, $repsField['defaultValue']);
        $this->assertEquals(4, $setsField['defaultValue']);
    }

    #[Test]
    public function it_handles_assistance_band_type()
    {
        $user = User::factory()->create();
        $assistanceBandExercise = Exercise::factory()->create([
            'title' => 'Assisted Pull-ups',
            'exercise_type' => 'banded_assistance'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $assistanceBandExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $fieldNames = collect($form['numericFields'])->pluck('name')->toArray();
        
        // Should generate band_color field for assistance bands too
        $this->assertContains('band_color', $fieldNames);
        $this->assertNotContains('weight', $fieldNames);
        
        $bandColorField = collect($form['numericFields'])->firstWhere('name', 'band_color');
        $this->assertEquals('select', $bandColorField['type']);
        $this->assertEquals('Band Color:', $bandColorField['label']);
    }

    #[Test]
    public function it_preserves_field_order_with_band_color_first()
    {
        $user = User::factory()->create();
        $bandedExercise = Exercise::factory()->create([
            'title' => 'Band Exercise',
            'exercise_type' => 'banded_resistance'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bandedExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $fieldNames = collect($form['numericFields'])->pluck('name')->toArray();
        
        // Band color should be first, followed by reps, then sets
        $this->assertEquals(['band_color', 'reps', 'rounds'], $fieldNames);
    }

    #[Test]
    public function it_generates_select_field_with_correct_structure()
    {
        $user = User::factory()->create();
        $bandedExercise = Exercise::factory()->create([
            'title' => 'Test Band Exercise',
            'exercise_type' => 'banded_resistance'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $bandedExercise->id,
            'date' => $this->testDate
        ]);

        $forms = $this->service->generateProgramForms($user->id, $this->testDate);
        
        $form = $forms[0];
        $bandColorField = collect($form['numericFields'])->firstWhere('name', 'band_color');
        
        // Verify select field structure matches what the view expects
        $this->assertEquals('select', $bandColorField['type']);
        $this->assertArrayHasKey('options', $bandColorField);
        $this->assertArrayHasKey('ariaLabels', $bandColorField);
        $this->assertArrayHasKey('field', $bandColorField['ariaLabels']);
        
        // Verify options structure
        foreach ($bandColorField['options'] as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
        
        // Verify all configured band colors are present
        $optionValues = collect($bandColorField['options'])->pluck('value')->toArray();
        $this->assertContains('red', $optionValues);
        $this->assertContains('blue', $optionValues);
        $this->assertContains('green', $optionValues);
    }
}