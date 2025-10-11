<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class LiftLogTsvImportDetailedMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $exercise1;
    protected $exercise2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        // Use the exercises that are automatically created by the User factory
        $this->exercise1 = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Bench Press')
            ->first();
        
        // Create Squat if it doesn't exist
        $this->exercise2 = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Squat')
            ->first();
        
        if (!$this->exercise2) {
            $this->exercise2 = Exercise::factory()->create([
                'user_id' => $this->user->id,
                'title' => 'Squat',
                'is_bodyweight' => false,
            ]);
        }
    }

    public function test_detailed_success_message_for_small_import()
    {
        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tGood form\n8/4/2025\t6:15 PM\tSquat\t185\t5\t3\tDeep squats";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2025-08-04'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('2 lift log(s) imported', $successMessage);
        $this->assertStringContainsString('Imported:', $successMessage);
        $this->assertStringContainsString('Bench Press on 08/04/2025 18:00 (135lbs x 5 reps x 3 sets)', $successMessage);
        $this->assertStringContainsString('Squat on 08/04/2025 18:15 (185lbs x 5 reps x 3 sets)', $successMessage);
    }

    public function test_detailed_success_message_for_updates()
    {
        // Create existing lift log with exact matching timestamp
        $existingLiftLog = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise1->id,
            'logged_at' => Carbon::createFromFormat('m/d/Y g:i A', '8/4/2025 6:00 PM'),
            'comments' => 'Old comments',
        ]);

        // Add lift sets that match what we'll import (same weight, reps, count) but different notes
        for ($i = 0; $i < 3; $i++) {
            LiftSet::create([
                'lift_log_id' => $existingLiftLog->id,
                'weight' => 135, // Same weight as TSV
                'reps' => 5,     // Same reps as TSV
                'notes' => 'Old notes',
            ]);
        }

        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tUpdated form";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2025-08-04'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 lift log(s) updated', $successMessage);
        $this->assertStringContainsString('Updated:', $successMessage);
        $this->assertStringContainsString('Bench Press on 08/04/2025 18:00 (135lbs x 5 reps x 3 sets)', $successMessage);
    }

    public function test_detailed_success_message_for_mixed_import_and_update()
    {
        // Create existing lift log with exact matching timestamp
        $existingLiftLog = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise1->id,
            'logged_at' => Carbon::createFromFormat('m/d/Y g:i A', '8/4/2025 6:00 PM'),
            'comments' => 'Old comments',
        ]);

        // Add lift sets that match what we'll import (same weight, reps, count) but different notes
        for ($i = 0; $i < 3; $i++) {
            LiftSet::create([
                'lift_log_id' => $existingLiftLog->id,
                'weight' => 135, // Same weight as TSV
                'reps' => 5,     // Same reps as TSV
                'notes' => 'Old notes',
            ]);
        }

        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tUpdated form\n8/4/2025\t6:15 PM\tSquat\t185\t5\t3\tNew exercise";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2025-08-04'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 lift log(s) imported, 1 lift log(s) updated', $successMessage);
        $this->assertStringContainsString('Imported:', $successMessage);
        $this->assertStringContainsString('Updated:', $successMessage);
        $this->assertStringContainsString('Squat on 08/04/2025 18:15 (185lbs x 5 reps x 3 sets)', $successMessage);
        $this->assertStringContainsString('Bench Press on 08/04/2025 18:00 (135lbs x 5 reps x 3 sets)', $successMessage);
    }

    public function test_simple_success_message_for_large_import()
    {
        // Create TSV data with 10+ entries with different times (should not show detailed list)
        $tsvLines = [];
        for ($i = 1; $i <= 12; $i++) {
            $hour = ($i % 12) + 1; // 1-12 for valid PM times
            $tsvLines[] = "8/4/2025\t{$hour}:00 PM\tBench Press\t135\t5\t3\tSet $i";
        }
        $tsvData = implode("\n", $tsvLines);

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2025-08-04'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('12 lift log(s) imported', $successMessage);
        $this->assertStringNotContainsString('Imported:', $successMessage); // Should not show detailed list
        $this->assertStringNotContainsString('<ul>', $successMessage); // Should not contain HTML lists
    }

    public function test_success_message_with_invalid_rows()
    {
        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tGood form\nInvalid Row\n8/4/2025\t6:15 PM\tSquat\t185\t5\t3\tDeep squats";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2025-08-04'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('2 lift log(s) imported', $successMessage);
        $this->assertStringContainsString('Warning:', $successMessage);
        $this->assertStringContainsString('Invalid Row', $successMessage);
    }

    public function test_success_message_when_no_data_imported_or_updated()
    {
        // Create existing lift logs that exactly match what we'll try to import
        $existingLiftLog = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise1->id,
            'logged_at' => Carbon::createFromFormat('m/d/Y g:i A', '8/4/2025 6:00 PM'),
            'comments' => 'Good form',
        ]);

        // Add lift sets that exactly match
        for ($i = 0; $i < 3; $i++) {
            LiftSet::create([
                'lift_log_id' => $existingLiftLog->id,
                'weight' => 135,
                'reps' => 5,
                'notes' => 'Good form',
            ]);
        }

        // Try to import the exact same data
        $tsvData = "8/4/2025\t6:00 PM\tBench Press\t135\t5\t3\tGood form";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2025-08-04'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('No new data was imported or updated - all entries already exist with the same data.', $successMessage);
        $this->assertStringNotContainsString('lift log(s) imported', $successMessage);
        $this->assertStringNotContainsString('lift log(s) updated', $successMessage);
    }
}