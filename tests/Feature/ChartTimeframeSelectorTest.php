<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;

class ChartTimeframeSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'weighted'
        ]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function chart_includes_timeframe_selector_buttons()
    {
        // Create some lift logs to ensure chart is rendered
        $this->createLiftLogsOverTime();

        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id
        ]));

        $response->assertStatus(200);
        
        // Check for timeframe selector buttons
        $response->assertSee('All', false);
        $response->assertSee('1 Year', false);
        $response->assertSee('6 Months', false);
        $response->assertSee('3 Months', false);
        
        // Check for data attributes
        $response->assertSee('data-timeframe="all"', false);
        $response->assertSee('data-timeframe="1yr"', false);
        $response->assertSee('data-timeframe="6mo"', false);
        $response->assertSee('data-timeframe="3mo"', false);
        
        // Check for active class on "6 Months" button by default
        $response->assertSee('data-timeframe="6mo">6 Months</button>', false);
        $response->assertSee('timeframe-btn active', false);
    }

    /** @test */
    public function chart_has_timeframe_enabled_attribute()
    {
        $this->createLiftLogsOverTime();

        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id
        ]));

        $response->assertStatus(200);
        $response->assertSee('data-chart-timeframe-enabled="true"', false);
    }

    /** @test */
    public function chart_without_data_does_not_show_timeframe_selector()
    {
        // Don't create any lift logs
        $response = $this->get(route('exercises.show-logs', [
            'exercise' => $this->exercise->id
        ]));

        $response->assertStatus(200);
        
        // Should show empty state message instead
        $response->assertSee('No training data yet', false);
        
        // Should not show timeframe selector
        $response->assertDontSee('data-timeframe="all"', false);
    }

    /** @test */
    public function chart_component_builder_supports_timeframe_selector()
    {
        $chartBuilder = \App\Services\ComponentBuilder::chart('testChart', 'Test Chart')
            ->showTimeframeSelector();
        
        $component = $chartBuilder->build();
        
        $this->assertTrue($component['data']['showTimeframeSelector']);
    }

    /** @test */
    public function chart_component_builder_defaults_to_no_timeframe_selector()
    {
        $chartBuilder = \App\Services\ComponentBuilder::chart('testChart', 'Test Chart');
        
        $component = $chartBuilder->build();
        
        $this->assertFalse($component['data']['showTimeframeSelector']);
    }

    /**
     * Helper to create lift logs spread over time
     */
    private function createLiftLogsOverTime(): void
    {
        $dates = [
            Carbon::now()->subMonths(12),
            Carbon::now()->subMonths(9),
            Carbon::now()->subMonths(6),
            Carbon::now()->subMonths(3),
            Carbon::now()->subMonth(),
            Carbon::now()->subWeek(),
        ];

        foreach ($dates as $date) {
            $liftLog = LiftLog::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'logged_at' => $date,
            ]);

            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'weight' => 100,
                'reps' => 5,
            ]);
        }
    }
}
