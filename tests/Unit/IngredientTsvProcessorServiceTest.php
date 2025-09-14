<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\IngredientTsvProcessorService;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientTsvProcessorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IngredientTsvProcessorService();
    }

    /** @test */
    public function it_processes_tsv_data_correctly()
    {
        $expectedHeader = ['Col1', 'Col2'];
        $tsvData = "Col1\tCol2\nValue1\tValue2\nValue3\tValue4";

        $processedRows = [];
        $rowProcessor = function ($rowData) use (&$processedRows) {
            $processedRows[] = $rowData;
        };

        $result = $this->service->processTsv($tsvData, $expectedHeader, $rowProcessor);

        $this->assertEquals(2, $result['processedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals([['Col1' => 'Value1', 'Col2' => 'Value2'], ['Col1' => 'Value3', 'Col2' => 'Value4']], $processedRows);
    }

    /** @test */
    public function it_returns_error_for_invalid_header()
    {
        $expectedHeader = ['Col1', 'Col2'];
        $tsvData = "WrongCol1\tCol2\nValue1\tValue2";

        $processedRows = [];
        $rowProcessor = function ($rowData) use (&$processedRows) {
            $processedRows[] = $rowData;
        };

        $result = $this->service->processTsv($tsvData, $expectedHeader, $rowProcessor);

        $this->assertEquals(0, $result['processedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Invalid TSV header', $result['errors'][0]);
        $this->assertEmpty($processedRows);
    }

    /** @test */
    public function it_handles_invalid_row_column_count()
    {
        $expectedHeader = ['Col1', 'Col2'];
        $tsvData = "Col1\tCol2\nValue1\tValue2\tExtra\nValue3";

        $processedRows = [];
        $rowProcessor = function ($rowData) use (&$processedRows) {
            $processedRows[] = $rowData;
        };

        $result = $this->service->processTsv($tsvData, $expectedHeader, $rowProcessor);

        $this->assertEquals(0, $result['processedCount']);
        $this->assertCount(2, $result['invalidRows']);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($processedRows);
    }

    /** @test */
    public function it_handles_exceptions_in_row_processor()
    {
        $expectedHeader = ['Col1', 'Col2'];
        $tsvData = "Col1\tCol2\nValue1\tValue2";

        $processedRows = [];
        $rowProcessor = function ($rowData) use (&$processedRows) {
            throw new \Exception('Test Exception');
        };

        $result = $this->service->processTsv($tsvData, $expectedHeader, $rowProcessor);

        $this->assertEquals(0, $result['processedCount']);
        $this->assertCount(1, $result['invalidRows']);
        $this->assertStringContainsString('Test Exception', $result['invalidRows'][0]);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($processedRows);
    }

    /** @test */
    public function it_gets_unit_from_abbreviation_correctly()
    {
        $unitG = Unit::factory()->create(['name' => 'gram', 'abbreviation' => 'g']);
        $unitPc = Unit::factory()->create(['name' => 'piece', 'abbreviation' => 'pc']);

        $this->assertEquals($unitG->id, $this->service->getUnitFromAbbreviation('g')->id);
        $this->assertEquals($unitG->id, $this->service->getUnitFromAbbreviation('gram')->id);
        $this->assertEquals($unitPc->id, $this->service->getUnitFromAbbreviation('apple (S)')->id);
        $this->assertNull($this->service->getUnitFromAbbreviation('nonexistent'));
    }
}
