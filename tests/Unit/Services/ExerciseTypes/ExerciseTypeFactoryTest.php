<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Services\ExerciseTypes\RegularExerciseType;
use App\Services\ExerciseTypes\BandedExerciseType;
use App\Services\ExerciseTypes\BandedResistanceExerciseType;
use App\Services\ExerciseTypes\BandedAssistanceExerciseType;
use App\Services\ExerciseTypes\BodyweightExerciseType;
use App\Services\ExerciseTypes\ExerciseTypeInterface;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

class ExerciseTypeFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache before each test
        ExerciseTypeFactory::clearCache();
    }

    /** @test */
    public function it_creates_regular_exercise_type_for_standard_exercise()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(RegularExerciseType::class, $strategy);
        $this->assertEquals('regular', $strategy->getTypeName());
    }

    /** @test */
    public function it_creates_banded_resistance_exercise_type_for_resistance_band_exercise()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'banded_resistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BandedResistanceExerciseType::class, $strategy);
        $this->assertEquals('banded_resistance', $strategy->getTypeName());
    }

    /** @test */
    public function it_creates_banded_assistance_exercise_type_for_assistance_band_exercise()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'banded_assistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BandedAssistanceExerciseType::class, $strategy);
        $this->assertEquals('banded_assistance', $strategy->getTypeName());
    }

    /** @test */
    public function it_creates_bodyweight_exercise_type_for_bodyweight_exercise()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'bodyweight',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BodyweightExerciseType::class, $strategy);
        $this->assertEquals('bodyweight', $strategy->getTypeName());
    }

    /** @test */
    public function it_uses_exercise_type_field_directly()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'banded_resistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BandedResistanceExerciseType::class, $strategy);
        $this->assertEquals('banded_resistance', $strategy->getTypeName());
    }

    /** @test */
    public function it_caches_strategy_instances_when_caching_enabled()
    {
        config(['exercise_types.factory.cache_strategies' => true]);
        
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $strategy1 = ExerciseTypeFactory::create($exercise);
        $strategy2 = ExerciseTypeFactory::create($exercise);

        $this->assertSame($strategy1, $strategy2);
    }

    /** @test */
    public function it_does_not_cache_strategy_instances_when_caching_disabled()
    {
        config(['exercise_types.factory.cache_strategies' => false]);
        
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $strategy1 = ExerciseTypeFactory::create($exercise);
        $strategy2 = ExerciseTypeFactory::create($exercise);

        $this->assertNotSame($strategy1, $strategy2);
        $this->assertEquals(get_class($strategy1), get_class($strategy2));
    }

    /** @test */
    public function it_creates_different_cache_keys_for_different_exercises()
    {
        $exercise1 = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $exercise2 = Exercise::factory()->create([
            'exercise_type' => 'bodyweight',
        ]);

        $strategy1 = ExerciseTypeFactory::create($exercise1);
        $strategy2 = ExerciseTypeFactory::create($exercise2);

        $this->assertNotSame($strategy1, $strategy2);
        $this->assertInstanceOf(RegularExerciseType::class, $strategy1);
        $this->assertInstanceOf(BodyweightExerciseType::class, $strategy2);
    }

    /** @test */
    public function it_clears_cache_successfully()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $strategy1 = ExerciseTypeFactory::create($exercise);
        ExerciseTypeFactory::clearCache();
        $strategy2 = ExerciseTypeFactory::create($exercise);

        $this->assertNotSame($strategy1, $strategy2);
        $this->assertEquals(get_class($strategy1), get_class($strategy2));
    }

    /** @test */
    public function it_returns_available_types_from_configuration()
    {
        $availableTypes = ExerciseTypeFactory::getAvailableTypes();

        $this->assertIsArray($availableTypes);
        $this->assertContains('regular', $availableTypes);
        $this->assertContains('banded_resistance', $availableTypes);
        $this->assertContains('banded_assistance', $availableTypes);
        $this->assertContains('bodyweight', $availableTypes);
    }

    /** @test */
    public function it_checks_if_type_is_supported()
    {
        $this->assertTrue(ExerciseTypeFactory::isTypeSupported('regular'));
        $this->assertTrue(ExerciseTypeFactory::isTypeSupported('banded_resistance'));
        $this->assertTrue(ExerciseTypeFactory::isTypeSupported('banded_assistance'));
        $this->assertTrue(ExerciseTypeFactory::isTypeSupported('bodyweight'));
        $this->assertFalse(ExerciseTypeFactory::isTypeSupported('nonexistent'));
    }

    /** @test */
    public function it_falls_back_to_regular_type_when_configuration_missing()
    {
        // Temporarily modify config to simulate missing configuration
        $originalConfig = config('exercise_types.types.regular');
        config(['exercise_types.types.regular' => null]);

        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        try {
            $strategy = ExerciseTypeFactory::create($exercise);
            
            // Should fall back to RegularExerciseType directly
            $this->assertInstanceOf(\App\Services\ExerciseTypes\RegularExerciseType::class, $strategy);
            $this->assertEquals('regular', $strategy->getTypeName());
        } finally {
            // Restore original config
            config(['exercise_types.types.regular' => $originalConfig]);
        }
    }

    /** @test */
    public function it_falls_back_to_regular_type_when_class_does_not_exist()
    {
        // Temporarily modify config to use non-existent class
        $originalConfig = config('exercise_types.types.regular.class');
        config(['exercise_types.types.regular.class' => 'NonExistentClass']);

        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        try {
            $strategy = ExerciseTypeFactory::create($exercise);
            
            // Should fall back to RegularExerciseType directly
            $this->assertInstanceOf(\App\Services\ExerciseTypes\RegularExerciseType::class, $strategy);
            $this->assertEquals('regular', $strategy->getTypeName());
        } finally {
            // Restore original config
            config(['exercise_types.types.regular.class' => $originalConfig]);
        }
    }

    /** @test */
    public function it_validates_that_class_implements_interface()
    {
        // Temporarily modify config to use class that doesn't implement interface
        $originalConfig = config('exercise_types.types.regular.class');
        config(['exercise_types.types.regular.class' => \stdClass::class]);

        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        try {
            $strategy = ExerciseTypeFactory::create($exercise);
            
            // Should fall back to RegularExerciseType directly
            $this->assertInstanceOf(\App\Services\ExerciseTypes\RegularExerciseType::class, $strategy);
            $this->assertEquals('regular', $strategy->getTypeName());
        } finally {
            // Restore original config
            config(['exercise_types.types.regular.class' => $originalConfig]);
        }
    }

    /** @test */
    public function it_uses_fallback_type_when_strategy_creation_fails_and_fallback_available()
    {
        // Set up fallback configuration
        config(['exercise_types.factory.fallback_type' => 'regular']);
        
        // Temporarily break the banded_resistance configuration
        $originalConfig = config('exercise_types.types.banded_resistance.class');
        config(['exercise_types.types.banded_resistance.class' => 'NonExistentClass']);

        $exercise = Exercise::factory()->create([
            'exercise_type' => 'banded_resistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(RegularExerciseType::class, $strategy);

        // Restore original config
        config(['exercise_types.types.banded_resistance.class' => $originalConfig]);
    }

    /** @test */
    public function it_generates_unique_cache_keys_for_different_exercise_properties()
    {
        $exercise1 = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $exercise2 = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $exercise3 = Exercise::factory()->create([
            'exercise_type' => 'bodyweight',
        ]);

        $strategy1 = ExerciseTypeFactory::create($exercise1);
        $strategy2 = ExerciseTypeFactory::create($exercise2);
        $strategy3 = ExerciseTypeFactory::create($exercise3);

        // Different IDs should create different cache entries
        $this->assertNotSame($strategy1, $strategy2);
        
        // Different properties should create different cache entries
        $this->assertNotSame($strategy1, $strategy3);
        
        // But same exercise should return same cached instance
        $strategy1Again = ExerciseTypeFactory::create($exercise1);
        $this->assertSame($strategy1, $strategy1Again);
    }

    /** @test */
    public function it_handles_exercise_type_in_cache_key_generation()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'regular',
        ]);

        $strategy1 = ExerciseTypeFactory::create($exercise);
        $strategy2 = ExerciseTypeFactory::create($exercise);

        $this->assertSame($strategy1, $strategy2);
        $this->assertInstanceOf(RegularExerciseType::class, $strategy1);
    }

    /** @test */
    public function it_handles_invalid_exercise_type_gracefully()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'invalid_type',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        // Should fall back to RegularExerciseType
        $this->assertInstanceOf(RegularExerciseType::class, $strategy);
        $this->assertEquals('regular', $strategy->getTypeName());
    }

    /** @test */
    public function it_handles_missing_exercise_type_gracefully()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => null,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        // Should fall back to RegularExerciseType
        $this->assertInstanceOf(RegularExerciseType::class, $strategy);
        $this->assertEquals('regular', $strategy->getTypeName());
    }

    /** @test */
    public function it_handles_empty_exercise_type_gracefully()
    {
        $exercise = Exercise::factory()->create([
            'exercise_type' => '',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        // Should fall back to RegularExerciseType
        $this->assertInstanceOf(RegularExerciseType::class, $strategy);
        $this->assertEquals('regular', $strategy->getTypeName());
    }

    /** @test */
    public function it_creates_correct_strategy_for_all_exercise_type_values()
    {
        $testCases = [
            'regular' => RegularExerciseType::class,
            'bodyweight' => BodyweightExerciseType::class,
            'banded_resistance' => BandedResistanceExerciseType::class,
            'banded_assistance' => BandedAssistanceExerciseType::class,
        ];

        foreach ($testCases as $exerciseType => $expectedClass) {
            $exercise = Exercise::factory()->create([
                'exercise_type' => $exerciseType,
            ]);

            $strategy = ExerciseTypeFactory::create($exercise);

            $this->assertInstanceOf($expectedClass, $strategy, "Failed for exercise_type: {$exerciseType}");
            $this->assertEquals($exerciseType, $strategy->getTypeName(), "Type name mismatch for exercise_type: {$exerciseType}");
        }
    }

    /** @test */
    public function it_caches_strategies_correctly_with_exercise_type_based_keys()
    {
        config(['exercise_types.factory.cache_strategies' => true]);

        // Create exercises with same type but different IDs
        $exercise1 = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $exercise2 = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $exercise3 = Exercise::factory()->create(['exercise_type' => 'banded_assistance']);

        $strategy1 = ExerciseTypeFactory::create($exercise1);
        $strategy2 = ExerciseTypeFactory::create($exercise2);
        $strategy3 = ExerciseTypeFactory::create($exercise3);

        // Different exercises should have different cached strategies
        $this->assertNotSame($strategy1, $strategy2);
        $this->assertNotSame($strategy1, $strategy3);
        $this->assertNotSame($strategy2, $strategy3);

        // Same exercise should return same cached strategy
        $strategy1Again = ExerciseTypeFactory::create($exercise1);
        $this->assertSame($strategy1, $strategy1Again);

        // Verify correct types
        $this->assertInstanceOf(BandedResistanceExerciseType::class, $strategy1);
        $this->assertInstanceOf(BandedResistanceExerciseType::class, $strategy2);
        $this->assertInstanceOf(BandedAssistanceExerciseType::class, $strategy3);
    }

    /** @test */
    public function it_creates_safe_strategy_without_throwing_exceptions()
    {
        // Test with invalid exercise type
        $exercise = Exercise::factory()->create([
            'exercise_type' => 'completely_invalid_type',
        ]);

        $strategy = ExerciseTypeFactory::createSafe($exercise);

        // Should always return a valid strategy, never throw
        $this->assertInstanceOf(ExerciseTypeInterface::class, $strategy);
        $this->assertInstanceOf(RegularExerciseType::class, $strategy);
    }

    /** @test */
    public function it_validates_exercise_data_with_appropriate_strategy()
    {
        $regularExercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        $bodyweightExercise = Exercise::factory()->create(['exercise_type' => 'bodyweight']);
        $bandedExercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);

        $regularRules = ExerciseTypeFactory::validateExerciseData($regularExercise, []);
        $bodyweightRules = ExerciseTypeFactory::validateExerciseData($bodyweightExercise, []);
        $bandedRules = ExerciseTypeFactory::validateExerciseData($bandedExercise, []);

        // All should return arrays of validation rules
        $this->assertIsArray($regularRules);
        $this->assertIsArray($bodyweightRules);
        $this->assertIsArray($bandedRules);

        // Should contain common rules
        $this->assertArrayHasKey('reps', $regularRules);
        $this->assertArrayHasKey('reps', $bodyweightRules);
        $this->assertArrayHasKey('reps', $bandedRules);
    }

    /** @test */
    public function it_falls_back_to_legacy_logic_when_exercise_type_is_missing()
    {
        // Create exercise using legacy fields (simulating old data)
        $exercise = Exercise::factory()->make([
            'exercise_type' => null,
            'exercise_type' => 'bodyweight'
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BodyweightExerciseType::class, $strategy);
        $this->assertEquals('bodyweight', $strategy->getTypeName());
    }

    /** @test */
    public function it_falls_back_to_legacy_logic_for_banded_exercises_when_exercise_type_is_missing()
    {
        // Clear cache to ensure fresh strategy creation
        ExerciseTypeFactory::clearCache();
        
        // Test resistance band fallback - create with different ID to avoid cache collision
        $resistanceExercise = Exercise::factory()->make([
            'id' => 999,
            'exercise_type' => null,
            'exercise_type' => 'banded_resistance'
        ]);

        $resistanceStrategy = ExerciseTypeFactory::create($resistanceExercise);
        $this->assertInstanceOf(BandedResistanceExerciseType::class, $resistanceStrategy);
        $this->assertEquals('banded_resistance', $resistanceStrategy->getTypeName());

        // Clear cache again to ensure no interference
        ExerciseTypeFactory::clearCache();

        // Test assistance band fallback - create with different ID
        $assistanceExercise = Exercise::factory()->make([
            'id' => 998,
            'exercise_type' => null,
            'exercise_type' => 'banded_assistance'
        ]);

        $assistanceStrategy = ExerciseTypeFactory::create($assistanceExercise);
        $this->assertInstanceOf(BandedAssistanceExerciseType::class, $assistanceStrategy);
        $this->assertEquals('banded_assistance', $assistanceStrategy->getTypeName());
    }
}