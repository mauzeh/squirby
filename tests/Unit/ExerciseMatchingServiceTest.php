<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Services\ExerciseMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExerciseMatchingService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExerciseMatchingService();
        $this->user = User::factory()->create();
    }

    public function test_exact_match_case_insensitive()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Back Squat',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('back squat', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_exact_match_with_different_case()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Pull-ups',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('Pull-Ups', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_prefers_exact_match_over_partial()
    {
        $exactMatch = Exercise::factory()->create([
            'title' => 'Squat',
            'user_id' => $this->user->id
        ]);
        
        $partialMatch = Exercise::factory()->create([
            'title' => 'Back Squat',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('Squat', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exactMatch->id, $result->id);
    }

    public function test_handles_hyphen_variations()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Pull-ups',
            'user_id' => $this->user->id
        ]);

        // Test without hyphen
        $result1 = $this->service->findBestMatch('Pullups', $this->user->id);
        $this->assertNotNull($result1);
        $this->assertEquals($exercise->id, $result1->id);

        // Test with space
        $result2 = $this->service->findBestMatch('Pull ups', $this->user->id);
        $this->assertNotNull($result2);
        $this->assertEquals($exercise->id, $result2->id);
    }

    public function test_handles_plural_variations()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Push-up',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('Push-ups', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_prefers_starts_with_match()
    {
        $startsWithMatch = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => $this->user->id
        ]);
        
        $containsMatch = Exercise::factory()->create([
            'title' => 'Incline Bench Press',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('Bench', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($startsWithMatch->id, $result->id);
    }

    public function test_handles_abbreviations()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Dumbbell Row',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('DB Row', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_returns_null_when_no_match()
    {
        Exercise::factory()->create([
            'title' => 'Back Squat',
            'user_id' => $this->user->id
        ]);

        // Search for something completely different
        $result = $this->service->findBestMatch('Jumping Jacks', $this->user->id);

        $this->assertNull($result);
    }

    public function test_only_searches_user_available_exercises()
    {
        $otherUser = User::factory()->create();
        
        $otherUserExercise = Exercise::factory()->create([
            'title' => 'Back Squat',
            'user_id' => $otherUser->id
        ]);

        $result = $this->service->findBestMatch('Back Squat', $this->user->id);

        $this->assertNull($result);
    }

    public function test_finds_global_exercises()
    {
        $globalExercise = Exercise::factory()->create([
            'title' => 'Back Squat',
            'user_id' => null // Global exercise
        ]);

        $result = $this->service->findBestMatch('Back Squat', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($globalExercise->id, $result->id);
    }

    public function test_handles_extra_whitespace()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Back Squat',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('  Back   Squat  ', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_prefers_whole_word_match()
    {
        $wholeWordMatch = Exercise::factory()->create([
            'title' => 'Press',
            'user_id' => $this->user->id
        ]);
        
        $partialWordMatch = Exercise::factory()->create([
            'title' => 'Pressing Movement',
            'user_id' => $this->user->id
        ]);

        $result = $this->service->findBestMatch('Press', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($wholeWordMatch->id, $result->id);
    }

    public function test_handles_compound_exercise_names()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Barbell Back Squat',
            'user_id' => $this->user->id
        ]);

        // Should match with partial name
        $result = $this->service->findBestMatch('Back Squat', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_handles_crossfit_abbreviations()
    {
        // Test common CrossFit abbreviations
        $exercises = [
            'Kettlebell Swing' => ['KB Swing', 'KBS'],
            'Toes to Bar' => ['T2B', 'TTB'],
            'Chest to Bar' => ['C2B', 'CTB'],
            'Handstand Push Up' => ['HSPU'],
            'Double Under' => ['DU'],
            'Muscle Up' => ['MU'],
            'Wall Ball' => ['WB'],
            'Box Jump Over' => ['BJO', 'BJOU'],
        ];

        foreach ($exercises as $fullName => $abbreviations) {
            $exercise = Exercise::factory()->create([
                'title' => $fullName,
                'user_id' => $this->user->id
            ]);

            foreach ($abbreviations as $abbr) {
                $result = $this->service->findBestMatch($abbr, $this->user->id);
                
                $this->assertNotNull($result, "Failed to match '{$abbr}' to '{$fullName}'");
                $this->assertEquals($exercise->id, $result->id, "'{$abbr}' matched wrong exercise");
            }
        }
    }

    public function test_handles_olympic_lift_abbreviations()
    {
        $exercises = [
            'Power Clean' => ['PC'],
            'Power Snatch' => ['PS'],
            'Front Squat' => ['FS'],
            'Back Squat' => ['BS'],
            'Overhead Squat' => ['OHS'],
            'Push Press' => ['PP'],
            'Push Jerk' => ['PJ'],
            'Deadlift' => ['DL'],
            'Romanian Deadlift' => ['RDL'],
        ];

        foreach ($exercises as $fullName => $abbreviations) {
            $exercise = Exercise::factory()->create([
                'title' => $fullName,
                'user_id' => $this->user->id
            ]);

            foreach ($abbreviations as $abbr) {
                $result = $this->service->findBestMatch($abbr, $this->user->id);
                
                $this->assertNotNull($result, "Failed to match '{$abbr}' to '{$fullName}'");
                $this->assertEquals($exercise->id, $result->id, "'{$abbr}' matched wrong exercise");
            }
        }
    }

    public function test_abbreviations_work_in_compound_names()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Dumbbell Row',
            'user_id' => $this->user->id
        ]);

        // Should match "DB Row" to "Dumbbell Row"
        $result = $this->service->findBestMatch('DB Row', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_avoids_false_positives_with_abbreviations()
    {
        // Create exercises that could conflict
        Exercise::factory()->create([
            'title' => 'Push Up',
            'user_id' => $this->user->id
        ]);
        
        $pushPress = Exercise::factory()->create([
            'title' => 'Push Press',
            'user_id' => $this->user->id
        ]);

        // "PP" should match "Push Press", not "Push Up"
        $result = $this->service->findBestMatch('PP', $this->user->id);

        $this->assertNotNull($result);
        $this->assertEquals($pushPress->id, $result->id);
    }
}
