<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Exercise;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramDateNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        // Make user an admin since ProgramController requires admin access
        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        if (!$adminRole) {
            $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        }
        $this->user->roles()->attach($adminRole);
        
        $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function program_index_shows_last_record_button_when_not_on_last_record_date()
    {
        // Create a program entry 5 days ago
        $lastRecordDate = Carbon::now()->subDays(5);
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'date' => $lastRecordDate,
        ]);

        // Visit today's date (not the last record date)
        $response = $this->actingAs($this->user)->get(route('programs.index'));

        $response->assertStatus(200);
        $response->assertSee('Last Record');
        $response->assertSee('href="' . route('programs.index', ['date' => $lastRecordDate->toDateString()]) . '"', false);
    }

    /** @test */
    public function program_index_hides_last_record_button_when_on_last_record_date()
    {
        // Create a program entry 5 days ago
        $lastRecordDate = Carbon::now()->subDays(5);
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'date' => $lastRecordDate,
        ]);

        // Visit the last record date
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $lastRecordDate->toDateString()]));

        $response->assertStatus(200);
        // Should not see the Last Record button link
        $response->assertDontSee('<a href="' . route('programs.index', ['date' => $lastRecordDate->toDateString()]) . '" class="date-link">', false);
        $response->assertDontSee('>Last Record</a>', false);
    }

    /** @test */
    public function program_index_hides_last_record_button_when_no_records_exist()
    {
        $response = $this->actingAs($this->user)->get(route('programs.index'));

        $response->assertStatus(200);
        // Should not see the Last Record button when no records exist
        $response->assertDontSee('>Last Record</a>', false);
    }

    /** @test */
    public function program_index_shows_separate_today_button_when_today_not_in_range()
    {
        // Visit a date that's more than 1 day away from today (outside the -1 to +1 range)
        $selectedDate = Carbon::now()->subDays(5);
        
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        // Should see separate "Today" button
        $response->assertSee('Today');
        $response->assertSee('href="' . route('programs.index', ['date' => Carbon::today()->toDateString()]) . '"', false);
        // Should see actual dates in the date buttons (not "Today" text)
        $response->assertSee($selectedDate->copy()->subDay()->format('D M d'));
        $response->assertSee($selectedDate->format('D M d'));
        $response->assertSee($selectedDate->copy()->addDay()->format('D M d'));
    }

    /** @test */
    public function program_index_shows_today_text_in_date_button_when_today_is_in_range()
    {
        // Visit yesterday (today will be in the -1 to +1 range)
        $selectedDate = Carbon::now()->subDay();
        
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        // Should see "Today" text in one of the date buttons, not as a separate button
        $response->assertSee('Today');
        // Should NOT see a separate Today button
        $response->assertDontSee('<a href="' . route('programs.index', ['date' => Carbon::today()->toDateString()]) . '" class="date-link today-date">', false);
    }

    /** @test */
    public function program_index_shows_today_text_when_viewing_today()
    {
        $response = $this->actingAs($this->user)->get(route('programs.index'));

        $response->assertStatus(200);
        // Should see "Today" text in the active date button
        $response->assertSee('Today');
        $response->assertSee('class="date-link active today-date"', false);
        // Should NOT see a separate Today button
        $response->assertDontSee('<a href="' . route('programs.index', ['date' => Carbon::today()->toDateString()]) . '" class="date-link today-date">', false);
    }

    /** @test */
    public function program_index_shows_correct_date_range_buttons()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        
        // Should show -1, 0, +1 days relative to selected date
        $response->assertSee('Tue Jan 14'); // -1 day
        $response->assertSee('Wed Jan 15'); // selected date
        $response->assertSee('Thu Jan 16'); // +1 day
    }

    /** @test */
    public function program_index_marks_selected_date_as_active()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        // Check for active class in the date link
        $response->assertSee('date-link active', false);
    }

    /** @test */
    public function program_index_marks_today_with_special_class_when_in_range()
    {
        // Visit yesterday so today appears in the date range
        $selectedDate = Carbon::today()->subDay();
        
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('today-date', false);
    }

    /** @test */
    public function last_record_button_navigates_to_correct_date()
    {
        // Create a program entry 3 days ago
        $lastRecordDate = Carbon::now()->subDays(3);
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'date' => $lastRecordDate,
        ]);

        // Click the last record button (simulate by visiting the URL it would link to)
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $lastRecordDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('Program for ' . $lastRecordDate->format('M d, Y'));
    }

    /** @test */
    public function today_button_navigates_to_today()
    {
        // Visit a date far from today
        $selectedDate = Carbon::now()->subDays(10);
        
        // First verify Today button appears
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $selectedDate->toDateString()]));
        $response->assertSee('Today');

        // Click the Today button (simulate by visiting today's URL)
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => Carbon::today()->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('Program for ' . Carbon::today()->format('M d, Y'));
    }

    /** @test */
    public function date_navigation_works_with_multiple_programs()
    {
        // Create programs on different dates
        $date1 = Carbon::parse('2025-01-10');
        $date2 = Carbon::parse('2025-01-15');
        $date3 = Carbon::parse('2025-01-20');

        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'date' => $date1,
        ]);

        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'date' => $date2,
        ]);

        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'date' => $date3,
        ]);

        // Visit the middle date
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $date2->toDateString()]));

        $response->assertStatus(200);
        
        // Should show the most recent record (date3) as last record
        $response->assertSee('Last Record');
        $response->assertSee('href="' . route('programs.index', ['date' => $date3->toDateString()]) . '"', false);
    }

    /** @test */
    public function last_record_date_is_calculated_correctly_for_user()
    {
        $otherUser = User::factory()->create();
        $otherExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);

        // Create programs for both users
        $userLastDate = Carbon::parse('2025-01-15');
        $otherUserLastDate = Carbon::parse('2025-01-20'); // More recent

        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'date' => $userLastDate,
        ]);

        Program::factory()->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $otherExercise->id,
            'date' => $otherUserLastDate,
        ]);

        // Visit as first user - should only see their own last record date
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => Carbon::parse('2025-01-10')->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('Last Record');
        $response->assertSee('href="' . route('programs.index', ['date' => $userLastDate->toDateString()]) . '"', false);
        $response->assertDontSee('href="' . route('programs.index', ['date' => $otherUserLastDate->toDateString()]) . '"', false);
    }

    /** @test */
    public function date_picker_shows_selected_date()
    {
        $selectedDate = Carbon::parse('2025-01-15');
        
        $response = $this->actingAs($this->user)->get(route('programs.index', ['date' => $selectedDate->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('value="' . $selectedDate->format('Y-m-d') . '"', false);
    }

    /** @test */
    public function program_index_defaults_to_today_when_no_date_provided()
    {
        $response = $this->actingAs($this->user)->get(route('programs.index'));

        $response->assertStatus(200);
        $response->assertSee('Program for ' . Carbon::today()->format('M d, Y'));
        $response->assertSee('value="' . Carbon::today()->format('Y-m-d') . '"', false);
    }
}