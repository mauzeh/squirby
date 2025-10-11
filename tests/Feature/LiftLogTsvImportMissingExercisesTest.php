<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiftLogTsvImportMissingExercisesTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_with_few_missing_exercises_shows_detailed_list()
    {
        $user = User::factory()->create();
        
        // Create only one exercise
        Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Existing Exercise'
        ]);

        // TSV data with 3 missing exercises (under the 10 limit)
        $tsvData = "1/1/2024\t8:00 AM\tMissing Exercise 1\t100\t10\t3\tTest notes\n";
        $tsvData .= "1/1/2024\t8:05 AM\tMissing Exercise 2\t150\t8\t3\tTest notes\n";
        $tsvData .= "1/1/2024\t8:10 AM\tMissing Exercise 3\t200\t6\t3\tTest notes";

        $response = $this->actingAs($user)->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2024-01-01'
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('No exercises were found for the following names:', $errorMessage);
        $this->assertStringContainsString('Missing Exercise 1', $errorMessage);
        $this->assertStringContainsString('Missing Exercise 2', $errorMessage);
        $this->assertStringContainsString('Missing Exercise 3', $errorMessage);
        $this->assertStringContainsString('<ul>', $errorMessage);
    }

    public function test_import_with_many_missing_exercises_shows_count_only()
    {
        $user = User::factory()->create();
        
        // Create only one exercise
        Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Existing Exercise'
        ]);

        // TSV data with 12 missing exercises (over the 10 limit)
        $tsvData = '';
        for ($i = 1; $i <= 12; $i++) {
            $tsvData .= "1/1/2024\t8:00 AM\tMissing Exercise {$i}\t100\t10\t3\tTest notes\n";
        }

        $response = $this->actingAs($user)->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2024-01-01'
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('No exercises were found for 12 exercise names', $errorMessage);
        $this->assertStringNotContainsString('<ul>', $errorMessage);
        $this->assertStringNotContainsString('Missing Exercise 1', $errorMessage);
    }

    public function test_partial_import_with_few_missing_exercises_shows_detailed_warning()
    {
        $user = User::factory()->create();
        
        // Create one exercise that will be found
        Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Existing Exercise'
        ]);

        // TSV data with 1 existing and 3 missing exercises
        $tsvData = "1/1/2024\t8:00 AM\tExisting Exercise\t100\t10\t3\tTest notes\n";
        $tsvData .= "1/1/2024\t8:05 AM\tMissing Exercise 1\t150\t8\t3\tTest notes\n";
        $tsvData .= "1/1/2024\t8:10 AM\tMissing Exercise 2\t200\t6\t3\tTest notes\n";
        $tsvData .= "1/1/2024\t8:15 AM\tMissing Exercise 3\t250\t5\t3\tTest notes";

        $response = $this->actingAs($user)->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2024-01-01'
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 lift log(s) imported', $successMessage);
        $this->assertStringContainsString('Warning:', $successMessage);
        $this->assertStringContainsString('The following exercises were not found', $successMessage);
        $this->assertStringContainsString('Missing Exercise 1', $successMessage);
        $this->assertStringContainsString('Missing Exercise 2', $successMessage);
        $this->assertStringContainsString('Missing Exercise 3', $successMessage);
    }

    public function test_partial_import_with_many_missing_exercises_shows_count_warning()
    {
        $user = User::factory()->create();
        
        // Create one exercise that will be found
        Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Existing Exercise'
        ]);

        // TSV data with 1 existing and 12 missing exercises
        $tsvData = "1/1/2024\t8:00 AM\tExisting Exercise\t100\t10\t3\tTest notes\n";
        for ($i = 1; $i <= 12; $i++) {
            $tsvData .= "1/1/2024\t8:0{$i} AM\tMissing Exercise {$i}\t100\t10\t3\tTest notes\n";
        }

        $response = $this->actingAs($user)->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2024-01-01'
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 lift log(s) imported', $successMessage);
        $this->assertStringContainsString('Warning:', $successMessage);
        $this->assertStringContainsString('12 exercise names were not found', $successMessage);
        $this->assertStringNotContainsString('Missing Exercise 1', $successMessage);
    }

    public function test_handles_duplicate_missing_exercise_names_correctly()
    {
        $user = User::factory()->create();

        // TSV data with duplicate missing exercise names
        $tsvData = "1/1/2024\t8:00 AM\tMissing Exercise\t100\t10\t3\tTest notes\n";
        $tsvData .= "1/1/2024\t8:05 AM\tMissing Exercise\t150\t8\t3\tTest notes\n";
        $tsvData .= "1/1/2024\t8:10 AM\tAnother Missing\t200\t6\t3\tTest notes";

        $response = $this->actingAs($user)->post(route('lift-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2024-01-01'
        ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        // Should only count unique exercise names (2, not 3)
        $this->assertStringContainsString('Missing Exercise', $errorMessage);
        $this->assertStringContainsString('Another Missing', $errorMessage);
        // Should not have duplicate entries in the list
        $this->assertEquals(1, substr_count($errorMessage, 'Missing Exercise'));
    }
}