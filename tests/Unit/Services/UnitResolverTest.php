<?php

namespace Tests\Unit\Services;

use App\Services\UnitResolver;
use App\Models\User;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UnitResolverTest extends TestCase
{
    private UnitResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UnitResolver();
    }

    #[Test]
    public function it_returns_identity_for_same_unit(): void
    {
        $this->assertEquals(100.0, $this->resolver->convert(100.0, 'lbs', 'lbs'));
        $this->assertEquals(85.5, $this->resolver->convert(85.5, 'kg', 'kg'));
        $this->assertEquals(100.0, $this->resolver->convert(100.0, 'LBS', 'lbs'));
        $this->assertEquals(85.5, $this->resolver->convert(85.5, 'kg', 'KG'));
    }

    #[Test]
    public function it_converts_lbs_to_kg_with_half_kilogram_rounding(): void
    {
        // 135 lbs * 0.45359237 = 61.2349 -> nearest 0.5 kg is 61.0
        // wait! Let's calculate:
        // 135 * 0.45359237 = 61.23497. 61.23497 * 2 = 122.4699. round(122.4699) = 122. 122 / 2 = 61.0.
        // wait, does 135 lbs convert to 61.0 or 61.5? 61.23 is closer to 61.0 than 61.5. So it rounds to 61.0.
        // Wait! The plan says: "Example: 135 lbs -> 61.5 kg -> 136 lbs."
        // Oh! Wait, why would 135 lbs convert to 61.5 kg in the example?
        // Let's re-verify the math:
        // 135 lbs * 0.45359237 = 61.23497.
        // Wait, why did the plan say "135 lbs -> 61.5 kg -> 136 lbs"?
        // Let's check: 61.5 kg * 2.20462262 = 135.584 -> round is 136.
        // 61.0 kg * 2.20462262 = 134.48 -> round is 134.
        // Wait, what about 136 lbs?
        // 136 lbs * 0.45359237 = 61.68856 -> round to 0.5 is 61.5.
        // 61.5 kg * 2.20462262 = 135.58 -> round is 136.
        // Ah! 136 lbs -> 61.5 kg -> 136 lbs is a clean round trip.
        // But 135 lbs -> 61.0 kg -> 134 lbs is also drift! 135 lbs drift to 134 lbs.
        // Let's write assertions for both.
        
        // 100 lbs * 0.45359237 = 45.359 -> round to 0.5 is 45.5
        $this->assertEquals(45.5, $this->resolver->convert(100.0, 'lbs', 'kg'));

        // 200 lbs * 0.45359237 = 90.718 -> round to 0.5 is 90.5
        $this->assertEquals(90.5, $this->resolver->convert(200.0, 'lbs', 'kg'));

        // 135 lbs * 0.45359237 = 61.235 -> round to 0.5 is 61.0
        $this->assertEquals(61.0, $this->resolver->convert(135.0, 'lbs', 'kg'));
    }

    #[Test]
    public function it_converts_kg_to_lbs_with_whole_pound_rounding(): void
    {
        // 61.5 kg * 2.20462262 = 135.58 -> round to 1 is 136.0
        $this->assertEquals(136.0, $this->resolver->convert(61.5, 'kg', 'lbs'));

        // 100 kg * 2.20462262 = 220.46 -> round to 1 is 220.0
        $this->assertEquals(220.0, $this->resolver->convert(100.0, 'kg', 'lbs'));
    }

    #[Test]
    public function it_documents_round_trip_drift(): void
    {
        // Start with 135 lbs
        $lbsOriginal = 135.0;
        
        // Convert to kg -> 61.0 kg
        $kg = $this->resolver->convert($lbsOriginal, 'lbs', 'kg');
        $this->assertEquals(61.0, $kg);

        // Convert back to lbs -> 134.0 lbs (drift!)
        $lbsReflected = $this->resolver->convert($kg, 'kg', 'lbs');
        $this->assertEquals(134.0, $lbsReflected);
        $this->assertNotEquals($lbsOriginal, $lbsReflected);
    }

    #[Test]
    public function it_formats_lbs_and_kg_correctly(): void
    {
        // lbs has 0 decimals for whole numbers, but shows decimals for fractional values
        $this->assertEquals('100 lbs', $this->resolver->format(100.0, 'lbs'));
        $this->assertEquals('100.4 lbs', $this->resolver->format(100.4, 'lbs'));
        $this->assertEquals('100.5 lbs', $this->resolver->format(100.5, 'lbs'));

        // kg has 0 decimals for whole numbers, 1 decimal for .5
        $this->assertEquals('60 kg', $this->resolver->format(60.0, 'kg'));
        $this->assertEquals('60.5 kg', $this->resolver->format(60.5, 'kg'));
        // rounded during formatting
        $this->assertEquals('60.5 kg', $this->resolver->format(60.3, 'kg'));
        $this->assertEquals('60 kg', $this->resolver->format(60.2, 'kg'));
    }

    #[Test]
    public function it_returns_correct_increments_and_steps(): void
    {
        // For default or lbs
        $this->assertEquals(5.0, $this->resolver->getWeightIncrement());
        $this->assertEquals(1.0, $this->resolver->getWeightStep());

        // For kg preference (injected mock user or manually built user)
        $user = new User();
        $user->weight_unit = 'kg';

        $this->assertEquals(2.5, $this->resolver->getWeightIncrement($user));
        $this->assertEquals(0.5, $this->resolver->getWeightStep($user));
    }

    #[Test]
    public function it_passes_through_unknown_units(): void
    {
        $this->assertEquals(50.0, $this->resolver->convert(50.0, 'unknown', 'lbs'));
        $this->assertEquals(50.0, $this->resolver->convert(50.0, 'lbs', 'unknown'));
        $this->assertEquals('50 unknown', $this->resolver->format(50.0, 'unknown'));
    }
}
