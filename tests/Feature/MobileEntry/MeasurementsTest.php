<?php

namespace Tests\Feature\MobileEntry;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\MeasurementType;
use App\Models\BodyLog;
use Carbon\Carbon;

class MeasurementsTest extends TestCase
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
    public function user_can_view_measurements_mobile_entry_page()
    {
        $response = $this->get(route('mobile-entry.measurements'));

        $response->assertStatus(200);
        $response->assertViewIs('mobile-entry.flexible');
    }



    /** @test */
    public function user_can_log_measurement_from_mobile_entry()
    {
        $measurementType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);

        $today = Carbon::today();

        $response = $this->post(route('body-logs.store'), [
            'measurement_type_id' => $measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'Morning weigh-in',
            'redirect_to' => 'mobile-entry-measurements'
        ]);

        $response->assertRedirect(route('mobile-entry.measurements', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $measurementType->id,
            'value' => 185.5,
            'comments' => 'Morning weigh-in'
        ]);
    }

    /** @test */
    public function user_can_update_existing_measurement_from_mobile_entry()
    {
        $measurementType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);

        $today = Carbon::today();

        // First, log a measurement
        $this->post(route('body-logs.store'), [
            'measurement_type_id' => $measurementType->id,
            'value' => 185.0,
            'date' => $today->toDateString(),
            'logged_at' => '08:00',
            'comments' => 'First measurement',
            'redirect_to' => 'mobile-entry-measurements'
        ]);

        // Then, log again for the same day (should update)
        $response = $this->post(route('body-logs.store'), [
            'measurement_type_id' => $measurementType->id,
            'value' => 185.5,
            'date' => $today->toDateString(),
            'logged_at' => '08:30',
            'comments' => 'Updated measurement',
            'redirect_to' => 'mobile-entry-measurements'
        ]);

        $response->assertRedirect(route('mobile-entry.measurements', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success');

        // Should only have one entry for this measurement type and date
        $this->assertEquals(1, BodyLog::where('user_id', $this->user->id)
            ->where('measurement_type_id', $measurementType->id)
            ->whereDate('logged_at', $today)
            ->count());

        // Should have the updated values
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'measurement_type_id' => $measurementType->id,
            'value' => 185.5,
            'comments' => 'Updated measurement'
        ]);
    }

    /** @test */
    public function measurements_page_shows_only_unlogged_measurement_types_as_forms()
    {
        // Create multiple measurement types
        $weightType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);

        $bodyFatType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Body Fat',
            'default_unit' => '%'
        ]);

        // Log one measurement
        BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $weightType->id,
            'value' => 185.5,
            'logged_at' => now()
        ]);

        $response = $this->get(route('mobile-entry.measurements'));

        $response->assertStatus(200);
        
        // Check that the page only shows form for unlogged measurement type
        $data = $response->viewData('data');
        $formComponents = collect($data['components'])->where('type', 'form')->values();
        $this->assertCount(1, $formComponents);
        $this->assertEquals('Body Fat', $formComponents[0]['data']['title']);
        
        // Should show the logged measurement in logged items
        $itemsComponent = collect($data['components'])->firstWhere('type', 'items');
        $this->assertCount(1, $itemsComponent['data']['items']);
        $this->assertEquals('Weight', $itemsComponent['data']['items'][0]['title']);
    }

    /** @test */
    public function measurements_page_shows_logged_items()
    {
        $measurementType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);

        $today = Carbon::today();

        // Create a logged measurement
        BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $measurementType->id,
            'value' => 185.5,
            'logged_at' => $today,
            'comments' => 'Morning weigh-in'
        ]);

        $response = $this->get(route('mobile-entry.measurements', ['date' => $today->toDateString()]));

        $response->assertStatus(200);
        
        $data = $response->viewData('data');
        $itemsComponent = collect($data['components'])->firstWhere('type', 'items');
        $this->assertCount(1, $itemsComponent['data']['items']);
        
        $loggedItem = $itemsComponent['data']['items'][0];
        $this->assertEquals('Weight', $loggedItem['title']);
        $this->assertEquals('185.5 lbs', $loggedItem['message']['text']);
        $this->assertEquals('Morning weigh-in', $loggedItem['freeformText']);
    }

    /** @test */
    public function measurement_forms_do_not_have_delete_buttons()
    {
        $measurementType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);

        $response = $this->get(route('mobile-entry.measurements'));

        $response->assertStatus(200);
        
        // Check that forms don't have delete actions
        $data = $response->viewData('data');
        $formComponents = collect($data['components'])->where('type', 'form')->values();
        $this->assertCount(1, $formComponents);
        
        $form = $formComponents[0]['data'];
        $this->assertNull($form['deleteAction']);
        $this->assertArrayNotHasKey('deleteForm', $form['ariaLabels']);
        
        // Verify the HTML doesn't contain delete buttons for forms
        $response->assertDontSee('btn-delete');
        $response->assertDontSee('fa-trash');
        $response->assertDontSee('delete-form');
    }
}