<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use App\Models\User;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test ExerciseController Chart Date Range Logic
 * 
 * Tests the intelligent date formatting on the X-axis based on data span.
 */
class ExerciseControllerChartDateRangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'exercise_type' => 'regular',
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * @test
     */
    public function it_uses_day_unit_and_mmm_d_format_for_data_within_90_days()
    {
        // Arrange: Create logs spanning 30 days
        $this->createLiftLogsSpanning(30);

        // Act
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', $this->exercise));

        // Assert
        $response->assertOk();
        
        // Check that the chart component is rendered with correct time scale
        $components = $response->viewData('data')['components'];
        $tabsComponent = collect($components)->firstWhere('type', 'tabs');
        
        $this->assertNotNull($tabsComponent, 'Tabs component should exist');
        
        // Find the history tab content
        $historyTab = collect($tabsComponent['data']['tabs'])->firstWhere('id', 'history');
        $this->assertNotNull($historyTab, 'History tab should exist');
        
        // Find chart component within the history tab
        $chartComponent = collect($historyTab['components'])->firstWhere('type', 'chart');
        
        $this->assertNotNull($chartComponent);
        $this->assertEquals('day', $chartComponent['data']['options']['scales']['x']['time']['unit']);
        $this->assertEquals('MMM d', $chartComponent['data']['options']['scales']['x']['time']['displayFormats']['day']);
    }

    /**
     * @test
     */
    public function it_uses_month_unit_and_mmm_d_format_for_data_between_90_and_365_days()
    {
        // Arrange: Create logs spanning 180 days (6 months)
        $this->createLiftLogsSpanning(180);

        // Act
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', $this->exercise));

        // Assert
        $response->assertOk();
        
        $components = $response->viewData('data')['components'];
        $tabsComponent = collect($components)->firstWhere('type', 'tabs');
        
        $this->assertNotNull($tabsComponent, 'Tabs component should exist');
        
        // Find the history tab content
        $historyTab = collect($tabsComponent['data']['tabs'])->firstWhere('id', 'history');
        $this->assertNotNull($historyTab, 'History tab should exist');
        
        // Find chart component within the history tab
        $chartComponent = collect($historyTab['components'])->firstWhere('type', 'chart');
        
        $this->assertNotNull($chartComponent);
        $this->assertEquals('month', $chartComponent['data']['options']['scales']['x']['time']['unit']);
        $this->assertEquals('MMM d', $chartComponent['data']['options']['scales']['x']['time']['displayFormats']['month']);
    }

    /**
     * @test
     */
    public function it_uses_month_unit_and_mmm_yy_format_for_data_between_1_and_2_years()
    {
        // Arrange: Create logs spanning 500 days (~1.4 years)
        $this->createLiftLogsSpanning(500);

        // Act
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', $this->exercise));

        // Assert
        $response->assertOk();
        
        $components = $response->viewData('data')['components'];
        $tabsComponent = collect($components)->firstWhere('type', 'tabs');
        
        $this->assertNotNull($tabsComponent, 'Tabs component should exist');
        
        // Find the history tab content
        $historyTab = collect($tabsComponent['data']['tabs'])->firstWhere('id', 'history');
        $this->assertNotNull($historyTab, 'History tab should exist');
        
        // Find chart component within the history tab
        $chartComponent = collect($historyTab['components'])->firstWhere('type', 'chart');
        
        $this->assertNotNull($chartComponent);
        $this->assertEquals('month', $chartComponent['data']['options']['scales']['x']['time']['unit']);
        $this->assertEquals('MMM yy', $chartComponent['data']['options']['scales']['x']['time']['displayFormats']['month']);
    }

    /**
     * @test
     */
    public function it_uses_month_unit_and_mmm_yyyy_format_for_data_over_2_years()
    {
        // Arrange: Create logs spanning 900 days (~2.5 years)
        $this->createLiftLogsSpanning(900);

        // Act
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', $this->exercise));

        // Assert
        $response->assertOk();
        
        $components = $response->viewData('data')['components'];
        $tabsComponent = collect($components)->firstWhere('type', 'tabs');
        
        $this->assertNotNull($tabsComponent, 'Tabs component should exist');
        
        // Find the history tab content
        $historyTab = collect($tabsComponent['data']['tabs'])->firstWhere('id', 'history');
        $this->assertNotNull($historyTab, 'History tab should exist');
        
        // Find chart component within the history tab
        $chartComponent = collect($historyTab['components'])->firstWhere('type', 'chart');
        
        $this->assertNotNull($chartComponent);
        $this->assertEquals('month', $chartComponent['data']['options']['scales']['x']['time']['unit']);
        $this->assertEquals('MMM yyyy', $chartComponent['data']['options']['scales']['x']['time']['displayFormats']['month']);
    }

    /**
     * @test
     */
    public function it_does_not_render_chart_when_all_logs_have_zero_1rm()
    {
        // Arrange: Create logs with high-rep sets (>10 reps) that result in 0 1RM
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        // Create high-rep sets (20 reps) which won't calculate 1RM
        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 95,
            'reps' => 20,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->get(route('exercises.show-logs', $this->exercise));

        // Assert
        $response->assertOk();
        
        // Chart should not be rendered when no valid 1RM data exists
        $components = $response->viewData('data')['components'];
        $tabsComponent = collect($components)->firstWhere('type', 'tabs');
        
        $this->assertNotNull($tabsComponent, 'Tabs component should exist');
        
        // Find the history tab content
        $historyTab = collect($tabsComponent['data']['tabs'])->firstWhere('id', 'history');
        $this->assertNotNull($historyTab, 'History tab should exist');
        
        // Find chart component within the history tab - should be null
        $chartComponent = collect($historyTab['components'])->firstWhere('type', 'chart');
        
        $this->assertNull($chartComponent);
    }

    /**
     * Helper method to create lift logs spanning a given number of days
     */
    protected function createLiftLogsSpanning(int $days): void
    {
        $startDate = Carbon::now()->subDays($days);
        $interval = max(1, floor($days / 10)); // Create ~10 logs spread across the period
        
        for ($i = 0; $i < 10; $i++) {
            $date = $startDate->copy()->addDays($i * $interval);
            
            $liftLog = LiftLog::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'logged_at' => $date,
            ]);

            // Create a set with low reps to ensure 1RM is calculated
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'weight' => 100 + ($i * 5),
                'reps' => 5,
            ]);
        }
    }
}
