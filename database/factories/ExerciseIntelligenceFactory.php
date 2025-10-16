<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseIntelligenceFactory extends Factory
{
    protected $model = ExerciseIntelligence::class;

    public function definition()
    {
        return [
            'exercise_id' => Exercise::factory(),
            'canonical_name' => $this->faker->slug(2),
            'muscle_data' => [
                'muscles' => [
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
                ]
            ],
            'primary_mover' => 'pectoralis_major',
            'largest_muscle' => 'pectoralis_major',
            'movement_archetype' => $this->faker->randomElement(['push', 'pull', 'squat', 'hinge', 'carry', 'core']),
            'category' => $this->faker->randomElement(['strength', 'cardio', 'mobility', 'plyometric', 'flexibility']),
            'difficulty_level' => $this->faker->numberBetween(1, 5),
            'recovery_hours' => $this->faker->numberBetween(24, 72),
        ];
    }

    public function forGlobalExercise()
    {
        return $this->state(function (array $attributes) {
            return [
                'exercise_id' => Exercise::factory()->state(['user_id' => null]),
            ];
        });
    }

    public function withMuscleData(array $muscles)
    {
        return $this->state(function (array $attributes) use ($muscles) {
            return [
                'muscle_data' => ['muscles' => $muscles],
            ];
        });
    }
}