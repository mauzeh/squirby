<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class MeasurementLogImportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function authenticated_user_can_import_measurement_logs()
    {
        $tsvData = "09/07/2025\t10:00\tBodyweight\t180\tlbs\tMorning weight\n" .
                 "09/07/2025\t12:00\tWaist\t32\tin\tPost lunch";

        $response = $this->post(route('measurement-logs.import-tsv'), [
            'tsv_data' => $tsvData,
        ]);

        $response->assertRedirect(route('measurement-logs.index'));
        $response->assertSessionHas('success', 'TSV data imported successfully!');

        $this->assertDatabaseCount('measurement_logs', 2);
        $this->assertDatabaseHas('measurement_logs', [
            'user_id' => $this->user->id,
            'value' => 180,
            'comments' => 'Morning weight',
        ]);
        $this->assertDatabaseHas('measurement_logs', [
            'user_id' => $this->user->id,
            'value' => 32,
            'comments' => 'Post lunch',
        ]);
    }

    /** @test */
    public function it_returns_error_for_empty_tsv_data()
    {
        $response = $this->post(route('measurement-logs.import-tsv'), [
            'tsv_data' => '',
        ]);

        $response->assertRedirect(route('measurement-logs.index'));
        $response->assertSessionHas('error', 'TSV data cannot be empty.');
    }
}
