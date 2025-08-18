<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\DailyLog;
use Carbon\Carbon;

class DailyLogControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all daily logs for a specific day can be destroyed.
     */
    public function test_destroy_day_deletes_all_logs_for_a_given_date(): void
    {
        // Create some daily logs for a specific date
        $dateToDestroy = Carbon::today();
        \Database\Factories\DailyLogFactory::new()->count(3)->create([
            'logged_at' => $dateToDestroy->format('Y-m-d H:i:s'),
        ]);

        // Create a log for a different day to ensure it's not deleted
        \Database\Factories\DailyLogFactory::new()->create([
            'logged_at' => Carbon::yesterday()->format('Y-m-d H:i:s'),
        ]);

        // Explicitly check that the DELETE operation does not work
        $response = $this->delete(route('daily-logs.destroy-day', [
            'date' => $dateToDestroy->format('Y-m-d')
        ]));
        $response->assertStatus(404); // Page not found

        // Make a POST request to the destroyDay route
        $response = $this->post(route('daily-logs.destroy-day'), [
            'date' => $dateToDestroy->format('Y-m-d'),
        ]);

        // Assert that the daily logs for the specified date are deleted
        $this->assertCount(0, DailyLog::whereDate('logged_at', $dateToDestroy)->get());

        // Assert that the log for the different day is not deleted
        $this->assertCount(1, DailyLog::whereDate('logged_at', Carbon::yesterday())->get());

        // Assert that the response redirects to the daily logs index page
        $response->assertRedirect(route('daily-logs.index', ['date' => $dateToDestroy->format('Y-m-d')]));

        // Assert that a success message is present
        $response->assertSessionHas('success', 'All logs for the day deleted successfully!');
    }

    /**
     * Test that destroyDay requires a date.
     */
    public function test_destroy_day_requires_date(): void
    {
        $response = $this->post(route('daily-logs.destroy-day'), [
            'date' => null,
        ]);

        $response->assertSessionHasErrors('date');
    }
}
