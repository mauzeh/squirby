<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_an_exercise()
    {
        $exercise = Exercise::factory()->create();
        $intelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id
        ]);

        $this->assertInstanceOf(Exercise::class, $intelligence->exercise);
        $this->assertEquals($exercise->id, $intelligence->exercise->id);
    }

    /** @test */
    public function scope_for_global_exercises_returns_only_intelligence_for_global_exercises()
    {
        $user = User::factory()->create();
        
        // Create global exercise with intelligence
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $globalIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $globalExercise->id
        ]);
        
        // Create user exercise with intelligence
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        $userIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $userExercise->id
        ]);

        $globalIntelligenceData = ExerciseIntelligence::forGlobalExercises()->get();

        $this->assertCount(1, $globalIntelligenceData);
        $this->assertTrue($globalIntelligenceData->contains($globalIntelligence));
        $this->assertFalse($globalIntelligenceData->contains($userIntelligence));
    }

    /** @test */
    public function scope_by_movement_archetype_filters_correctly()
    {
        $pushIntelligence = ExerciseIntelligence::factory()->create([
            'movement_archetype' => 'push'
        ]);
        $pullIntelligence = ExerciseIntelligence::factory()->create([
            'movement_archetype' => 'pull'
        ]);

        $pushResults = ExerciseIntelligence::byMovementArchetype('push')->get();
        $pullResults = ExerciseIntelligence::byMovementArchetype('pull')->get();

        $this->assertCount(1, $pushResults);
        $this->assertTrue($pushResults->contains($pushIntelligence));
        $this->assertFalse($pushResults->contains($pullIntelligence));

        $this->assertCount(1, $pullResults);
        $this->assertTrue($pullResults->contains($pullIntelligence));
        $this->assertFalse($pullResults->contains($pushIntelligence));
    }

    /** @test */
    public function scope_by_category_filters_correctly()
    {
        $strengthIntelligence = ExerciseIntelligence::factory()->create([
            'category' => 'strength'
        ]);
        $cardioIntelligence = ExerciseIntelligence::factory()->create([
            'category' => 'cardio'
        ]);

        $strengthResults = ExerciseIntelligence::byCategory('strength')->get();
        $cardioResults = ExerciseIntelligence::byCategory('cardio')->get();

        $this->assertCount(1, $strengthResults);
        $this->assertTrue($strengthResults->contains($strengthIntelligence));
        $this->assertFalse($strengthResults->contains($cardioIntelligence));

        $this->assertCount(1, $cardioResults);
        $this->assertTrue($cardioResults->contains($cardioIntelligence));
        $this->assertFalse($cardioResults->contains($strengthIntelligence));
    }

    /** @test */
    public function get_primary_mover_muscles_returns_correct_muscles()
    {
        $muscleData = [
            [
                'name' => 'pectoralis_major',
                'role' => 'primary_mover',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'quadriceps',
                'role' => 'primary_mover',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'anterior_deltoid',
                'role' => 'synergist',
                'contraction_type' => 'isotonic'
            ]
        ];

        $intelligence = ExerciseIntelligence::factory()
            ->withMuscleData($muscleData)
            ->create();

        $primaryMovers = $intelligence->getPrimaryMoverMuscles();

        $this->assertCount(2, $primaryMovers);
        $this->assertContains('pectoralis_major', $primaryMovers);
        $this->assertContains('quadriceps', $primaryMovers);
        $this->assertNotContains('anterior_deltoid', $primaryMovers);
    }

    /** @test */
    public function get_synergist_muscles_returns_correct_muscles()
    {
        $muscleData = [
            [
                'name' => 'pectoralis_major',
                'role' => 'primary_mover',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'anterior_deltoid',
                'role' => 'synergist',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'triceps_brachii',
                'role' => 'synergist',
                'contraction_type' => 'isotonic'
            ]
        ];

        $intelligence = ExerciseIntelligence::factory()
            ->withMuscleData($muscleData)
            ->create();

        $synergists = $intelligence->getSynergistMuscles();

        $this->assertCount(2, $synergists);
        $this->assertContains('anterior_deltoid', $synergists);
        $this->assertContains('triceps_brachii', $synergists);
        $this->assertNotContains('pectoralis_major', $synergists);
    }

    /** @test */
    public function get_stabilizer_muscles_returns_correct_muscles()
    {
        $muscleData = [
            [
                'name' => 'pectoralis_major',
                'role' => 'primary_mover',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'core_stabilizers',
                'role' => 'stabilizer',
                'contraction_type' => 'isometric'
            ],
            [
                'name' => 'rotator_cuff',
                'role' => 'stabilizer',
                'contraction_type' => 'isometric'
            ]
        ];

        $intelligence = ExerciseIntelligence::factory()
            ->withMuscleData($muscleData)
            ->create();

        $stabilizers = $intelligence->getStabilizerMuscles();

        $this->assertCount(2, $stabilizers);
        $this->assertContains('core_stabilizers', $stabilizers);
        $this->assertContains('rotator_cuff', $stabilizers);
        $this->assertNotContains('pectoralis_major', $stabilizers);
    }

    /** @test */
    public function get_isotonic_muscles_returns_correct_muscles()
    {
        $muscleData = [
            [
                'name' => 'pectoralis_major',
                'role' => 'primary_mover',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'anterior_deltoid',
                'role' => 'synergist',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'core_stabilizers',
                'role' => 'stabilizer',
                'contraction_type' => 'isometric'
            ]
        ];

        $intelligence = ExerciseIntelligence::factory()
            ->withMuscleData($muscleData)
            ->create();

        $isotonicMuscles = $intelligence->getIsotonicMuscles();

        $this->assertCount(2, $isotonicMuscles);
        $this->assertContains('pectoralis_major', $isotonicMuscles);
        $this->assertContains('anterior_deltoid', $isotonicMuscles);
        $this->assertNotContains('core_stabilizers', $isotonicMuscles);
    }

    /** @test */
    public function get_isometric_muscles_returns_correct_muscles()
    {
        $muscleData = [
            [
                'name' => 'pectoralis_major',
                'role' => 'primary_mover',
                'contraction_type' => 'isotonic'
            ],
            [
                'name' => 'core_stabilizers',
                'role' => 'stabilizer',
                'contraction_type' => 'isometric'
            ],
            [
                'name' => 'rotator_cuff',
                'role' => 'stabilizer',
                'contraction_type' => 'isometric'
            ]
        ];

        $intelligence = ExerciseIntelligence::factory()
            ->withMuscleData($muscleData)
            ->create();

        $isometricMuscles = $intelligence->getIsometricMuscles();

        $this->assertCount(2, $isometricMuscles);
        $this->assertContains('core_stabilizers', $isometricMuscles);
        $this->assertContains('rotator_cuff', $isometricMuscles);
        $this->assertNotContains('pectoralis_major', $isometricMuscles);
    }

    /** @test */
    public function muscle_helper_methods_return_empty_arrays_when_no_muscle_data()
    {
        $intelligence = ExerciseIntelligence::factory()->create([
            'muscle_data' => []
        ]);

        $this->assertEmpty($intelligence->getPrimaryMoverMuscles());
        $this->assertEmpty($intelligence->getSynergistMuscles());
        $this->assertEmpty($intelligence->getStabilizerMuscles());
        $this->assertEmpty($intelligence->getIsotonicMuscles());
        $this->assertEmpty($intelligence->getIsometricMuscles());
    }

    /** @test */
    public function muscle_helper_methods_return_empty_arrays_when_muscles_key_missing()
    {
        $intelligence = ExerciseIntelligence::factory()->create([
            'muscle_data' => ['other_data' => 'value']
        ]);

        $this->assertEmpty($intelligence->getPrimaryMoverMuscles());
        $this->assertEmpty($intelligence->getSynergistMuscles());
        $this->assertEmpty($intelligence->getStabilizerMuscles());
        $this->assertEmpty($intelligence->getIsotonicMuscles());
        $this->assertEmpty($intelligence->getIsometricMuscles());
    }

    /** @test */
    public function casts_work_correctly()
    {
        $intelligence = ExerciseIntelligence::factory()->create([
            'difficulty_level' => '3',
            'recovery_hours' => '48'
        ]);

        $this->assertIsInt($intelligence->difficulty_level);
        $this->assertIsInt($intelligence->recovery_hours);
        $this->assertEquals(3, $intelligence->difficulty_level);
        $this->assertEquals(48, $intelligence->recovery_hours);
    }
}