<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BandService;

class BandServiceTest extends TestCase
{
    protected BandService $bandService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bandService = new BandService();

        config(['bands.colors' => [
            'orange' => ['order' => 1],
            'red'    => ['order' => 2],
            'blue'   => ['order' => 3],
            'green'  => ['order' => 4],
            'black'  => ['order' => 5],
        ]]);
        config(['bands.max_reps_before_band_change' => 15]);
        config(['bands.default_reps_on_band_change' => 8]);
    }

    public function test_get_bands_returns_configured_bands()
    {
        $bands = $this->bandService->getBands();
        $this->assertIsArray($bands);
        $this->assertCount(5, $bands);
        $this->assertArrayHasKey('orange', $bands);
        $this->assertArrayHasKey('black', $bands);
        $this->assertEquals(1, $bands['orange']['order']);
        $this->assertEquals(2, $bands['red']['order']);
        $this->assertEquals(5, $bands['black']['order']);
        $this->assertArrayNotHasKey('resistance', $bands['red']);
    }

    public function test_get_next_harder_band_returns_correct_band()
    {
        $this->assertEquals('red', $this->bandService->getNextHarderBand('orange', 'resistance'));
        $this->assertEquals('blue', $this->bandService->getNextHarderBand('red', 'resistance'));
        $this->assertEquals('green', $this->bandService->getNextHarderBand('blue', 'resistance'));
        $this->assertEquals('black', $this->bandService->getNextHarderBand('green', 'resistance'));
        $this->assertNull($this->bandService->getNextHarderBand('black', 'resistance')); // Hardest band
        $this->assertNull($this->bandService->getNextHarderBand('nonexistent', 'resistance'));
    }

    public function test_get_previous_easier_band_returns_correct_band()
    {
        $this->assertEquals('red', $this->bandService->getPreviousEasierBand('blue', 'resistance'));
        $this->assertEquals('orange', $this->bandService->getPreviousEasierBand('red', 'resistance'));
        $this->assertNull($this->bandService->getPreviousEasierBand('orange', 'resistance')); // Easiest band
        $this->assertNull($this->bandService->getPreviousEasierBand('nonexistent', 'resistance'));
    }

    public function test_get_next_harder_band_with_assistance_type()
    {
        // For assistance, harder means less assistance, so previous band in order
        $this->assertEquals('orange', $this->bandService->getNextHarderBand('red', 'assistance'));
        $this->assertEquals('red', $this->bandService->getNextHarderBand('blue', 'assistance'));
        $this->assertNull($this->bandService->getNextHarderBand('orange', 'assistance')); // Hardest assistance band
    }

    public function test_get_previous_easier_band_with_assistance_type()
    {
        // For assistance, easier means more assistance, so next band in order
        $this->assertEquals('red', $this->bandService->getPreviousEasierBand('orange', 'assistance'));
        $this->assertEquals('black', $this->bandService->getPreviousEasierBand('green', 'assistance'));
        $this->assertNull($this->bandService->getPreviousEasierBand('black', 'assistance')); // Easiest assistance band
    }

    public function test_case_insensitive_band_lookups()
    {
        $this->assertEquals('red', $this->bandService->getNextHarderBand('Orange', 'resistance'));
        $this->assertEquals('orange', $this->bandService->getPreviousEasierBand('RED', 'resistance'));
    }
}
