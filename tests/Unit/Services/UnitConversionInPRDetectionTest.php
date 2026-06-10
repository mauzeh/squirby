<?php

namespace Tests\Unit\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\ExerciseTypes\RegularExerciseType;
use App\Services\ExerciseTypes\BodyweightExerciseType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UnitConversionInPRDetectionTest extends TestCase
{
    #[Test]
    public function it_detects_1rm_pr_across_different_units(): void
    {
        $strategy = new RegularExerciseType();

        $user = new User();
        $user->weight_unit = 'lbs';

        $exercise = new Exercise();
        $exercise->exercise_type = 'regular';

        // Previous log: 200 lbs
        $prevLog = new LiftLog();
        $prevLog->id = 1;
        $prevLog->user = $user;
        $prevLog->setRelation('exercise', $exercise);
        $prevSet = new LiftSet(['weight' => 200, 'unit' => 'lbs', 'reps' => 1]);
        $prevLog->setRelation('liftSets', collect([$prevSet]));
        $previousLogs = new EloquentCollection([$prevLog]);

        // Scenario 1: Current log 100 kg (approx 220 lbs) -> Should be a 1RM PR!
        $currentLog1 = new LiftLog();
        $currentLog1->id = 2;
        $currentLog1->user = $user;
        $currentLog1->setRelation('exercise', $exercise);
        $currSet1 = new LiftSet(['weight' => 100, 'unit' => 'kg', 'reps' => 1]);
        $currentLog1->setRelation('liftSets', collect([$currSet1]));

        $metrics1 = $strategy->calculateCurrentMetrics($currentLog1);
        $prs1 = $strategy->compareToPrevious($metrics1, $previousLogs, $currentLog1);

        $this->assertNotEmpty($prs1);
        $this->assertEquals('one_rm', $prs1[0]['type']);
        $this->assertEquals(100.0, $prs1[0]['value']); // Stored in current log's unit
        $this->assertEquals(90.5, $prs1[0]['previous_value']); // 200 lbs converted to kg is 90.5 kg

        // Scenario 2: Current log 80 kg (approx 176 lbs) -> Should NOT be a 1RM PR!
        $currentLog2 = new LiftLog();
        $currentLog2->id = 3;
        $currentLog2->user = $user;
        $currentLog2->setRelation('exercise', $exercise);
        $currSet2 = new LiftSet(['weight' => 80, 'unit' => 'kg', 'reps' => 1]);
        $currentLog2->setRelation('liftSets', collect([$currSet2]));

        $metrics2 = $strategy->calculateCurrentMetrics($currentLog2);
        $prs2 = $strategy->compareToPrevious($metrics2, $previousLogs, $currentLog2);

        // Filter out rep-specific PRs since 80kg is the first time 80kg is done,
        // but it's not a 1RM PR compared to 200 lbs (90.5 kg).
        $oneRmPr = collect($prs2)->firstWhere('type', 'one_rm');
        $this->assertNull($oneRmPr);
    }

    #[Test]
    public function it_detects_volume_pr_across_different_units(): void
    {
        $strategy = new RegularExerciseType();

        $user = new User();
        $user->weight_unit = 'lbs';

        $exercise = new Exercise();
        $exercise->exercise_type = 'regular';

        // Previous log: 3 sets of 100 lbs = 300 lbs total volume
        $prevLog = new LiftLog();
        $prevLog->id = 1;
        $prevLog->user = $user;
        $prevLog->setRelation('exercise', $exercise);
        $prevSets = collect([
            new LiftSet(['weight' => 100, 'unit' => 'lbs', 'reps' => 1]),
            new LiftSet(['weight' => 100, 'unit' => 'lbs', 'reps' => 1]),
            new LiftSet(['weight' => 100, 'unit' => 'lbs', 'reps' => 1]),
        ]);
        $prevLog->setRelation('liftSets', $prevSets);
        $previousLogs = new EloquentCollection([$prevLog]);

        // Scenario 1: Current log 3 sets of 50 kg (total volume 150 kg ≈ 330 lbs) -> Should be a Volume PR!
        $currentLog1 = new LiftLog();
        $currentLog1->id = 2;
        $currentLog1->user = $user;
        $currentLog1->setRelation('exercise', $exercise);
        $currSets1 = collect([
            new LiftSet(['weight' => 50, 'unit' => 'kg', 'reps' => 1]),
            new LiftSet(['weight' => 50, 'unit' => 'kg', 'reps' => 1]),
            new LiftSet(['weight' => 50, 'unit' => 'kg', 'reps' => 1]),
        ]);
        $currentLog1->setRelation('liftSets', $currSets1);

        $metrics1 = $strategy->calculateCurrentMetrics($currentLog1);
        $prs1 = $strategy->compareToPrevious($metrics1, $previousLogs, $currentLog1);

        $volumePr = collect($prs1)->firstWhere('type', 'volume');
        $this->assertNotNull($volumePr);
        $this->assertEquals(150.0, $volumePr['value']); // 150 kg
        // Previous volume 300 lbs (3 sets of 100 lbs): each set is 100 lbs -> 45.5 kg -> total 136.5 kg
        $this->assertEquals(136.5, $volumePr['previous_value']);
    }

    #[Test]
    public function it_detects_rep_specific_pr_across_different_units(): void
    {
        $strategy = new RegularExerciseType();

        $user = new User();
        $user->weight_unit = 'lbs';

        $exercise = new Exercise();
        $exercise->exercise_type = 'regular';

        // Previous log: 200 lbs for 5 reps
        $prevLog = new LiftLog();
        $prevLog->id = 1;
        $prevLog->user = $user;
        $prevLog->setRelation('exercise', $exercise);
        $prevSet = new LiftSet(['weight' => 200, 'unit' => 'lbs', 'reps' => 5]);
        $prevLog->setRelation('liftSets', collect([$prevSet]));
        $previousLogs = new EloquentCollection([$prevLog]);

        // Current log: 95 kg for 5 reps (95 kg ≈ 209 lbs > 200 lbs) -> Should be a rep-specific PR!
        $currentLog = new LiftLog();
        $currentLog->id = 2;
        $currentLog->user = $user;
        $currentLog->setRelation('exercise', $exercise);
        $currSet = new LiftSet(['weight' => 95, 'unit' => 'kg', 'reps' => 5]);
        $currentLog->setRelation('liftSets', collect([$currSet]));

        $metrics = $strategy->calculateCurrentMetrics($currentLog);
        $prs = $strategy->compareToPrevious($metrics, $previousLogs, $currentLog);

        $repPr = collect($prs)->firstWhere('type', 'rep_specific');
        $this->assertNotNull($repPr);
        $this->assertEquals(5, $repPr['rep_count']);
        $this->assertEquals(95.0, $repPr['value']);
        // Previous 200 lbs in kg is 90.5 kg
        $this->assertEquals(90.5, $repPr['previous_value']);
    }

    #[Test]
    public function it_detects_hypertrophy_and_density_prs_across_different_units(): void
    {
        $strategy = new RegularExerciseType();

        $user = new User();
        $user->weight_unit = 'lbs';

        $exercise = new Exercise();
        $exercise->exercise_type = 'regular';

        // Previous log: 100 kg (approx 220 lbs) for 5 reps
        $prevLog = new LiftLog();
        $prevLog->id = 1;
        $prevLog->user = $user;
        $prevLog->setRelation('exercise', $exercise);
        $prevSet = new LiftSet(['weight' => 100, 'unit' => 'kg', 'reps' => 5]);
        $prevLog->setRelation('liftSets', collect([$prevSet]));
        $previousLogs = new EloquentCollection([$prevLog]);

        // Current log: 220 lbs for 8 reps. 220 lbs is equivalent to 100 kg (approx 220.4 lbs).
        // Since 8 reps > 5 reps at the same weight, it should trigger a hypertrophy PR!
        $currentLog = new LiftLog();
        $currentLog->id = 2;
        $currentLog->user = $user;
        $currentLog->setRelation('exercise', $exercise);
        $currSet = new LiftSet(['weight' => 220, 'unit' => 'lbs', 'reps' => 8]);
        $currentLog->setRelation('liftSets', collect([$currSet]));

        $metrics = $strategy->calculateCurrentMetrics($currentLog);
        $prs = $strategy->compareToPrevious($metrics, $previousLogs, $currentLog);

        $hypertrophyPr = collect($prs)->firstWhere('type', 'hypertrophy');
        $this->assertNotNull($hypertrophyPr);
        $this->assertEquals(220.0, $hypertrophyPr['weight']);
        $this->assertEquals(8, $hypertrophyPr['value']);
        $this->assertEquals(5, $hypertrophyPr['previous_value']);
    }

    #[Test]
    public function it_detects_bodyweight_volume_prs_across_different_units(): void
    {
        $strategy = new BodyweightExerciseType();

        $user = new User();
        $user->weight_unit = 'lbs';

        $exercise = new Exercise();
        $exercise->exercise_type = 'bodyweight';

        // Previous log: bodyweight + 45 lbs (3 sets of 5 reps = 15 reps. Volume = 3 * 5 * 45 = 675 lbs volume)
        $prevLog = new LiftLog();
        $prevLog->id = 1;
        $prevLog->user = $user;
        $prevLog->setRelation('exercise', $exercise);
        $prevSets = collect([
            new LiftSet(['weight' => 45, 'unit' => 'lbs', 'reps' => 5]),
            new LiftSet(['weight' => 45, 'unit' => 'lbs', 'reps' => 5]),
            new LiftSet(['weight' => 45, 'unit' => 'lbs', 'reps' => 5]),
        ]);
        $prevLog->setRelation('liftSets', $prevSets);
        $previousLogs = new EloquentCollection([$prevLog]);

        // Current log: bodyweight + 25 kg (approx 55 lbs) for 3 sets of 5 reps (volume = 3 * 5 * 25 = 375 kg ≈ 826 lbs volume) -> Volume PR!
        $currentLog = new LiftLog();
        $currentLog->id = 2;
        $currentLog->user = $user;
        $currentLog->setRelation('exercise', $exercise);
        $currSets = collect([
            new LiftSet(['weight' => 25, 'unit' => 'kg', 'reps' => 5]),
            new LiftSet(['weight' => 25, 'unit' => 'kg', 'reps' => 5]),
            new LiftSet(['weight' => 25, 'unit' => 'kg', 'reps' => 5]),
        ]);
        $currentLog->setRelation('liftSets', $currSets);

        $metrics = $strategy->calculateCurrentMetrics($currentLog);
        $prs = $strategy->compareToPrevious($metrics, $previousLogs, $currentLog);

        $volumePr = collect($prs)->firstWhere('type', 'volume');
        $this->assertNotNull($volumePr);
        $this->assertEquals(375.0, $volumePr['value']);
        // Previous 675 lbs volume (3 sets of 5 reps at 45 lbs): each set is 45 lbs -> 20.5 kg -> total 307.5 kg
        $this->assertEquals(307.5, $volumePr['previous_value']);
    }
}
