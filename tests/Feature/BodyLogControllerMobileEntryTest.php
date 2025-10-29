<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\MeasurementType;
use App\Models\BodyLog;
use Carbon\Carbon;

class BodyLogControllerMobileEntryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $measurementType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->measurementType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_creates_new_body_log_when_none_exists_for_date()
    {
        $today = Carbon::today();

        $response = $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Morning weigh-in',
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success', 'Measurement logged successfully.');

        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'comments' => 'Morning weigh-in'
        ]);

        // Should only have one entry
        $this->assertEquals(1, BodyLog::where('user_id', $this->user->id)
            ->where('measurement_type_id', $this->measurementType->id)
            ->whereDate('logged_at', $today)
            ->count());
    }

    /** @test */
    public function it_updates_existing_body_log_when_one_exists_for_same_date_and_measurement_type()
    {
        $today = Carbon::today();

        // Create existing log
        $existingLog = BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 180.0,
            'logged_at' => $today->setTime(7, 0),
            'comments' => 'First measurement'
        ]);

        $response = $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Updated measurement',
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success', 'Measurement updated successfully.');

        // Should still only have one entry (updated, not created new)
        $this->assertEquals(1, BodyLog::where('user_id', $this->user->id)
            ->where('measurement_type_id', $this->measurementType->id)
            ->whereDate('logged_at', $today)
            ->count());

        // Check that the existing log was updated
        $existingLog->refresh();
        $this->assertEquals(185.5, $existingLog->value);
        $this->assertEquals('Updated measurement', $existingLog->comments);
        $this->assertEquals('08:00', $existingLog->logged_at->format('H:i'));
    }

    /** @test */
    public function it_allows_multiple_measurements_for_different_measurement_types_on_same_date()
    {
        $today = Carbon::today();
        
        $bodyFatType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Body Fat',
            'default_unit' => '%'
        ]);

        // Log weight
        $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Weight measurement',
        ]);

        // Log body fat
        $this->post(route('body-logs.store'), [
            'measurement_type_id' => $bodyFatType->id,
            'value' => 15.2,
            'date' => $today->toDateString(),
            'logged_at' => '08:05',
            'comments' => 'Body fat measurement',
        ]);

        // Should have two separate entries
        $this->assertEquals(2, BodyLog::where('user_id', $this->user->id)
            ->whereDate('logged_at', $today)
            ->count());

        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
        ]);

        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $bodyFatType->id,
            'value' => 15.2,
        ]);
    }

    /** @test */
    public function it_allows_same_measurement_type_on_different_dates()
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        // Log weight today
        $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Today weight',
        ]);

        // Log weight yesterday
        $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 186.0,
            'date' => $yesterday->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Yesterday weight',
        ]);

        // Should have two separate entries
        $this->assertEquals(2, BodyLog::where('user_id', $this->user->id)
            ->where('measurement_type_id', $this->measurementType->id)
            ->count());

        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
        ]);

        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 186.0,
        ]);
    }

    /** @test */
    public function it_redirects_to_mobile_entry_when_redirect_to_parameter_is_provided()
    {
        $today = Carbon::today();

        $response = $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Mobile entry test',
            'redirect_to' => 'mobile-entry-measurements'
        ]);

        $response->assertRedirect(route('mobile-entry.measurements', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success', 'Measurement logged successfully.');
    }

    /** @test */
    public function it_redirects_to_mobile_entry_with_update_message_when_updating_existing_log()
    {
        $today = Carbon::today();

        // Create existing log
        BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 180.0,
            'logged_at' => $today->setTime(7, 0),
        ]);

        $response = $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Updated via mobile',
            'redirect_to' => 'mobile-entry-measurements'
        ]);

        $response->assertRedirect(route('mobile-entry.measurements', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success', 'Measurement updated successfully.');
    }

    /** @test */
    public function update_method_redirects_to_mobile_entry_when_redirect_to_parameter_is_provided()
    {
        $today = Carbon::today();
        
        $bodyLog = BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 180.0,
            'logged_at' => $today,
        ]);

        $response = $this->put(route('body-logs.update', $bodyLog), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Updated measurement',
            'redirect_to' => 'mobile-entry-measurements'
        ]);

        $response->assertRedirect(route('mobile-entry.measurements', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success', 'Measurement updated successfully.');
    }

    /** @test */
    public function destroy_method_redirects_to_mobile_entry_when_redirect_to_parameter_is_provided()
    {
        $today = Carbon::today();
        
        $bodyLog = BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 180.0,
            'logged_at' => $today,
        ]);

        $response = $this->delete(route('body-logs.destroy', $bodyLog), [
            'redirect_to' => 'mobile-entry-measurements',
            'date' => $today->toDateString()
        ]);

        $response->assertRedirect(route('mobile-entry.measurements', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success', 'Measurement deleted successfully.');
        
        $this->assertDatabaseMissing('body_logs', [
            'id' => $bodyLog->id
        ]);
    }

    /** @test */
    public function it_only_updates_logs_for_authenticated_user()
    {
        $otherUser = User::factory()->create();
        $today = Carbon::today();

        // Create log for other user
        $otherUserLog = BodyLog::factory()->create([
            'user_id' => $otherUser->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 200.0,
            'logged_at' => $today,
        ]);

        // Try to create log for same measurement type and date (should create new, not update other user's)
        $response = $this->post(route('body-logs.store'), [
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'My measurement',
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $response->assertSessionHas('success', 'Measurement logged successfully.');

        // Should have two separate entries (one for each user)
        $this->assertEquals(2, BodyLog::where('measurement_type_id', $this->measurementType->id)
            ->whereDate('logged_at', $today)
            ->count());

        // Other user's log should be unchanged
        $otherUserLog->refresh();
        $this->assertEquals(200.0, $otherUserLog->value);
        $this->assertEquals($otherUser->id, $otherUserLog->user_id);

        // Current user should have their own log
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $this->measurementType->id,
            'value' => 185.5,
        ]);
    }
}