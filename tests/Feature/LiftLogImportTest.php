<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;

class LiftLogImportTest extends TestCase
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
    public function authenticated_user_can_import_lift_logs()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Push Ups']);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Squats']);

        $tsvData = "09/07/2025\t08:00\tPush Ups\t10\t3\t15\tWarm up\n" .
                 "09/07/2025\t08:30\tSquats\t50\t5\t10\tMain set";

        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-09-07',
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success', 'TSV data imported successfully!');

        $this->assertDatabaseCount('lift_logs', 2);
        $this->assertDatabaseCount('lift_sets', 25);
    }

    /** @test */
    public function it_returns_error_for_empty_tsv_data()
    {
        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => '',
            'date' => '2025-09-07',
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error', 'TSV data cannot be empty.');
    }

    /** @test */
    public function it_returns_error_for_not_found_exercises()
    {
        $tsvData = "09/07/2025\t08:00\tNonExistentExercise\t10\t3\t15\tWarm up";

        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-09-07',
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error', 'No exercises found for: NonExistentExercise');
    }

    /** @test */
    public function it_returns_error_for_invalid_rows()
    {
        $tsvData = "invalid row";

        $response = $this->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-09-07',
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error', 'No lift logs imported due to invalid data in rows: "invalid row"');
    }
}