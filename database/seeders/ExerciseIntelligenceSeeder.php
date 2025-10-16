<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;

class ExerciseIntelligenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $intelligenceData = [
            // Basic Compound Movements
            'Back Squat' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'rectus_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'vastus_lateralis', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'gastrocnemius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'erector_spinae', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'rectus_femoris',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'squat',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 72,
            ],
            
            'Front Squat' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'rectus_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'vastus_lateralis', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'gastrocnemius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'erector_spinae', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'upper_trapezius', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'rectus_femoris',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'squat',
                'category' => 'strength',
                'difficulty_level' => 5,
                'recovery_hours' => 72,
            ],

            'Deadlift' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'semitendinosus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'erector_spinae', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_femoris', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'latissimus_dorsi', 'role' => 'synergist', 'contraction_type' => 'isometric'],
                        ['name' => 'rhomboids', 'role' => 'synergist', 'contraction_type' => 'isometric'],
                        ['name' => 'middle_trapezius', 'role' => 'synergist', 'contraction_type' => 'isometric'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'gluteus_maximus',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'hinge',
                'category' => 'strength',
                'difficulty_level' => 5,
                'recovery_hours' => 72,
            ],

            'Romanian Deadlift' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'biceps_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'semitendinosus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'erector_spinae', 'role' => 'synergist', 'contraction_type' => 'isometric'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'biceps_femoris',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'hinge',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48,
            ],

            'Bench Press' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'pectoralis_major', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'anterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'triceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'erector_spinae', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'pectoralis_major',
                'largest_muscle' => 'pectoralis_major',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48,
            ],

            'DB Bench Press' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'pectoralis_major', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'anterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'triceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'erector_spinae', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'pectoralis_major',
                'largest_muscle' => 'pectoralis_major',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 48,
            ],

            'Strict Press' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'anterior_deltoid', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'medial_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'triceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'upper_trapezius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'erector_spinae', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'anterior_deltoid',
                'largest_muscle' => 'anterior_deltoid',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 48,
            ],

            // Bodyweight Exercises
            'Push-Up' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'pectoralis_major', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'anterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'triceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'pectoralis_major',
                'largest_muscle' => 'pectoralis_major',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 2,
                'recovery_hours' => 24,
            ],

            'Pull-Ups' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'latissimus_dorsi', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'rhomboids', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'middle_trapezius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'posterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'latissimus_dorsi',
                'largest_muscle' => 'latissimus_dorsi',
                'movement_archetype' => 'pull',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 48,
            ],

            'Chin-Ups' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'latissimus_dorsi', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_brachii', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'rhomboids', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'middle_trapezius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'posterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'latissimus_dorsi',
                'largest_muscle' => 'latissimus_dorsi',
                'movement_archetype' => 'pull',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 48,
            ],

            // Additional Compound Movements
            'Pendlay Row' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'latissimus_dorsi', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'rhomboids', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'middle_trapezius', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'posterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'erector_spinae', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'latissimus_dorsi',
                'largest_muscle' => 'latissimus_dorsi',
                'movement_archetype' => 'pull',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48,
            ],

            'Hip Thrust (Barbell)' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'gluteus_maximus',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'hinge',
                'category' => 'strength',
                'difficulty_level' => 2,
                'recovery_hours' => 48,
            ],

            'Kettlebell Swing' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'erector_spinae', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'anterior_deltoid', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'gluteus_maximus',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'hinge',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48,
            ],

            // Isolation-style exercises (though some are compound)
            'Walking Lunge (2-DB)' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'rectus_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'gastrocnemius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'rectus_femoris',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'squat',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48,
            ],

            'Back Rack Lunge (Step Back)' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'rectus_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'gastrocnemius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                        ['name' => 'erector_spinae', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'rectus_femoris',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'squat',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 48,
            ],

            'Ring Row' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'latissimus_dorsi', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'rhomboids', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'middle_trapezius', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'posterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'latissimus_dorsi',
                'largest_muscle' => 'latissimus_dorsi',
                'movement_archetype' => 'pull',
                'category' => 'strength',
                'difficulty_level' => 2,
                'recovery_hours' => 48,
            ],

            // Additional isolation-style exercises (using available exercises from CSV)
            'Push Press' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'anterior_deltoid', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'medial_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'triceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'upper_trapezius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_femoris', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'gluteus_maximus', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'anterior_deltoid',
                'largest_muscle' => 'anterior_deltoid',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 48,
            ],

            'Power Clean' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'gluteus_maximus', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'biceps_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_femoris', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'erector_spinae', 'role' => 'primary_mover', 'contraction_type' => 'isotonic'],
                        ['name' => 'upper_trapezius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'anterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'gastrocnemius', 'role' => 'synergist', 'contraction_type' => 'isotonic'],
                        ['name' => 'rectus_abdominis', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'gluteus_maximus',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'pull',
                'category' => 'plyometric',
                'difficulty_level' => 5,
                'recovery_hours' => 72,
            ],

            // Additional bodyweight exercises
            'L-Sit (Tucked, Parallelites)' => [
                'muscle_data' => [
                    'muscles' => [
                        ['name' => 'rectus_abdominis', 'role' => 'primary_mover', 'contraction_type' => 'isometric'],
                        ['name' => 'external_obliques', 'role' => 'primary_mover', 'contraction_type' => 'isometric'],
                        ['name' => 'anterior_deltoid', 'role' => 'synergist', 'contraction_type' => 'isometric'],
                        ['name' => 'triceps_brachii', 'role' => 'synergist', 'contraction_type' => 'isometric'],
                        ['name' => 'latissimus_dorsi', 'role' => 'stabilizer', 'contraction_type' => 'isometric'],
                    ]
                ],
                'primary_mover' => 'rectus_abdominis',
                'largest_muscle' => 'rectus_abdominis',
                'movement_archetype' => 'core',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 24,
            ],
        ];

        foreach ($intelligenceData as $exerciseTitle => $data) {
            // Find the exercise by title (global exercises only)
            $exercise = Exercise::where('title', $exerciseTitle)
                ->whereNull('user_id')
                ->first();

            if ($exercise) {
                // Create or update the intelligence data
                ExerciseIntelligence::updateOrCreate(
                    ['exercise_id' => $exercise->id],
                    $data
                );
            }
        }
    }
}