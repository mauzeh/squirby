<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BandService;
use Illuminate\Support\Facades\Config;

class BandServiceTest extends TestCase
{
    protected BandService $bandService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bandService = new BandService();

        // Mock the config helper for testing purposes
        config(['bands.colors' => [
            'red' => ['resistance' => 10, 'order' => 1],
            'blue' => ['resistance' => 20, 'order' => 2],
            'green' => ['resistance' => 30, 'order' => 3],
        ]]);
        config(['bands.max_reps_before_band_change' => 15]);
        config(['bands.default_reps_on_band_change' => 8]);
    }

    public function test_get_bands_returns_configured_bands()
    {
        $bands = $this->bandService->getBands();
        $this->assertIsArray($bands);
        $this->assertArrayHasKey('red', $bands);
        $this->assertEquals(10, $bands['red']['resistance']);
    }

    public function test_get_band_resistance_returns_correct_value()
    {
        $this->assertEquals(10, $this->bandService->getBandResistance('red'));
        $this->assertEquals(30, $this->bandService->getBandResistance('green'));
        $this->assertNull($this->bandService->getBandResistance('nonexistent'));
    }

    public function test_get_next_harder_band_returns_correct_band()
    {
        $this->assertEquals('blue', $this->bandService->getNextHarderBand('red', 'resistance'));
        $this->assertEquals('green', $this->bandService->getNextHarderBand('blue', 'resistance'));
        $this->assertNull($this->bandService->getNextHarderBand('green', 'resistance')); // Hardest band
        $this->assertNull($this->bandService->getNextHarderBand('nonexistent', 'resistance'));
    }

    public function test_get_previous_easier_band_returns_correct_band()
    {
        $this->assertEquals('blue', $this->bandService->getPreviousEasierBand('green', 'resistance'));
        $this->assertEquals('red', $this->bandService->getPreviousEasierBand('blue', 'resistance'));
        $this->assertNull($this->bandService->getPreviousEasierBand('red', 'resistance')); // Easiest band
        $this->assertNull($this->bandService->getPreviousEasierBand('nonexistent', 'resistance'));
    }

    public function test_get_next_harder_band_with_assistance_type()
    {
        // For assistance, harder means less assistance, so previous band in order
        $this->assertEquals('red', $this->bandService->getNextHarderBand('blue', 'assistance'));
        $this->assertEquals('blue', $this->bandService->getNextHarderBand('green', 'assistance'));
        $this->assertNull($this->bandService->getNextHarderBand('red', 'assistance')); // Hardest assistance band
    }

    public function test_get_previous_easier_band_with_assistance_type()
    {
        // For assistance, easier means more assistance, so next band in order
        $this->assertEquals('blue', $this->bandService->getPreviousEasierBand('red', 'assistance'));
        $this->assertEquals('green', $this->bandService->getPreviousEasierBand('blue', 'assistance'));
        $this->assertNull($this->bandService->getPreviousEasierBand('green', 'assistance')); // Easiest assistance band
    }
}
