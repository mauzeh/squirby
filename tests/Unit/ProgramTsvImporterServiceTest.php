<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProgramTsvImporterService;
use App\Models\User;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProgramTsvImporterServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_imports_tsv_content_without_a_trailing_newline()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Bench Press']);
        $programTsvImporterService = new ProgramTsvImporterService();

        $tsvContent = "2025-09-12\tBench Press\t3\t5\t0\n" .
                      "2025-09-13\tBench Press\t3\t5\t0\n" .
                      "2025-09-14\tBench Press\t3\t5\t0";

        // 2. Act
        $result = $programTsvImporterService->import($tsvContent, $user->id);

        // 3. Assert
        $this->assertEquals(3, $result['importedCount']);
        $this->assertDatabaseCount('programs', 3);
    }

    /** @test */
    public function it_creates_a_new_exercise_on_the_fly_if_it_does_not_exist()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $programTsvImporterService = new ProgramTsvImporterService();

        $tsvContent = "2025-09-12\tNon Existent Exercise\t3\t5\t0";

        // 2. Act
        $result = $programTsvImporterService->import($tsvContent, $user->id);

        // 3. Assert
        $this->assertEquals(1, $result['importedCount']);
        $this->assertDatabaseCount('programs', 1);
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'title' => 'Non Existent Exercise',
            'is_bodyweight' => false,
        ]);
    }
}
