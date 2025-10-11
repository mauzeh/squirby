<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MeasurementType;
use App\Models\BodyLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BodyLogTsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_body_log_import_shows_success_message()
    {
        $tsvData = "09/06/2025\t11:00\tWeight\t180\tlbs\tMorning weight";

        $response = $this->actingAs($this->user)
            ->post(route('body-logs.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('1 measurement(s) imported', $successMessage);

        // Verify the body log was actually created
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'value' => 180,
            'comments' => 'Morning weight',
        ]);
    }

    public function test_body_log_import_shows_success_message_when_no_new_data()
    {
        // Create existing measurement type and body log
        $measurementType = MeasurementType::firstOrCreate([
            'name' => 'Weight',
            'default_unit' => 'lbs',
        ]);

        BodyLog::create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $measurementType->id,
            'value' => 180,
            'comments' => 'Morning weight',
            'logged_at' => \Carbon\Carbon::createFromFormat('m/d/Y H:i', '09/06/2025 11:00'),
        ]);

        // Try to import the same data
        $tsvData = "09/06/2025\t11:00\tWeight\t180\tlbs\tMorning weight";

        $response = $this->actingAs($this->user)
            ->post(route('body-logs.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('No new data was imported - all entries already exist with the same data.', $successMessage);
    }
}