<?php

namespace Tests\Unit\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\ExerciseTypes\RegularExerciseType;
use App\Services\ExerciseTypes\BodyweightExerciseType;
use App\Services\ExerciseTypes\StaticHoldExerciseType;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UnitConversionInDisplayTest extends TestCase
{
    #[Test]
    public function it_formats_regular_exercise_weight_display_correctly_across_units(): void
    {
        $strategy = new RegularExerciseType();

        $exercise = new Exercise();
        $exercise->exercise_type = 'regular';

        // Stored lbs, User prefers lbs (identity)
        $userLbs = new User();
        $userLbs->weight_unit = 'lbs';
        
        $log1 = new LiftLog();
        $log1->user = $userLbs;
        $log1->setRelation('exercise', $exercise);
        $set1 = new LiftSet(['weight' => 135, 'unit' => 'lbs']);
        $log1->setRelation('liftSets', collect([$set1]));

        $this->assertEquals('135 lbs', $strategy->formatWeightDisplay($log1));

        // Stored lbs, User prefers kg
        $userKg = new User();
        $userKg->weight_unit = 'kg';

        $log2 = new LiftLog();
        $log2->user = $userKg;
        $log2->setRelation('exercise', $exercise);
        $set2 = new LiftSet(['weight' => 135, 'unit' => 'lbs']);
        $log2->setRelation('liftSets', collect([$set2]));

        // 135 lbs * 0.45359237 = 61.23 kg -> rounds to 61 kg
        $this->assertEquals('61 kg', $strategy->formatWeightDisplay($log2));

        // Stored kg, User prefers lbs
        $log3 = new LiftLog();
        $log3->user = $userLbs;
        $log3->setRelation('exercise', $exercise);
        $set3 = new LiftSet(['weight' => 60.5, 'unit' => 'kg']);
        $log3->setRelation('liftSets', collect([$set3]));

        // 60.5 kg * 2.20462262 = 133.37 lbs -> rounds to 133 lbs
        $this->assertEquals('133 lbs', $strategy->formatWeightDisplay($log3));
    }

    #[Test]
    public function it_formats_bodyweight_exercise_weight_display_correctly_across_units(): void
    {
        $strategy = new BodyweightExerciseType();

        $exercise = new Exercise();
        $exercise->exercise_type = 'bodyweight';

        $userKg = new User();
        $userKg->weight_unit = 'kg';

        // Case 1: Pure bodyweight (no added weight)
        $log1 = new LiftLog();
        $log1->user = $userKg;
        $log1->setRelation('exercise', $exercise);
        $set1 = new LiftSet(['weight' => 0, 'unit' => 'lbs']);
        $log1->setRelation('liftSets', collect([$set1]));

        $this->assertEquals('Bodyweight', $strategy->formatWeightDisplay($log1));

        // Case 2: Bodyweight + added weight (stored in lbs, user prefers kg)
        $log2 = new LiftLog();
        $log2->user = $userKg;
        $log2->setRelation('exercise', $exercise);
        $set2 = new LiftSet(['weight' => 45, 'unit' => 'lbs']);
        $log2->setRelation('liftSets', collect([$set2]));

        // 45 lbs * 0.45359237 = 20.41 kg -> rounds to 20.5 kg
        $this->assertEquals('Bodyweight +20.5 kg', $strategy->formatWeightDisplay($log2));
    }

    #[Test]
    public function it_formats_static_hold_exercise_weight_display_correctly_across_units(): void
    {
        $strategy = new StaticHoldExerciseType();

        $exercise = new Exercise();
        $exercise->exercise_type = 'static_hold';

        $userKg = new User();
        $userKg->weight_unit = 'kg';

        // Stored time 45s, added weight 20 lbs, user prefers kg
        $log = new LiftLog();
        $log->user = $userKg;
        $log->setRelation('exercise', $exercise);
        $set = new LiftSet(['weight' => 20, 'unit' => 'lbs', 'time' => 45]);
        $log->setRelation('liftSets', collect([$set]));

        // 20 lbs * 0.45359237 = 9.07 kg -> rounds to 9 kg
        $this->assertEquals('45s hold +9 kg', $strategy->formatWeightDisplay($log));
    }
}
