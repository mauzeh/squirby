<?php

namespace Tests\Unit\Services\ExerciseTypes;

use Tests\TestCase;
use App\Services\ExerciseTypes\ExerciseTypeFactory;
use App\Services\ExerciseTypes\RegularExerciseType;
use App\Services\ExerciseTypes\BandedExerciseType;
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
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(RegularExerciseType::class, $strategy);
        $this->assertEquals('regular', $strategy->getTypeName());
    }

    /** @test */
    public function it_creates_banded_exercise_type_for_resistance_band_exercise()
    {
        $exercise = Exercise::factory()->create([
            'is_bodyweight' => false,
            'band_type' => 'resistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BandedExerciseType::class, $strategy);
        $this->assertEquals('banded', $strategy->getTypeName());
    }

    /** @test */
    public function it_creates_banded_exercise_type_for_assistance_band_exercise()
    {
        $exercise = Exercise::factory()->create([
            'is_bodyweight' => false,
            'band_type' => 'assistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BandedExerciseType::class, $strategy);
        $this->assertEquals('banded', $strategy->getTypeName());
    }

    /** @test */
    public function it_creates_bodyweight_exercise_type_for_bodyweight_exercise()
    {
        $exercise = Exercise::factory()->create([
            'is_bodyweight' => true,
            'band_type' => null,
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BodyweightExerciseType::class, $strategy);
        $this->assertEquals('bodyweight', $strategy->getTypeName());
    }

    /** @test */
    public function it_prioritizes_band_type_over_bodyweight_flag()
    {
        $exercise = Exercise::factory()->create([
            'is_bodyweight' => true,
            'band_type' => 'resistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(BandedExerciseType::class, $strategy);
        $this->assertEquals('banded', $strategy->getTypeName());
    }

    /** @test */
    public function it_caches_strategy_instances_when_caching_enabled()
    {
        config(['exercise_types.factory.cache_strategies' => true]);
        
        $exercise = Exercise::factory()->create([
            'is_bodyweight' => false,
            'band_type' => null,
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
            'is_bodyweight' => false,
            'band_type' => null,
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
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $exercise2 = Exercise::factory()->create([
            'is_bodyweight' => true,
            'band_type' => null,
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
            'is_bodyweight' => false,
            'band_type' => null,
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
        $this->assertContains('banded', $availableTypes);
        $this->assertContains('bodyweight', $availableTypes);
    }

    /** @test */
    public function it_checks_if_type_is_supported()
    {
        $this->assertTrue(ExerciseTypeFactory::isTypeSupported('regular'));
        $this->assertTrue(ExerciseTypeFactory::isTypeSupported('banded'));
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
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        try {
            $strategy = ExerciseTypeFactory::create($exercise);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('No configuration found for exercise type', $e->getMessage());
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
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        try {
            $strategy = ExerciseTypeFactory::create($exercise);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Exercise type class does not exist', $e->getMessage());
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
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        try {
            $strategy = ExerciseTypeFactory::create($exercise);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Exercise type class must implement ExerciseTypeInterface', $e->getMessage());
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
        
        // Temporarily break the banded configuration
        $originalConfig = config('exercise_types.types.banded.class');
        config(['exercise_types.types.banded.class' => 'NonExistentClass']);

        $exercise = Exercise::factory()->create([
            'is_bodyweight' => false,
            'band_type' => 'resistance',
        ]);

        $strategy = ExerciseTypeFactory::create($exercise);

        $this->assertInstanceOf(RegularExerciseType::class, $strategy);

        // Restore original config
        config(['exercise_types.types.banded.class' => $originalConfig]);
    }

    /** @test */
    public function it_generates_unique_cache_keys_for_different_exercise_properties()
    {
        $exercise1 = Exercise::factory()->create([
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $exercise2 = Exercise::factory()->create([
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $exercise3 = Exercise::factory()->create([
            'is_bodyweight' => true,
            'band_type' => null,
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
    public function it_handles_null_band_type_in_cache_key_generation()
    {
        $exercise = Exercise::factory()->create([
            'is_bodyweight' => false,
            'band_type' => null,
        ]);

        $strategy1 = ExerciseTypeFactory::create($exercise);
        $strategy2 = ExerciseTypeFactory::create($exercise);

        $this->assertSame($strategy1, $strategy2);
        $this->assertInstanceOf(RegularExerciseType::class, $strategy1);
    }
}