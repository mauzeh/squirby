<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BodyLog;
use App\Models\MeasurementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodyLogTsvImportDetailedMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_detailed_success_message_for_small_import()
    {
        $user = User::factory()->create();

        $tsvData = "1/1/2024\t8:00 AM\tWeight\t180\tlbs\tMorning weight\n";
        $tsvData .= "1/1/2024\t8:05 AM\tHeight\t72\tin\tHeight measurement";

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('2 measurement(s) imported', $successMessage);
        $this->assertStringContainsString('Imported:', $successMessage);
        $this->assertStringContainsString('Weight on 01/01/2024 08:00', $successMessage);
        $this->assertStringContainsString('Height on 01/01/2024 08:05', $successMessage);
        $this->assertStringContainsString('180 lbs', $successMessage);
        $this->assertStringContainsString('72 in', $successMessage);
    }

    public function test_simple_success_message_for_large_import()
    {
        $user = User::factory()->create();

        // Create TSV data with 12 measurements (over the 10 limit)
        $tsvData = '';
        for ($i = 1; $i <= 12; $i++) {
            $tsvData .= "1/{$i}/2024\t8:00 AM\tWeight\t" . (180 + $i) . "\tlbs\tDaily weight\n";
        }

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('12 measurement(s) imported', $successMessage);
        $this->assertStringNotContainsString('Imported:', $successMessage);
        $this->assertStringNotContainsString('Weight on', $successMessage);
    }

    public function test_success_message_when_no_data_imported()
    {
        $user = User::factory()->create();
        
        // Create existing measurement
        $measurementType = MeasurementType::create([
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);
        
        BodyLog::create([
            'user_id' => $user->id,
            'measurement_type_id' => $measurementType->id,
            'value' => 180,
            'logged_at' => '2024-01-01 08:00:00',
            'comments' => 'Morning weight'
        ]);

        // Try to import the same data
        $tsvData = "1/1/2024\t8:00 AM\tWeight\t180\tlbs\tMorning weight";

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('No new data was imported - all entries already exist', $successMessage);
    }

    public function test_error_message_for_few_invalid_rows()
    {
        $user = User::factory()->create();

        // TSV data with invalid rows (missing columns)
        $tsvData = "1/1/2024\t8:00 AM\tWeight\n"; // Missing value and unit
        $tsvData .= "1/2/2024\t8:00 AM\n"; // Missing measurement type, value, and unit
        $tsvData .= "invalid date\t8:00 AM\tWeight\t180\tlbs\tComment"; // Invalid date

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('No measurements were imported due to invalid data in rows:', $errorMessage);
        $this->assertStringContainsString('"1/1/2024	8:00 AM	Weight"', $errorMessage);
        $this->assertStringContainsString('"1/2/2024	8:00 AM"', $errorMessage);
    }

    public function test_error_message_for_many_invalid_rows()
    {
        $user = User::factory()->create();

        // Create TSV data with 12 invalid rows (over the 10 limit)
        $tsvData = '';
        for ($i = 1; $i <= 12; $i++) {
            $tsvData .= "1/{$i}/2024\t8:00 AM\tWeight\n"; // Missing value and unit
        }

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('No measurements were imported due to invalid data in 12 rows', $errorMessage);
        $this->assertStringNotContainsString('"1/1/2024', $errorMessage);
    }

    public function test_success_message_with_few_invalid_rows_warning()
    {
        $user = User::factory()->create();

        // Mix of valid and invalid data
        $tsvData = "1/1/2024\t8:00 AM\tWeight\t180\tlbs\tValid entry\n";
        $tsvData .= "1/2/2024\t8:00 AM\tWeight\n"; // Invalid - missing value and unit
        $tsvData .= "1/3/2024\t8:00 AM\tHeight\t72\tin\tAnother valid entry";

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('2 measurement(s) imported', $successMessage);
        $this->assertStringContainsString('Warning:', $successMessage);
        $this->assertStringContainsString('Some rows were invalid:', $successMessage);
        $this->assertStringContainsString('"1/2/2024	8:00 AM	Weight"', $successMessage);
    }

    public function test_success_message_with_many_invalid_rows_warning()
    {
        $user = User::factory()->create();

        // One valid entry and 12 invalid entries
        $tsvData = "1/1/2024\t8:00 AM\tWeight\t180\tlbs\tValid entry\n";
        for ($i = 2; $i <= 13; $i++) {
            $tsvData .= "1/{$i}/2024\t8:00 AM\tWeight\n"; // Invalid - missing value and unit
        }

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 measurement(s) imported', $successMessage);
        $this->assertStringContainsString('Warning:', $successMessage);
        $this->assertStringContainsString('12 rows had invalid data and were skipped', $successMessage);
        $this->assertStringNotContainsString('"1/2/2024', $successMessage);
    }

    public function test_creates_measurement_types_automatically()
    {
        $user = User::factory()->create();

        $tsvData = "1/1/2024\t8:00 AM\tNew Measurement Type\t100\tunits\tTest comment";

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success');
        
        // Verify measurement type was created
        $this->assertDatabaseHas('measurement_types', [
            'name' => 'New Measurement Type',
            'default_unit' => 'units'
        ]);
        
        // Verify body log was created
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $user->id,
            'value' => 100,
            'comments' => 'Test comment'
        ]);
    }

    public function test_empty_tsv_data_shows_error()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('body-logs.import-tsv'), [
            'tsv_data' => ''
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('error', 'TSV data cannot be empty.');
    }
}