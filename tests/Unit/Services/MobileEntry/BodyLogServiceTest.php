<?php

namespace Tests\Unit\Services\MobileEntry;

use Tests\TestCase;
use App\Services\MobileEntry\BodyLogService;
use App\Models\User;
use App\Models\MeasurementType;
use App\Models\BodyLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BodyLogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BodyLogService();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_generate_forms_for_all_measurement_types()
    {
        // Create measurement types
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

        $selectedDate = Carbon::today();
        $forms = $this->service->generateItemSelectionList($this->user->id, $selectedDate);

        $this->assertCount(2, $forms);
        
        // Forms are sorted alphabetically, so Body Fat comes before Weight
        $this->assertEquals('Body Fat', $forms[0]['data']['title']);
        $this->assertEquals('Weight', $forms[1]['data']['title']);
        $this->assertEquals('form', $forms[0]['type']);
        $this->assertEquals('form', $forms[1]['type']);
    }

    /** @test */
    public function it_hides_forms_when_already_logged_today()
    {
        $measurementType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);

        $selectedDate = Carbon::today();
        
        // Create existing log for today
        BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $measurementType->id,
            'value' => 185.5,
            'logged_at' => $selectedDate,
            'comments' => 'Morning weigh-in'
        ]);

        $forms = $this->service->generateItemSelectionList($this->user->id, $selectedDate);

        // Should not show any forms since the measurement is already logged
        $this->assertCount(0, $forms);
        
        // But should show the logged item
        $component = $this->service->generateLoggedItems($this->user->id, $selectedDate);
        $this->assertCount(1, $component['data']['rows']);
        $this->assertEquals('Weight', $component['data']['rows'][0]['line1']);
        $this->assertArrayNotHasKey('line2', $component['data']['rows'][0]);
        $this->assertCount(1, $component['data']['rows'][0]['badges']);
        $this->assertEquals('185.5 lbs', $component['data']['rows'][0]['badges'][0]['text']);
        $this->assertEquals('info', $component['data']['rows'][0]['badges'][0]['colorClass']);
    }



    /** @test */
    public function it_generates_logged_items_correctly()
    {
        $measurementType = MeasurementType::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Weight',
            'default_unit' => 'lbs'
        ]);

        $selectedDate = Carbon::today();
        
        BodyLog::factory()->create([
            'user_id' => $this->user->id,
            'measurement_type_id' => $measurementType->id,
            'value' => 185.5,
            'logged_at' => $selectedDate,
            'comments' => 'Morning weigh-in'
        ]);

        $component = $this->service->generateLoggedItems($this->user->id, $selectedDate);

        $this->assertCount(1, $component['data']['rows']);
        $item = $component['data']['rows'][0];
        
        $this->assertEquals('Weight', $item['line1']);
        $this->assertArrayNotHasKey('line2', $item);
        $this->assertCount(1, $item['badges']);
        $this->assertEquals('185.5 lbs', $item['badges'][0]['text']);
        $this->assertEquals('info', $item['badges'][0]['colorClass']);
        $this->assertTrue($item['badges'][0]['emphasized']);
        $this->assertEquals('Morning weigh-in', $item['line3']);
    }



    /** @test */
    public function it_generates_contextual_help_messages()
    {
        $selectedDate = Carbon::today();
        
        // Test with no measurement types
        $messages = $this->service->generateContextualHelpMessages($this->user->id, $selectedDate);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Create measurement types', $messages[0]['text']);
        
        // Create measurement types but no logs - no message should be generated
        MeasurementType::factory()->create(['user_id' => $this->user->id]);
        MeasurementType::factory()->create(['user_id' => $this->user->id]);
        
        $messages = $this->service->generateContextualHelpMessages($this->user->id, $selectedDate);
        $this->assertCount(0, $messages);
    }
}